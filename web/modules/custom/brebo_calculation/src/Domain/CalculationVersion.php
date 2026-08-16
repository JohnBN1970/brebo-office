<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Version metadata binding structure, parameters and financial truth together. */
final readonly class CalculationVersion {

  public function __construct(
    public int $calculationId,
    public string $version,
    public CalculationStatus $status,
    public ClassificationSystem $classificationSystem,
    public CalculationParameters $parameters,
    public string $contentHash,
    public ?string $createdAt = NULL,
    public ?int $createdBy = NULL,
    public ?string $establishedAt = NULL,
    public ?int $establishedBy = NULL,
  ) {
    if ($this->calculationId <= 0 || trim($this->version) === '' || trim($this->contentHash) === '') {
      throw new \InvalidArgumentException('Calculation id, version and content hash are required.');
    }
    if ($this->status->isLocked() && ($this->establishedAt === NULL || $this->establishedBy === NULL)) {
      throw new \InvalidArgumentException('Locked calculation versions require establishment audit data.');
    }
  }

  public function isLocked(): bool {
    return $this->status->isLocked();
  }
}
