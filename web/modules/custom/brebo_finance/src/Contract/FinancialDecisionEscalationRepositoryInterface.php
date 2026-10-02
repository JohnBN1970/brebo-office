<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinancialDecisionEscalationRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function state(int $exceptionId): ?array;
  public function store(int $exceptionId,int $projectNid,string $attention,int $level,int $dueAt,int $now): void;
  /** @param array<string,mixed> $fields */
  public function audit(array $fields): void;
}
