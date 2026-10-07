<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationFactStoreInterface;

/** Builds source-preserving facts and the first geometry take-off. */
final class CalculationFactService {

  public function __construct(
    private readonly CalculationFactStoreInterface $store,
  ) {}

  /** @param list<array<string,mixed>> $quoteLines @return array<string,mixed> */
  public function importSupplierQuoteLines(int $setId, int $documentId, array $quoteLines): array {
    $now = $this->store->currentTime();
    $facts = [];
    $takeoff = [];
    $components = [];

    foreach ($quoteLines as $row) {
      $position = trim((string) ($row['position'] ?? ''));
      if ($position === '') {
        continue;
      }
      $quantity = max(0.0, (float) ($row['quantity'] ?? 0));
      $description = trim((string) ($row['description'] ?? ''));
      $page = isset($row['source_page']) ? (int) $row['source_page'] : NULL;

      $this->insertFact($setId, $documentId, 'quantity', $position, NULL, $quantity, (string) ($row['unit'] ?? ''), $page, $description, 'supplier_quote_normalizer', 0.90, $now);
      if ($description !== '') {
        $this->insertFact($setId, $documentId, 'description', $position, $description, NULL, NULL, $page, $description, 'supplier_quote_normalizer', 0.90, $now);
      }
      if (isset($row['unit_price'])) {
        $this->insertFact($setId, $documentId, 'supplier_unit_price', $position, NULL, (float) $row['unit_price'], 'EUR', $page, $description, 'supplier_quote_normalizer', 0.95, $now);
      }

      [$width, $height] = $this->dimensions($description . ' ' . (string) ($row['details'] ?? ''));
      if ($width !== NULL && $height !== NULL) {
        $this->insertFact($setId, $documentId, 'width_mm', $position, NULL, $width, 'mm', $page, $description, 'dimension_pattern', 0.92, $now);
        $this->insertFact($setId, $documentId, 'height_mm', $position, NULL, $height, 'mm', $page, $description, 'dimension_pattern', 0.92, $now);
        // Keep the take-off as geometry truth per element. Quantity is stored
        // separately and recipes decide how often a side/area is consumed.
        $top = $width / 1000.0;
        $bottom = $width / 1000.0;
        $left = $height / 1000.0;
        $right = $height / 1000.0;
        $area = ($width / 1000.0) * ($height / 1000.0);
        $perimeter = $top + $bottom + $left + $right;
        $this->store->insertTakeoff([
          'set_id' => $setId,
          'position_ref' => $position,
          'quantity' => $quantity,
          'width_mm' => $width,
          'height_mm' => $height,
          'area_m2' => $area,
          'perimeter_m' => $perimeter,
          'top_m' => $top,
          'bottom_m' => $bottom,
          'left_m' => $left,
          'right_m' => $right,
          'created' => $now,
        ]);
        $takeoff[] = compact('position', 'quantity', 'width', 'height', 'area', 'perimeter', 'top', 'bottom', 'left', 'right');

        // Seed a reviewable root component for each geometrically complete
        // position. This does not invent vak/glas subdivisions: it only
        // preserves the source position geometry as the parent for later
        // managed extraction of children.
        $componentRef = $position . ':frame';
        $this->store->insertComponent([
          'set_id' => $setId,
          'document_id' => $documentId,
          'position_ref' => $position,
          'component_ref' => $componentRef,
          'parent_component_ref' => NULL,
          'component_type' => 'frame',
          'classification_ref' => NULL,
          'description' => $description !== '' ? mb_substr($description, 0, 255) : NULL,
          'quantity' => $quantity > 0 ? $quantity : 1,
          'width_mm' => $width,
          'height_mm' => $height,
          'area_m2' => $area * max(1.0, $quantity),
          'perimeter_m' => $perimeter * max(1.0, $quantity),
          'source_page' => $page,
          'source_fragment' => $description !== '' ? $description : NULL,
          'extraction_method' => 'supplier_quote_normalizer',
          'confidence' => 0.92,
          'review_status' => 'proposed',
          'created' => $now,
        ]);
        $components[] = $componentRef;
      }
      $facts[] = $position;
    }

    return [
      'positions' => count($facts),
      'takeoff_rows' => count($takeoff),
      'component_rows' => count($components),
      'takeoff' => $takeoff,
      'components' => $components,
    ];
  }

  /** @return array{0:?float,1:?float} */
  private function dimensions(string $text): array {
    if (preg_match('/(?<!\d)(\d{3,5})\s*(?:mm)?\s*[x×]\s*(\d{3,5})\s*mm\b/ui', $text, $m)) {
      return [(float) $m[1], (float) $m[2]];
    }
    return [NULL, NULL];
  }

  private function insertFact(int $setId, int $documentId, string $type, string $position, ?string $text, ?float $number, ?string $unit, ?int $page, ?string $fragment, string $method, float $confidence, int $now): void {
    $this->store->insertFact([
      'set_id' => $setId,
      'document_id' => $documentId,
      'fact_type' => $type,
      'position_ref' => $position,
      'value_text' => $text,
      'value_number' => $number,
      'unit' => $unit,
      'source_page' => $page,
      'source_fragment' => $fragment,
      'extraction_method' => $method,
      'confidence' => $confidence,
      'review_status' => 'proposed',
      'created' => $now,
    ]);
  }

}
