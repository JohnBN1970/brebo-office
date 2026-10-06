<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Persistence boundary for contract monitoring. */
interface ContractMonitoringRepositoryInterface {

  /** @return array<string, mixed>|null */
  public function findAward(int $awardId): ?array;

  /** @param array<string, mixed> $record */
  public function insertObligation(array $record): int;

  /** @param array<string, mixed> $fields */
  public function completeObligation(int $obligationId, array $fields): void;

  /** @return array<int, array<string, mixed>> */
  public function findObligationsByAward(int $awardId): array;

  /** @return array<int, array<string, mixed>> */
  public function findOpenDeviationsByAward(int $awardId): array;

}
