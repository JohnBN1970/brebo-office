<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Infrastructure;

use Drupal\brebo_data_intake\Contract\IntakeDecisionRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;

final class DatabaseIntakeDecisionRepository implements IntakeDecisionRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public function record(int $recordId): ?array {
    $row = $this->database->select('brebo_data_record', 'record')
      ->fields('record', ['id', 'payload', 'status', 'created'])
      ->condition('record.id', $recordId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateReviewPayload(int $recordId, string $currentPayload, string $newPayload): bool {
    return $this->database->update('brebo_data_record')
      ->fields(['payload' => $newPayload])
      ->condition('id', $recordId)
      ->condition('status', 'review_required')
      ->condition('payload', $currentPayload)
      ->execute() === 1;
  }

  public function transitionReviewStatus(int $recordId, string $currentPayload, string $newStatus): bool {
    return $this->database->update('brebo_data_record')
      ->fields(['status' => $newStatus])
      ->condition('id', $recordId)
      ->condition('status', 'review_required')
      ->condition('payload', $currentPayload)
      ->execute() === 1;
  }

  public function audit(int $recordId, string $action, string $previousStatus, string $newStatus, int $actorUid, string $classification, array $canonical, string $note): void {
    $this->database->insert('brebo_data_intake_decision')->fields([
      'record_id' => $recordId,
      'action' => $action,
      'previous_status' => $previousStatus,
      'new_status' => $newStatus,
      'classification' => $classification,
      'canonical' => json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'note' => trim($note) !== '' ? trim($note) : NULL,
      'actor_uid' => max(0, $actorUid),
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  public function transactional(callable $callback): mixed {
    $transaction = $this->database->startTransaction();
    try {
      return $callback();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
