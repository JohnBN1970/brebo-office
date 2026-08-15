<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\node\NodeInterface;

/** Evaluates whether a work package is access-ready using its canonical scope. */
final class WorkPackageAccessReadiness {

  public function __construct(private readonly ZoneAccessReadiness $zoneReadiness) {}

  /**
   * @return array{applicable:bool,ready:bool,reason:string,project_id:?int,building_nid:?int,technical_zone_id:?int,summary:array}
   */
  public function evaluate(NodeInterface $package): array {
    if ($package->bundle() !== 'brebo_work_package') {
      throw new \InvalidArgumentException('Access readiness can only evaluate brebo_work_package nodes.');
    }

    $projectId = $package->hasField('field_brebo_project_ref') && !$package->get('field_brebo_project_ref')->isEmpty()
      ? (int) $package->get('field_brebo_project_ref')->target_id : NULL;
    $zoneId = $package->hasField('field_brebo_cluster_ref') && !$package->get('field_brebo_cluster_ref')->isEmpty()
      ? (int) $package->get('field_brebo_cluster_ref')->target_id : NULL;

    if (!$projectId || !$zoneId) {
      return [
        'applicable' => FALSE, 'ready' => TRUE,
        'reason' => 'Geen technische zone aan dit werkpakket gekoppeld; geen zonegebonden toegangscontrole toegepast.',
        'project_id' => $projectId, 'building_nid' => NULL, 'technical_zone_id' => $zoneId, 'summary' => [],
      ];
    }

    $zone = \Drupal::entityTypeManager()->getStorage('node')->load($zoneId);
    if (!$zone instanceof NodeInterface || $zone->bundle() !== 'brebo_cluster') {
      return [
        'applicable' => TRUE, 'ready' => FALSE,
        'reason' => 'Technische zone ontbreekt of is ongeldig.',
        'project_id' => $projectId, 'building_nid' => NULL, 'technical_zone_id' => $zoneId, 'summary' => [],
      ];
    }
    $buildingNid = $zone->hasField('field_brebo_building_ref') && !$zone->get('field_brebo_building_ref')->isEmpty()
      ? (int) $zone->get('field_brebo_building_ref')->target_id : NULL;
    if (!$buildingNid) {
      return [
        'applicable' => TRUE, 'ready' => FALSE,
        'reason' => 'Technische zone heeft geen canoniek gebouw.',
        'project_id' => $projectId, 'building_nid' => NULL, 'technical_zone_id' => $zoneId, 'summary' => [],
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
