<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Detects portfolio-wide deterioration and recurring financial patterns.
 */
final class PortfolioEarlyWarningService {

  public function __construct(
    private readonly PortfolioControlService $portfolio,
    private readonly Connection $database,
  ) {}

  /** @return array<string, mixed> */
  public function analyze(): array {
    $portfolio = $this->portfolio->analyze();
    $signals = [];
    $patterns = [];
    $score = 0;

    if ((float) $portfolio['portfolio_expected_result'] < 0) {
      $loss = abs((float) $portfolio['portfolio_expected_result']);
      $points = min(30, max(10, (int) ceil($loss / 10000)));
      $score += $points;
      $signals[] = 'De actieve portefeuille stuurt gezamenlijk op verlies: € ' . number_format($loss, 2, ',', '.') . '.';
      $patterns[] = $this->pattern('portfolio_loss', $points, $loss, 'Directie / Controller');
    }

    $deteriorating = (int) $portfolio['deteriorating'];
    if ($deteriorating >= 2) {
      $points = min(20, $deteriorating * 4);
      $score += $points;
      $signals[] = $deteriorating . ' projecten verslechteren structureel tegelijk.';
      $patterns[] = $this->pattern('multi_project_deterioration', $points, $deteriorating, 'Directie / Projectleiding');
    }

    $high = (int) $portfolio['critical_or_high'];
    if ($high >= 2) {
      $points = min(20, $high * 4);
      $score += $points;
      $signals[] = $high . ' projecten hebben een hoge of kritieke risicostatus.';
      $patterns[] = $this->pattern('risk_concentration', $points, $high, 'Directie / Controller');
    }

    $top80 = (array) $portfolio['projects_covering_80_pct_risk'];
    if (count($top80) <= 3 && count((array) $portfolio['projects']) >= 5 && (float) $portfolio['total_exposure_score'] > 0) {
      $points = 12;
      $score += $points;
      $signals[] = count($top80) . ' projecten veroorzaken samen circa 80% van het financiële portefeuillerisico.';
      $patterns[] = $this->pattern('risk_concentration_top_projects', $points, count($top80), 'Directie');
    }

    foreach ($this->recurringDrivers() as $driver) {
      $points = min(15, 3 + ((int) $driver['project_count'] * 2));
      $score += $points;
      $signals[] = 'Risicodriver "' . $driver['driver_code'] . '" is actief op ' . $driver['project_count'] . ' projecten.';
      $patterns[] = $this->pattern('recurring_driver:' . $driver['driver_code'], $points, (int) $driver['project_count'], 'Controller / Directie');
    }

    foreach ($this->supplierPatterns() as $supplier) {
      $points = min(15, 5 + ((int) $supplier['affected_projects'] * 2));
      $score += $points;
      $signals[] = 'Leverancier ' . $supplier['supplier_name'] . ' heeft afwijkende/geblokkeerde facturen op ' . $supplier['affected_projects'] . ' projecten (' . $supplier['invoice_count'] . ' facturen).';
      $patterns[] = $this->pattern('supplier_pattern:' . strtolower((string) $supplier['supplier_name']), $points, (float) $supplier['invoice_amount'], 'Controller / Inkoper');
    }

    $score = min(100, $score);
    usort($patterns, static fn(array $a, array $b): int => $b['points'] <=> $a['points']);
    $level = match (TRUE) {
      $score >= 75 => 'kritiek',
      $score >= 50 => 'hoog',
      $score >= 25 => 'verhoogd',
      default => 'laag',
    };

    return [
      'score' => $score,
      'level' => $level,
      'status' => $score >= 50 ? 'Directieactie vereist' : ($score >= 25 ? 'Portefeuilleactie vereist' : 'Onder controle'),
      'signals' => array_values(array_unique($signals)),
      'patterns' => $patterns,
      'top_pattern' => $patterns[0] ?? NULL,
      'portfolio' => $portfolio,
    ];
  }

  /** @return array<int, array<string, mixed>> */
  private function recurringDrivers(): array {
    if (!$this->database->schema()->tableExists('brebo_control_action')) {
      return [];
    }
    $query = $this->database->select('brebo_control_action', 'a');
    $query->addField('a', 'driver_code');
    $query->addExpression('COUNT(DISTINCT project_nid)', 'project_count');
    $query->condition('status', ['open', 'reopened', 'in_progress', 'escalated'], 'IN');
    $query->groupBy('driver_code');
    $query->having('COUNT(DISTINCT project_nid) >= :minimum', [':minimum' => 2]);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  private function supplierPatterns(): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_invoice')) {
      return [];
    }
    $query = $this->database->select('brebo_supplier_invoice', 'i');
    $query->addField('i', 'supplier_name');
    $query->addExpression('COUNT(*)', 'invoice_count');
    $query->addExpression('COUNT(DISTINCT project_nid)', 'affected_projects');
    $query->addExpression('COALESCE(SUM(gross_amount), 0)', 'invoice_amount');
    $query->condition('match_status', 'matched', '<>');
    $query->groupBy('supplier_name');
    $query->having('COUNT(DISTINCT project_nid) >= :minimum', [':minimum' => 2]);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<string, mixed> */
  private function pattern(string $code, int $points, float|int $value, string $owner): array {
    return [
      'code' => $code,
      'points' => $points,
      'value' => $value,
      'owner' => $owner,
    ];
  }

}
