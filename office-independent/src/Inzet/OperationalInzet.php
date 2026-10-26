<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Domain-only validation and variance calculation.
 * No persistence, authentication, or financial posting occurs here.
 */
final class OperationalInzet {
  /**
   * @param array<string,mixed> $planning
   * @param array<string,mixed> $registration
   * @return array{planned_minutes:int,registered_minutes:int,variance_minutes:int,issues:list<string>}
   */
  public static function compare(array $planning, array $registration): array {
    $issues = [];
    foreach (['project_id', 'person_id', 'work_date'] as $field) {
      if (!isset($planning[$field]) || !is_string($planning[$field]) || trim($planning[$field]) === '') {
        $issues[] = 'planning_missing_' . $field;
      }
      if (!isset($registration[$field]) || !is_string($registration[$field]) || trim($registration[$field]) === '') {
        $issues[] = 'registration_missing_' . $field;
      }
      if (isset($planning[$field], $registration[$field])
        && is_string($planning[$field]) && is_string($registration[$field])
        && $planning[$field] !== $registration[$field]) {
        $issues[] = 'mismatch_' . $field;
      }
    }
    $planned = self::minutes($planning, 'planned_minutes', $issues);
    $registered = self::minutes($registration, 'registered_minutes', $issues);
    if ($planned !== $registered) $issues[] = 'variance';
    return [
      'planned_minutes' => $planned,
      'registered_minutes' => $registered,
      'variance_minutes' => $registered - $planned,
      'issues' => array_values(array_unique($issues)),
    ];
  }

  /** @param array<string,mixed> $record @param list<string> $issues */
  private static function minutes(array $record, string $key, array &$issues): int {
    $value = $record[$key] ?? null;
    if (!is_int($value) || $value < 0 || $value > 1440) {
      $issues[] = 'invalid_' . $key;
      return 0;
    }
    return $value;
  }
}
