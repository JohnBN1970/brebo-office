<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

/** Controls project instalments and the operational Moneybird invoice mirror. */
final class BillingControlManager {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
    private readonly FinancialPhaseGateManager $phaseGateManager,
  ) {}

  public function registerInstalment(array $data, int $actorUid): int {
    foreach (['project_nid', 'contract_id', 'instalment_number', 'description', 'trigger_type', 'amount_ex_vat', 'vat_rate', 'planned_invoice_date'] as $required) {
      if (!isset($data[$required]) || $data[$required] === '') throw new InvalidArgumentException("$required is required.");
    }
    $triggerTypes = ['contract_date', 'calendar_date', 'verified_progress', 'milestone', 'change_order'];
    if (!in_array($data['trigger_type'], $triggerTypes, TRUE)) throw new InvalidArgumentException('Unsupported billing trigger type.');
    $contract = $this->database->select('brebo_finance_project_contract', 'c')->fields('c', ['id', 'project_nid', 'status'])->condition('id', (int) $data['contract_id'])->execute()->fetchAssoc();
    if ($contract === FALSE || (int) $contract['project_nid'] !== (int) $data['project_nid'] || $contract['status'] !== 'approved') throw new RuntimeException('Billing requires the approved project contract.');
    $vat = $this->decimal->calculate((string) $data['amount_ex_vat'], (string) $data['vat_rate']);
    if ($this->decimal->compare($vat->amountExVat, '0') <= 0) throw new InvalidArgumentException('Instalment amount must be positive.');
    $now = time();
    return (int) $this->database->insert('brebo_finance_billing_instalment')->fields([
      'project_nid' => (int) $data['project_nid'], 'contract_id' => (int) $data['contract_id'], 'change_order_id' => $data['change_order_id'] ?? NULL,
      'instalment_number' => (string) $data['instalment_number'], 'description' => (string) $data['description'], 'trigger_type' => (string) $data['trigger_type'],
      'trigger_ref' => $data['trigger_ref'] ?? NULL, 'trigger_threshold' => $data['trigger_threshold'] ?? NULL, 'building_object_type' => $data['building_object_type'] ?? NULL,
      'building_object_id' => $data['building_object_id'] ?? NULL, 'planned_invoice_date' => (string) $data['planned_invoice_date'], 'amount_ex_vat' => $vat->amountExVat,
      'vat_code' => $data['vat_code'] ?? 'NL_' . str_replace('.0000', '', $vat->vatRate), 'vat_rate' => $vat->vatRate, 'vat_amount' => $vat->vatAmount,
      'amount_inc_vat' => $vat->amountIncVat, 'status' => 'planned', 'evidence_payload' => json_encode($data['evidence'] ?? [], JSON_THROW_ON_ERROR),
      'created' => $now, 'created_by' => $actorUid, 'changed' => $now, 'changed_by' => $actorUid,
    ])->execute();
  }

  /** Releases a reached instalment for manual invoicing under four eyes. */
  public function approveBillable(int $instalmentId, string $triggerEvidence, int $approverUid): void {
    $row = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i')->condition('id', $instalmentId)->execute()->fetchAssoc();
    if ($row === FALSE || $row['status'] !== 'planned') throw new RuntimeException('Only a planned instalment can become billable.');
    if ((int) $row['created_by'] === $approverUid) throw new RuntimeException('The instalment creator cannot approve billability.');
    if (trim($triggerEvidence) === '') throw new InvalidArgumentException('Verified trigger evidence is required.');
    $this->phaseGateManager->requireRelease((int) $row['project_nid'], 'billing_release');
    $now = time();
    $this->database->update('brebo_finance_billing_instalment')->fields([
      'status' => 'billable', 'trigger_evidence' => $triggerEvidence, 'billable_at' => $now, 'billable_by' => $approverUid,
      'changed' => $now, 'changed_by' => $approverUid,
    ])->condition('id', $instalmentId)->execute();
  }

  /** Synchronizes a Moneybird sales invoice without granting invoice authority. */
  public function synchronizeMoneybirdInvoice(array $source, int $actorUid): int {
    foreach (['project_nid', 'moneybird_id', 'invoice_number', 'invoice_date', 'due_date', 'status', 'amount_ex_vat', 'vat_amount', 'amount_inc_vat', 'source_hash', 'recorded_at'] as $required) {
      if (!isset($source[$required]) || $source[$required] === '') throw new InvalidArgumentException("$required is required.");
    }
    $statuses = ['draft', 'sent', 'paid', 'overdue', 'disputed', 'credited', 'cancelled'];
    if (!in_array($source['status'], $statuses, TRUE)) throw new InvalidArgumentException('Unsupported Moneybird invoice status.');

    // External accounting mirrors may always ingest drafts/credits/cancellations, but
    // a newly sent/live invoice must have passed BREBO's billing gate first.
    if (in_array($source['status'], ['sent', 'overdue', 'disputed'], TRUE)) {
      $this->phaseGateManager->requireRelease((int) $source['project_nid'], 'billing_release');
      if (!empty($source['instalment_id'])) {
        $instalment = $this->database->select('brebo_finance_billing_instalment', 'i')->fields('i', ['status'])->condition('id', (int) $source['instalment_id'])->condition('project_nid', (int) $source['project_nid'])->execute()->fetchAssoc();
        if ($instalment === FALSE || !in_array($instalment['status'], ['billable', 'invoiced'], TRUE)) {
          throw new RuntimeException('A sales invoice may not become live before its BREBO instalment is billable.');
        }
      }
    }

    $gross = $this->decimal->add((string) $source['amount_ex_vat'], (string) $source['vat_amount']);
    if ($this->decimal->compare($gross, (string) $source['amount_inc_vat']) !== 0) throw new InvalidArgumentException('Invoice amount excluding VAT plus VAT must equal the amount including VAT.');
    $regularAmount = (string) ($source['regular_account_amount'] ?? $source['amount_inc_vat']);
    $gAccountAmount = (string) ($source['g_account_amount'] ?? '0');
    if ($this->decimal->compare($this->decimal->add($regularAmount, $gAccountAmount), (string) $source['amount_inc_vat']) !== 0) throw new InvalidArgumentException('Regular-account and G-account amounts must equal the invoice total.');
    $paidAmount = (string) ($source['paid_amount_inc_vat'] ?? '0');
    if ($this->decimal->compare($paidAmount, '0') < 0 || $this->decimal->compare($paidAmount, (string) $source['amount_inc_vat']) > 0) throw new InvalidArgumentException('Paid amount must be between zero and the invoice total.');
    $existing = $this->database->select('brebo_finance_sales_invoice', 'i')->fields('i', ['id', 'source_hash', 'recorded_at'])->condition('moneybird_id', (string) $source['moneybird_id'])->execute()->fetchAssoc();
    if ($existing !== FALSE && (int) $source['recorded_at'] < (int) $existing['recorded_at']) throw new RuntimeException('Older Moneybird data cannot overwrite a newer invoice mirror.');
    if ($existing !== FALSE && hash_equals((string) $existing['source_hash'], (string) $source['source_hash'])) return (int) $existing['id'];
    $fields = [
      'project_nid' => (int) $source['project_nid'], 'instalment_id' => $source['instalment_id'] ?? NULL, 'change_order_id' => $source['change_order_id'] ?? NULL,
      'moneybird_id' => (string) $source['moneybird_id'], 'invoice_number' => (string) $source['invoice_number'], 'invoice_date' => (string) $source['invoice_date'],
      'due_date' => (string) $source['due_date'], 'status' => (string) $source['status'], 'amount_ex_vat' => (string) $source['amount_ex_vat'],
      'vat_amount' => (string) $source['vat_amount'], 'amount_inc_vat' => (string) $source['amount_inc_vat'], 'paid_amount_inc_vat' => $paidAmount,
      'regular_account_amount' => $regularAmount, 'g_account_amount' => $gAccountAmount, 'dispute_reason' => $source['dispute_reason'] ?? NULL,
      'source_hash' => (string) $source['source_hash'], 'recorded_at' => (int) $source['recorded_at'], 'changed' => time(), 'changed_by' => $actorUid,
    ];
    if ($existing === FALSE) {
      $fields['created'] = time(); $fields['created_by'] = $actorUid;
      $id = (int) $this->database->insert('brebo_finance_sales_invoice')->fields($fields)->execute();
    }
    else {
      $id = (int) $existing['id'];
      $this->database->update('brebo_finance_sales_invoice')->fields($fields)->condition('id', $id)->execute();
    }
    if (!empty($source['instalment_id']) && in_array($source['status'], ['sent', 'paid', 'overdue', 'disputed'], TRUE)) {
      $this->database->update('brebo_finance_billing_instalment')->fields(['status' => $source['status'] === 'paid' ? 'paid' : 'invoiced', 'sales_invoice_id' => $id, 'changed' => time(), 'changed_by' => $actorUid])
        ->condition('id', (int) $source['instalment_id'])->condition('project_nid', (int) $source['project_nid'])->condition('status', ['billable', 'invoiced'], 'IN')->execute();
    }
    return $id;
  }
}
