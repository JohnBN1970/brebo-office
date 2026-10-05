<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Infrastructure;

use Drupal\brebo_glass\Contract\GlassPositionPersistenceInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

final class DrupalGlassPositionPersistence implements GlassPositionPersistenceInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  public function currentTime(): int {
    return $this->time->getRequestTime();
  }

  public function isNodeBundle(int $nid, string $bundle): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    return $node !== NULL && $node->bundle() === $bundle;
  }

  public function insert(array $values): int {
    return (int) $this->database
      ->insert('brebo_glass_position')
      ->fields($values)
      ->execute();
  }

  public function findAll(string $search, string $status, string $column, string $order, int $limit): array {
    $query = $this->database->select('brebo_glass_position', 'g')->fields('g');

    if ($status !== '') {
      $query->condition('g.technical_status', $status);
    }
    if ($search !== '') {
      $group = $query->orConditionGroup()
        ->condition('g.position_code', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('g.location', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('g.composition', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
      $query->condition($group);
    }

    return $query
      ->orderBy('g.' . $column, $order)
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

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

  public function find(int $id): ?array {
    $record = $this->database->select('brebo_glass_position', 'g')
      ->fields('g')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    return $record === FALSE ? NULL : $record;
  }

  public function approveMeasured(int $id, array $values): int {
    return (int) $this->database
      ->update('brebo_glass_position')
      ->fields($values)
      ->condition('id', $id)
      ->condition('technical_status', 'measured')
      ->condition('approved_at', NULL, 'IS NULL')
      ->execute();
  }

}
