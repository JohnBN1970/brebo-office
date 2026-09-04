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
    private readonly VatCalculator $decimal,
  ) {}

  /** Creates one draft batch and immutable recipient snapshots for its items. */
  public function prepare(array $releaseIds, string $executionDate, int $userId): int {
    $this->ensureStorage();
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

      $this->auditBatch($batchId, 'payment_batch_prepared', $userId, [
        'projects' => array_keys($projectIds),
        'payload_hash' => $draftHash,
        'release_ids' => $releaseIds,
      ]);
      return $batchId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** Runs deterministic controls and seals the exact reviewed payload. */
  public function controllerReview(int $batchId, int $userId): array {
    $this->ensureStorage();
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
      if (!$this->invoiceImporter->paymentRecipientUnchanged((int) $invoice['id'], (string) $item['recipient_hash'], $userId)) {
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
    $this->ensureStorage();
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
    $this->database->update('brebo_finance_payment_batch_item')
      ->fields(['status' => 'released'])
      ->condition('batch_id', $batchId)
      ->execute();
    $this->auditBatch($batchId, 'payment_batch_released', $userId, [
      'sealed_hash' => $currentHash,
      'note' => trim($note),
    ]);
  }

  /** Returns the exact sealed batch payload used by bank/SEPA adapters. */
  public function sealedPayload(int $batchId): array {
    $this->ensureStorage();
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

  /** Creates the payment-center storage for both upgraded and fresh environments. */
  private function ensureStorage(): void {
    $schema = $this->database->schema();
    $money = ['type' => 'numeric', 'precision' => 18, 'scale' => 4, 'not null' => TRUE, 'default' => 0];
    $user = ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE];

    if (!$schema->tableExists('brebo_finance_payment_batch')) {
      $schema->createTable('brebo_finance_payment_batch', [
        'description' => 'Four-eyes controlled immutable payment run awaiting bank execution.',
        'fields' => [
          'id' => ['type' => 'serial', 'not null' => TRUE],
          'batch_number' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
          'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'draft'],
          'execution_date' => ['type' => 'varchar', 'length' => 10, 'not null' => TRUE],
          'currency' => ['type' => 'varchar', 'length' => 3, 'not null' => TRUE, 'default' => 'EUR'],
          'item_count' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
          'control_sum' => $money,
          'payload_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
          'controller_verdict' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE, 'default' => 'pending'],
          'controller_payload' => ['type' => 'text', 'size' => 'big', 'not null' => FALSE],
          'reviewed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
          'reviewed_by' => $user,
          'release_note' => ['type' => 'text', 'not null' => FALSE],
          'released' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => FALSE],
          'released_by' => $user,
          'sealed_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => FALSE],
          'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'created_by' => $user,
          'changed' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'changed_by' => $user,
        ],
        'primary key' => ['id'],
        'unique keys' => ['batch_number' => ['batch_number']],
        'indexes' => [
          'status_execution' => ['status', 'execution_date'],
          'controller_verdict' => ['controller_verdict'],
        ],
      ]);
    }

    if (!$schema->tableExists('brebo_finance_payment_batch_item')) {
      $schema->createTable('brebo_finance_payment_batch_item', [
        'description' => 'Immutable creditor instruction belonging to a controlled payment run.',
        'fields' => [
          'id' => ['type' => 'serial', 'not null' => TRUE],
          'batch_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'position' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'release_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'invoice_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'instruction_type' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE],
          'amount' => $money,
          'currency' => ['type' => 'varchar', 'length' => 3, 'not null' => TRUE, 'default' => 'EUR'],
          'creditor_name' => ['type' => 'varchar', 'length' => 140, 'not null' => TRUE],
          'creditor_iban' => ['type' => 'varchar', 'length' => 34, 'not null' => TRUE],
          'creditor_bic' => ['type' => 'varchar', 'length' => 11, 'not null' => FALSE],
          'recipient_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
          'end_to_end_id' => ['type' => 'varchar', 'length' => 35, 'not null' => TRUE],
          'remittance_information' => ['type' => 'varchar', 'length' => 140, 'not null' => TRUE],
          'status' => ['type' => 'varchar', 'length' => 24, 'not null' => TRUE, 'default' => 'prepared'],
          'created' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'created_by' => $user,
        ],
        'primary key' => ['id'],
        'unique keys' => [
          'batch_position' => ['batch_id', 'position'],
          'batch_end_to_end' => ['batch_id', 'end_to_end_id'],
        ],
        'indexes' => [
          'batch_status' => ['batch_id', 'status'],
          'release' => ['release_id'],
          'invoice' => ['invoice_id'],
        ],
      ]);
    }
  }

  private function loadBatch(int $id, array $statuses): array {
    $row = $this->database->select('brebo_finance_payment_batch', 'b')->fields('b')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE || !in_array($row['status'], $statuses, TRUE)) {
      throw new UnexpectedValueException('Payment batch has an invalid state.');
    }
    return $row;
  }

  private function loadRelease(int $id): array {
    $row = $this->database->select('brebo_finance_payment_release', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new UnexpectedValueException('Payment release does not exist.');
    }
    return $row;
  }

  private function loadInvoice(int $id): array {
    $row = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i')->condition('id', $id)->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new UnexpectedValueException('Purchase invoice does not exist.');
    }
    return $row;
  }

  private function loadItems(int $batchId): array {
    return $this->database->select('brebo_finance_payment_batch_item', 'i')
      ->fields('i')
      ->condition('batch_id', $batchId)
      ->orderBy('position')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function releaseAlreadyInOpenBatch(int $releaseId): bool {
    $query = $this->database->select('brebo_finance_payment_batch_item', 'i');
    $query->innerJoin('brebo_finance_payment_batch', 'b', 'b.id = i.batch_id');
    $query->condition('i.release_id', $releaseId)
      ->condition('b.status', ['cancelled', 'rejected', 'executed', 'reconciled'], 'NOT IN');
    return (bool) $query->countQuery()->execute()->fetchField();
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
    $this->database->insert('brebo_finance_audit')->fields([
      'project_nid' => 0,
      'entity_type' => 'payment_batch',
      'entity_id' => $batchId,
      'action' => $action,
      'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'reason' => 'Controlled BREBO payment-run workflow.',
      'created' => time(),
      'created_by' => $userId,
    ])->execute();
  }

}
