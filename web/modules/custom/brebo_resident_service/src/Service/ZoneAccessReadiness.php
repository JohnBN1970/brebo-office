<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\Core\Database\Connection;

/** Calculates access readiness for a technical zone/cluster within a project. */
final class ZoneAccessReadiness {

  public function __construct(
    private readonly Connection $database,
    private readonly AccessContactResolver $resolver,
  ) {}

  /**
   * @return array{total:int,ready:int,attention:int,vacant:int,no_contact:int,refused:int,blocked:int,unknown:int,percentage:float,rows:array}
   */
  public function calculate(int $projectId, int $buildingNid, int $technicalZoneId): array {
    $result = [
      'total' => 0, 'ready' => 0, 'attention' => 0, 'vacant' => 0,
      'no_contact' => 0, 'refused' => 0, 'blocked' => 0, 'unknown' => 0,
      'percentage' => 100.0, 'rows' => [],
    ];

    // A zone is allowed to define explicit dwelling scope through canonical
    // brebo_dwelling nodes. When no dwelling scope exists, access readiness is
    // evaluated only at zone/building/project level and does not invent a
    // requirement for every BAG residence in the building.
    $dwellingIds = $this->database->select('node__field_brebo_cluster_ref', 'cr')
      ->fields('cr', ['entity_id'])
      ->condition('cr.field_brebo_cluster_ref_target_id', $technicalZoneId)
      ->condition('cr.deleted', 0)
      ->execute()
      ->fetchCol();

    if (!$dwellingIds) {
      $effective = $this->resolver->resolve($buildingNid, $technicalZoneId, NULL, $projectId);
      $ready = $effective ? $this->resolver->isReady($effective) : TRUE;
      $result['ready'] = $ready ? 1 : 0;
      $result['attention'] = $ready ? 0 : 1;
      $result['percentage'] = $ready ? 100.0 : 0.0;
      $result['rows'][] = [
        'scope' => 'zone',
        'label' => 'Technische zone',
        'occupancy' => NULL,
        'status' => $effective['access_status'] ?? 'not_needed',
        'inherited_from' => $effective['inherited_from'] ?? 'none',
        'ready' => $ready,
      ];
      return $result;
    }

    foreach ($dwellingIds as $dwellingNid) {
      $dwelling = $this->database->select('node__field_brebo_address', 'a')
        ->fields('a', ['field_brebo_address_value'])
        ->condition('entity_id', (int) $dwellingNid)
        ->condition('deleted', 0)
        ->execute()
        ->fetchField();
      if (!$dwelling) {
        continue;
      }

      // Match the permanent BAG-backed residence without creating a second
      // technical truth. A later canonical migration can replace this address
      // bridge with a direct residence reference.
      $residence = $this->database->select('brebo_residence', 'r')
        ->fields('r', ['id', 'address_line', 'occupancy_status'])
        ->condition('building_nid', $buildingNid)
        ->condition('address_line', (string) $dwelling)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if (!$residence) {
        continue;
      }

      $result['total']++;
      $occupancy = (string) ($residence['occupancy_status'] ?: 'unknown');
      if (in_array($occupancy, ['vacant', 'temporarily_vacant'], TRUE)) {
        $result['vacant']++;
      }
      $effective = $this->resolver->resolve($buildingNid, $technicalZoneId, (int) $residence['id'], $projectId);
      $status = $effective['access_status'] ?? 'unknown';
      $ready = $effective ? $this->resolver->isReady($effective) : FALSE;
      $result[$ready ? 'ready' : 'attention']++;
      if (!$ready && array_key_exists($status, $result)) {
        $result[$status]++;
      }
      elseif (!$ready) {
        $result['unknown']++;
      }
      $result['rows'][] = [
        'scope' => 'residence', 'id' => (int) $residence['id'], 'label' => (string) $residence['address_line'],
        'occupancy' => $occupancy, 'status' => $status,
        'inherited_from' => $effective['inherited_from'] ?? 'none', 'ready' => $ready,
      ];
    }

    $result['percentage'] = $result['total'] > 0 ? round(($result['ready'] / $result['total']) * 100, 1) : 100.0;
    return $result;
  }
}
