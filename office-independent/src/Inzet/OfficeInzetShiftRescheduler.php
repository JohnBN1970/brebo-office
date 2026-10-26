<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Explicit rescheduling boundary. Existing shift remains the source of truth.
 * Only times may change; project and person cannot silently change.
 */
final class OfficeInzetShiftRescheduler {
  public function __construct(private PDO $db) {}

  public function rescheduleForOperator(string $operatorId, int $shiftId, string $expectedStart, string $expectedEnd, string $newStart, string $newEnd): void {
    require_once __DIR__ . '/OfficeInzetShiftAssignmentControl.php';
    if ($operatorId === '' || strlen($operatorId)>128 || $shiftId<1) throw new RuntimeException('Invalid shift or operator');
    $this->db->beginTransaction();
    try {
      $lookup = $this->db->prepare('SELECT person_id FROM office_inzet_shifts WHERE id = ?');
      $lookup->execute([$shiftId]);
      $personId = $lookup->fetchColumn();
      if ($personId === false) throw new RuntimeException('Shift not found');
      $lock = $this->db->prepare('SELECT id FROM office_inzet_people WHERE id = ? AND active = 1 FOR UPDATE');
      $lock->execute([$personId]);
      if (!$lock->fetchColumn()) throw new RuntimeException('Employee not active');
      $get = $this->db->prepare('SELECT project_id,person_id,starts_at,ends_at,status FROM office_inzet_shifts WHERE id = ? FOR UPDATE');
      $get->execute([$shiftId]);
      $current = $get->fetch(PDO::FETCH_ASSOC);
      if (!$current || (string)$current['person_id'] !== (string)$personId
        || !in_array($current['status'],['planned','confirmed'],true)) throw new RuntimeException('Shift not editable');
      if ($current['starts_at'] !== $expectedStart || $current['ends_at'] !== $expectedEnd) {
        throw new RuntimeException('Shift changed since it was opened');
      }
      $permission = $this->db->prepare("SELECT 1 FROM office_project_memberships m
        INNER JOIN office_operator_accounts a ON a.id = m.operator_id
        WHERE m.operator_id = ? AND m.project_id = ? AND m.permission = 'manage'
          AND a.active = 1 FOR UPDATE");
      $permission->execute([$operatorId,$current['project_id']]);
      if (!$permission->fetchColumn()) throw new RuntimeException('Project management permission required');
      $team = $this->db->prepare('SELECT 1 FROM office_inzet_project_team WHERE project_id = ? AND person_id = ? FOR UPDATE');
      $team->execute([$current['project_id'],$personId]);
      if (!$team->fetchColumn()) throw new RuntimeException('Employee no longer assigned to project');
      $candidate = OfficeInzetShiftAssignmentControl::validate([
        'project_id'=>(int)$current['project_id'],'person_id'=>(int)$personId,
        'starts_at'=>$newStart,'ends_at'=>$newEnd
      ],[(int)$current['project_id']],[(int)$personId]);
      $overlap = $this->db->prepare('SELECT id,person_id,starts_at,ends_at,status FROM office_inzet_shifts
        WHERE person_id = ? AND starts_at < ? AND ends_at > ? FOR UPDATE');
      $overlap->execute([$personId,$newEnd,$newStart]);
      OfficeInzetShiftAssignmentControl::assertNoOverlap($candidate,$overlap->fetchAll(PDO::FETCH_ASSOC),$shiftId);
      $update = $this->db->prepare('UPDATE office_inzet_shifts SET starts_at = ?, ends_at = ? WHERE id = ? AND starts_at = ? AND ends_at = ?');
      $update->execute([$newStart,$newEnd,$shiftId,$expectedStart,$expectedEnd]);
      if ($update->rowCount()!==1) throw new RuntimeException('Shift revision conflict or unchanged times');
      $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
