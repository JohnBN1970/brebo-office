<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Builds evidence-based supplier scorecards from finance and performance data.
 */
final class SupplierScorecardService {

  public function __construct(
    private readonly Connection $database,
    private readonly SupplierPerformanceService $performance,
  ) {}

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
      $financeScore = max(0, min(100, (int) round(100 - ($exceptionRate * 2))));
      $performance = $this->performance->summarize((string) $row['supplier_name']);
      $performanceScore = max(0, min(100, 80 + (int) $performance['score_adjustment']));
      $hasPerformanceEvidence = (int) $performance['events'] > 0;

      $weights = $hasPerformanceEvidence
        ? ['finance' => 0.35, 'performance' => 0.65]
        : ['finance' => 1.0, 'performance' => 0.0];
      $score = (int) round(($financeScore * $weights['finance']) + ($performanceScore * $weights['performance']));

      $turnover = max(0.0, (float) $row['turnover']);
      $failureCost = max(0.0, (float) $performance['failure_cost']);
      $tcoPenaltyPct = $turnover > 0 ? ($failureCost / $turnover) * 100 : 0.0;
      $tcoAdjustedScore = max(0, (int) round($score - min(25.0, $tcoPenaltyPct)));

      $categoryScores = $this->categoryScores((array) $performance['categories']);
      $confidenceObservations = $invoiceCount + (int) $performance['events'];
      $evidence = ['factuurmatch'];
      if ($hasPerformanceEvidence) {
        $evidence[] = 'prestatieregister';
      }

      $scorecards[] = [
        'supplier_name' => (string) $row['supplier_name'],
        'score' => $score,
        'tco_adjusted_score' => $tcoAdjustedScore,
        'rating' => $this->rating($tcoAdjustedScore),
        'finance_score' => $financeScore,
        'performance_score' => $performanceScore,
        'invoice_count' => $invoiceCount,
        'performance_events' => (int) $performance['events'],
        'project_count' => (int) $row['project_count'],
        'turnover' => round($turnover, 2),
        'matched_count' => (int) $row['matched_count'],
        'exception_count' => $exceptions,
        'exception_rate_pct' => round($exceptionRate, 1),
        'failure_cost' => round($failureCost, 2),
        'failure_hours' => round((float) $performance['hours_lost'], 2),
        'failure_cost_pct_turnover' => round($tcoPenaltyPct, 2),
        'category_scores' => $categoryScores,
        'confidence' => $this->confidence($confidenceObservations),
        'confidence_observations' => $confidenceObservations,
        'evidence' => $evidence,
        'missing_dimensions' => $this->missingDimensions($categoryScores),
        'economic_signal' => $this->economicSignal($turnover, $failureCost),
      ];
    }

    usort($scorecards, static fn(array $a, array $b): int => $a['tco_adjusted_score'] <=> $b['tco_adjusted_score']);
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

  /** @param array<string, mixed> $categories
   *  @return array<string, int>
   */
  private function categoryScores(array $categories): array {
    $scores = [];
    foreach (['planning', 'quality', 'failure_cost', 'complaint', 'warranty', 'kam', 'commercial'] as $category) {
      if (!isset($categories[$category])) {
        continue;
      }
      $positive = (int) ($categories[$category]['positive'] ?? 0);
      $negative = (int) ($categories[$category]['negative'] ?? 0);
      $total = max(1, $positive + $negative);
      $scores[$category] = max(0, min(100, (int) round(50 + (($positive - $negative) / $total) * 50)));
    }
    return $scores;
  }

  /** @param array<string, int> $categoryScores
   *  @return string[]
   */
  private function missingDimensions(array $categoryScores): array {
    $labels = [
      'planning' => 'planning_en_leverbetrouwbaarheid',
      'quality' => 'kwaliteit_en_afkeur',
      'failure_cost' => 'faalkosten_en_herstelwerk',
      'complaint' => 'klachten',
      'warranty' => 'garantieclaims',
      'kam' => 'veiligheid_en_KAM',
      'commercial' => 'commerciele_samenwerking',
    ];
    $missing = [];
    foreach ($labels as $key => $label) {
      if (!array_key_exists($key, $categoryScores)) {
        $missing[] = $label;
      }
    }
    return $missing;
  }

  private function economicSignal(float $turnover, float $failureCost): string {
    if ($failureCost <= 0) {
      return 'Geen geregistreerde negatieve TCO-correctie.';
    }
    $pct = $turnover > 0 ? ($failureCost / $turnover) * 100 : 0.0;
    return 'Historische faalkosten € ' . number_format($failureCost, 2, ',', '.') . ' (' . number_format($pct, 2, ',', '.') . '% van geregistreerde omzet).';
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
