<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Resolves configurable productivity and material norms for BREBO domains. */
final class CalculationNormLibrary {

  public function __construct(private readonly Connection $database) {}

  /**
   * @param array<string, mixed> $context
   */
  public function value(string $domain, string $normKey, array $context, float $fallback): float {
    if (!$this->database->schema()->tableExists('brebo_calculation_norm')) {
      return $fallback;
    }

    $rows = $this->database->select('brebo_calculation_norm', 'n')
      ->fields('n')
      ->condition('domain', $domain)
      ->condition('norm_key', $normKey)
      ->condition('active', 1)
      ->orderBy('priority', 'DESC')
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $conditions = json_decode((string) ($row['conditions_json'] ?? ''), TRUE);
      if (!is_array($conditions)) {
        $conditions = [];
      }
      if ($this->matches($conditions, $context)) {
        return (float) $row['value'];
      }
    }
    return $fallback;
  }

  /** @param array<string,mixed> $conditions @param array<string,mixed> $context */
  private function matches(array $conditions, array $context): bool {
    foreach ($conditions as $key => $expected) {
      if (str_ends_with($key, '_min')) {
        $field = substr($key, 0, -4);
        if (!isset($context[$field]) || (float) $context[$field] < (float) $expected) return FALSE;
        continue;
      }
      if (str_ends_with($key, '_max')) {
        $field = substr($key, 0, -4);
        if (!isset($context[$field]) || (float) $context[$field] > (float) $expected) return FALSE;
        continue;
      }
      if (!array_key_exists($key, $context)) return FALSE;
      if (is_array($expected)) {
        if (!in_array($context[$key], $expected, TRUE)) return FALSE;
      }
      elseif ((string) $context[$key] !== (string) $expected) return FALSE;
    }
    return TRUE;
  }

}
