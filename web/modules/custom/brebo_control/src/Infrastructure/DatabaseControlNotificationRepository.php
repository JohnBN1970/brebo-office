<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\ControlNotificationRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseControlNotificationRepository implements ControlNotificationRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function existsByDedupKey(string $dedupKey): bool {
    if (!$this->database->schema()->tableExists('brebo_control_notification')) {
      return FALSE;
    }
    return (int) $this->database->select('brebo_control_notification', 'n')
      ->condition('dedup_key', $dedupKey)
      ->countQuery()->execute()->fetchField() > 0;
  }

  public function create(array $fields): int {
    return (int) $this->database->insert('brebo_control_notification')
      ->fields($fields)
      ->execute();
  }

}
