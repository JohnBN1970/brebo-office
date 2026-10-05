<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\brebo_resident_service\Contract\ResidentAccessReadRepositoryInterface;

/** Calculates access readiness for a technical zone/cluster within a project. */
final class ZoneAccessReadiness {

  public function __construct(
    private readonly ResidentAccessReadRepositoryInterface $repository,
    private readonly AccessContactResolver $resolver,
  ) {}

  /** @return array{total:int,ready:int,attention:int,vacant:int,no_contact:int,refused:int,blocked:int,unknown:int,percentage:float,rows:array} */
  public function calculate(int $projectId, int $buildingNid, int $technicalZoneId): array {
    $result = [
      'total' => 0, 'ready' => 0, 'attention' => 0, 'vacant' => 0,
      'no_contact' => 0, 'refused' => 0, 'blocked' => 0, 'unknown' => 0,
      'percentage' => 100.0, 'rows' => [],
    ];

    $residences = $this->repository->residencesForZone($buildingNid, $technicalZoneId);
    if ($residences === []) {
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

    foreach ($residences as $residence) {
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
        'scope' => 'residence',
        'id' => (int) $residence['id'],
        'label' => (string) $residence['address_line'],
        'occupancy' => $occupancy,
        'status' => $status,
        'inherited_from' => $effective['inherited_from'] ?? 'none',
        'ready' => $ready,
      ];
    }

    $result['percentage'] = $result['total'] > 0 ? round(($result['ready'] / $result['total']) * 100, 1) : 100.0;
    return $result;
  }

}
