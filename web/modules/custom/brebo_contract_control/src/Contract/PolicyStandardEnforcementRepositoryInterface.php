<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for policy enforcement. */
interface PolicyStandardEnforcementRepositoryInterface {

  /** @return array<string, mixed>|null */
  public function findActiveRule(string $policyCode, int $now): ?array;

  /** @return array<string, mixed>|null */
  public function findRuleById(int $policyId): ?array;

  /** @param array<string, mixed> $record */
  public function insertException(array $record): int;

}
