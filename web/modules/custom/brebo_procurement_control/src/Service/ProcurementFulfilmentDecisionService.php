<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

/**
 * BREBO-wide decision logic for project stock versus supplier fulfilment.
 *
 * Domain modules provide the requirement and verified options. This service
 * ranks those options without inventing stock, delivery dates or prices.
 */
final class ProcurementFulfilmentDecisionService {

  /**
   * @param array<string,mixed> $requirement
   * @param array<int,array<string,mixed>> $projectStock
   * @param array<int,array<string,mixed>> $supplierOptions
   * @return array<string,mixed>
   */
  public function decide(array $requirement, array $projectStock, array $supplierOptions): array {
    $quantity = max(0.0, (float) ($requirement['quantity'] ?? 0));
    $requiredDate = trim((string) ($requirement['required_date'] ?? ''));
    if ($quantity <= 0 || $requiredDate === '') {
      throw new \InvalidArgumentException('Hoeveelheid en uiterste benodigde datum zijn verplicht.');
    }

    $free = 0.0;
    $stockRefs = [];
    foreach ($projectStock as $stock) {
      if (empty($stock['technical_match']) || empty($stock['actually_delivered'])) {
        continue;
      }
      $available = max(0.0, (float) ($stock['delivered_quantity'] ?? 0)
        - (float) ($stock['used_quantity'] ?? 0)
        - (float) ($stock['damaged_quantity'] ?? 0)
        - (float) ($stock['reserved_quantity'] ?? 0));
      if ($available <= 0) {
        continue;
      }
      $free += $available;
      $stockRefs[] = ['reference' => $stock['reference'] ?? NULL, 'free_quantity' => $available];
    }

    if ($free >= $quantity) {
      return [
        'route' => 'project_stock',
        'urgency' => 'none',
        'required_quantity' => $quantity,
        'free_project_quantity' => $free,
        'stock_sources' => $stockRefs,
        'supplier' => NULL,
        'reason' => 'Voldoende technisch passende, werkelijk geleverde en vrij inzetbare projectvoorraad.',
      ];
    }

    $shortage = max(0.0, $quantity - $free);
    $eligible = [];
    foreach ($supplierOptions as $option) {
      $deliveryDate = trim((string) ($option['delivery_date'] ?? ''));
      $price = $option['total_price'] ?? NULL;
      if (empty($option['technical_match']) || $deliveryDate === '' || !is_numeric($price)) {
        continue;
      }
      if ($deliveryDate > $requiredDate) {
        continue;
      }
      $eligible[] = $option + ['total_price' => (float) $price];
    }

    if ($eligible === []) {
      return [
        'route' => 'escalate',
        'urgency' => 'critical',
        'required_quantity' => $quantity,
        'free_project_quantity' => $free,
        'shortage_quantity' => $shortage,
        'supplier' => NULL,
        'reason' => 'Geen geverifieerde leverancier kan de technische behoefte aantoonbaar voor de benodigde datum leveren.',
      ];
    }

    usort($eligible, static function (array $a, array $b): int {
      $price = ((float) $a['total_price']) <=> ((float) $b['total_price']);
      return $price !== 0 ? $price : strcmp((string) $a['delivery_date'], (string) $b['delivery_date']);
    });
    $selected = $eligible[0];
    $mode = (string) ($selected['delivery_mode'] ?? 'regular');
    $urgency = in_array($mode, ['regular', 'accelerated', 'emergency'], TRUE) ? $mode : 'regular';

    return [
      'route' => 'supplier',
      'urgency' => $urgency,
      'required_quantity' => $quantity,
      'free_project_quantity' => $free,
      'shortage_quantity' => $shortage,
      'supplier' => $selected,
      'alternatives_considered' => count($eligible),
      'reason' => 'Goedkoopste geverifieerde technisch passende optie die de benodigde datum haalt.',
    ];
  }
}
