<?php
declare(strict_types=1);
namespace Brebo\Office\Approval;
use PDO;
use DomainException;
/** Explicit test-only persistence; caller authenticates admin and verifies CSRF. */
final class OfficeBlueprintApprovalModeRepository {
    public function __construct(private readonly PDO $db) {}
    public function get(string $blueprintKey,string $stepId):string {
        $q=$this->db->prepare('SELECT approval_mode FROM office_test_blueprint_approval_modes WHERE blueprint_key=? AND step_id=?');
        $q->execute([$blueprintKey,$stepId]);
        $mode=$q->fetchColumn();
        return $mode===false?'four_eyes':OfficeApprovalPolicy::resolve('project_start',(string)$mode);
    }
    public function set(string $blueprintKey,string $stepId,string $mode,int $actorId,bool $isTest,bool $isAdmin):void {
        if (!$isTest||!$isAdmin||$actorId<1) throw new DomainException('Admin in Office-test required');
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,127}$/D',$blueprintKey)
            || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D',$stepId)) throw new DomainException('Invalid blueprint step');
        OfficeApprovalPolicy::resolve('project_start',$mode);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT approval_mode FROM office_test_blueprint_approval_modes WHERE blueprint_key=? AND step_id=? FOR UPDATE');
            $q->execute([$blueprintKey,$stepId]);
            $old=$q->fetchColumn();$old=$old===false?'four_eyes':(string)$old;
            if ($old!==$mode) {
                $up=$this->db->prepare('INSERT INTO office_test_blueprint_approval_modes (blueprint_key,step_id,approval_mode,changed_by_operator_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE approval_mode=VALUES(approval_mode),changed_by_operator_id=VALUES(changed_by_operator_id)');
                $up->execute([$blueprintKey,$stepId,$mode,$actorId]);
                $ev=$this->db->prepare('INSERT INTO office_test_blueprint_approval_mode_events (blueprint_key,step_id,old_mode,new_mode,actor_operator_id) VALUES (?,?,?,?,?)');
                $ev->execute([$blueprintKey,$stepId,$old,$mode,$actorId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
