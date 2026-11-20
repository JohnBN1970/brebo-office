<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Database\Connection;

/**
 * Persists references, never message copies, for module communication blocks.
 *
 * Consumers MUST authorize the communication and destination before display.
 */
final class MailCommunicationContextLinks {

  public function __construct(private readonly Connection $database) {}

  public function propose(int $communicationId, string $module, string $objectType, int $objectId, string $basis = ''): void {
    if ($communicationId <= 0 || $objectId <= 0 || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $module)
      || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $objectType)) {
      throw new \InvalidArgumentException('Invalid communication context reference.');
    }
    $table = 'brebo_mail_communication_context_link';
    $existing = $this->database->select($table, 'l')
      ->fields('l', ['id'])
      ->condition('communication_id', $communicationId)
      ->condition('module', $module)
      ->condition('object_type', $objectType)
      ->condition('object_id', $objectId)
      ->range(0, 1)->execute()->fetchField();
    if ($existing !== FALSE) {
      // Never downgrade a confirmed link or overwrite its audit evidence.
      return;
    }
    $now = time();
    $this->database->insert($table)->fields([
      'communication_id' => $communicationId,
      'module' => $module,
      'object_type' => $objectType,
      'object_id' => $objectId,
      'state' => 'proposed',
      'basis' => mb_substr($basis, 0, 512),
      'confirmed_by' => 0,
      'created' => $now,
      'changed' => $now,
    ])->execute();
  }

  /**
   * Confirmation must only be called after permission checks in the caller.
   */
  public function confirm(int $linkId, int $actorUid): bool {
    if ($linkId <= 0 || $actorUid <= 0) {
      throw new \InvalidArgumentException('Link and authenticated actor required.');
    }
    return $this->database->update('brebo_mail_communication_context_link')
      ->fields(['state' => 'confirmed', 'confirmed_by' => $actorUid, 'changed' => time()])
      ->condition('id', $linkId)
      ->condition('state', 'proposed')
      ->execute() === 1;
  }

  /**
   * Read-only candidates; authorization must be applied before rendering.
   *
   * @return array<int, array<string, mixed>>
   */
  public function confirmedForObject(string $module, string $objectType, int $objectId, int $limit = 50): array {
    if ($objectId <= 0 || $limit < 1 || $limit > 100
      || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $module)
      || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $objectType)) {
      throw new \InvalidArgumentException('Invalid destination or limit.');
    }
    return $this->database->select('brebo_mail_communication_context_link', 'l')
      ->fields('l', ['id', 'communication_id', 'module', 'object_type', 'object_id', 'confirmed_by', 'changed'])
      ->condition('module', $module)
      ->condition('object_type', $objectType)
      ->condition('object_id', $objectId)
      ->condition('state', 'confirmed')
      ->orderBy('changed', 'DESC')
      ->range(0, $limit)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
