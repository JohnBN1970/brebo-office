<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Orchestrates quote -> order -> invoice -> payment for BREBO Finance.
 */
final class FinanceWorkflowService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly InvoiceControlService $invoiceControl,
    private readonly PaymentControlService $paymentControl,
  ) {}

  /**
   * Create one purchase order from a selected supplier quote.
   *
   * @return array<string, mixed>
   */
  public function createOrderFromSelectedQuote(NodeInterface $quote): array {
    if ($quote->bundle() !== 'brebo_supplier_quote') {
      throw new \InvalidArgumentException('Expected a supplier quote.');
    }
    if (!(bool) ($quote->get('field_brebo_quote_selected')->value ?? FALSE)) {
      throw new \RuntimeException('Alleen een geselecteerde leveranciersofferte kan een inkooporder worden.');
    }

    $existing = $this->database->select('brebo_purchase_order', 'o')
      ->fields('o')
      ->condition('supplier_quote_nid', (int) $quote->id())
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if ($existing) {
      return $existing;
    }

    $rfq = $quote->get('field_brebo_rfq_ref')->entity;
    if (!$rfq instanceof NodeInterface || $rfq->bundle() !== 'brebo_rfq') {
      throw new \RuntimeException('Geselecteerde offerte heeft geen geldige prijsaanvraag.');
    }
    $projectId = (int) ($rfq->get('field_brebo_project_ref')->target_id ?? 0);
    $workBudgetId = (int) ($rfq->get('field_brebo_work_budget_ref')->target_id ?? 0);
    if ($projectId <= 0) {
      throw new \RuntimeException('Prijsaanvraag heeft geen projectkoppeling.');
    }

    $gross = max(0.0, (float) ($quote->get('field_brebo_quote_total')->value ?? 0));
    $orderNumber = 'PO-' . date('Y') . '-' . str_pad((string) $quote->id(), 6, '0', STR_PAD_LEFT);
    $now = time();
    $id = $this->database->insert('brebo_purchase_order')->fields([
      'order_number' => $orderNumber,
      'project_nid' => $projectId,
      'work_budget_nid' => $workBudgetId ?: NULL,
      'supplier_quote_nid' => (int) $quote->id(),
      'supplier_name' => (string) ($quote->get('field_brebo_supplier_name')->value ?? $quote->label()),
      'order_date' => $now,
      'net_amount' => $gross,
      'vat_amount' => 0,
      'gross_amount' => $gross,
      'g_account_pct' => 0,
      'status' => 'approved',
      'created' => $now,
      'changed' => $now,
    ])->execute();

    return (array) $this->database->select('brebo_purchase_order', 'o')
      ->fields('o')->condition('id', (int) $id)->execute()->fetchAssoc();
  }

  /**
   * Register and control a supplier invoice.
   *
   * @param array<string, mixed> $invoice
   * @return array<string, mixed>
   */
  public function registerInvoice(int $orderId, array $invoice): array {
    $order = $this->loadOrder($orderId);
    $existing = $this->database->select('brebo_supplier_invoice', 'i')
      ->fields('i', ['invoice_number', 'supplier_name'])
      ->condition('supplier_name', (string) ($invoice['supplier_name'] ?? $order['supplier_name']))
      ->execute()->fetchAllAssoc('invoice_number', \PDO::FETCH_ASSOC);

    $budgetAmount = $this->resolveWorkBudgetAmount((int) ($order['work_budget_nid'] ?? 0));
    $invoice['supplier_name'] = $invoice['supplier_name'] ?? $order['supplier_name'];
    $invoice['approval_status'] = 'pending';
    $control = $this->invoiceControl->assess($invoice, [
      'budget_amount' => $budgetAmount,
      'gross_amount' => (float) $order['gross_amount'],
      'g_account_pct' => (float) $order['g_account_pct'],
      'tolerance_pct' => 1.0,
    ], array_values($existing));

    $now = time();
    $approval = $control['status'] === 'approved' ? 'approved' : 'pending';
    $matchStatus = (string) $control['match']['status'];
    $id = $this->database->insert('brebo_supplier_invoice')->fields([
      'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
      'purchase_order_id' => $orderId,
      'project_nid' => (int) $order['project_nid'],
      'supplier_name' => (string) $invoice['supplier_name'],
      'invoice_date' => (int) ($invoice['invoice_date'] ?? $now),
      'due_date' => isset($invoice['due_date']) ? (int) $invoice['due_date'] : NULL,
      'net_amount' => (float) ($invoice['net_amount'] ?? 0),
      'vat_amount' => (float) ($invoice['vat_amount'] ?? 0),
      'gross_amount' => (float) ($invoice['gross_amount'] ?? 0),
      'g_account_amount' => (float) ($invoice['g_account_amount'] ?? 0),
      'match_status' => $matchStatus,
      'approval_status' => $approval,
      'payment_status' => 'open',
      'created' => $now,
      'changed' => $now,
    ])->execute();

    return ['invoice_id' => (int) $id, 'control' => $control];
  }

  /**
   * Build a payment proposal only when all gates are green.
   *
   * @return array<string, mixed>
   */
  public function paymentProposal(int $invoiceId): array {
    $invoice = $this->loadInvoice($invoiceId);
    $payments = $this->database->select('brebo_supplier_payment', 'p')
      ->fields('p')
      ->condition('invoice_id', $invoiceId)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $control = $this->paymentControl->assess($invoice, $payments);

    return [
      'invoice_id' => $invoiceId,
      'supplier' => $invoice['supplier_name'],
      'invoice_number' => $invoice['invoice_number'],
      'regular_amount' => max(0.0, (float) $control['remaining'] - (float) $control['g_account_remaining']),
      'g_account_amount' => (float) $control['g_account_remaining'],
      'control' => $control,
    ];
  }

  /**
   * Book a payment after validating the latest proposal.
   *
   * @return array<string, mixed>
   */
  public function bookPayment(int $invoiceId, float $regularAmount, float $gAccountAmount, string $reference = ''): array {
    $proposal = $this->paymentProposal($invoiceId);
    if (!$proposal['control']['payment_allowed']) {
      throw new \RuntimeException('Betaling is door de financiële controles geblokkeerd.');
    }
    if ($regularAmount < 0 || $gAccountAmount < 0 || $regularAmount + $gAccountAmount <= 0) {
      throw new \InvalidArgumentException('Betaalbedrag moet groter zijn dan nul.');
    }
    if ($regularAmount + $gAccountAmount > (float) $proposal['control']['remaining'] + 0.02) {
      throw new \RuntimeException('Betaalbedrag overschrijdt het openstaande factuurbedrag.');
    }
    if ($gAccountAmount > (float) $proposal['control']['g_account_remaining'] + 0.02) {
      throw new \RuntimeException('G-rekeningbetaling overschrijdt het resterende G-rekeningbedrag.');
    }

    $now = time();
    $paymentId = $this->database->insert('brebo_supplier_payment')->fields([
      'invoice_id' => $invoiceId,
      'payment_date' => $now,
      'regular_amount' => round($regularAmount, 2),
      'g_account_amount' => round($gAccountAmount, 2),
      'payment_reference' => $reference,
      'status' => 'booked',
      'created' => $now,
    ])->execute();

    $after = $this->paymentProposal($invoiceId);
    $this->database->update('brebo_supplier_invoice')->fields([
      'payment_status' => $after['control']['status'] === 'paid' ? 'paid' : 'partial',
      'changed' => $now,
    ])->condition('id', $invoiceId)->execute();

    return ['payment_id' => (int) $paymentId, 'proposal' => $after];
  }

  /** @return array<string, mixed> */
  private function loadOrder(int $id): array {
    $row = $this->database->select('brebo_purchase_order', 'o')->fields('o')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('Inkooporder niet gevonden.');
    }
    return $row;
  }

  /** @return array<string, mixed> */
  private function loadInvoice(int $id): array {
    $row = $this->database->select('brebo_supplier_invoice', 'i')->fields('i')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('Leveranciersfactuur niet gevonden.');
    }
    return $row;
  }

  private function resolveWorkBudgetAmount(int $workBudgetId): float {
    if ($workBudgetId <= 0) {
      return 0.0;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $lineIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'brebo_work_budget_line')
      ->condition('field_brebo_work_budget_ref', $workBudgetId)
      ->execute();
    $amount = 0.0;
    foreach ($storage->loadMultiple($lineIds) as $line) {
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $source = $line->get('field_brebo_calc_line_ref')->entity;
      if ($source instanceof NodeInterface && $source->hasField('field_brebo_direct_cost')) {
        $amount += max(0.0, (float) ($source->get('field_brebo_direct_cost')->value ?? 0));
      }
    }
    return round($amount, 2);
  }

}
