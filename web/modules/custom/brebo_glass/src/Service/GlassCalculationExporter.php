<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_calculation\Service\CalculationObjectLineWriter;
use Drupal\Core\Session\AccountInterface;

/** Exports approved glass positions into the canonical BREBO calculation domain. */
final class GlassCalculationExporter {

  public function __construct(
    private readonly GlassPositionRepository $positions,
    private readonly GlassCalculationLineFactory $lineFactory,
    private readonly CalculationObjectLineWriter $writer,
  ) {}

  /** @return int[] */
  public function export(
    int $positionId,
    int $calculationId,
    string $version,
    string $paragraphKey,
    AccountInterface $account,
  ): array {
    $position = $this->positions->find($positionId);
    if (!$position) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }

    $created = [];
    foreach ($this->lineFactory->build($position) as $line) {
      $costs = match ((string) $line['key']) {
        'glass' => ['material' => 0.0],
        'installation' => ['labour' => 0.0],
        'handling' => ['equipment' => 0.0],
        default => ['other' => 0.0],
      };
      $created[] = $this->writer->write(
        calculationId: $calculationId,
        version: $version,
        paragraphKey: $paragraphKey,
        description: (string) $line['description'],
        quantity: (float) $line['quantity'],
        unit: (string) $line['unit'],
        unitCosts: $costs,
        sourceDomain: 'brebo_glass_position',
        sourceReference: (string) $line['source_reference'],
        account: $account,
      );
    }

    return $created;
  }

}
