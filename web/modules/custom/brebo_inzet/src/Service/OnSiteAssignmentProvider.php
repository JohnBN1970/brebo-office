<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\OnSiteRepositoryInterface;

/** Builds the minimal Office assignment payload needed by OnSite. */
final class OnSiteAssignmentProvider {

  public function __construct(
    private readonly OnSiteRepositoryInterface $repository,
    private readonly ProjectClockZoneManager $zoneManager,
  ) {}

  /**
   * @return array<int,array{id:string,name:string,zones:array<int,array{id:string,name:string,latitude:float,longitude:float,radius_metres:float}>}>
   */
  public function currentForUser(int $uid, ?string $date = NULL): array {
    $date ??= (new \DateTimeImmutable('now'))->format('Y-m-d');
    $projects = [];

    foreach ($this->repository->projects($this->repository->projectIdsForUser($uid, $date)) as $project) {
      $zones = [];
      foreach ($this->zoneManager->loadForProject((int) $project['id']) as $zone) {
        if (!$zone['active']) {
          continue;
        }
        $zones[] = [
          'id' => (string) $zone['id'],
          'name' => $zone['name'],
          'latitude' => $zone['latitude'],
          'longitude' => $zone['longitude'],
          'radius_metres' => $zone['radius'],
        ];
      }
      $projects[] = [
        'id' => (string) $project['id'],
        'name' => (string) $project['name'],
        'zones' => $zones,
      ];
    }

    return $projects;
  }

  /**
   * Returns known BREBO buildings and personnel zones for user-initiated
   * clock actions. It must never drive background location tracking.
   *
   * @return array<int,array<string,mixed>>
   */
  public function clockLocations(): array {
    return $this->repository->clockLocations();
  }

}
