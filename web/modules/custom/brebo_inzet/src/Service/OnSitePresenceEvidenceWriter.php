<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\OnSiteRepositoryInterface;

/**
 * Persists explicit OnSite clock-action evidence without booking work hours.
 *
 * Coordinates are intentionally not accepted or stored here.
 */
final class OnSitePresenceEvidenceWriter {

  public function __construct(
    private readonly OnSiteRepositoryInterface $repository,
  ) {}

  /**
   * @return array{id:int,project_id:string,building_id:string,zone_id:string,kind:string,occurred_at:string}
   */
  public function record(
    int $uid,
    string $projectId,
    string $zoneId,
    string $kind,
    string $occurredAt,
    string $buildingId = '',
  ): array {
    if (!in_array($kind, ['in', 'out'], TRUE)) {
      throw new \InvalidArgumentException('Ongeldig OnSite eventtype.');
    }

    try {
      $occurred = new \DateTimeImmutable($occurredAt);
    }
    catch (\Throwable) {
      throw new \InvalidArgumentException('Ongeldig tijdstip.');
    }

    $projectIdInt = $this->positiveIntOrZero($projectId);
    $zoneIdInt = $this->positiveIntOrZero($zoneId);
    $buildingIdInt = $this->positiveIntOrZero($buildingId);

    if ($zoneIdInt > 0) {
      $zone = $this->repository->clockZone($zoneIdInt);
      if ($zone === NULL) {
        throw new \InvalidArgumentException('Ongeldige personeelszone.');
      }
      $zoneBuildingId = (int) $zone['building_id'];
      $zoneProjectId = (int) $zone['project_id'];
      if ($buildingIdInt <= 0) {
        $buildingIdInt = $zoneBuildingId;
      }
      elseif ($zoneBuildingId > 0 && $zoneBuildingId !== $buildingIdInt) {
        throw new \InvalidArgumentException('Personeelszone hoort bij een ander gebouw.');
      }
      if ($projectIdInt <= 0) {
        $projectIdInt = $zoneProjectId;
      }
      elseif ($zoneProjectId > 0 && $zoneProjectId !== $projectIdInt) {
        throw new \InvalidArgumentException('Personeelszone hoort bij een ander project.');
      }
    }

    if ($buildingIdInt <= 0 && $projectIdInt <= 0) {
      throw new \InvalidArgumentException('Gebouw of project ontbreekt.');
    }
    if ($buildingIdInt > 0 && !$this->repository->validBuilding($buildingIdInt)) {
      throw new \InvalidArgumentException('Ongeldig gebouw.');
    }
    if ($projectIdInt > 0 && !$this->repository->validProject($projectIdInt)) {
      throw new \InvalidArgumentException('Ongeldig project.');
    }

    $id = $this->repository->createPresenceEvent([
      'title' => sprintf(
        'OnSite %s gebruiker %d gebouw %d project %d %s',
        strtoupper($kind),
        $uid,
        $buildingIdInt,
        $projectIdInt,
        $occurred->format('Y-m-d H:i:s'),
      ),
      'user_id' => $uid,
      'project_id' => $projectIdInt,
      'building_id' => $buildingIdInt,
      'zone_id' => $zoneIdInt,
      'kind' => $kind,
      'occurred_at' => $occurred->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
    ]);

    return [
      'id' => $id,
      'project_id' => $projectIdInt > 0 ? (string) $projectIdInt : '',
      'building_id' => $buildingIdInt > 0 ? (string) $buildingIdInt : '',
      'zone_id' => $zoneIdInt > 0 ? (string) $zoneIdInt : '',
      'kind' => $kind,
      'occurred_at' => $occurred->format(DATE_ATOM),
    ];
  }

  private function positiveIntOrZero(string $value): int {
    $value = trim($value);
    if ($value === '') {
      return 0;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT);
    if ($validated === FALSE || (int) $validated <= 0) {
      throw new \InvalidArgumentException('Ongeldige identifier.');
    }
    return (int) $validated;
  }

}
