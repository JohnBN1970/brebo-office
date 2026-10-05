<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Infrastructure;

use Drupal\brebo_glass\Contract\GlassAvailabilityRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseGlassAvailabilityRepository implements GlassAvailabilityRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function isAvailable(): bool {
    return $this->database->schema()->tableExists('brebo_glass_stock_event');
  }

  public function record(array $values): int {
    return (int) $this->database
      ->insert('brebo_glass_stock_event')
      ->fields($values)
      ->execute();
  }

  public function totals(int $projectId, string $glassGroupKey): array {
    if (!$this->isAvailable()) {
      return [];
    }

    $query = $this->database->select('brebo_glass_stock_event', 'e');
    $query->addField('e', 'event_type');
    $query->addExpression('SUM(e.quantity)', 'total');
    $query
      ->condition('project_nid', $projectId)
      ->condition('glass_group_key', $glassGroupKey)
      ->groupBy('event_type');

    $totals = [];
    foreach ($query->execute() as $row) {
      $totals[(string) $row->event_type] = (float) $row->total;
    }
    return $totals;
  }

}
