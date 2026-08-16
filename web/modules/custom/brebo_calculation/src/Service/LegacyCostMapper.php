<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CostBreakdown;

/** Maps the legacy exclusive cost category into the new multi-carrier model. */
final class LegacyCostMapper {

  /** @return array{costs: CostBreakdown, warning: ?string} */
  public function map(string $category, float $unitPrice): array {
    if ($unitPrice < 0) {
      throw new \InvalidArgumentException('Legacy unit price cannot be negative.');
    }

    $costs = match ($category) {
      'Arbeid' => new CostBreakdown(labour: $unitPrice),
      'Materiaal' => new CostBreakdown(material: $unitPrice),
      'Materieel' => new CostBreakdown(equipment: $unitPrice),
      'Onderaanneming' => new CostBreakdown(subcontracting: $unitPrice),
      'Overig' => new CostBreakdown(other: $unitPrice),
      default => new CostBreakdown(other: $unitPrice),
    };

    return [
      'costs' => $costs,
      'warning' => in_array($category, ['Arbeid', 'Materiaal', 'Materieel', 'Onderaanneming', 'Overig'], TRUE)
        ? NULL
        : sprintf('Onbekende legacy kostencategorie "%s" is als Overig gemapt.', $category),
    ];
  }

}
