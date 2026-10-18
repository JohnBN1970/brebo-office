<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;

/**
 * Explicit trusted reconciliation command. The verified mappings are supplied
 * by a privileged Office service, never by a browser or mobile request.
 * One shift at a time; no background execution or implicit activation.
 */
final class OnSiteOfficeShiftReconciliation {
  public function __construct(
    private PDO $db,
    private OnSiteOfficeCancellationProjection $cancellation,
    private OnSiteOfficeShiftRevisionProjection $revision
  ) {}

  /**
   * @param array<int,string> $verifiedEmployees
   * @param array<int,string> $verifiedZones
   * @return array{status:string,changed:bool}
   */
  public function reconcile(int $shiftId, array $verifiedEmployees, array $verifiedZones, ?string $expectedOldStartUtc = null): array {
    if ($shiftId < 1) throw new \InvalidArgumentException('Invalid shift id');
    $query = $this->db->prepare('SELECT project_id,person_id,status FROM office_inzet_shifts WHERE id = ?');
    $query->execute([$shiftId]);
    $shift = $query->fetch(PDO::FETCH_ASSOC);
    if (!$shift) throw new \RuntimeException('Office shift not found');
    $project = (int)$shift['project_id'];
    $person = (int)$shift['person_id'];
    if ($project < 1 || $person < 1
      || !isset($verifiedEmployees[$person],$verifiedZones[$project])
      || trim($verifiedEmployees[$person]) === '' || trim($verifiedZones[$project]) === '') {
      throw new \DomainException('Verified Office to OnSite mapping required');
    }
    if ($shift['status'] === 'cancelled') {
      return [
        'status'=>'cancelled',
        'changed'=>$this->cancellation->deactivateCancelled($shiftId,$verifiedEmployees,$verifiedZones)>0
      ];
    }
    if ($shift['status'] === 'confirmed') {
      if ($expectedOldStartUtc === null) {
        throw new \InvalidArgumentException('Expected old shift start required for revision');
      }
      return [
        'status'=>'confirmed',
        'changed'=>$this->revision->revise(
          $shiftId,$verifiedEmployees[$person],$verifiedZones[$project],$expectedOldStartUtc
        )
      ];
    }
    // A merely planned shift must never cause OnSite assignment activation.
    return ['status'=>'ignored','changed'=>false];
  }
}
