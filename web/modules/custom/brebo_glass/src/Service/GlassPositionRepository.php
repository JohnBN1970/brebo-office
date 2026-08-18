<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Persists and retrieves glass positions in the canonical object structure.
 */
final class GlassPositionRepository {

  private const SORT_COLUMNS = [
    'position' => 'position_code',
    'location' => 'location',
    'glass_type' => 'glass_type',
    'area' => 'area_m2',
    'weight' => 'estimated_weight_kg',
    'status' => 'technical_status',
    'changed' => 'changed',
  ];

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

  /**
   * Returns a bounded, filterable glass schedule.
   *
   * @return array<int, array<string, mixed>>
   */
  public function findAll(string $search = '', string $status = '', string $sort = 'changed', string $direction = 'desc'): array {
    $query = $this->database->select('brebo_glass_position', 'g')
      ->fields('g');

    if ($status !== '') {
      $query->condition('g.technical_status', $status);
    }

    $search = trim($search);
    if ($search !== '') {
      $group = $query->orConditionGroup()
        ->condition('g.position_code', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('g.location', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('g.composition', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
      $query->condition($group);
    }

    $column = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS['changed'];
    $order = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

    return $query
      ->orderBy('g.' . $column, $order)
      ->range(0, 250)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * @return array<string, int>
   */
  public function countByStatus(): array {
    $query = $this->database->select('brebo_glass_position', 'g');
    $query->addField('g', 'technical_status', 'status');
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('g.technical_status');

    $counts = ['all' => 0];
    foreach ($query->execute() as $row) {
      $counts[(string) $row->status] = (int) $row->total;
      $counts['all'] += (int) $row->total;
    }
    return $counts;
  }

  private function assertNodeBundle(int $nid, string $bundle, string $label): void {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node || $node->bundle() !== $bundle) {
      throw new \InvalidArgumentException(sprintf('Het gekozen %s is geen geldig BREBO-object.', $label));
    }
  }

}
