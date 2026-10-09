<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\IntakeDecisionRepositoryInterface;
use Drupal\brebo_data_intake\Contract\IntakeDestinationInterface;
use Drupal\brebo_data_intake\Contract\IntakeLockInterface;
use RuntimeException;

/** Applies audited human decisions to source-neutral intake records. */
final class IntakeDecisionManager {

  /** @param iterable<IntakeDestinationInterface> $destinations */
  public function __construct(
    private readonly IntakeDecisionRepositoryInterface $repository,
    private readonly IntakeLockInterface $lock,
    private readonly iterable $destinations,
  ) {}

  /** @return array<string, mixed>|null */
  public function snapshot(int $recordId): ?array {
    $row = $this->repository->record($recordId);
    if ($row === NULL) {
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
   * @param array<string, mixed> $canonical
   */
  public function correct(int $recordId, string $expectedRevision, string $classification, array $canonical, int $actorUid, string $note = ''): array {
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
        return ['state' => 'unchanged', 'record_id' => $recordId, 'revision' => $expectedRevision];
      }
      $action = $classificationChanged && $canonicalChanged ? 'reclassify_relink' : ($classificationChanged ? 'reclassify' : 'relink');
      $this->repository->transactional(function () use ($recordId, $current, $encoded, $action, $actorUid, $classification, $canonical, $note): void {
        if (!$this->repository->updateReviewPayload($recordId, $current['payload'], $encoded)) {
          throw new RuntimeException('Dit intake-item is inmiddels door iemand anders gewijzigd. Vernieuw de pagina.');
        }
        $this->repository->audit($recordId, $action, 'review_required', 'review_required', $actorUid, $classification, $canonical, $note);
      });
      return ['state' => 'review_required', 'record_id' => $recordId, 'revision' => $this->revision('review_required', $encoded), 'action' => $action];
    });
  }

  /** Records a reviewed product proposal without authorizing public pricing. */
  public function recordProductProposal(int $recordId, string $expectedRevision, string $system, int $actorUid, string $note = ''): array {
    return $this->withRecordLock($recordId, function () use ($recordId, $expectedRevision, $system, $actorUid, $note): array {
      $current = $this->loadCurrent($recordId, $expectedRevision);
      $stored = $this->decodePayload($current['payload']);
      $envelope = is_array($stored['envelope'] ?? NULL) ? $stored['envelope'] : [];
      if (($envelope['classification'] ?? NULL) !== 'website_project_request') {
        throw new RuntimeException('Productkeuze is alleen beschikbaar voor websiteprojectaanvragen.');
      }
      if (!in_array($system, ['ideal4000', 'ideal7000_nl'], TRUE)) {
        throw new RuntimeException('Ongeldige profielcode.');
      }
      if ($actorUid <= 0) {
        throw new RuntimeException('Een aangemelde beoordelaar is verplicht.');
      }
      $envelope['product_review'] = [
        'system' => $system,
        'status' => 'proposed',
        'actor_uid' => $actorUid,
        'recorded_at' => time(),
      ];
      $stored['envelope'] = $envelope;
      $encoded = json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $canonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];
      $this->repository->transactional(function () use ($recordId, $current, $encoded, $actorUid, $canonical, $note): void {
        if (!$this->repository->updateReviewPayload($recordId, $current['payload'], $encoded)) {
          throw new RuntimeException('Dit intake-item is inmiddels gewijzigd. Vernieuw de pagina.');
        }
        $this->repository->audit($recordId, 'product_proposal', 'review_required', 'review_required', $actorUid, 'website_project_request', $canonical, $note);
      });
      return ['state' => 'review_required', 'record_id' => $recordId, 'revision' => $this->revision('review_required', $encoded)];
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
        if ($destination->supports($classification)) {
          $destinationResult = $destination->route($envelope);
          break;
        }
      }
      if ($destinationResult === NULL) {
        throw new RuntimeException('Er is nog geen destination-contract voor deze classificatie.');
      }
      if (!$destinationResult->isTerminal()) {
        throw new RuntimeException('De vakmodule heeft het item niet geaccepteerd: ' . $destinationResult->reason . '.');
      }
      $canonical = is_array($envelope['canonical'] ?? NULL) ? $envelope['canonical'] : [];
      $this->repository->transactional(function () use ($recordId, $current, $actorUid, $classification, $canonical, $note): void {
        if (!$this->repository->transitionReviewStatus($recordId, $current['payload'], 'accepted')) {
          throw new RuntimeException('Dit intake-item is inmiddels door iemand anders beoordeeld.');
        }
        $this->repository->audit($recordId, 'accept', 'review_required', 'accepted', $actorUid, $classification, $canonical, $note);
      });
      return ['state' => 'accepted', 'record_id' => $recordId, 'destination' => $destinationResult->toArray()];
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
      $this->repository->transactional(function () use ($recordId, $current, $actorUid, $classification, $canonical, $note): void {
        if (!$this->repository->transitionReviewStatus($recordId, $current['payload'], 'rejected')) {
          throw new RuntimeException('Dit intake-item is inmiddels door iemand anders beoordeeld.');
        }
        $this->repository->audit($recordId, 'reject', 'review_required', 'rejected', $actorUid, $classification, $canonical, $note);
      });
      return ['state' => 'rejected', 'record_id' => $recordId];
    });
  }

  /** @return array{payload:string,status:string} */
  private function loadCurrent(int $recordId, string $expectedRevision): array {
    $row = $this->repository->record($recordId);
    if ($row === NULL) {
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

  private function revision(string $status, string $payload): string {
    return hash('sha256', $status . "\0" . $payload);
  }

}
