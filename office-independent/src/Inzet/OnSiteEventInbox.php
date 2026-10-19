<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Durable inbox for authenticated automatic zone transitions.
 * Requires a separately reviewed migration; never creates tables at runtime.
 * The employee id must be resolved from a verified device token upstream.
 */
final class OnSiteEventInbox {
  public function __construct(private PDO $db) {}

  public function store(string $verifiedEmployeeId, string $eventId, array $event): bool {
    if (!ctype_digit($verifiedEmployeeId) || (int)$verifiedEmployeeId < 1
      || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $eventId)) {
      throw new RuntimeException('Invalid authenticated event identity');
    }
    foreach (['project_id', 'zone_id', 'kind', 'occurred_at'] as $field) {
      if (!isset($event[$field]) || !is_string($event[$field])) {
        throw new RuntimeException('Invalid validated event');
      }
    }
    $query = $this->db->prepare(
      'INSERT INTO office_onsite_event_inbox
       (event_id, person_id, project_id, zone_id, event_kind, occurred_at_utc)
       VALUES (?, ?, ?, ?, ?, ?)'
    );
    try {
      $query->execute([
        $eventId, $verifiedEmployeeId, $event['project_id'],
        $event['zone_id'], $event['kind'], $event['occurred_at'],
      ]);
      return true;
    } catch (\PDOException $error) {
      if ($error->getCode() !== '23000') throw $error;
      // A duplicate event id must never overwrite or double-count time.
      return false;
    }
  }
}
