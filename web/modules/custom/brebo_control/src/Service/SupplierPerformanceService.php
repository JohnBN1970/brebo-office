<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Records auditable supplier performance events.
 */
final class SupplierPerformanceService {

  private const CATEGORIES = ['planning', 'quality', 'failure_cost', 'complaint', 'warranty', 'kam', 'commercial'];
  private const IMPACTS = ['positive', 'negative'];

  public function __construct(private readonly Connection $database) {}

  public function record(
    string $supplierName,
    string $category,
    string $eventType,
    string $impact,
    int $severity,
    string $description,
    ?int $projectId = NULL,
    float $financialImpact = 0.0,
    float $hoursImpact = 0.0,
    ?string $evidence = NULL,
    ?int $recordedBy = NULL,
    ?int $occurredAt = NULL,
  ): int {
    $supplierName = trim($supplierName);
    if ($supplierName === '') {
      throw new \InvalidArgumentException('Leveranciersnaam is verplicht.');
    }
    if (!in_array($category, self::CATEGORIES, TRUE)) {
      throw new \InvalidArgumentException('Ongeldige prestatiecategorie.');
    }
    if (!in_array($impact, self::IMPACTS, TRUE)) {
      throw new \InvalidArgumentException('Impact moet positive of negative zijn.');
    }
    if ($severity < 1 || $severity > 5) {
      throw new \InvalidArgumentException('Severity moet tussen 1 en 5 liggen.');
    }
    if (trim($description) === '') {
      throw new \InvalidArgumentException('Omschrijving is verplicht.');
    }

    $now = time();
    return (int) $this->database->insert('brebo_supplier_performance_event')->fields([
      'supplier_name' => $supplierName,
      'project_nid' => $projectId,
      'category' => $category,
      'event_type' => trim($eventType),
      'impact' => $impact,
      'severity' => $severity,
      'financial_impact' => round(abs($financialImpact), 2),
      'hours_impact' => round(abs($hoursImpact), 2),
      'description' => trim($description),
      'evidence' => $evidence !== NULL ? trim($evidence) : NULL,
      'recorded_by' => $recordedBy,
      'occurred_at' => $occurredAt ?? $now,
      'created' => $now,
    ])->execute();
  }

  /** @return array<string, mixed> */
  public function summarize(string $supplierName): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_performance_event')) {
      return ['events' => 0, 'score_adjustment' => 0, 'failure_cost' => 0.0, 'hours_lost' => 0.0, 'categories' => []];
    }
    $rows = $this->database->select('brebo_supplier_performance_event', 'e')->fields('e')
      ->condition('supplier_name', $supplierName)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $adjustment = 0;
    $failureCost = 0.0;
    $hours = 0.0;
    $categories = [];
    foreach ($rows as $row) {
      $severity = (int) $row['severity'];
      $direction = $row['impact'] === 'positive' ? 1 : -1;
      $adjustment += $direction * $severity * 2;
      if ($direction < 0) {
        $failureCost += (float) $row['financial_impact'];
        $hours += (float) $row['hours_impact'];
      }
      $category = (string) $row['category'];
      $categories[$category] ??= ['positive' => 0, 'negative' => 0];
      $categories[$category][$row['impact']]++;
    }
    return [
      'events' => count($rows),
      'score_adjustment' => max(-40, min(20, $adjustment)),
      'failure_cost' => round($failureCost, 2),
      'hours_lost' => round($hours, 2),
      'categories' => $categories,
    ];
  }

}
