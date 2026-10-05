<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Infrastructure;

use Drupal\brebo_resident_service\Contract\ResidentAccessReadRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal/database adapter for resident access-readiness source data. */
final class DrupalResidentAccessReadRepository implements ResidentAccessReadRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function accessForScope(string $scopeType, int $scopeId, ?int $projectId = NULL): ?array {
    $query = $this->database->select('brebo_access_contact', 'a')
      ->fields('a')
      ->condition('scope_type', $scopeType)
      ->condition('scope_id', $scopeId);

    if ($scopeType !== 'project' && $projectId !== NULL) {
      $or = $query->orConditionGroup()
        ->condition('project_id', $projectId)
        ->isNull('project_id');
      $query->condition($or);
    }

    $query->orderBy('project_id', 'DESC')
      ->orderBy('changed', 'DESC')
      ->range(0, 1);

    $row = $query->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function residencesForZone(int $buildingNid, int $technicalZoneId): array {
    $dwellingIds = $this->database->select('node__field_brebo_cluster_ref', 'cr')
      ->fields('cr', ['entity_id'])
      ->condition('cr.field_brebo_cluster_ref_target_id', $technicalZoneId)
      ->condition('cr.deleted', 0)
      ->execute()
      ->fetchCol();

    if (!$dwellingIds) {
      return [];
    }

    $rows = [];
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

      $rows[] = [
        'id' => (int) $residence['id'],
        'address_line' => (string) $residence['address_line'],
        'occupancy_status' => (string) ($residence['occupancy_status'] ?: 'unknown'),
      ];
    }
    return $rows;
  }

  public function workPackage(int $packageId): ?array {
    $node = $this->entityTypeManager->getStorage('node')->load($packageId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_work_package') {
      return NULL;
    }

    return [
      'id' => (int) $node->id(),
      'project_id' => $node->hasField('field_brebo_project_ref') && !$node->get('field_brebo_project_ref')->isEmpty()
        ? (int) $node->get('field_brebo_project_ref')->target_id
        : NULL,
      'technical_zone_id' => $node->hasField('field_brebo_cluster_ref') && !$node->get('field_brebo_cluster_ref')->isEmpty()
        ? (int) $node->get('field_brebo_cluster_ref')->target_id
        : NULL,
    ];
  }

  public function buildingForZone(int $technicalZoneId): ?int {
    $zone = $this->entityTypeManager->getStorage('node')->load($technicalZoneId);
    if (!$zone instanceof NodeInterface || $zone->bundle() !== 'brebo_cluster') {
      return NULL;
    }
    $buildingId = $zone->hasField('field_brebo_building_ref') && !$zone->get('field_brebo_building_ref')->isEmpty()
      ? (int) $zone->get('field_brebo_building_ref')->target_id
      : 0;
    return $buildingId > 0 ? $buildingId : NULL;
  }

  public function workPackagesForProject(int $projectId): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', $projectId)
      ->sort('field_brebo_planned_start', 'ASC')
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $package) {
      if (!$package instanceof NodeInterface) {
        continue;
      }
      $rows[] = [
        'id' => (int) $package->id(),
        'label' => (string) $package->label(),
        'planned_start' => $package->hasField('field_brebo_planned_start')
          ? (string) ($package->get('field_brebo_planned_start')->value ?? '')
          : '',
      ];
    }
    return $rows;
  }

  public function timezoneName(): string {
    return (string) ($this->configFactory->get('system.date')->get('timezone.default') ?: date_default_timezone_get());
  }

}
