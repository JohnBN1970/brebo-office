<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\PaymentBatchRepositoryInterface;
use Drupal\brebo_finance\Contract\PaymentRecipientGatewayInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Builds and seals controlled payment runs from approved payment releases. */
final class PaymentBatchManager {

  public function __construct(
    private readonly PaymentBatchRepositoryInterface $repository,
    private readonly PaymentRecipientGatewayInterface $recipients,
    private readonly VatCalculator $decimal,
  ) {}

  /** Creates one draft batch and immutable recipient snapshots for its items. */
  public function prepare(array $releaseIds, string $executionDate, int $userId): int {
    $this->repository->ensureStorage();
    $releaseIds = array_values(array_unique(array_map('intval', $releaseIds)));
    if ($releaseIds === []) {
      throw new InvalidArgumentException('Select at least one approved payment release.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $executionDate)) {
      throw new InvalidArgumentException('Execution date must be YYYY-MM-DD.');
    }
    if ($executionDate < date('Y-m-d')) {
      throw new InvalidArgumentException('Execution date may not be in the past.');
    }

    return $this->repository->transactional(function () use ($releaseIds, $executionDate, $userId): int {
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

        $recipient = $this->recipients->snapshot((int) $invoice['id'], $userId);
        $projectIds[(int) $invoice['project_nid']] = TRUE;
        $regular = (string) $release['regular_account_amount'];
        $gAccount = (string) $release['g_account_amount'];
        $splitTotal = $this->decimal->add($regular, $gAccount);
        if ($this->decimal->compare($splitTotal, (string) $release['total_amount']) !== 0) {
          throw new RuntimeException("Payment release {$releaseId} split no longer equals the released total.");
        }
        if ($this->decimal->compare($regular, '0') > 0) {
          $items[] = $this->itemPayload($release, $invoice, $recipient, 'regular', $regular, $executionDate);
        }
        if ($this->decimal->compare($gAccount, '0') > 0) {
          // The existing Finance model only stores a masked G-account IBAN.
          // Never route a G-account amount to the supplier's regular IBAN.
          throw new RuntimeException("Factuur {$invoice['id']} bevat een G-rekeningdeel, maar er is nog geen volledig geverifieerde G-rekening-IBAN beschikbaar voor bankinitiatie. Betaalrun geblokkeerd.");
        }
      }

      if ($items === []) {
        throw new RuntimeException('Selected releases contain no payable amount.');
      }

      $now = time();
      $batchNumber = 'BRB-' . gmdate('Ymd-His', $now) . '-' . strtoupper(substr(hash('sha256', implode(',', $releaseIds) . ':' . $now), 0, 8));
      $draftHash = $this->hash(['batch_number' => $batchNumber, 'execution_date' => $executionDate, 'currency' => $currency, 'items' => $items]);
      $batchId = $this->repository->insertBatch([
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
      ]);

      foreach ($items as $position => $item) {
        $this->repository->insertItem([
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
        ]);
      }

      $this->auditBatch($batchId, 'payment_batch_prepared', $userId, [
        'projects' => array_keys($projectIds),
        'payload_hash' => $draftHash,
        'release_ids' => $releaseIds,
      ]);
      return $batchId;
    });
  }

  /** Runs deterministic controls and seals the exact reviewed payload. */
  public function controllerReview(int $batchId, int $userId): array {
    $this->repository->ensureStorage();
    $batch = $this->loadBatch($batchId, ['draft', 'reviewed']);
    $items = $this->loadItems($batchId);
    if ($items === []) {
      throw new RuntimeException('An empty payment batch cannot be reviewed.');
    }

    $blockers = [];
    $warnings = [];
    foreach ($items as $item) {
      $release = $this->loadRelease((int) $item['release_id']);
      $invoice = $this->loadInvoice((int) $item['invoice_id']);
      if ($release['status'] !== 'approved') {
        $blockers[] = "Release {$release['id']} is niet meer goedgekeurd.";
      }
      if ($invoice['match_status'] !== 'matched') {
        $blockers[] = "Factuur {$invoice['id']} is niet meer volledig gematcht.";
      }
      if (in_array($invoice['status'], ['paid', 'cancelled'], TRUE)) {
        $blockers[] = "Factuur {$invoice['id']} is al betaald of geannuleerd.";
      }
      if ($this->decimal->compare((string) $item['amount'], '0') <= 0) {
        $blockers[] = "Betaalinstructie {$item['id']} heeft geen positief bedrag.";
      }
      if ((string) $item['currency'] !== 'EUR') {
        $blockers[] = "Betaalinstructie {$item['id']} is niet in EUR.";
      }
      if (!$this->recipients->unchanged((int) $invoice['id'], (string) $item['recipient_hash'], $userId)) {
        $blockers[] = "Betaalrekening van factuur {$invoice['id']} is gewijzigd na voorbereiding.";
      }
      if ((string) $item['instruction_type'] === 'g_account') {
        $blockers[] = "G-rekeninginstructie {$item['id']} kan nog niet worden verzonden zonder volledig geverifieerde G-rekening-IBAN.";
      }
    }

    if ((int) $batch['item_count'] !== count($items)) {
      $blockers[] = 'Aantal betaalinstructies wijkt af van de opgeslagen batchkop.';
    }
    if ($this->decimal->compare((string) $batch['control_sum'], $this->sumItems($items)) !== 0) {
      $blockers[] = 'Control sum wijkt af van de actuele batchinhoud.';
    }

    $verdict = $blockers !== [] ? 'red' : ($warnings !== [] ? 'orange' : 'green');
    $payloadHash = $this->currentPayloadHash($batch, $items);
    $now = time();
    $this->repository->updateBatch($batchId, [
      'status' => 'reviewed',
      'controller_verdict' => $verdict,
      'controller_payload' => json_encode(['blockers' => $blockers, 'warnings' => $warnings], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'payload_hash' => $payloadHash,
      'reviewed' => $now,
      'reviewed_by' => $userId,
      'changed' => $now,
      'changed_by' => $userId,
    ]);
    $this->auditBatch($batchId, 'payment_batch_controller_reviewed', $userId, [
      'verdict' => $verdict,
      'blockers' => $blockers,
      'warnings' => $warnings,
      'payload_hash' => $payloadHash,
    ]);
    return ['verdict' => $verdict, 'blockers' => $blockers, 'warnings' => $warnings, 'payload_hash' => $payloadHash];
  }

  /** Four-eyes release. A red deterministic verdict can never be overridden. */
  public function release(int $batchId, string $note, int $userId): void {
    $this->repository->ensureStorage();
    $batch = $this->loadBatch($batchId, ['reviewed']);
    if (trim($note) === '') {
      throw new InvalidArgumentException('A release note is required.');
    }
    if ((int) $batch['created_by'] === $userId) {
      throw new RuntimeException('The payment-run preparer may not release their own batch.');
    }
    if ($batch['controller_verdict'] === 'red' || $batch['controller_verdict'] === 'pending') {
      throw new RuntimeException('A red or missing controller verdict blocks payment-run release.');
    }

    $items = $this->loadItems($batchId);
    $currentHash = $this->currentPayloadHash($batch, $items);
    if (!hash_equals((string) $batch['payload_hash'], $currentHash)) {
      throw new RuntimeException('Payment batch changed after controller review; run a new review.');
    }
    foreach ($items as $item) {
      if (!$this->recipients->unchanged((int) $item['invoice_id'], (string) $item['recipient_hash'], $userId)) {
        throw new RuntimeException('Recipient changed after controller review; payment-run release is blocked.');
      }
    }

    $now = time();
    $this->repository->updateBatch($batchId, [
      'status' => 'released',
      'release_note' => trim($note),
      'released' => $now,
      'released_by' => $userId,
      'sealed_hash' => $currentHash,
      'changed' => $now,
      'changed_by' => $userId,
    ]);
    $this->repository->updateItems($batchId, ['status' => 'released']);
    $this->auditBatch($batchId, 'payment_batch_released', $userId, [
      'sealed_hash' => $currentHash,
      'note' => trim($note),
    ]);
  }

  /** Returns the exact sealed batch payload used by bank/SEPA adapters. */
  public function sealedPayload(int $batchId): array {
    $this->repository->ensureStorage();
    $batch = $this->loadBatch($batchId, ['released', 'submitted', 'executed', 'reconciled']);
    $items = $this->loadItems($batchId);
    $hash = $this->currentPayloadHash($batch, $items);
    if (empty($batch['sealed_hash']) || !hash_equals((string) $batch['sealed_hash'], $hash)) {
      throw new RuntimeException('Sealed payment batch no longer matches its immutable payload hash.');
    }
    return ['batch' => $batch, 'items' => $items, 'sealed_hash' => $hash];
  }

  private function itemPayload(array $release, array $invoice, array $recipient, string $type, string $amount, string $executionDate): array {
    $endToEnd = substr('BRB-' . $release['release_number'] . '-' . strtoupper($type), 0, 35);
    return [
      'project_nid' => (int) $invoice['project_nid'],
      'release_id' => (int) $release['id'],
      'invoice_id' => (int) $invoice['id'],
      'instruction_type' => $type,
      'amount' => $amount,
      'currency' => 'EUR',
      'execution_date' => $executionDate,
      'creditor_name' => substr((string) $recipient['account_name'], 0, 140),
      'creditor_iban' => (string) $recipient['iban'],
      'creditor_bic' => substr((string) ($recipient['bic'] ?? ''), 0, 11),
      'recipient_hash' => (string) $recipient['recipient_hash'],
      'end_to_end_id' => $endToEnd,
      'remittance_information' => substr('Factuur ' . $invoice['invoice_number'], 0, 140),
    ];
  }

  private function loadBatch(int $id, array $statuses): array {
    $row = $this->repository->batch($id);
    if ($row === NULL || !in_array($row['status'], $statuses, TRUE)) {
      throw new UnexpectedValueException('Payment batch has an invalid state.');
    }
    return $row;
  }

  private function loadRelease(int $id): array {
    $row = $this->repository->release($id);
    if ($row === NULL) throw new UnexpectedValueException('Payment release does not exist.');
    return $row;
  }

  private function loadInvoice(int $id): array {
    $row = $this->repository->invoice($id);
    if ($row === NULL) throw new UnexpectedValueException('Purchase invoice does not exist.');
    return $row;
  }

  private function loadItems(int $batchId): array {
    return $this->repository->items($batchId);
  }

  private function releaseAlreadyInOpenBatch(int $releaseId): bool {
    return $this->repository->releaseInOpenBatch($releaseId);
  }

  private function sumItems(array $items): string {
    $sum = '0';
    foreach ($items as $item) {
      $sum = $this->decimal->add($sum, (string) $item['amount']);
    }
    return $sum;
  }

  private function currentPayloadHash(array $batch, array $items): string {
    $payload = [
      'batch_number' => $batch['batch_number'],
      'execution_date' => $batch['execution_date'],
      'currency' => $batch['currency'],
      'items' => array_map(static fn(array $item): array => [
        'release_id' => (int) $item['release_id'],
        'invoice_id' => (int) $item['invoice_id'],
        'instruction_type' => $item['instruction_type'],
        'amount' => (string) $item['amount'],
        'currency' => $item['currency'],
        'creditor_name' => $item['creditor_name'],
        'creditor_iban' => $item['creditor_iban'],
        'creditor_bic' => $item['creditor_bic'],
        'recipient_hash' => $item['recipient_hash'],
        'end_to_end_id' => $item['end_to_end_id'],
        'remittance_information' => $item['remittance_information'],
      ], $items),
    ];
    return $this->hash($payload);
  }

  private function hash(array $payload): string {
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
  }

  private function auditBatch(int $batchId, string $action, int $userId, array $payload): void {
    $this->repository->insertAudit([
      'project_nid' => 0,
      'entity_type' => 'payment_batch',
      'entity_id' => $batchId,
      'action' => $action,
      'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'reason' => 'Controlled BREBO payment-run workflow.',
      'created' => time(),
      'created_by' => $userId,
    ]);
  }

}
