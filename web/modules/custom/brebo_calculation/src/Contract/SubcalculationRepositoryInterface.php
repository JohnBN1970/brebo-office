<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

interface SubcalculationRepositoryInterface {
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
