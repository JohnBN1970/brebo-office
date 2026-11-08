<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

use PDO;

/** Read-only canonical CRM acceptance -> latest locked execution baseline. */
final class OfficeWorkBudgetBaselineReader
{
    public function __construct(private readonly PDO $db) {}

    public function forProject(int $projectId): ?array
    {
        if ($projectId < 1) throw new \InvalidArgumentException('Ongeldig project.');
        // Never infer an empty/zero budget when the schema or accepted baseline is absent.
        $sql = "SELECT b.id, b.version_number, b.locked_at
                FROM office_calc_crm_acceptance_transfers t
                INNER JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id=t.id
                WHERE t.office_project_id=:project
                ORDER BY b.version_number DESC, b.id DESC LIMIT 1";
        $q=$this->db->prepare($sql);
        $q->execute(['project'=>$projectId]);
        $baseline=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($baseline)) return null;
        if (!is_string($baseline['locked_at']??null) || $baseline['locked_at']==='') {
            throw new \RuntimeException('Werkbegroting niet vastgesteld.');
        }
        // Material, equipment, subcontract and other are procurement-capable cost types.
        // Labor is deliberately excluded; this is not a full project-cost comparison.
        $q=$this->db->prepare("SELECT cost_type, budget_cents FROM office_calc_work_budget_lines WHERE baseline_id=:baseline");
        $q->execute(['baseline'=>(int)$baseline['id']]);
        $total=0;
        $count=0;
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $type=(string)($line['cost_type']??'');
            if (!in_array($type,['labor','material','equipment','subcontract','other'],true)) {
                throw new \RuntimeException('Onbekende begrotingskostensoort.');
            }
            if ($type==='labor') continue;
            $raw=(string)($line['budget_cents']??'');
            if (!preg_match('/^(0|[1-9][0-9]*)$/D',$raw)) throw new \RuntimeException('Ongeldig begrotingsbedrag.');
            $amount=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if (!is_int($amount) || $amount>PHP_INT_MAX-$total) throw new \OverflowException('Begroting te groot.');
            $total+=$amount;
            $count++;
        }
        // An empty baseline is not an authoritative zero budget.
        if ($count===0) return null;
        return ['project_id'=>$projectId,'locked'=>true,'currency'=>'EUR',
            'vat_basis'=>'excluding','baseline_id'=>(int)$baseline['id'],
            'version_number'=>(int)$baseline['version_number'],
            'procurement_budget_cents'=>$total,'procurement_line_count'=>$count];
    }
}
