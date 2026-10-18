<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;

/**
 * Conservative cancellation reconciliation. Does not remove assignments:
 * deactivates only an exact verified mapping for a cancelled native shift.
 * Never called from mobile and never activates any OnSite assignment.
 */
final class OnSiteOfficeCancellationProjection {
  public function __construct(private PDO $db, private OnSiteProjectionWriter $writer) {}

  /** @param array<int,string> $verifiedEmployees
   *  @param array<int,string> $verifiedZones
   */
  public function deactivateCancelled(int $shiftId, array $verifiedEmployees, array $verifiedZones): int {
    if ($shiftId < 1) throw new \InvalidArgumentException('Invalid shift');
    $query = $this->db->prepare('SELECT project_id, person_id, status FROM office_inzet_shifts WHERE id = ?');
    $query->execute([$shiftId]);
    $shift = $query->fetch(PDO::FETCH_ASSOC);
    if (!$shift || $shift['status'] !== 'cancelled') return 0;
    $projectId = (int)$shift['project_id'];
    $personId = (int)$shift['person_id'];
    if ($projectId < 1 || $personId < 1
      || !isset($verifiedEmployees[$personId],$verifiedZones[$projectId])
      || trim($verifiedEmployees[$personId]) === '' || trim($verifiedZones[$projectId]) === '') {
      throw new \DomainException('Verified cancellation mapping required');
    }
    // Project/zone assignment identity is broader than a single shift.
    // Do not deactivate it while another confirmed shift for this person/project exists.
    $other = $this->db->prepare("SELECT 1 FROM office_inzet_shifts
      WHERE project_id = ? AND person_id = ? AND id <> ? AND status = 'confirmed' LIMIT 1");
    $other->execute([$projectId,$personId,$shiftId]);
    if ($other->fetchColumn()) return 0;
    return $this->writer->deactivate($verifiedEmployees[$personId],(string)$projectId,$verifiedZones[$projectId]);
  }
}
