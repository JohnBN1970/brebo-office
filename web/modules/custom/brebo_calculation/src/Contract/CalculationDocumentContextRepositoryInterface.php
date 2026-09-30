<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/** Read access to the document-derived calculation context. */
interface CalculationDocumentContextRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function latestSet(int $calculationId): ?array;

  /** @return list<array<string,mixed>> */
  public function documents(int $setId): array;

  /** @return list<array<string,mixed>> */
  public function facts(int $setId): array;

  /** @return list<array<string,mixed>> */
  public function takeoff(int $setId): array;

}
