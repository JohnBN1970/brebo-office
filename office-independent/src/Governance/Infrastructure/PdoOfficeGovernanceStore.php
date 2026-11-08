<?php
declare(strict_types=1);
namespace Brebo\Office\Governance\Infrastructure;

use Brebo\Office\Governance\Contract\OfficeGovernanceStore;
use PDO;

/** Persistence and live Office ACL adapter. No legacy identity translation. */
final class PdoOfficeGovernanceStore implements OfficeGovernanceStore
{
    private const TABLES=['quality'=>'office_quality_checks','risk'=>'office_risks','procurement'=>'office_procurement_orders'];
    public function __construct(private readonly PDO $db) {}
    private function table(string $module): string
    {
        return self::TABLES[$module] ?? throw new \InvalidArgumentException('Onbekende module.');
    }
    private function rows(string $sql,array $args): array
    {
        $q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function contexts(string $actor,string $module): array
    {
        $this->table($module);
        return $this->rows("SELECT p.id AS project_id,p.title,pb.building_id,b.name AS building_name FROM office_projects p
            JOIN office_project_buildings pb ON pb.project_id=p.id
            JOIN office_buildings b ON b.id=pb.building_id
            JOIN office_project_memberships m ON m.project_id=p.id
            JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
            WHERE m.operator_id=? AND m.permission IN ('read','manage')
            AND EXISTS (SELECT 1 FROM office_module_permissions g WHERE g.operator_id=a.id AND g.module=? AND g.permission='read')
            ORDER BY p.title,p.id,pb.building_id",[$actor,$module]);
    }
    public function allowed(string $actor,string $module,string $permission,int $project,int $building): bool
    {
        $this->table($module);
        if (!in_array($permission,['read','write','approve','award','receive'],true)) return false;
        $membership=$permission==='read' ? "m.permission IN ('read','manage')" : "m.permission='manage'";
        return $this->rows("SELECT 1 FROM office_module_permissions g
            JOIN office_operator_accounts a ON a.id=g.operator_id AND a.active=1
            JOIN office_project_memberships m ON m.operator_id=a.id
            JOIN office_projects p ON p.id=m.project_id
            JOIN office_project_buildings pb ON pb.project_id=p.id
            JOIN office_buildings b ON b.id=pb.building_id
            WHERE g.operator_id=? AND g.module=? AND g.permission=?
            AND p.id=? AND b.id=? AND $membership
            AND EXISTS (SELECT 1 FROM office_module_permissions r WHERE r.operator_id=a.id AND r.module=g.module AND r.permission='read')",
            [$actor,$module,$permission,$project,$building])!==[];
    }
    public function ownerAllowed(string $owner,string $module,int $project,int $building): bool
    {
        return $this->allowed($owner,$module,'read',$project,$building);
    }
    /** UI choices are scoped using the same live module and object ACL as records. */
    public function choices(string $actor,string $module,int $project,int $building): array
    {
        if (!$this->allowed($actor,$module,'read',$project,$building)) throw new \DomainException('Geen toegang.');
        $owners=$this->rows("SELECT a.id,a.username AS label FROM office_operator_accounts a
            JOIN office_project_memberships m ON m.operator_id=a.id
            WHERE a.active=1 AND m.project_id=? AND m.permission IN ('read','manage')
            AND EXISTS (SELECT 1 FROM office_module_permissions g WHERE g.operator_id=a.id AND g.module=? AND g.permission='read') ORDER BY a.username",[$project,$module]);
        $suppliers=$module==='procurement' ? $this->rows("SELECT r.id,r.display_name AS label FROM office_crm_relations r
            WHERE r.status='active' AND EXISTS (SELECT 1 FROM office_crm_relation_roles roles WHERE roles.relation_id=r.id AND roles.role_code='supplier') ORDER BY r.display_name",[]) : [];
        $findings=$module==='quality' ? $this->rows('SELECT f.id,f.observation AS label FROM office_inspection_findings f
            JOIN office_inspections i ON i.id=f.inspection_id WHERE i.building_id=? AND (i.project_id=? OR i.project_id IS NULL) ORDER BY f.id DESC LIMIT 200',[$building,$project]) : [];
        return ['owner'=>$owners,'supplier_relation_id'=>$suppliers,'inspection_finding_id'=>$findings];
    }
    public function supplierExists(int $relation): bool
    {
        return $this->rows("SELECT 1 FROM office_crm_relations r WHERE r.id=? AND r.status='active' AND EXISTS (SELECT 1 FROM office_crm_relation_roles roles WHERE roles.relation_id=r.id AND roles.role_code='supplier')",[$relation])!==[];
    }
    public function inspectionFindingExists(int $finding,int $project,int $building): bool
    {
        return $this->rows('SELECT 1 FROM office_inspection_findings f JOIN office_inspections i ON i.id=f.inspection_id WHERE f.id=? AND i.building_id=? AND (i.project_id=? OR i.project_id IS NULL)',[$finding,$building,$project])!==[];
    }
    private function decode(array $row): array
    {
        $row['payload']=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
        unset($row['payload_json']);return $row;
    }
    public function find(string $module,int $id): ?array
    {
        $rows=$this->rows('SELECT * FROM '.$this->table($module).' WHERE id=?',[$id]);
        return $rows===[] ? null : $this->decode($rows[0]);
    }
    public function listing(string $actor,string $module,int $project,int $building): array
    {
        if (!$this->allowed($actor,$module,'read',$project,$building)) throw new \DomainException('Geen toegang.');
        return array_map($this->decode(...),$this->rows('SELECT * FROM '.$this->table($module).' WHERE project_id=? AND building_id=? ORDER BY id DESC',[$project,$building]));
    }
    public function persist(string $module,string $actor,string $command,string $fingerprint,array $r,int $revision): array
    {
        $table=$this->table($module);
        if ($this->db->inTransaction()) throw new \LogicException('Eigen transactie vereist.');
        $this->db->beginTransaction();
        try {
            // Re-check authorization in the write transaction, not only at the UI boundary.
            if (!$this->allowed($actor,$module,'write',$r['project_id'],$r['building_id'])) throw new \DomainException('Geen schrijfrechten.');
            $extra=match (true) {
                $module==='quality' && $r['status']==='accepted' => 'approve',
                $module==='risk' && $r['status']==='closed' => 'approve',
                $module==='procurement' && $r['status']==='approved' => 'approve',
                $module==='procurement' && $r['status']==='ordered' => 'award',
                $module==='procurement' && $r['status']==='received' => 'receive',
                default => null,
            };
            if ($extra!==null && !$this->allowed($actor,$module,$extra,$r['project_id'],$r['building_id'])) throw new \DomainException('Mandaat ontbreekt.');
            $oldCommand=$this->rows('SELECT fingerprint,result_json FROM office_governance_commands WHERE operator_id=? AND command_key=?',[$actor,$command]);
            if ($oldCommand!==[]) {
                if (!hash_equals($oldCommand[0]['fingerprint'],$fingerprint)) throw new \DomainException('Opdrachtcode eerder gebruikt voor andere inhoud.');
                $result=json_decode($oldCommand[0]['result_json'],true,32,JSON_THROW_ON_ERROR);
                $this->db->commit();return $result;
            }
            $payload=json_encode($r['payload'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $now=gmdate('Y-m-d H:i:s');
            if ($r['id']===0) {
                if ($revision!==0) throw new \DomainException('Ongeldige startrevisie.');
                $q=$this->db->prepare("INSERT INTO $table (project_id,building_id,title,status,payload_json,revision,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,1,?,?,?,?)");
                $q->execute([$r['project_id'],$r['building_id'],$r['title'],$r['status'],$payload,$actor,$actor,$now,$now]);
                $id=(int)$this->db->lastInsertId();
            } else {
                $id=$r['id'];
                $q=$this->db->prepare("UPDATE $table SET title=?,status=?,payload_json=?,revision=revision+1,updated_by=?,updated_at=? WHERE id=? AND project_id=? AND building_id=? AND revision=?");
                $q->execute([$r['title'],$r['status'],$payload,$actor,$now,$id,$r['project_id'],$r['building_id'],$revision]);
                if ($q->rowCount()!==1) throw new \DomainException('Dossier is gewijzigd. Herlaad eerst.');
            }
            $result=['id'=>$id,'revision'=>$revision+1,'status'=>$r['status']];
            $q=$this->db->prepare('INSERT INTO office_governance_commands (operator_id,command_key,fingerprint,result_json) VALUES (?,?,?,?)');
            $q->execute([$actor,$command,$fingerprint,json_encode($result,JSON_THROW_ON_ERROR)]);
            $q=$this->db->prepare('INSERT INTO office_governance_audit (module,record_id,project_id,building_id,revision,operator_id,snapshot_json,created_at) VALUES (?,?,?,?,?,?,?,?)');
            $snapshot=$r;$snapshot['id']=$id;$snapshot['revision']=$revision+1;
            $q->execute([$module,$id,$r['project_id'],$r['building_id'],$revision+1,$actor,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$now]);
            $this->db->commit();return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
