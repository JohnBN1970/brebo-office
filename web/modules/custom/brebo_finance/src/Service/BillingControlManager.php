<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\BillingControlRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

/** Controls project instalments and the operational Moneybird invoice mirror. */
final class BillingControlManager {

  public function __construct(
    private readonly BillingControlRepositoryInterface $repository,
    private readonly VatCalculator $decimal,
    private readonly FinancialPhaseGateManager $phaseGateManager,
  ) {}

  public function registerInstalment(array $data, int $actorUid): int {
    foreach (['project_nid', 'contract_id', 'instalment_number', 'description', 'trigger_type', 'planned_invoice_date'] as $required) {
      if (!isset($data[$required]) || $data[$required] === '') throw new InvalidArgumentException("$required is required.");
    }
    $triggerTypes = ['contract_date', 'calendar_date', 'verified_progress', 'milestone', 'change_order'];
    if (!in_array($data['trigger_type'], $triggerTypes, TRUE)) throw new InvalidArgumentException('Unsupported billing trigger type.');
    $contract = $this->repository->approvedContract((int) $data['contract_id']);
    if ($contract === NULL || (int) $contract['project_nid'] !== (int) $data['project_nid'] || $contract['status'] !== 'approved') throw new RuntimeException('Billing requires the approved project contract.');

    $lines = $this->normalizeLines($data['lines'] ?? NULL);
    if ($lines !== []) {
      $totals = $this->lineTotals($lines);
      if (isset($data['amount_ex_vat']) && $data['amount_ex_vat'] !== '' && $this->decimal->compare((string) $data['amount_ex_vat'], $totals['amount_ex_vat']) !== 0) {
        throw new InvalidArgumentException('Instalment line total excluding VAT must equal the instalment amount excluding VAT.');
      }
      $amountExVat = $totals['amount_ex_vat'];
      $vatAmount = $totals['vat_amount'];
      $amountIncVat = $totals['amount_inc_vat'];
      $vatCode = count($totals['vat_codes']) === 1 ? $totals['vat_codes'][0] : 'MIXED';
      $vatRate = count($totals['vat_rates']) === 1 ? $totals['vat_rates'][0] : '0.0000';
    }
    else {
      foreach (['amount_ex_vat', 'vat_rate'] as $required) {
        if (!isset($data[$required]) || $data[$required] === '') throw new InvalidArgumentException("$required is required when no instalment lines are supplied.");
      }
      $vat = $this->decimal->calculate((string) $data['amount_ex_vat'], (string) $data['vat_rate']);
      $amountExVat = $vat->amountExVat;
      $vatAmount = $vat->vatAmount;
      $amountIncVat = $vat->amountIncVat;
      $vatCode = (string) ($data['vat_code'] ?? 'NL_' . str_replace('.0000', '', $vat->vatRate));
      $vatRate = $vat->vatRate;
    }
    if ($this->decimal->compare($amountExVat, '0') <= 0) throw new InvalidArgumentException('Instalment amount must be positive.');

    $now = time();
    $fields = [
      'project_nid' => (int) $data['project_nid'], 'contract_id' => (int) $data['contract_id'], 'change_order_id' => $data['change_order_id'] ?? NULL,
      'instalment_number' => (string) $data['instalment_number'], 'description' => (string) $data['description'], 'trigger_type' => (string) $data['trigger_type'],
      'trigger_ref' => $data['trigger_ref'] ?? NULL, 'trigger_threshold' => $data['trigger_threshold'] ?? NULL, 'building_object_type' => $data['building_object_type'] ?? NULL,
      'building_object_id' => $data['building_object_id'] ?? NULL, 'planned_invoice_date' => (string) $data['planned_invoice_date'], 'amount_ex_vat' => $amountExVat,
      'vat_code' => $vatCode, 'vat_rate' => $vatRate, 'vat_amount' => $vatAmount,
      'amount_inc_vat' => $amountIncVat, 'status' => 'planned', 'evidence_payload' => json_encode($data['evidence'] ?? [], JSON_THROW_ON_ERROR),
      'created' => $now, 'created_by' => $actorUid, 'changed' => $now, 'changed_by' => $actorUid,
    ];
    return $this->repository->createInstalment($fields, $lines, (int) $data['project_nid'], $actorUid, $now);
  }

  /** Releases a reached instalment for manual invoicing under four eyes. */
  public function approveBillable(int $instalmentId, string $triggerEvidence, int $approverUid): void {
    $row = $this->repository->instalment($instalmentId);
    if ($row === NULL || $row['status'] !== 'planned') throw new RuntimeException('Only a planned instalment can become billable.');
    if ((int) $row['created_by'] === $approverUid) throw new RuntimeException('The instalment creator cannot approve billability.');
    if (trim($triggerEvidence) === '') throw new InvalidArgumentException('Verified trigger evidence is required.');
    $this->phaseGateManager->requireRelease((int) $row['project_nid'], 'billing_release');
    $now = time();
    $this->repository->updateInstalment($instalmentId, [
      'status' => 'billable', 'trigger_evidence' => $triggerEvidence, 'billable_at' => $now, 'billable_by' => $approverUid,
      'changed' => $now, 'changed_by' => $approverUid,
    ]);
  }

  /** Synchronizes a Moneybird sales invoice without granting invoice authority. */
  public function synchronizeMoneybirdInvoice(array $source, int $actorUid): int {
    foreach (['project_nid', 'moneybird_id', 'invoice_number', 'invoice_date', 'due_date', 'status', 'amount_ex_vat', 'vat_amount', 'amount_inc_vat', 'source_hash', 'recorded_at'] as $required) {
      if (!isset($source[$required]) || $source[$required] === '') throw new InvalidArgumentException("$required is required.");
    }
    $statuses = ['draft', 'sent', 'paid', 'overdue', 'disputed', 'credited', 'cancelled'];
    if (!in_array($source['status'], $statuses, TRUE)) throw new InvalidArgumentException('Unsupported Moneybird invoice status.');

    if (in_array($source['status'], ['sent', 'overdue', 'disputed'], TRUE)) {
      $this->phaseGateManager->requireRelease((int) $source['project_nid'], 'billing_release');
      if (!empty($source['instalment_id'])) {
        $instalment = $this->repository->instalmentForProject((int) $source['instalment_id'], (int) $source['project_nid']);
        if ($instalment === NULL || !in_array($instalment['status'], ['billable', 'invoiced'], TRUE)) {
          throw new RuntimeException('A sales invoice may not become live before its BREBO instalment is billable.');
        }
      }
    }

    $lines = $this->normalizeLines($source['lines'] ?? NULL);
    if ($lines !== []) {
      $lineTotals = $this->lineTotals($lines);
      foreach (['amount_ex_vat', 'vat_amount', 'amount_inc_vat'] as $field) {
        if ($this->decimal->compare((string) $source[$field], $lineTotals[$field]) !== 0) {
          throw new InvalidArgumentException("Sales invoice line total does not equal $field.");
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
    $existing = $this->repository->salesInvoiceByMoneybirdId((string) $source['moneybird_id']);
    if ($existing !== NULL && (int) $source['recorded_at'] < (int) $existing['recorded_at']) throw new RuntimeException('Older Moneybird data cannot overwrite a newer invoice mirror.');
    if ($existing !== NULL && hash_equals((string) $existing['source_hash'], (string) $source['source_hash'])) return (int) $existing['id'];
    $fields = [
      'project_nid' => (int) $source['project_nid'], 'instalment_id' => $source['instalment_id'] ?? NULL, 'change_order_id' => $source['change_order_id'] ?? NULL,
      'moneybird_id' => (string) $source['moneybird_id'], 'invoice_number' => (string) $source['invoice_number'], 'invoice_date' => (string) $source['invoice_date'],
      'due_date' => (string) $source['due_date'], 'status' => (string) $source['status'], 'amount_ex_vat' => (string) $source['amount_ex_vat'],
      'vat_amount' => (string) $source['vat_amount'], 'amount_inc_vat' => (string) $source['amount_inc_vat'], 'paid_amount_inc_vat' => $paidAmount,
      'regular_account_amount' => $regularAmount, 'g_account_amount' => $gAccountAmount, 'dispute_reason' => $source['dispute_reason'] ?? NULL,
      'source_hash' => (string) $source['source_hash'], 'recorded_at' => (int) $source['recorded_at'], 'changed' => time(), 'changed_by' => $actorUid,
    ];
    $instalmentId = !empty($source['instalment_id']) ? (int) $source['instalment_id'] : NULL;
    return $this->repository->persistSalesInvoice(
      $existing !== NULL ? (int) $existing['id'] : NULL,
      $fields,
      $lines,
      (int) $source['project_nid'],
      $instalmentId,
      (string) $source['status'],
      $actorUid,
      time(),
    );
  }

  /** @return list<array<string, string>> */
  private function normalizeLines(mixed $lines): array {
    if ($lines === NULL || $lines === []) return [];
    if (!is_array($lines)) throw new InvalidArgumentException('Billing lines must be an array.');
    $normalized = [];
    foreach (array_values($lines) as $index => $line) {
      if (!is_array($line)) throw new InvalidArgumentException('Every billing line must be an array.');
      foreach (['description', 'amount_ex_vat', 'vat_rate'] as $required) {
        if (!isset($line[$required]) || $line[$required] === '') throw new InvalidArgumentException("Billing line $index requires $required.");
      }
      if (trim((string) $line['description']) === '') throw new InvalidArgumentException("Billing line $index requires a description.");
      $vat = $this->decimal->calculate((string) $line['amount_ex_vat'], (string) $line['vat_rate']);
      if ($this->decimal->compare($vat->amountExVat, '0') <= 0) throw new InvalidArgumentException("Billing line $index amount must be positive.");
      $normalized[] = [
        'description' => trim((string) $line['description']),
        'amount_ex_vat' => $vat->amountExVat,
        'vat_code' => trim((string) ($line['vat_code'] ?? 'NL_' . str_replace('.0000', '', $vat->vatRate))),
        'vat_rate' => $vat->vatRate,
        'vat_amount' => $vat->vatAmount,
        'amount_inc_vat' => $vat->amountIncVat,
        'source_ref' => trim((string) ($line['source_ref'] ?? '')),
      ];
    }
    return $normalized;
  }

  /** @param list<array<string, string>> $lines */
  private function lineTotals(array $lines): array {
    $ex = '0'; $vat = '0'; $inc = '0'; $codes = []; $rates = [];
    foreach ($lines as $line) {
      $ex = $this->decimal->add($ex, $line['amount_ex_vat']);
      $vat = $this->decimal->add($vat, $line['vat_amount']);
      $inc = $this->decimal->add($inc, $line['amount_inc_vat']);
      $codes[$line['vat_code']] = TRUE;
      $rates[$line['vat_rate']] = TRUE;
    }
    return ['amount_ex_vat' => $ex, 'vat_amount' => $vat, 'amount_inc_vat' => $inc, 'vat_codes' => array_keys($codes), 'vat_rates' => array_keys($rates)];
  }


}
