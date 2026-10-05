<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read/write persistence boundary for automatic financial controls. */
interface FinancialControlScannerRepositoryInterface {

  public function hasLockedBudget(int $projectNid): bool;

  /** @return list<array<string,mixed>> */
  public function openContractObligations(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function scenarioSnapshots(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function openBillingInstalments(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function openSalesInvoices(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function supplierScoreSnapshots(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function openFailureCosts(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function activeChangeOrders(int $projectNid): array;

  public function budgetMutationExists(int $projectNid, string $mutationNumber): bool;

  /** @return array<string,mixed>|null */
  public function latestCommittedCashForecast(int $projectNid): ?array;

  /** @return list<array<string,mixed>> */
  public function overdueReceivables(int $projectNid, string $today): array;

  /** @return list<array<string,mixed>> */
  public function openPurchaseInvoices(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function staleBudgetMutations(int $projectNid, int $createdBefore): array;

  /** @return list<array<string,mixed>> */
  public function expiredGAccountInstructions(int $projectNid, string $today): array;

  public function latestForecastDate(int $projectNid): ?string;

  public function findingStatus(int $projectNid, string $code, string $sourceType, int $sourceId): ?string;

  /** @param array<string,mixed> $keys @param array<string,mixed> $insertFields @param array<string,mixed> $fields */
  public function upsertFinding(array $keys, array $insertFields, array $fields): void;

  /** @return list<array{id:int,control_code:string,source_type:string,source_id:int}> */
  public function openAutomaticFindings(int $projectNid, string $origin): array;

  /** @param array<string,mixed> $fields */
  public function updateFinding(int $findingId, array $fields): void;

}
