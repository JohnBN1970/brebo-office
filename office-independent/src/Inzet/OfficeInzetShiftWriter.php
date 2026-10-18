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

  public function create(array $request, array $managedProjects, array $eligiblePeople): int {
    $shift = OfficeInzetShiftAssignmentControl::validate($request,$managedProjects,$eligiblePeople);
    $personId = $shift['person_id'];
    $this->db->beginTransaction();
    try {
      $lock = $this->db->prepare('SELECT id FROM office_inzet_people WHERE id = ? AND active = 1 FOR UPDATE');
      $lock->execute([$personId]);
      if (!$lock->fetchColumn()) throw new RuntimeException('Personnel record not active');
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
