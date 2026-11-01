<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/** Soft archive/reactivate. Never deletes people, assignments or OnSite history. */
final class OfficePersonnelStatusAction {
  public function __construct(private PDO $db) {}

  public function apply(array $post, string $operatorId, string $sessionId, string $secret): bool {
    if (!OfficeInzetPersonnelProfileCsrf::verify($post['csrf_token'] ?? null, $sessionId, $secret)) {
      throw new \DomainException('Invalid personnel request token');
    }
    $projectId = filter_var($post['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $personId = filter_var($post['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $operation = $post['personnel_action'] ?? null;
    if (!is_int($projectId) || !is_int($personId) || !in_array($operation, ['archive','reactivate'], true)
      || $operatorId === '' || strlen($operatorId) > 128) throw new \InvalidArgumentException('Invalid personnel status action');
    $this->db->beginTransaction();
    try {
      $q = $this->db->prepare("SELECT p.id FROM office_inzet_people p
        INNER JOIN office_inzet_project_team t ON t.person_id=p.id AND t.project_id=?
        INNER JOIN office_project_memberships m ON m.project_id=t.project_id AND m.permission='manage'
        INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
        WHERE p.id=? AND m.operator_id=? FOR UPDATE");
      $q->execute([$projectId,$personId,$operatorId]);
      if ($q->fetchColumn() === false) {
        $this->db->rollBack();
        return false;
      }
      $update = $this->db->prepare('UPDATE office_inzet_people SET active=? WHERE id=?');
      $update->execute([$operation === 'reactivate' ? 1 : 0,$personId]);
      $this->db->commit();
      return true;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
