<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Read-only adapter for the existing BREBO Finance batch schema.
 * It never creates payment data, and it does not silently fall back to Drupal.
 * Requires an explicitly verified schema and an Office-owned PDO connection.
 */
final class OfficePaymentCanonicalBatchRepository {
  public function __construct(private readonly \PDO $pdo, private readonly bool $schemaVerified = false) {}

  public function load(int $batchId): array {
    if (!$this->schemaVerified) throw new \DomainException('Canonical payment batch schema not verified');
    if ($batchId < 1) throw new \DomainException('Invalid payment batch');
    if ($this->pdo->inTransaction()) {
      throw new \DomainException('Canonical batch lookup requires independent transaction boundary');
    }
    // The read snapshot keeps the header and instructions consistent.
    $this->pdo->beginTransaction();
    try {
      $header = $this->pdo->prepare(
        'SELECT * FROM brebo_finance_payment_batch WHERE id = :id'
      );
      $header->execute(['id'=>$batchId]);
      $batch = $header->fetch(\PDO::FETCH_ASSOC);
      if (!is_array($batch) || ($batch['status'] ?? '') !== 'released') {
        throw new \DomainException('Released canonical payment batch unavailable');
      }
      $details = $this->pdo->prepare(
        'SELECT * FROM brebo_finance_payment_batch_item WHERE batch_id = :id ORDER BY position, id'
      );
      $details->execute(['id'=>$batchId]);
      $items = $details->fetchAll(\PDO::FETCH_ASSOC);
      if ($items === [] || (int)($batch['item_count'] ?? -1) !== count($items)) {
        throw new \DomainException('Canonical payment batch item count mismatch');
      }
      if (($batch['currency'] ?? null) !== 'EUR'
        || !in_array(($batch['controller_verdict'] ?? null), ['green', 'orange'], true)
        || (int)($batch['created_by'] ?? 0) < 1
        || (int)($batch['released_by'] ?? 0) < 1) {
        throw new \DomainException('Canonical payment batch release controls failed');
      }
      $seenPositions = [];
      $seenInstructions = [];
      $totalCents = 0;
      foreach ($items as $item) {
        if (($item['status'] ?? null) !== 'released'
          || ($item['currency'] ?? null) !== 'EUR'
          || ($item['instruction_type'] ?? null) !== 'regular'
          || !preg_match('/^[0-9]+\.[0-9]{2}$/D', (string)($item['amount'] ?? ''))) {
          throw new \DomainException('Unsafe canonical payment instruction');
        }
        $position = (int)($item['position'] ?? 0);
        $identity = (string)($item['end_to_end_id'] ?? '');
        if ($position < 1 || isset($seenPositions[$position])
          || $identity === '' || isset($seenInstructions[$identity])) {
          throw new \DomainException('Duplicate canonical payment instruction');
        }
        $seenPositions[$position] = true;
        $seenInstructions[$identity] = true;
        [$euros, $cents] = explode('.', (string)$item['amount']);
        if (strlen($euros) > 12) throw new \DomainException('Payment amount exceeds safe range');
        $value = ((int)$euros * 100) + (int)$cents;
        if ($value <= 0) throw new \DomainException('Non-positive payment amount');
        $totalCents += $value;
      }
      $control = (string)($batch['control_sum'] ?? '');
      if (!preg_match('/^[0-9]+\.[0-9]{2}$/D', $control)) {
        throw new \DomainException('Invalid payment batch control sum');
      }
      [$euros, $cents] = explode('.', $control);
      if (strlen($euros) > 12 || $totalCents !== ((int)$euros * 100 + (int)$cents)) {
        throw new \DomainException('Canonical payment batch total mismatch');
      }
      $sealed = OfficePaymentCanonicalSeal::verify($batch, $items);
      $this->pdo->commit();
      return ['batch'=>$batch, 'items'=>$items, 'sealed_hash'=>$sealed];
    } catch (\Throwable $error) {
      if ($this->pdo->inTransaction()) $this->pdo->rollBack();
      throw $error;
    }
  }

  public function resolver(): OfficePaymentTrustedBatchResolver {
    return new OfficePaymentTrustedBatchResolver(
      fn(int $batchId): array => $this->load($batchId)
    );
  }
}
