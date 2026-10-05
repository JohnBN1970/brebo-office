<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\brebo_resident_service\Contract\ResidentAccessReadRepositoryInterface;

/** Resolves the most specific applicable access/contact instruction. */
final class AccessContactResolver {

  public function __construct(
    private readonly ResidentAccessReadRepositoryInterface $repository,
  ) {}

  /** Returns effective access/contact data using residence > zone > building > project. */
  public function resolve(?int $buildingNid = NULL, ?int $technicalZoneId = NULL, ?int $residenceId = NULL, ?int $projectId = NULL): ?array {
    $scopes = [];
    if ($residenceId) {
      $scopes[] = ['residence', $residenceId];
    }
    if ($technicalZoneId) {
      $scopes[] = ['technical_zone', $technicalZoneId];
    }
    if ($buildingNid) {
      $scopes[] = ['building', $buildingNid];
    }
    if ($projectId) {
      $scopes[] = ['project', $projectId];
    }

    foreach ($scopes as [$type, $id]) {
      $row = $this->repository->accessForScope($type, $id, $projectId);
      if ($row !== NULL) {
        $row['inherited_from'] = $type;
        return $row;
      }
    }
    return NULL;
  }

  /** Whether work may be considered access-ready. */
  public function isReady(array $access): bool {
    if (empty($access['access_required'])) {
      return TRUE;
    }
    return in_array($access['access_status'] ?? 'unknown', ['confirmed', 'granted', 'key_available'], TRUE);
  }

}
