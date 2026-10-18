<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Explicit revision reconciliation for an already staged OnSite assignment.
 * Never activates a shift or changes its project/employee mapping.
 * Caller must supply verified canonical mapping and the old UTC start.
 */
final class OnSiteOfficeShiftRevisionProjection {
  public function __construct(private PDO $db) {}

  public function revise(int $shiftId, string $verifiedEmployeeId, string $verifiedZoneId, string $expectedOldStartUtc): bool {
    if ($shiftId < 1 || trim($verifiedEmployeeId) === '' || trim($verifiedZoneId) === ''
      || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$expectedOldStartUtc)) {
      throw new \InvalidArgumentException('Verified revision identity required');
    }
    $query = $this->db->prepare('SELECT project_id,person_id,starts_at,ends_at,status FROM office_inzet_shifts WHERE id = ?');
    $query->execute([$shiftId]);
    $shift = $query->fetch(PDO::FETCH_ASSOC);
    if (!$shift || $shift['status'] !== 'confirmed') return false;
    $utc = new DateTimeZone('UTC');
    $from = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$shift['starts_at'],$utc);
    $until = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$shift['ends_at'],$utc);
    $old = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$expectedOldStartUtc,$utc);
    if (!$from || !$until || !$old || $until <= $from
      || $from->format('Y-m-d H:i:s') !== $shift['starts_at']
      || $until->format('Y-m-d H:i:s') !== $shift['ends_at']
      || $old->format('Y-m-d H:i:s') !== $expectedOldStartUtc) {
      throw new \InvalidArgumentException('Invalid canonical revision times');
    }
    $projectId = (string)(int)$shift['project_id'];
    $this->db->beginTransaction();
    try {
      $select = $this->db->prepare('SELECT active,valid_until FROM onsite_project_zone_assignments
        WHERE employee_id = ? AND project_id = ? AND zone_id = ? AND valid_from = ? FOR UPDATE');
      $oldIdentity = [$verifiedEmployeeId,$projectId,$verifiedZoneId,$old->format('c')];
      $select->execute($oldIdentity);
      $previous = $select->fetch(PDO::FETCH_ASSOC);
      if (!$previous) { $this->db->rollBack(); return false; }
      if ($old->format('c') === $from->format('c') && $previous['valid_until'] === $until->format('c')) {
        $this->db->commit(); return false;
      }
      // A changed end time keeps the same assignment identity. Only check
      // collisions when the canonical start time itself changes.
      if ($old->format('c') !== $from->format('c')) {
        $collision = $this->db->prepare('SELECT 1 FROM onsite_project_zone_assignments
          WHERE employee_id = ? AND project_id = ? AND zone_id = ? AND valid_from = ? FOR UPDATE');
        $collision->execute([$verifiedEmployeeId,$projectId,$verifiedZoneId,$from->format('c')]);
        if ($collision->fetchColumn()) throw new \DomainException('New assignment identity already exists');
      }
      $update = $this->db->prepare('UPDATE onsite_project_zone_assignments
        SET valid_from = ?, valid_until = ?, active = 0
        WHERE employee_id = ? AND project_id = ? AND zone_id = ? AND valid_from = ?');
      $update->execute([$from->format('c'),$until->format('c'),...$oldIdentity]);
      if ($update->rowCount() !== 1) throw new \RuntimeException('Revision conflict');
      $this->db->commit();
      return true;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
