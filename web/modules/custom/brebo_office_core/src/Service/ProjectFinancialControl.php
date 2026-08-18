<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds a live project forecast from approved work budgets and actuals.
 */
final class ProjectFinancialControl {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkforceLaborCostService $laborCost,
    private readonly Connection $database,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function analyze(NodeInterface $project): array {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Expected a BREBO project.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $packageIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref', $project->id())
      ->execute();

    $budgetIds = [];
    if ($packageIds) {
      $budgetIds = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'brebo_work_budget')
        ->condition('field_brebo_package_ref', array_values($packageIds), 'IN')
        ->execute();
    }

    $budgetCost = 0.0;
    $actualLaborCost = 0.0;
    $forecastLaborCost = 0.0;
    $budgetHours = 0.0;
    $actualHours = 0.0;
    $forecastHours = 0.0;
    $lineCount = 0;
    $signals = [];
    $rows = [];

    if ($budgetIds) {
      $lineIds = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'brebo_work_budget_line')
        ->condition('field_brebo_work_budget_ref', array_values($budgetIds), 'IN')
        ->execute();
      foreach ($storage->loadMultiple($lineIds) as $line) {
        if (!$line instanceof NodeInterface) {
          continue;
        }
        $lineCount++;
        $source = $line->get('field_brebo_calc_line_ref')->entity;
        $directCost = $source instanceof NodeInterface && $source->hasField('field_brebo_direct_cost')
          ? max(0.0, (float) ($source->get('field_brebo_direct_cost')->value ?? 0)) : 0.0;
        $finance = $this->laborCost->synchronizeAndAnalyze($line);
        $budgetCost += $directCost;
        $actualLaborCost += (float) $finance['actual_cost'];
        $forecastLaborCost += (float) $finance['forecast_cost'];
        $budgetHours += (float) $finance['budget_hours'];
        $actualHours += (float) $finance['actual_hours'];
        $forecastHours += (float) $finance['forecast_hours'];
        if ($finance['status'] !== 'Akkoord') {
          $signals[] = $line->label() . ': ' . $finance['status'];
        }
        $rows[] = [
          'label' => $line->label(),
          'budget_hours' => $finance['budget_hours'],
          'actual_hours' => $finance['actual_hours'],
          'forecast_hours' => $finance['forecast_hours'],
          'labor_rate' => $finance['labor_rate'],
          'actual_labor_cost' => $finance['actual_cost'],
          'forecast_labor_cost' => $finance['forecast_cost'],
          'status' => $finance['status'],
        ];
      }
    }

    $budgetLaborCost = 0.0;
    foreach ($rows as $row) {
      $budgetLaborCost += (float) $row['budget_hours'] * (float) $row['labor_rate'];
    }
    $nonLaborBudget = max(0.0, $budgetCost - $budgetLaborCost);

    $commitmentCost = 0.0;
    $commitmentRows = [];
    $rfqIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_rfq')
      ->condition('field_brebo_project_ref', $project->id())
      ->execute();
    if ($rfqIds) {
      $quoteIds = $storage->getQuery()->accessCheck(FALSE)
        ->condition('type', 'brebo_supplier_quote')
        ->condition('field_brebo_rfq_ref', array_values($rfqIds), 'IN')
        ->condition('field_brebo_quote_selected', 1)
        ->execute();
      foreach ($storage->loadMultiple($quoteIds) as $quote) {
        if (!$quote instanceof NodeInterface) {
          continue;
        }
        $rfq = $quote->get('field_brebo_rfq_ref')->entity;
        $amount = max(0.0, (float) ($quote->get('field_brebo_quote_total')->value ?? 0));
        $commitmentCost += $amount;
        $commitmentRows[] = [
          'supplier' => (string) ($quote->get('field_brebo_supplier_name')->value ?? $quote->label()),
          'quote_number' => (string) ($quote->get('field_brebo_quote_number')->value ?? ''),
          'rfq' => $rfq?->label() ?? '—',
          'amount' => round($amount, 2),
          'status' => (string) ($quote->get('field_brebo_quote_status')->value ?? 'Geselecteerd'),
        ];
      }
    }

    $transactions = $this->financeTransactions((int) $project->id());
    $ordered = (float) $transactions['ordered'];
    $invoiced = (float) $transactions['invoiced'];
    $approvedInvoices = (float) $transactions['approved'];
    $paid = (float) $transactions['paid'];
    $paidG = (float) $transactions['paid_g'];
    $openPayables = max(0.0, $approvedInvoices - $paid);

    $effectiveCommitment = $ordered > 0 ? $ordered : $commitmentCost;
    $nonLaborForecast = max($nonLaborBudget, $effectiveCommitment, $approvedInvoices);
    $forecastCost = $nonLaborForecast + $forecastLaborCost;
    $variance = $forecastCost - $budgetCost;
    $commitmentCoverage = $nonLaborBudget > 0 ? ($effectiveCommitment / $nonLaborBudget) * 100 : 0.0;

    if ($effectiveCommitment > $nonLaborBudget + 0.01) {
      $signals[] = 'Inkoopverplichtingen overschrijden het niet-arbeidsbudget met € ' . number_format($effectiveCommitment - $nonLaborBudget, 2, ',', '.') . '.';
    }
    elseif ($effectiveCommitment > 0 && $commitmentCoverage >= 90) {
      $signals[] = 'Minimaal 90% van het niet-arbeidsbudget is reeds vastgelegd.';
    }
    if ($invoiced > $ordered + 0.02 && $ordered > 0) {
      $signals[] = 'Gefactureerd bedrag ligt boven het totaal van de inkooporders.';
    }
    if ((int) $transactions['blocked_invoices'] > 0) {
      $signals[] = $transactions['blocked_invoices'] . ' leveranciersfactuur/facturen hebben geen geldige 3-way match.';
    }
    if ((int) $transactions['overdue_invoices'] > 0) {
      $signals[] = $transactions['overdue_invoices'] . ' goedgekeurde leveranciersfactuur/facturen zijn vervallen en nog niet volledig betaald.';
    }

    $revenue = $this->projectRevenue((int) $project->id(), $budgetCost, $forecastCost);
    foreach ($revenue['signals'] as $signal) {
      $signals[] = $signal;
    }

    $status = 'Akkoord';
    if ($variance > 0.01 || (int) $transactions['blocked_invoices'] > 0 || (float) $revenue['expected_result'] < -0.01) {
      $status = 'Overschrijding verwacht';
    }
    elseif ((float) $revenue['margin_delta_pct'] < -1.0 || ($budgetCost > 0 && $forecastCost >= $budgetCost * 0.95)) {
      $status = 'Aandacht';
    }
    if (!$budgetIds || $lineCount === 0) {
      $status = 'Onvoldoende data';
      $signals[] = 'Geen werkbegrotingsregels beschikbaar voor financiële projectsturing.';
    }

    return [
      'status' => $status,
      'work_budgets' => count($budgetIds),
      'lines' => $lineCount,
      'budget_cost' => round($budgetCost, 2),
      'budget_labor_cost' => round($budgetLaborCost, 2),
      'non_labor_budget' => round($nonLaborBudget, 2),
      'commitment_cost' => round($effectiveCommitment, 2),
      'commitment_coverage_pct' => round($commitmentCoverage, 1),
      'ordered_cost' => round($ordered, 2),
      'invoiced_cost' => round($invoiced, 2),
      'approved_invoice_cost' => round($approvedInvoices, 2),
      'paid_cost' => round($paid, 2),
      'paid_g_account' => round($paidG, 2),
      'open_payables' => round($openPayables, 2),
      'blocked_invoices' => (int) $transactions['blocked_invoices'],
      'overdue_invoices' => (int) $transactions['overdue_invoices'],
      'actual_labor_cost' => round($actualLaborCost, 2),
      'actual_total_cost' => round($actualLaborCost + $approvedInvoices, 2),
      'forecast_labor_cost' => round($forecastLaborCost, 2),
      'forecast_cost' => round($forecastCost, 2),
      'variance' => round($variance, 2),
      'contract_value' => $revenue['contract_value'],
      'approved_variations' => $revenue['approved_variations'],
      'pending_variations' => $revenue['pending_variations'],
      'contract_revenue' => $revenue['contract_revenue'],
      'forecast_revenue' => $revenue['forecast_revenue'],
      'start_result' => $revenue['start_result'],
      'start_margin_pct' => $revenue['start_margin_pct'],
      'expected_result' => $revenue['expected_result'],
      'expected_margin_pct' => $revenue['expected_margin_pct'],
      'margin_delta_pct' => $revenue['margin_delta_pct'],
      'result_delta' => $revenue['result_delta'],
      'budget_hours' => round($budgetHours, 2),
      'actual_hours' => round($actualHours, 2),
      'forecast_hours' => round($forecastHours, 2),
      'signals' => array_values(array_unique($signals)),
      'rows' => $rows,
      'commitment_rows' => $commitmentRows,
      'transaction_rows' => $transactions['rows'],
      'revenue_rows' => $revenue['rows'],
      'actual_scope' => $transactions['available']
        ? 'Werkelijke arbeid, inkooporders, leveranciersfacturen, betalingen en projectopbrengsten uit BREBO Finance.'
        : 'Werkelijke arbeid uit goedgekeurde uren. BREBO Finance is nog niet geïnstalleerd; transacties en opbrengsten zijn daarom niet beschikbaar.',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function projectRevenue(int $projectId, float $budgetCost, float $forecastCost): array {
    if (!$this->database->schema()->tableExists('brebo_project_revenue')) {
      return [
        'contract_value' => 0.0, 'approved_variations' => 0.0, 'pending_variations' => 0.0,
        'contract_revenue' => 0.0, 'forecast_revenue' => 0.0, 'start_result' => 0.0,
        'start_margin_pct' => 0.0, 'expected_result' => -round($forecastCost, 2),
        'expected_margin_pct' => 0.0, 'margin_delta_pct' => 0.0, 'result_delta' => 0.0,
        'signals' => ['Geen opbrengstenregister beschikbaar; marge kan nog niet betrouwbaar worden berekend.'],
        'rows' => [],
      ];
    }

    $rows = $this->database->select('brebo_project_revenue', 'r')
      ->fields('r')->condition('project_nid', $projectId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $contract = 0.0;
    $approvedVariations = 0.0;
    $pendingVariations = 0.0;
    $expectedVariations = 0.0;
    $signals = [];

    foreach ($rows as &$row) {
      $amount = (float) $row['amount'];
      $type = (string) $row['revenue_type'];
      $rowStatus = (string) $row['status'];
      $probability = max(0.0, min(100.0, (float) $row['probability_pct']));
      $weighted = round(abs($amount) * ($probability / 100), 2);
      $row['weighted_amount'] = $weighted;

      if ($type === 'contract' && in_array($rowStatus, ['approved', 'contracted'], TRUE)) {
        $contract += abs($amount);
        continue;
      }
      if (!in_array($type, ['more_work', 'less_work'], TRUE)) {
        continue;
      }
      $signed = $type === 'less_work' ? -abs($amount) : abs($amount);
      if (in_array($rowStatus, ['approved', 'contracted'], TRUE)) {
        $approvedVariations += $signed;
        $expectedVariations += $signed;
      }
      elseif (in_array($rowStatus, ['submitted', 'pending'], TRUE)) {
        $pendingVariations += $signed;
        $expectedVariations += $type === 'less_work' ? -$weighted : $weighted;
      }
    }
    unset($row);

    $contractRevenue = $contract + $approvedVariations;
    $forecastRevenue = $contract + $expectedVariations;
    $startResult = $contract > 0 ? $contract - $budgetCost : 0.0;
    $startMargin = $contract > 0 ? ($startResult / $contract) * 100 : 0.0;
    $expectedResult = $forecastRevenue - max(0.0, $forecastCost);
    $expectedMargin = $forecastRevenue > 0 ? ($expectedResult / $forecastRevenue) * 100 : 0.0;
    $marginDelta = $expectedMargin - $startMargin;
    $resultDelta = $expectedResult - $startResult;

    if ($contract <= 0) {
      $signals[] = 'Geen goedgekeurde aanneemsom/contractwaarde geregistreerd; projectmarge kan niet betrouwbaar worden beoordeeld.';
    }
    if ($expectedResult < -0.01) {
      $signals[] = 'Negatief verwacht projectresultaat: € ' . number_format(abs($expectedResult), 2, ',', '.') . ' verlies.';
    }
    if ($marginDelta < -1.0 && $contract > 0) {
      $signals[] = 'Marge lekt weg: verwachte marge ligt ' . number_format(abs($marginDelta), 2, ',', '.') . ' procentpunt onder de startmarge.';
    }
    if (abs($pendingVariations) > 0.01) {
      $signals[] = 'Openstaand meer-/minderwerk beïnvloedt de omzetprognose en is nog niet definitief gecontracteerd.';
    }

    return [
      'contract_value' => round($contract, 2),
      'approved_variations' => round($approvedVariations, 2),
      'pending_variations' => round($pendingVariations, 2),
      'contract_revenue' => round($contractRevenue, 2),
      'forecast_revenue' => round($forecastRevenue, 2),
      'start_result' => round($startResult, 2),
      'start_margin_pct' => round($startMargin, 2),
      'expected_result' => round($expectedResult, 2),
      'expected_margin_pct' => round($expectedMargin, 2),
      'margin_delta_pct' => round($marginDelta, 2),
      'result_delta' => round($resultDelta, 2),
      'signals' => $signals,
      'rows' => $rows,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function financeTransactions(int $projectId): array {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_purchase_order') || !$schema->tableExists('brebo_supplier_invoice') || !$schema->tableExists('brebo_supplier_payment')) {
      return [
        'available' => FALSE, 'ordered' => 0.0, 'invoiced' => 0.0,
        'approved' => 0.0, 'paid' => 0.0, 'paid_g' => 0.0,
        'blocked_invoices' => 0, 'overdue_invoices' => 0, 'rows' => [],
      ];
    }

    $orderedQuery = $this->database->select('brebo_purchase_order', 'o');
    $orderedQuery->condition('project_nid', $projectId);
    $orderedQuery->condition('status', 'cancelled', '<>');
    $orderedQuery->addExpression('COALESCE(SUM(gross_amount), 0)', 'total');
    $ordered = (float) $orderedQuery->execute()->fetchField();

    $invoiceQuery = $this->database->select('brebo_supplier_invoice', 'i');
    $invoiceQuery->fields('i');
    $invoiceQuery->condition('project_nid', $projectId);
    $invoices = $invoiceQuery->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $invoiced = 0.0;
    $approved = 0.0;
    $blocked = 0;
    $overdue = 0;
    $invoiceIds = [];
    $rows = [];
    foreach ($invoices as $invoice) {
      $invoiceIds[] = (int) $invoice['id'];
      $gross = (float) $invoice['gross_amount'];
      $invoiced += $gross;
      if ($invoice['approval_status'] === 'approved') {
        $approved += $gross;
      }
      if ($invoice['match_status'] !== 'matched') {
        $blocked++;
      }
      if ($invoice['approval_status'] === 'approved' && $invoice['payment_status'] !== 'paid' && !empty($invoice['due_date']) && (int) $invoice['due_date'] < time()) {
        $overdue++;
      }
      $rows[(int) $invoice['id']] = [
        'supplier' => $invoice['supplier_name'],
        'invoice_number' => $invoice['invoice_number'],
        'gross_amount' => round($gross, 2),
        'match_status' => $invoice['match_status'],
        'approval_status' => $invoice['approval_status'],
        'payment_status' => $invoice['payment_status'],
        'paid_amount' => 0.0,
        'paid_g_account' => 0.0,
      ];
    }

    $paid = 0.0;
    $paidG = 0.0;
    if ($invoiceIds) {
      $paymentQuery = $this->database->select('brebo_supplier_payment', 'p');
      $paymentQuery->fields('p');
      $paymentQuery->condition('invoice_id', $invoiceIds, 'IN');
      $paymentQuery->condition('status', 'cancelled', '<>');
      foreach ($paymentQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $payment) {
        $regular = (float) $payment['regular_amount'];
        $g = (float) $payment['g_account_amount'];
        $paid += $regular + $g;
        $paidG += $g;
        if (isset($rows[(int) $payment['invoice_id']])) {
          $rows[(int) $payment['invoice_id']]['paid_amount'] += round($regular + $g, 2);
          $rows[(int) $payment['invoice_id']]['paid_g_account'] += round($g, 2);
        }
      }
    }

    return [
      'available' => TRUE,
      'ordered' => round($ordered, 2),
      'invoiced' => round($invoiced, 2),
      'approved' => round($approved, 2),
      'paid' => round($paid, 2),
      'paid_g' => round($paidG, 2),
      'blocked_invoices' => $blocked,
      'overdue_invoices' => $overdue,
      'rows' => array_values($rows),
    ];
  }

}
