<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Trusted Office write boundary. Call only after the operator's manage rights
 * and the person's project eligibility have been verified server-side.
 * Locks the personnel row to serialize concurrent scheduling for one person.
 */
final class OfficeInzetShiftWriter {
  public function __construct(private PDO $db) {}

  public function create(array $request, array $managedProjects, array $eligiblePeople, ?string $operatorId = null): int {
    $shift = OfficeInzetShiftAssignmentControl::validate($request,$managedProjects,$eligiblePeople);
    $personId = $shift['person_id'];
    $this->db->beginTransaction();
    try {
      $lock = $this->db->prepare('SELECT id FROM office_inzet_people WHERE id = ? AND active = 1 FOR UPDATE');
      $lock->execute([$personId]);
      if (!$lock->fetchColumn()) throw new RuntimeException('Personnel record not active');
      if ($operatorId !== null) {
        $permission = $this->db->prepare("SELECT m.project_id FROM office_project_memberships m
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE m.operator_id = ? AND m.project_id = ? AND m.permission = 'manage' AND a.active = 1 FOR UPDATE");
        $permission->execute([$operatorId, $shift['project_id']]);
        if (!$permission->fetchColumn()) throw new RuntimeException('Project management permission revoked');
        $team = $this->db->prepare('SELECT t.person_id FROM office_inzet_project_team t
          WHERE t.project_id = ? AND t.person_id = ? FOR UPDATE');
        $team->execute([$shift['project_id'], $personId]);
        if (!$team->fetchColumn()) throw new RuntimeException('Employee project assignment revoked');
      }
      $query = $this->db->prepare("SELECT id, person_id, starts_at, ends_at, status FROM office_inzet_shifts
        WHERE person_id = ? AND starts_at < ? AND ends_at > ? FOR UPDATE");
      $query->execute([$personId,$shift['ends_at'],$shift['starts_at']]);
      OfficeInzetShiftAssignmentControl::assertNoOverlap($shift,$query->fetchAll(PDO::FETCH_ASSOC));
      $insert = $this->db->prepare('INSERT INTO office_inzet_shifts (project_id,person_id,starts_at,ends_at,status) VALUES (?,?,?,?,?)');
      $insert->execute([$shift['project_id'],$personId,$shift['starts_at'],$shift['ends_at'],$shift['status']]);
      $id = (int)$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
