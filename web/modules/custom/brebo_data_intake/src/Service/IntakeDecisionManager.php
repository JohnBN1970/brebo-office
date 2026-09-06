<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeDestinationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use RuntimeException;

/** Applies audited human decisions to source-neutral intake records. */
final class IntakeDecisionManager {

  /** @param iterable<IntakeDestinationInterface> $destinations */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly LockBackendInterface $lock,
    private readonly iterable $destinations,
  ) {}

  /** @return array<string,mixed>|null */
  public function snapshot(int $recordId): ?array {
    $row = $this->database->select('brebo_data_record', 'record')
      ->fields('record', ['id', 'payload', 'status', 'created'])
      ->condition('record.id', $recordId)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      return NULL;
    }

    $payload = $this->decodePayload((string) $row['payload']);
    return [
      'id' => (int) $row['id'],
      'status' => (string) $row['status'],
      'payload' => $payload,
      'revision' => $this->revision((string) $row['status'], (string) $row['payload']),
      'created' => (int) $row['created'],
    ];
  }

  /**
   * Stores human corrections while keeping the item in the review queue.
   *
   * @param array<string,mixed> $canonical
   */
  public function correct(
    int $recordId,
    string $expectedRevision,
    string $classification,
    array $canonical,
    int $actorUid,
    string $note = '',
  ): array {
    return $this->withRecordLock($recordId, function () use ($recordId, $expectedRevision, $classification, $canonical, $actorUid, $note): array {
      $current = $this->loadCurrent($recordId, $expectedRevision);
      $stored = $this->decodePayload($current['payload']);
      $envelope = is_array($stored['envelope'] ?? NULL) ? $stored['envelope'] : [];

      $previousClassification = trim((string) ($envelope['classification'] ?? ''));
      $previousCanonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];
      $classification = strtolower(trim($classification));
      if ($classification === '') {
        throw new RuntimeException('Classificatie mag niet leeg zijn.');
      }

      $canonical = $this->normalizeCanonical($canonical);
      $envelope['classification'] = $classification;
      $envelope['canonical'] = $canonical;
      $stored['envelope'] = $envelope;
      $encoded = json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

      $classificationChanged = $previousClassification !== $classification;
      $canonicalChanged = $previousCanonical != $canonical;
      if (!$classificationChanged && !$canonicalChanged) {
        return [
          'state' => 'unchanged',
          'record_id' => $recordId,
          'revision' => $expectedRevision,
        ];
      }

      $action = $classificationChanged && $canonicalChanged
        ? 'reclassify_relink'
        : ($classificationChanged ? 'reclassify' : 'relink');

      $transaction = $this->database->startTransaction();
      try {
        $updated = $this->database->update('brebo_data_record')
          ->fields(['payload' => $encoded])
          ->condition('id', $recordId)
          ->condition('status', 'review_required')
          ->condition('payload', $current['payload'])
          ->execute();
        if ($updated !== 1) {
          throw new RuntimeException('Dit intake-item is inmiddels door iemand anders gewijzigd. Vernieuw de pagina.');
        }
        $this->audit($recordId, $action, 'review_required', 'review_required', $actorUid, $classification, $canonical, $note);
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        throw $e;
      }

      return [
        'state' => 'review_required',
        'record_id' => $recordId,
        'revision' => $this->revision('review_required', $encoded),
        'action' => $action,
      ];
    });
  }

  /** Routes a reviewed item through the owning destination contract. */
  public function accept(int $recordId, string $expectedRevision, int $actorUid, string $note = ''): array {
    return $this->withRecordLock($recordId, function () use ($recordId, $expectedRevision, $actorUid, $note): array {
      $current = $this->loadCurrent($recordId, $expectedRevision);
      $stored = $this->decodePayload($current['payload']);
      $envelope = is_array($stored['envelope'] ?? NULL) ? $stored['envelope'] : [];
      $classification = strtolower(trim((string) ($envelope['classification'] ?? '')));
      if ($classification === '') {
        throw new RuntimeException('Dit intake-item heeft nog geen geldige classificatie.');
      }

      $destinationResult = NULL;
      foreach ($this->destinations as $destination) {
        if (!$destination->supports($classification)) {
          continue;
        }
        $destinationResult = $destination->route($envelope);
        break;
      }
      if ($destinationResult === NULL) {
        throw new RuntimeException('Er is nog geen destination-contract voor deze classificatie.');
      }

      $destinationState = (string) ($destinationResult['state'] ?? 'review_required');
      if (!in_array($destinationState, ['created', 'duplicate', 'routed', 'accepted', 'processed'], TRUE)) {
        $reason = trim((string) ($destinationResult['reason'] ?? $destinationState));
        throw new RuntimeException('De vakmodule heeft het item niet geaccepteerd: ' . $reason . '.');
      }

      $canonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];
      $transaction = $this->database->startTransaction();
      try {
        $updated = $this->database->update('brebo_data_record')
          ->fields(['status' => 'accepted'])
          ->condition('id', $recordId)
          ->condition('status', 'review_required')
          ->condition('payload', $current['payload'])
          ->execute();
        if ($updated !== 1) {
          throw new RuntimeException('Dit intake-item is inmiddels door iemand anders beoordeeld.');
        }
        $this->audit($recordId, 'accept', 'review_required', 'accepted', $actorUid, $classification, $canonical, $note);
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        throw $e;
      }

      return [
        'state' => 'accepted',
        'record_id' => $recordId,
        'destination' => $destinationResult,
      ];
    });
  }

  /** Rejects an intake item without invoking any destination. */
  public function reject(int $recordId, string $expectedRevision, int $actorUid, string $note): array {
    return $this->withRecordLock($recordId, function () use ($recordId, $expectedRevision, $actorUid, $note): array {
      $note = trim($note);
      if ($note === '') {
        throw new RuntimeException('Geef bij afwijzen kort de reden op.');
      }

      $current = $this->loadCurrent($recordId, $expectedRevision);
      $stored = $this->decodePayload($current['payload']);
      $envelope = is_array($stored['envelope'] ?? NULL) ? $stored['envelope'] : [];
      $classification = strtolower(trim((string) ($envelope['classification'] ?? '')));
      $canonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];

      $transaction = $this->database->startTransaction();
      try {
        $updated = $this->database->update('brebo_data_record')
          ->fields(['status' => 'rejected'])
          ->condition('id', $recordId)
          ->condition('status', 'review_required')
          ->condition('payload', $current['payload'])
          ->execute();
        if ($updated !== 1) {
          throw new RuntimeException('Dit intake-item is inmiddels door iemand anders beoordeeld.');
        }
        $this->audit($recordId, 'reject', 'review_required', 'rejected', $actorUid, $classification, $canonical, $note);
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        throw $e;
      }

      return ['state' => 'rejected', 'record_id' => $recordId];
    });
  }

  /** @return array{payload:string,status:string} */
  private function loadCurrent(int $recordId, string $expectedRevision): array {
    $row = $this->database->select('brebo_data_record', 'record')
      ->fields('record', ['payload', 'status'])
      ->condition('record.id', $recordId)
      ->execute()
      ->fetchAssoc();
    if (!$row) {
      throw new RuntimeException('Intake-item bestaat niet meer.');
    }
    $status = (string) $row['status'];
    $payload = (string) $row['payload'];
    if ($status !== 'review_required') {
      throw new RuntimeException('Dit intake-item staat niet meer open voor beoordeling.');
    }
    if (!hash_equals($this->revision($status, $payload), $expectedRevision)) {
      throw new RuntimeException('Dit intake-item is inmiddels gewijzigd. Vernieuw de pagina.');
    }
    return ['payload' => $payload, 'status' => $status];
  }

  /** @param callable():array<string,mixed> $callback */
  private function withRecordLock(int $recordId, callable $callback): array {
    $name = 'brebo_data_intake:decision:' . $recordId;
    if (!$this->lock->acquire($name, 30.0)) {
      throw new RuntimeException('Dit intake-item wordt op dit moment door iemand anders beoordeeld.');
    }
    try {
      return $callback();
    }
    finally {
      $this->lock->release($name);
    }
  }

  /** @return array<string,mixed> */
  private function decodePayload(string $payload): array {
    try {
      $decoded = json_decode($payload, TRUE, 512, JSON_THROW_ON_ERROR);
      return is_array($decoded) ? $decoded : [];
    }
    catch (\JsonException) {
      throw new RuntimeException('Het opgeslagen intake-item bevat ongeldige JSON.');
    }
  }

  /** @param array<string,mixed> $canonical */
  private function normalizeCanonical(array $canonical): array {
    $normalized = [];
    foreach (['relationship_id', 'project_nid', 'building_nid', 'supplier_ref', 'contact_id'] as $key) {
      if (!array_key_exists($key, $canonical)) {
        continue;
      }
      $value = is_string($canonical[$key]) ? trim($canonical[$key]) : $canonical[$key];
      if ($value === '' || $value === NULL || $value === 0 || $value === '0') {
        continue;
      }
      $normalized[$key] = $value;
    }
    return $normalized;
  }

  /** @param array<string,mixed> $canonical */
  private function audit(
    int $recordId,
    string $action,
    string $previousStatus,
    string $newStatus,
    int $actorUid,
    string $classification,
    array $canonical,
    string $note,
  ): void {
    $this->database->insert('brebo_data_intake_decision')
      ->fields([
        'record_id' => $recordId,
        'action' => $action,
        'previous_status' => $previousStatus,
        'new_status' => $newStatus,
        'classification' => $classification,
        'canonical' => json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'note' => trim($note) !== '' ? trim($note) : NULL,
        'actor_uid' => max(0, $actorUid),
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  private function revision(string $status, string $payload): string {
    return hash('sha256', $status . "\0" . $payload);
  }

}
