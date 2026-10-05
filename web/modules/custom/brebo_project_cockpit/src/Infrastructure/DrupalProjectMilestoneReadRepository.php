<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectMilestoneReadRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal entity adapter for ordered project route-item reads. */
final class DrupalProjectMilestoneReadRepository implements ProjectMilestoneReadRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function routeItems(int $projectId): array {
    if ($this->entityTypeManager->getStorage('node_type')->load('brebo_route_item') === NULL) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_route_item')
      ->condition('field_brebo_project_ref', $projectId)
      ->sort('field_brebo_route_sequence', 'ASC')
      ->sort('field_brebo_route_due', 'ASC')
      ->execute();
    if ($ids === []) {
      return [];
    }

    $items = [];
    foreach ($storage->loadMultiple($ids) as $item) {
      if (!$item instanceof NodeInterface) {
        continue;
      }

      $owner = $item->hasField('field_brebo_route_owner')
        ? $item->get('field_brebo_route_owner')->entity
        : NULL;

      $items[] = [
        'id' => (int) $item->id(),
        'label' => (string) $item->label(),
        'status' => $this->value($item, 'field_brebo_route_status'),
        'phase' => $this->value($item, 'field_brebo_lens_domain'),
        'due' => ($due = $this->value($item, 'field_brebo_route_due')) !== '' ? $due : NULL,
        'kind' => $this->value($item, 'field_brebo_route_kind'),
        'owner' => $owner ? (string) $owner->label() : NULL,
        'evidence' => $this->value($item, 'field_brebo_route_evidence'),
      ];
    }

    return $items;
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

}
