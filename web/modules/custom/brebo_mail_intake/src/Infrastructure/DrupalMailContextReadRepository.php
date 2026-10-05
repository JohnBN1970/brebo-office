<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailContextReadRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for canonical project/building context reads. */
final class DrupalMailContextReadRepository implements MailContextReadRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function activeProjects(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->execute();

    $projects = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $buildingIds = [];
      if ($node->hasField('field_brebo_building_refs')) {
        foreach ($node->get('field_brebo_building_refs')->referencedEntities() as $building) {
          if ($building instanceof NodeInterface && $building->bundle() === 'brebo_building') {
            $buildingIds[] = (int) $building->id();
          }
        }
      }
      $projects[] = [
        'id' => (int) $node->id(),
        'label' => (string) $node->label(),
        'building_ids' => array_values(array_unique($buildingIds)),
      ];
    }
    return $projects;
  }

  public function activeBuildings(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_building')
      ->condition('status', 1)
      ->execute();

    $buildings = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $buildings[] = $this->projectBuilding($node);
    }
    return $buildings;
  }

  public function building(int $buildingId): ?array {
    if ($buildingId <= 0) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($buildingId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_building' || !$node->isPublished()) {
      return NULL;
    }
    return $this->projectBuilding($node);
  }

  /** @return array{id:int,label:string,address:string,postal_code:string,city:string} */
  private function projectBuilding(NodeInterface $node): array {
    return [
      'id' => (int) $node->id(),
      'label' => (string) $node->label(),
      'address' => $this->fieldValue($node, 'field_brebo_address'),
      'postal_code' => $this->fieldValue($node, 'field_brebo_postal_code'),
      'city' => $this->fieldValue($node, 'field_brebo_city'),
    ];
  }

  private function fieldValue(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($node->get($field)->value ?? ''));
  }

}
