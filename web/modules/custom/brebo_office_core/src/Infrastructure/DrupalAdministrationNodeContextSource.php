<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\AdministrationNodeContextSourceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for administration context resolution. */
final class DrupalAdministrationNodeContextSource implements AdministrationNodeContextSourceInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function context(int $nodeId): ?array {
    $node = $this->loadNode($nodeId);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    return [
      'bundle' => $node->bundle(),
      'project_id' => $this->projectIdForNode($node),
    ];
  }

  private function projectIdForNode(NodeInterface $node): ?int {
    if ($node->bundle() === 'brebo_project') {
      return (int) $node->id();
    }

    foreach (['field_brebo_project_ref', 'field_brebo_package_ref', 'field_brebo_calculation_ref'] as $field) {
      if (!$node->hasField($field)) {
        continue;
      }
      $related = $node->get($field)->entity;
      if ($related instanceof NodeInterface) {
        $projectId = $this->projectIdForNode($related);
        if ($projectId !== NULL) {
          return $projectId;
        }
      }
    }

    return NULL;
  }

  private function loadNode(int $nodeId): ?NodeInterface {
    if ($nodeId <= 0) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
