<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Persists glass positions while enforcing canonical object relations.
 */
final class GlassPositionRepository {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * @param array<string, mixed> $values
   */
  public function insert(array $values): int {
    $this->assertNodeBundle((int) $values['building_nid'], 'brebo_building', 'gebouw');
    if (!empty($values['project_nid'])) {
      $this->assertNodeBundle((int) $values['project_nid'], 'brebo_project', 'project');
    }

    $now = $this->time->getRequestTime();
    $values += ['created' => $now, 'changed' => $now];

    return (int) $this->database->insert('brebo_glass_position')
      ->fields($values)
      ->execute();
  }

  private function assertNodeBundle(int $nid, string $bundle, string $label): void {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node || $node->bundle() !== $bundle) {
      throw new \InvalidArgumentException(sprintf('Het gekozen %s is geen geldig BREBO-object.', $label));
    }
  }

}

