<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\brebo_resident_service\Contract\ResidentAccessReadRepositoryInterface;

/** Evaluates whether a work package is access-ready using its canonical scope. */
final class WorkPackageAccessReadiness {

  public function __construct(
    private readonly ResidentAccessReadRepositoryInterface $repository,
    private readonly ZoneAccessReadiness $zoneReadiness,
  ) {}

  /** @return array{applicable:bool,ready:bool,reason:string,project_id:?int,building_nid:?int,technical_zone_id:?int,summary:array} */
  public function evaluate(int $packageId): array {
    $package = $this->repository->workPackage($packageId);
    if ($package === NULL) {
      throw new \InvalidArgumentException('Access readiness can only evaluate brebo_work_package nodes.');
    }

    $projectId = $package['project_id'];
    $zoneId = $package['technical_zone_id'];
    if (!$projectId || !$zoneId) {
      return [
        'applicable' => FALSE,
        'ready' => TRUE,
        'reason' => 'Geen technische zone aan dit werkpakket gekoppeld; geen zonegebonden toegangscontrole toegepast.',
        'project_id' => $projectId,
        'building_nid' => NULL,
        'technical_zone_id' => $zoneId,
        'summary' => [],
      ];
    }

    $buildingNid = $this->repository->buildingForZone($zoneId);
    if (!$buildingNid) {
      return [
        'applicable' => TRUE,
        'ready' => FALSE,
        'reason' => 'Technische zone ontbreekt, is ongeldig of heeft geen canoniek gebouw.',
        'project_id' => $projectId,
        'building_nid' => NULL,
        'technical_zone_id' => $zoneId,
        'summary' => [],
      ];
    }

    $summary = $this->zoneReadiness->calculate($projectId, $buildingNid, $zoneId);
    $ready = $summary['attention'] === 0;
    return [
      'applicable' => TRUE,
      'ready' => $ready,
      'reason' => $ready ? 'Toegang is gereed voor de technische scope.' : sprintf('%d scope-item(s) vragen nog aandacht voor toegang.', $summary['attention']),
      'project_id' => $projectId,
      'building_nid' => $buildingNid,
      'technical_zone_id' => $zoneId,
      'summary' => $summary,
    ];
  }

}
