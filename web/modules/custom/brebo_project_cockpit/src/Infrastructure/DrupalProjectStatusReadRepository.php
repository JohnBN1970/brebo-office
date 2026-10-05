<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectStatusReadRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/** Drupal entity adapter for project-status source reads. */
final class DrupalProjectStatusReadRepository implements ProjectStatusReadRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  public function domainStatusValues(string $bundle, int $projectId, array $statusFields): array {
    if ($this->entityTypeManager->getStorage('node_type')->load($bundle) === NULL) {
      return ['available' => FALSE, 'total' => 0, 'status_values' => []];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'])) {
      return ['available' => FALSE, 'total' => 0, 'status_values' => []];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId)
      ->execute();

    $statusField = NULL;
    foreach ($statusFields as $candidate) {
      if (isset($fields[$candidate])) {
        $statusField = $candidate;
        break;
      }
    }

    $values = [];
    if ($statusField !== NULL) {
      foreach ($storage->loadMultiple($ids) as $node) {
        $values[] = (string) $node->get($statusField)->value;
      }
    }

    return ['available' => TRUE, 'total' => count($ids), 'status_values' => $values];
  }

  public function clockStatusCounts(int $projectId): array {
    $bundle = 'brebo_clock_registration';
    if ($this->entityTypeManager->getStorage('node_type')->load($bundle) === NULL) {
      return ['available' => FALSE, 'total' => 0, 'red' => 0, 'orange' => 0];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'], $fields['field_brebo_clock_severity'])) {
      return ['available' => FALSE, 'total' => 0, 'red' => 0, 'orange' => 0];
    }

    $base = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId);

    return [
      'available' => TRUE,
      'total' => (int) (clone $base)->count()->execute(),
      'red' => (int) (clone $base)->condition('field_brebo_clock_severity', 'rood')->count()->execute(),
      'orange' => (int) (clone $base)->condition('field_brebo_clock_severity', 'oranje')->count()->execute(),
    ];
  }

}
