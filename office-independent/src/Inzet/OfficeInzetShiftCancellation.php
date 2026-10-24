<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Soft-cancel a shift under the live Office project ACL.
 * A cancelled shift stays in the audit history and is not physically deleted.
 * The affected employee row is locked before the shift, matching create locks.
 */
final class OfficeInzetShiftCancellation {
  public function __construct(private PDO $db) {}

  public function cancelForOperator(string $operatorId, int $shiftId): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $shiftId < 1) {
      throw new RuntimeException('Invalid operator or shift');
    }
    $this->db->beginTransaction();
    try {
      // Determine the employee first, then serialize against new assignments.
      $find = $this->db->prepare('SELECT person_id FROM office_inzet_shifts WHERE id = ?');
      $find->execute([$shiftId]);
      $personId = $find->fetchColumn();
      if ($personId === false) throw new RuntimeException('Shift not found');
      $lock = $this->db->prepare('SELECT id FROM office_inzet_people WHERE id = ? FOR UPDATE');
      $lock->execute([$personId]);
      if (!$lock->fetchColumn()) throw new RuntimeException('Employee not found');
      $shift = $this->db->prepare('SELECT project_id, person_id, status FROM office_inzet_shifts WHERE id = ? FOR UPDATE');
      $shift->execute([$shiftId]);
      $row = $shift->fetch(PDO::FETCH_ASSOC);
      if (!$row || (string)$row['person_id'] !== (string)$personId) throw new RuntimeException('Shift changed');
      $acl = $this->db->prepare("SELECT 1 FROM office_project_memberships m
        INNER JOIN office_operator_accounts a ON a.id = m.operator_id
        WHERE m.operator_id = ? AND m.project_id = ? AND m.permission = 'manage'
          AND a.active = 1 FOR UPDATE");
      $acl->execute([$operatorId,$row['project_id']]);
      if (!$acl->fetchColumn()) throw new RuntimeException('Project management permission required');
      if ($row['status'] === 'cancelled') {
        $this->db->commit();
        return false;
      }
      if (!in_array($row['status'], ['planned','confirmed'],true)) {
        throw new RuntimeException('Shift status cannot be cancelled');
      }
      $update = $this->db->prepare("UPDATE office_inzet_shifts SET status = 'cancelled' WHERE id = ? AND status = ?");
      $update->execute([$shiftId,$row['status']]);
      if ($update->rowCount() !== 1) throw new RuntimeException('Shift cancellation conflict');
      $this->db->commit();
      return true;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
