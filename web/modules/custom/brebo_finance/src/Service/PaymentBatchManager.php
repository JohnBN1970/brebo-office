<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Builds and seals controlled payment runs from approved payment releases. */
final class PaymentBatchManager {

  public function __construct(
    private readonly Connection $database,
    private readonly PurchaseInvoiceImporter $invoiceImporter,
    private readonly PaymentReleaseManager $releaseManager,
  ) {}

  /** Creates one draft batch and immutable recipient snapshots for its items. */
  public function prepare(array $releaseIds, string $executionDate, int $userId): int {
    $releaseIds = array_values(array_unique(array_map('intval', $releaseIds)));
    if ($releaseIds === []) {
      throw new InvalidArgumentException('Select at least one approved payment release.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $executionDate)) {
      throw new InvalidArgumentException('Execution date must be YYYY-MM-DD.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $items = [];
      $currency = NULL;
      $projectIds = [];
      foreach ($releaseIds as $releaseId) {
        $release = $this->loadRelease($releaseId);
        if ($release['status'] !== 'approved') {
          throw new RuntimeException("Payment release {$releaseId} is not approved.");
        }
        $invoice = $this->loadInvoice((int) $release['invoice_id']);
        if ($invoice['match_status'] !== 'matched' || in_array($invoice['status'], ['paid', 'cancelled'], TRUE)) {
          throw new RuntimeException("Invoice {$invoice['id']} is no longer eligible for payment.");
        }
        if ((string) $release['currency'] !== 'EUR') {
          throw new RuntimeException('Payment batches currently support EUR/SEPA only.');
        }
        $currency ??= (string) $release['currency'];
        if ($currency !== (string) $release['currency']) {
          throw new RuntimeException('A payment batch cannot mix currencies.');
        }
        if ($this->releaseAlreadyInOpenBatch($releaseId)) {
          throw new RuntimeException("Payment release {$releaseId} is already present in an active payment batch.");
        }

        $recipient = $this->invoiceImporter->paymentRecipientSnapshot((int) $invoice['id'], $userId);
        $projectIds[(int) $invoice['project_nid']] = TRUE;
        $regular = (string) $release['regular_account_amount'];
        $gAccount = (string) $release['g_account_amount'];
        if ((float) $regular > 0) {
          $items[] = $this->itemPayload($release, $invoice, $recipient, 'regular', $regular, $executionDate);
        }
        if ((float) $gAccount > 0) {
          $items[] = $this->itemPayload($release, $invoice, $recipient, 'g_account', $gAccount, $executionDate);
        }
      }

      if ($items === []) {
        throw new RuntimeException('Selected releases contain no payable amount.');
      }

      $now = time();
      $batchNumber = 'BRB-' . gmdate('Ymd-His', $now) . '-' . strtoupper(substr(hash('sha256', implode(',', $releaseIds) . ':' . $now), 0, 8));
      $draftHash = $this->hash(['batch_number' => $batchNumber, 'execution_date' => $executionDate, 'currency' => $currency, 'items' => $items]);
      $batchId = (int) $this->database->insert('brebo_finance_payment_batch')->fields([
        'batch_number' => $batchNumber,
        'status' => 'draft',
        'execution_date' => $executionDate,
        'currency' => $currency ?? 'EUR',
        'item_count' => count($items),
        'control_sum' => $this->sumItems($items),
        'payload_hash' => $draftHash,
        'controller_verdict' => 'pending',
        'created' => $now,
        'created_by' => $userId,
        'changed' => $now,
        'changed_by' => $userId,
      ])->execute();

      foreach ($items as $position => $item) {
        $this->database->insert('brebo_finance_payment_batch_item')->fields([
          'batch_id' => $batchId,
          'position' => $position + 1,
          'project_nid' => $item['project_nid'],
          'release_id' => $item['release_id'],
          'invoice_id' => $item['invoice_id'],
          'instruction_type' => $item['instruction_type'],
          'amount' => $item['amount'],
          'currency' => 'EUR',
          'creditor_name' => $item['creditor_name'],
          'creditor_iban' => $item['creditor_iban'],
          'creditor_bic' => $item['creditor_bic'] ?: NULL,
          'recipient_hash' => $item['recipient_hash'],
          'end_to_end_id' => $item['end_to_end_id'],
          'remittance_information' => $item['remittance_information'],
          'status' => 'prepared',
          'created' => $now,
          'created_by' => $userId,
        ])->execute();
      }

      $this->auditBatch($batchId, 'payment_batch_prepared', $userId, ['projects' => array_keys($projectIds), 'payload_hash' => $draftHash, 'release_ids' => $releaseIds]);
      return $batchId;
    }
    catch (\Throwable $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      throw $e;
    }
  }

  /** Runs deterministic controls and seals the exact reviewed payload. */
  public function controllerReview(int $batchId, int $userId): array {
    $batch = $this->loadBatch($batchId, ['draft', 'reviewed']);
    $items = $this->loadItems($batchId);
    $blockers = [];
    $warnings = [];
    foreach ($items as $item) {
      $release = $this->loadRelease((int) $item['release_id']);
      $invoice = $this->loadInvoice((int) $item['invoice_id']);
      if ($release['status'] !== 'approved') $blockers[] = "Release {$release['id']} is niet meer goedgekeurd.";
      if ($invoice['match_status'] !== 'matched') $blockers[] = "Factuur {$invoice['id']} is niet meer volledig gematcht.";
      if (in_array($invoice['status'], ['paid', 'cancelled'], TRUE)) $blockers[] = "Factuur {$invoice['id']} is al betaald of geannuleerd.";
      if (!$this->invoiceImporter->paymentRecipientUnchanged((int) $invoice['id'], (string) $item['recipient_hash'], $userId)) {
        $blockers[] = "Betaalrekening van factuur {$invoice['id']} is gewijzigd na voorbereiding.";
      }
      if ($item['instruction_type'] === 'g_account' && (float) $item['amount'] > 0) {
        $warnings[] = "G-rekeninginstructie voor factuur {$invoice['id']} blijft apart zichtbaar in de betaalrun.";
      }
    }

    $verdict = $blockers !== [] ? 'red' : ($warnings !== [] ? 'orange' : 'green');
    $payloadHash = $this->currentPayloadHash($batch, $items);
    $now = time();
    $this->database->update('brebo_finance_payment_batch')->fields([
      'status' => 'reviewed',
      'controller_verdict' => $verdict,
      'controller_payload' => json_encode(['blockers' => $blockers, 'warnings' => $warnings], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'payload_hash' => $payloadHash,
      'reviewed' => $now,
      'reviewed_by' => $userId,
      'changed' => $now,
      'changed_by' => $userId,
    ])->condition('id', $batchId)->execute();
    $this->auditBatch($batchId, 'payment_batch_controller_reviewed', $userId, ['verdict' => $verdict, 'blockers' => $blockers, 'warnings' => $warnings, 'payload_hash' => $payloadHash]);
    return ['verdict' => $verdict, 'blockers' => $blockers, 'warnings' => $warnings, 'payload_hash' => $payloadHash];
  }

  /** Four-eyes release. A red deterministic verdict can never be overridden. */
  public function release(int $batchId, string $note, int $userId): void {
    $batch = $this->loadBatch($batchId, ['reviewed']);
    if (trim($note) === '') throw new InvalidArgumentException('A release note is required.');
    if ((int) $batch['created_by'] === $userId) throw new RuntimeException('The payment-run preparer may not release their own batch.');
    if ($batch['controller_verdict'] === 'red' || $batch['controller_verdict'] === 'pending') throw new RuntimeException('A red or missing controller verdict blocks payment-run release.');

    $items = $this->loadItems($batchId);
    $currentHash = $this->currentPayloadHash($batch, $items);
    if (!hash_equals((string) $batch['payload_hash'], $currentHash)) throw new RuntimeException('Payment batch changed after controller review; run a new review.');
    foreach ($items as $item) {
      if (!$this->invoiceImporter->paymentRecipientUnchanged((int) $item['invoice_id'], (string) $item['recipient_hash'], $userId)) {
        throw new RuntimeException('Recipient changed after controller review; payment-run release is blocked.');
      }
    }

    $now = time();
    $this->database->update('brebo_finance_payment_batch')->fields([
      'status' => 'released',
      'release_note' => trim($note),
      'released' => $now,
      'released_by' => $userId,
      'sealed_hash' => $currentHash,
      'changed' => $now,
      'changed_by' => $userId,
    ])->condition('id', $batchId)->execute();
    $this->database->update('brebo_finance_payment_batch_item')->fields(['status' => 'released'])->condition('batch_id', $batchId)->execute();
    $this->auditBatch($batchId, 'payment_batch_released', $userId, ['sealed_hash' => $currentHash, 'note' => trim($note)]);
  }

  private function itemPayload(array $release, array $invoice, array $recipient, string $type, string $amount, string $executionDate): array {
    $endToEnd = substr('BRB-' . $release['release_number'] . '-' . strtoupper($type), 0, 35);
    return [
      'project_nid' => (int) $invoice['project_nid'], 'release_id' => (int) $release['id'], 'invoice_id' => (int) $invoice['id'],
      'instruction_type' => $type, 'amount' => $amount, 'currency' => 'EUR', 'execution_date' => $executionDate,
      'creditor_name' => substr((string) $recipient['account_name'], 0, 140), 'creditor_iban' => (string) $recipient['iban'],
      'creditor_bic' => substr((string) ($recipient['bic'] ?? ''), 0, 11), 'recipient_hash' => (string) $recipient['recipient_hash'],
      'end_to_end_id' => $endToEnd, 'remittance_information' => substr('Factuur ' . $invoice['invoice_number'], 0, 140),
    ];
  }

  private function loadBatch(int $id, array $statuses): array { $row = $this->database->select('brebo_finance_payment_batch', 'b')->fields('b')->condition('id', $id)->execute()->fetchAssoc(); if ($row === FALSE || !in_array($row['status'], $statuses, TRUE)) throw new UnexpectedValueException('Payment batch has an invalid state.'); return $row; }
  private function loadRelease(int $id): array { $row = $this->database->select('brebo_finance_payment_release', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc(); if ($row === FALSE) throw new UnexpectedValueException('Payment release does not exist.'); return $row; }
  private function loadInvoice(int $id): array { $row = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i')->condition('id', $id)->execute()->fetchAssoc(); if ($row === FALSE) throw new UnexpectedValueException('Purchase invoice does not exist.'); return $row; }
  private function loadItems(int $batchId): array { return $this->database->select('brebo_finance_payment_batch_item', 'i')->fields('i')->condition('batch_id', $batchId)->orderBy('position')->execute()->fetchAll(\PDO::FETCH_ASSOC); }
  private function releaseAlreadyInOpenBatch(int $releaseId): bool { $q = $this->database->select('brebo_finance_payment_batch_item', 'i'); $q->innerJoin('brebo_finance_payment_batch', 'b', 'b.id = i.batch_id'); $q->condition('i.release_id', $releaseId)->condition('b.status', ['cancelled', 'rejected', 'executed'], 'NOT IN'); return (bool) $q->countQuery()->execute()->fetchField(); }
  private function sumItems(array $items): string { $sum = '0.0000'; foreach ($items as $item) $sum = number_format((float) $sum + (float) $item['amount'], 4, '.', ''); return $sum; }
  private function currentPayloadHash(array $batch, array $items): string { $payload = ['batch_number' => $batch['batch_number'], 'execution_date' => $batch['execution_date'], 'currency' => $batch['currency'], 'items' => array_map(fn(array $i) => ['release_id' => (int) $i['release_id'], 'invoice_id' => (int) $i['invoice_id'], 'instruction_type' => $i['instruction_type'], 'amount' => (string) $i['amount'], 'currency' => $i['currency'], 'creditor_name' => $i['creditor_name'], 'creditor_iban' => $i['creditor_iban'], 'creditor_bic' => $i['creditor_bic'], 'recipient_hash' => $i['recipient_hash'], 'end_to_end_id' => $i['end_to_end_id'], 'remittance_information' => $i['remittance_information']], $items)]; return $this->hash($payload); }
  private function hash(array $payload): string { return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)); }
  private function auditBatch(int $batchId, string $action, int $userId, array $payload): void { $this->database->insert('brebo_finance_audit')->fields(['project_nid' => 0, 'entity_type' => 'payment_batch', 'entity_id' => $batchId, 'action' => $action, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'reason' => 'Controlled BREBO payment-run workflow.', 'created' => time(), 'created_by' => $userId])->execute(); }

}
