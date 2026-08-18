<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_control\Service\PortfolioEarlyWarningService;
use Drupal\brebo_control\Service\SupplierScorecardService;
use Drupal\brebo_procurement_control\Service\ProcurementDecisionIntelligenceService;
use Drupal\Core\Database\Connection;

/** Aggregates finance, contract, supplier and decision control for management. */
final class ManagementControlCenterService {

  public function __construct(
    private readonly Connection $database,
    private readonly PortfolioEarlyWarningService $portfolioWarnings,
    private readonly SupplierScorecardService $supplierScorecards,
    private readonly ProcurementDecisionIntelligenceService $decisionIntelligence,
  ) {}

  /** @return array<string, mixed> */
  public function dashboard(?int $now = NULL): array {
    $now ??= time();
    $portfolio = $this->portfolioWarnings->analyze();
    $decisions = $this->decisionIntelligence->analyze();
    $suppliers = $this->supplierScorecards->all();

    $blockedPayments = 0.0;
    $overdueObligations = 0;
    $criticalCases = 0;
    $caseExposure = 0.0;

    if ($this->database->schema()->tableExists('brebo_supplier_invoice')) {
      $query = $this->database->select('brebo_supplier_invoice', 'i');
      $query->addExpression('COALESCE(SUM(gross_amount),0)', 'amount');
      $or = $query->orConditionGroup()
        ->condition('approval_status', 'approved', '<>')
        ->condition('match_status', 'matched', '<>');
      $query->condition($or);
      $blockedPayments = (float) $query->execute()->fetchField();
    }

    if ($this->database->schema()->tableExists('brebo_contract_obligation')) {
      $overdueObligations = (int) $this->database->select('brebo_contract_obligation', 'o')
        ->condition('status', 'completed', '<>')
        ->condition('due_at', 0, '>')
        ->condition('due_at', $now, '<')
        ->countQuery()->execute()->fetchField();
    }

    if ($this->database->schema()->tableExists('brebo_controller_case')) {
      $query = $this->database->select('brebo_controller_case', 'c');
      $query->addExpression('COUNT(*)', 'case_count');
      $query->addExpression('COALESCE(SUM(financial_exposure),0)', 'exposure');
      $query->condition('status', 'concluded', '<>');
      $query->condition('severity', ['high', 'critical'], 'IN');
      $row = $query->execute()->fetchAssoc() ?: [];
      $criticalCases = (int) ($row['case_count'] ?? 0);
      $caseExposure = (float) ($row['exposure'] ?? 0);
    }

    $supplierRisk = array_values(array_filter($suppliers, static fn(array $row): bool => (int) ($row['tco_adjusted_score'] ?? 100) < 65));

    return [
      'generated_at' => $now,
      'headline' => [
        'blocked_payment_value' => round($blockedPayments, 2),
        'controller_case_exposure' => round($caseExposure, 2),
        'critical_controller_cases' => $criticalCases,
        'overdue_contract_obligations' => $overdueObligations,
        'portfolio_risk_score' => (int) ($portfolio['score'] ?? 0),
        'portfolio_risk_level' => (string) ($portfolio['level'] ?? 'laag'),
        'human_override_net_value' => round((float) ($decisions['human_override_net_value'] ?? 0), 2),
        'suppliers_below_c_rating' => count($supplierRisk),
      ],
      'portfolio' => $portfolio,
      'decision_intelligence' => $decisions,
      'supplier_risk' => array_slice($supplierRisk, 0, 10),
      'management_status' => $this->managementStatus($portfolio, $criticalCases, $overdueObligations),
    ];
  }

  /** @param array<string, mixed> $portfolio */
  private function managementStatus(array $portfolio, int $criticalCases, int $overdueObligations): string {
    if ($criticalCases > 0 || (int) ($portfolio['score'] ?? 0) >= 75) {
      return 'directie_ingrijpen';
    }
    if ((int) ($portfolio['score'] ?? 0) >= 50 || $overdueObligations >= 5) {
      return 'management_actie';
    }
    if ((int) ($portfolio['score'] ?? 0) >= 25 || $overdueObligations > 0) {
      return 'aandacht';
    }
    return 'onder_controle';
  }
}
