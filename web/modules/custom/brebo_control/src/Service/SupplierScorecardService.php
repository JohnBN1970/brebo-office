<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds evidence-based supplier scorecards from available BREBO data.
 */
final class SupplierScorecardService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<int, array<string, mixed>> */
  public function all(): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_invoice')) {
      return [];
    }

    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'invoice_count');
    $query->addExpression('COUNT(DISTINCT project_nid)', 'project_count');
    $query->addExpression('COALESCE(SUM(gross_amount), 0)', 'turnover');
    $query->addExpression("SUM(CASE WHEN match_status = 'matched' THEN 1 ELSE 0 END)", 'matched_count');
    $query->addExpression("SUM(CASE WHEN match_status <> 'matched' THEN 1 ELSE 0 END)", 'exception_count');
    $query->groupBy('supplier_name');

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $scorecards = [];
    foreach ($rows as $row) {
      $invoiceCount = max(1, (int) $row['invoice_count']);
      $exceptions = (int) $row['exception_count'];
      $exceptionRate = ($exceptions / $invoiceCount) * 100;
      $score = max(0, min(100, (int) round(100 - ($exceptionRate * 2))));
      $scorecards[] = [
        'supplier_name' => (string) $row['supplier_name'],
        'score' => $score,
        'rating' => $this->rating($score),
        'invoice_count' => $invoiceCount,
        'project_count' => (int) $row['project_count'],
        'turnover' => round((float) $row['turnover'], 2),
        'matched_count' => (int) $row['matched_count'],
        'exception_count' => $exceptions,
        'exception_rate_pct' => round($exceptionRate, 1),
        'confidence' => $this->confidence($invoiceCount),
        'evidence' => ['factuurmatch'],
        'missing_dimensions' => [
          'prijsafwijking_t.o.v._inkooporder',
          'planning_en_leverbetrouwbaarheid',
          'kwaliteit_en_afkeur',
          'faalkosten_en_herstelwerk',
          'klachten',
          'garantieclaims',
          'veiligheid_en_KAM',
        ],
      ];
    }

    usort($scorecards, static fn(array $a, array $b): int => $a['score'] <=> $b['score']);
    return $scorecards;
  }

  /** @return array<string, mixed>|null */
  public function supplier(string $supplierName): ?array {
    foreach ($this->all() as $scorecard) {
      if (strcasecmp($scorecard['supplier_name'], $supplierName) === 0) {
        return $scorecard;
      }
    }
    return NULL;
  }

  private function rating(int $score): string {
    return match (TRUE) {
      $score >= 90 => 'A',
      $score >= 80 => 'B',
      $score >= 65 => 'C',
      $score >= 50 => 'D',
      default => 'E',
    };
  }

  private function confidence(int $observations): string {
    return match (TRUE) {
      $observations >= 25 => 'hoog',
      $observations >= 10 => 'middel',
      $observations >= 3 => 'laag',
      default => 'onvoldoende',
    };
  }

}
