<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

use Brebo\Office\Governance\Contract\OfficeGovernanceStore;

/** Native business rules. No framework, database, session or HTTP dependencies. */
final class OfficeGovernanceService
{
    public const MODULES = ['quality', 'risk', 'procurement'];
    public function __construct(private readonly OfficeGovernanceStore $store) {}

    public function read(string $actor, string $module, int $project, int $building): array
    {
        $this->context($actor, $module, 'read', $project, $building);
        return $this->store->listing($actor, $module, $project, $building);
    }

    public function save(string $actor, string $module, array $input): array
    {
        $project = self::integer($input['project_id'] ?? null, 1);
        $building = self::integer($input['building_id'] ?? null, 1);
        $this->context($actor, $module, 'write', $project, $building);
        $id = self::integer($input['id'] ?? 0, 0);
        $revision = self::integer($input['revision'] ?? 0, 0);
        $command = self::text($input, 'command', 64);
        if (!preg_match('/^[a-f0-9]{32,64}$/D', $command)) throw new \InvalidArgumentException('Ongeldige opdrachtcode.');
        $old = $id === 0 ? null : $this->store->find($module, $id);
        if ($id !== 0 && ($old === null || (int)$old['project_id'] !== $project || (int)$old['building_id'] !== $building)) {
            throw new \OutOfBoundsException('Dossier niet gevonden.');
        }
        // Idempotent retries are handled atomically by storage, including after a state transition.
        $payload = match ($module) {
            'quality' => $this->quality($input, $old, $project, $building),
            'risk' => $this->risk($input, $project, $building),
            'procurement' => $this->procurement($actor, $input, $old, $project, $building),
        };
        $status = $payload['status'];
        if ($old !== null && $old['status'] !== $status) {
            $transitions = match ($module) {
                'quality' => ['open'=>['deviation','accepted'], 'deviation'=>['recheck'], 'recheck'=>['deviation','accepted'], 'accepted'=>['recheck']],
                'risk' => ['open'=>['mitigating','closed'], 'mitigating'=>['closed','open'], 'closed'=>['open']],
                'procurement' => ['draft'=>['submitted','cancelled'], 'submitted'=>['draft','approved','cancelled'], 'approved'=>['ordered','cancelled'], 'ordered'=>['received'], 'received'=>[], 'cancelled'=>[]],
            };
            if (!in_array($status, $transitions[$old['status']] ?? [], true)) throw new \DomainException('Ongeldige statusovergang.');
        } elseif ($old === null && $status !== ($module === 'procurement' ? 'draft' : 'open')) {
            throw new \DomainException('Nieuwe dossiers beginnen als open of concept.');
        }
        if ($module === 'quality' && $status === 'accepted') $this->context($actor, $module, 'approve', $project, $building);
        if ($module === 'risk' && $status === 'closed') $this->context($actor, $module, 'approve', $project, $building);
        $record = ['id'=>$id, 'project_id'=>$project, 'building_id'=>$building,
            'title'=>self::text($input, 'title', 255), 'status'=>$status, 'payload'=>$payload];
        $fingerprint = hash('sha256', json_encode([$module,$actor,$revision,$record], JSON_THROW_ON_ERROR));
        return $this->store->persist($module, $actor, $command, $fingerprint, $record, $revision);
    }

    private function quality(array $i, ?array $old, int $project, int $building): array
    {
        $status = self::choice($i, 'status', ['open','deviation','recheck','accepted']);
        $finding = self::integer($i['inspection_finding_id'] ?? 0, 0);
        if ($finding > 0 && !$this->store->inspectionFindingExists($finding, $project, $building)) throw new \DomainException('Inspectiebevinding hoort niet bij dit dossier.');
        if (!in_array($i['blocks_release'] ?? '0',['0','1'],true)) throw new \InvalidArgumentException('Ongeldige vrijgaveblokkade.');
        $p = ['status'=>$status, 'requirement'=>self::text($i,'requirement',4000),
            'observation'=>self::text($i,'observation',8000), 'owner'=>$this->owner($i,'quality',$project,$building),
            'due_date'=>self::date($i), 'evidence'=>self::text($i,'evidence',2000,false),
            'remedy'=>self::text($i,'remedy',4000,false), 'inspection_finding_id'=>$finding,
            'blocks_release'=>($i['blocks_release'] ?? '0') === '1'];
        if (in_array($status, ['deviation','recheck'], true) && $p['remedy'] === '') throw new \DomainException('Herstelmaatregel verplicht.');
        if ($status === 'accepted' && ($p['evidence'] === '' || $p['blocks_release'])) throw new \DomainException('Akkoord vereist bewijs en opgeheven vrijgaveblokkade.');
        return $p;
    }

    private function risk(array $i, int $project, int $building): array
    {
        $p = ['status'=>self::choice($i,'status',['open','mitigating','closed']),
            'owner'=>$this->owner($i,'risk',$project,$building), 'due_date'=>self::date($i),
            'probability'=>self::integer($i['probability'] ?? null,1,5),
            'impact'=>self::integer($i['impact'] ?? null,1,5)];
        foreach (['cause','event','consequence','measure','residual_risk'] as $key) $p[$key]=self::text($i,$key,4000);
        $p['evidence']=self::text($i,'evidence',2000,false);
        if ($p['status'] === 'closed' && $p['evidence'] === '') throw new \DomainException('Afsluiten vereist beheersbewijs.');
        return $p;
    }

    private function procurement(string $actor, array $i, ?array $old, int $project, int $building): array
    {
        $status = self::choice($i,'status',['draft','submitted','approved','ordered','received','cancelled']);
        $supplier = self::integer($i['supplier_relation_id'] ?? null,1);
        if (!$this->store->supplierExists($supplier)) throw new \DomainException('Actieve CRM-leverancier vereist.');
        $quantity = self::integer($i['quantity'] ?? null,1,1000000);
        $price = self::integer($i['unit_price_cents'] ?? null,1,1000000000);
        // Order value belongs to Procurement. Finance consumes this value by reference; no mirror ledger.
        $p = ['status'=>$status, 'supplier_relation_id'=>$supplier, 'quantity'=>$quantity,
            'unit_price_cents'=>$price, 'amount_cents'=>$quantity*$price, 'currency'=>'EUR',
            'description'=>self::text($i,'description',8000), 'due_date'=>self::date($i),
            'owner'=>$this->owner($i,'procurement',$project,$building),
            'selection_reason'=>self::text($i,'selection_reason',4000),
            'evidence'=>self::text($i,'evidence',2000,false),
            'created_by'=>$old['payload']['created_by'] ?? $actor,
            'approved_by'=>$old['payload']['approved_by'] ?? null];
        if ($old !== null && !in_array($old['status'], ['draft','submitted'],true)) {
            foreach (['supplier_relation_id','quantity','unit_price_cents','description','selection_reason','due_date','owner'] as $key) {
                if ($p[$key] !== $old['payload'][$key]) throw new \DomainException('Goedgekeurde opdrachtinhoud staat vast.');
            }
        }
        if ($status === 'approved') $this->context($actor,'procurement','approve',$project,$building);
        if ($status === 'approved' && ($old['status'] ?? null) === 'submitted') {
            $this->context($actor,'procurement','approve',$project,$building);
            if ($actor === $p['created_by']) throw new \DomainException('Een andere bevoegde medewerker moet goedkeuren.');
            $p['approved_by']=$actor;
        }
        if (in_array($status,['ordered','received'],true)) {
            $this->context($actor,'procurement',$status === 'ordered' ? 'award' : 'receive',$project,$building);
            if ($p['approved_by'] === null || $p['evidence'] === '') throw new \DomainException('Goedkeuring en opdracht-/prestatiebewijs vereist.');
        }
        return $p;
    }

    private function owner(array $input, string $module, int $project, int $building): string
    {
        $owner=self::text($input,'owner',128);
        if (!$this->store->ownerAllowed($owner,$module,$project,$building)) throw new \DomainException('Eigenaar heeft geen toegang tot dit dossier.');
        return $owner;
    }
    private function context(string $actor,string $module,string $permission,int $project,int $building): void
    {
        if (!in_array($module,self::MODULES,true) || $actor === '' || $project < 1 || $building < 1
            || !$this->store->allowed($actor,$module,$permission,$project,$building)) throw new \DomainException('Geen toegang tot dit dossier.');
    }
    private static function text(array $i,string $key,int $limit,bool $required=true): string
    {
        $value=$i[$key]??'';
        if (!is_string($value) || strlen(trim($value))>$limit || ($required && trim($value)==='')) throw new \InvalidArgumentException('Ongeldig veld: '.$key);
        return trim($value);
    }
    private static function choice(array $i,string $key,array $values): string
    {
        $value=self::text($i,$key,32);
        if (!in_array($value,$values,true)) throw new \InvalidArgumentException('Ongeldige '.$key);
        return $value;
    }
    private static function date(array $i): string
    {
        $s=self::text($i,'due_date',10); $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s);
        if (!$d || $d->format('Y-m-d')!==$s) throw new \InvalidArgumentException('Ongeldige datum.');
        return $s;
    }
    private static function integer(mixed $v,int $min,int $max=PHP_INT_MAX): int
    {
        if (!is_int($v) && !is_string($v)) throw new \InvalidArgumentException('Ongeldig getal.');
        $n=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
        if (!is_int($n)) throw new \InvalidArgumentException('Ongeldig getal.');
        return $n;
    }
}
