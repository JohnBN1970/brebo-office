<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Result of a guarded legacy-to-domain write migration. */
final readonly class MigrationWriteResult {

  public function __construct(
    public int $calculationId,
    public string $version,
    public int $structureCount,
    public int $rowCount,
    public string $contentHash,
  ) {}

}
