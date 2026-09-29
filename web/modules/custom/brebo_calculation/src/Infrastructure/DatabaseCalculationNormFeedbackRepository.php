<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationNormFeedbackRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationNormFeedbackRepository implements CalculationNormFeedbackRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function insertObservation(array $values): int {
    return (int) $this->database->insert('brebo_calculation_norm_observation')->fields($values)->execute();
  }

  public function summary(string $domain, string $normKey): array {
    if (!$this->database->schema()->tableExists('brebo_calculation_norm_observation')) {
      return [];
    }
    $query = $this->database->select('brebo_calculation_norm_observation', 'o');
    $query->addExpression('COUNT(*)', 'samples');
    $query->addExpression('AVG(planned_value)', 'planned_avg');
    $query->addExpression('AVG(actual_value)', 'actual_avg');
    $query->addExpression('AVG(delta_pct)', 'delta_pct_avg');
    $query->condition('domain', $domain)->condition('norm_key', $normKey);
    return $query->execute()->fetchAssoc() ?: [];
  }
}
