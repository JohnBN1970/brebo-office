<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

/** Builds source-preserving facts and the first geometry take-off. */
final class CalculationFactService {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /** @param list<array<string,mixed>> $quoteLines @return array<string,mixed> */
  public function importSupplierQuoteLines(int $setId, int $documentId, array $quoteLines): array {
    $now = $this->time->getRequestTime();
    $facts = [];
    $takeoff = [];

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
        $area = ($width / 1000.0) * ($height / 1000.0) * $quantity;
        $perimeter = 2.0 * (($width / 1000.0) + ($height / 1000.0)) * $quantity;
        $this->database->insert('brebo_calculation_takeoff')->fields([
          'set_id' => $setId,
          'position_ref' => $position,
          'quantity' => $quantity,
          'width_mm' => $width,
          'height_mm' => $height,
          'area_m2' => $area,
          'perimeter_m' => $perimeter,
          'created' => $now,
        ])->execute();
        $takeoff[] = compact('position', 'quantity', 'width', 'height', 'area', 'perimeter');
      }
      $facts[] = $position;
    }

    return ['positions' => count($facts), 'takeoff_rows' => count($takeoff), 'takeoff' => $takeoff];
  }

  /** @return array{0:?float,1:?float} */
  private function dimensions(string $text): array {
    if (preg_match('/(?<!\d)(\d{3,5})\s*(?:mm)?\s*[x×]\s*(\d{3,5})\s*mm\b/ui', $text, $m)) {
      return [(float) $m[1], (float) $m[2]];
    }
    return [NULL, NULL];
  }

  private function insertFact(int $setId, int $documentId, string $type, string $position, ?string $text, ?float $number, ?string $unit, ?int $page, ?string $fragment, string $method, float $confidence, int $now): void {
    $this->database->insert('brebo_calculation_fact')->fields([
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
    ])->execute();
  }

}
