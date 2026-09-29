<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface SubcalculationRepositoryInterface {
  /** @return list<array<string,mixed>> */
  public function subcalculations(int $calculationId, string $version): array;
  /** @return list<array<string,mixed>> */
  public function applications(int $subcalculationId): array;
  /** @return list<array<string,mixed>> */
  public function structure(int $calculationId, string $version): array;
  /** @return list<array<string,mixed>> */
  public function rowDomains(int $calculationId, string $version): array;
  public function scopeCount(int $subcalculationId): int;
  public function applicationCount(int $subcalculationId): int;
  /** @return array<string,mixed>|null */
  public function applicationForSubcalculation(int $applicationId, int $subcalculationId): ?array;
  /** @return list<array<string,mixed>> */
  public function applicationObjectsDetailed(int $applicationId): array;
  public function exceptionObjectCount(int $applicationId): int;
  public function deleteScope(int $scopeId): void;

  /** @param array<string,mixed> $values */
  public function insertSubcalculation(array $values): int;
  /** @param array<string,mixed> $values */
  public function insertScope(array $values): int;
  /** @param array<string,mixed> $values */
  public function insertApplication(array $values): int;
  /** @param array<string,mixed> $values */
  public function insertApplicationObject(array $values): int;

  /** @return array<string,mixed>|null */
  public function application(int $applicationId): ?array;
  /** @return array<string,mixed>|null */
  public function subcalculation(int $subcalculationId): ?array;
  /** @return list<array<string,mixed>> */
  public function scopes(int $subcalculationId): array;
  /** @return list<int> */
  public function rowIdsForParagraph(int $calculationId, string $version, string $paragraphKey): array;
  /** @return array<string,mixed>|null */
  public function rowCosts(int $calculationId, string $version, int $rowId): ?array;
  /** @return list<array<string,mixed>> */
  public function applicationObjects(int $applicationId): array;
  /** @return list<array<string,mixed>> */
  public function exceptionLines(array $applicationObjectIds): array;

  public function isEditableVersion(int $calculationId, string $version): bool;
  public function scopeExists(int $calculationId, string $version, string $scopeType, string $scopeRef): bool;
  public function nextScopeOrder(int $subcalculationId): int;
}
