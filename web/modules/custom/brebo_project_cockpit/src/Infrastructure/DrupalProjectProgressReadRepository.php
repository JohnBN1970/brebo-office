<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectProgressReadRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal entity adapter for project progress reads. */
final class DrupalProjectProgressReadRepository implements ProjectProgressReadRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  public function activities(int $projectId): array {
    $bundle = 'brebo_plan_activity';
    if ($this->entityTypeManager->getStorage('node_type')->load($bundle) === NULL) {
      return [];
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'])) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId)
      ->execute();
    if ($ids === []) {
      return [];
    }

    $activities = [];
    foreach ($storage->loadMultiple($ids) as $activity) {
      if (!$activity instanceof NodeInterface) {
        continue;
      }

      $activities[] = [
        'progress' => $this->numericField($activity, 'field_brebo_plan_progress'),
        'duration' => $this->numericField($activity, 'field_brebo_plan_duration'),
        'start' => $this->stringFieldOrNull($activity, 'field_brebo_plan_start'),
        'baseline_end' => $this->stringFieldOrNull($activity, 'field_brebo_plan_baseline_end'),
        'end' => $this->stringFieldOrNull($activity, 'field_brebo_plan_end'),
        'status' => $this->stringField($activity, 'field_brebo_plan_status'),
        'critical' => (bool) ($activity->hasField('field_brebo_plan_critical') ? $activity->get('field_brebo_plan_critical')->value : FALSE),
      ];
    }

    return $activities;
  }

  private function numericField(NodeInterface $node, string $field): ?float {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return NULL;
    }
    $value = $node->get($field)->value;
    return is_numeric($value) ? (float) $value : NULL;
  }

  private function stringField(NodeInterface $node, string $field): string {
    return !$node->hasField($field) || $node->get($field)->isEmpty()
      ? ''
      : (string) $node->get($field)->value;
  }

  private function stringFieldOrNull(NodeInterface $node, string $field): ?string {
    $value = $this->stringField($node, $field);
    return $value === '' ? NULL : $value;
  }

}
