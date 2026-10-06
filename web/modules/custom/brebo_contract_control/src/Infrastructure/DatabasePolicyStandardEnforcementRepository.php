<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\PolicyStandardEnforcementRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for policy enforcement persistence. */
final class DatabasePolicyStandardEnforcementRepository implements PolicyStandardEnforcementRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed>|null */
  public function findActiveRule(string $policyCode, int $now): ?array {
    $record = $this->database
      ->select('brebo_policy_rule', 'p')
      ->fields('p')
      ->condition('policy_code', $policyCode)
      ->condition('status', 'active')
      ->condition('effective_from', $now, '<=')
      ->orderBy('version', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /** @return array<string, mixed>|null */
  public function findRuleById(int $policyId): ?array {
    $record = $this->database
      ->select('brebo_policy_rule', 'p')
      ->fields('p')
      ->condition('id', $policyId)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  /** @param array<string, mixed> $record */
  public function insertException(array $record): int {
    return (int) $this->database
      ->insert('brebo_policy_exception')
      ->fields($record)
      ->execute();
  }

}
