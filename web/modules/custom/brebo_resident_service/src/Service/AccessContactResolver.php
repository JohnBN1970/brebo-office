<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Service;

use Drupal\Core\Database\Connection;

/** Resolves the most specific applicable access/contact instruction. */
final class AccessContactResolver {

  public function __construct(private readonly Connection $database) {}

  /**
   * Returns effective access/contact data using residence > zone > building.
   */
  public function resolve(int $buildingNid, ?int $technicalZoneId = NULL, ?int $residenceId = NULL, ?int $projectId = NULL): ?array {
    $scopes = [];
    if ($residenceId) {
      $scopes[] = ['residence', $residenceId];
    }
    if ($technicalZoneId) {
      $scopes[] = ['technical_zone', $technicalZoneId];
    }
    $scopes[] = ['building', $buildingNid];

    foreach ($scopes as [$type, $id]) {
      $query = $this->database->select('brebo_access_contact', 'a')->fields('a')->condition('scope_type', $type)->condition('scope_id', $id);
      if ($projectId !== NULL) {
        $or = $query->orConditionGroup()->condition('project_id', $projectId)->isNull('project_id');
        $query->condition($or);
      }
      $query->orderBy('project_id', 'DESC')->orderBy('changed', 'DESC')->range(0, 1);
      $row = $query->execute()->fetchAssoc();
      if ($row) {
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
