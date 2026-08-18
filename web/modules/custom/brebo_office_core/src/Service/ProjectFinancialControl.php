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

    // Once real purchase orders exist they replace selected quotes as the
    // stronger commitment source; quotes remain the fallback before ordering.
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

    $status = 'Akkoord';
    if ($variance > 0.01 || (int) $transactions['blocked_invoices'] > 0) {
      $status = 'Overschrijding verwacht';
    }
    elseif ($budgetCost > 0 && $forecastCost >= $budgetCost * 0.95) {
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
      'budget_hours' => round($budgetHours, 2),
      'actual_hours' => round($actualHours, 2),
      'forecast_hours' => round($forecastHours, 2),
      'signals' => array_values(array_unique($signals)),
      'rows' => $rows,
      'commitment_rows' => $commitmentRows,
      'transaction_rows' => $transactions['rows'],
      'actual_scope' => $transactions['available']
        ? 'Werkelijke arbeid uit goedgekeurde uren plus inkooporders, leveranciersfacturen en geboekte betalingen uit BREBO Finance.'
        : 'Werkelijke arbeid uit goedgekeurde uren. BREBO Finance is nog niet geïnstalleerd; transactiedata is daarom niet beschikbaar.',
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

    $ordered = (float) $this->database->select('brebo_purchase_order', 'o')
      ->condition('project_nid', $projectId)
      ->condition('status', 'cancelled', '<>')
      ->addExpression('COALESCE(SUM(gross_amount), 0)', 'total')
      ->execute()->fetchField();

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
