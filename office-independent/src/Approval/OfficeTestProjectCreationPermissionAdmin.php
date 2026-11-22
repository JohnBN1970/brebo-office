<?php
declare(strict_types=1);
namespace Brebo\Office\Approval;
use PDO;
use DomainException;

/** Explicit Office-test administrator action. Never trust a browser preview role. */
final class OfficeTestProjectCreationPermissionAdmin {
    public function __construct(private readonly PDO $db) {}

    public function status(string $operatorId): bool {
        $q=$this->db->prepare("SELECT 1 FROM office_operator_accounts a
            JOIN office_module_permissions p ON p.operator_id=a.id
            WHERE a.id=? AND a.active=1 AND p.module='projects'
              AND p.permission='create' LIMIT 1");
        $q->execute([$operatorId]);
        return $q->fetchColumn()!==false;
    }

    public function change(string $actorId,string $targetId,string $action,string $reason): bool {
        if (!ctype_digit($actorId) || !ctype_digit($targetId)
            || (int)$actorId < 1 || (int)$targetId < 1
            || !in_array($action,['grant','revoke'],true)
            || trim($reason)==='' || mb_strlen(trim($reason))>255) {
            throw new DomainException('Ongeldige autorisatieaanvraag.');
        }
        // Recheck the site administrator from the database within the request,
        // not from a submitted role, session hint or CSS preview mode.
        if (!(new OfficeTestSiteAdministratorAccess($this->db))->isAuthorized($actorId,true)) {
            throw new DomainException('Alleen sitebeheerder.');
        }
        if ($this->db->inTransaction()) throw new DomainException('Ongeldige transactie.');
        $this->db->beginTransaction();
        try {
            $target=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 FOR UPDATE');
            $target->execute([$targetId]);
            if ($target->fetchColumn()===false) throw new DomainException('Actieve medewerker niet gevonden.');
            $current=$this->db->prepare("SELECT operator_id FROM office_module_permissions
                WHERE operator_id=? AND module='projects' AND permission='create' FOR UPDATE");
            $current->execute([$targetId]);
            $has=$current->fetchColumn()!==false;
            if (($action==='grant' && $has) || ($action==='revoke' && !$has)) {
                $this->db->commit();
                return false;
            }
            if ($action==='grant') {
                $stmt=$this->db->prepare("INSERT INTO office_module_permissions (operator_id,module,permission)
                    VALUES (?,'projects','create')");
            } else {
                $stmt=$this->db->prepare("DELETE FROM office_module_permissions
                    WHERE operator_id=? AND module='projects' AND permission='create'");
            }
            $stmt->execute([$targetId]);
            $audit=$this->db->prepare('INSERT INTO office_test_project_create_permission_events
                (actor_operator_id,target_operator_id,action,reason) VALUES (?,?,?,?)');
            $audit->execute([$actorId,$targetId,$action,trim($reason)]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
