<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only Office-native MySQL approved session lookup.
 * No schema creation, Drupal dependencies or approval side effects.
 * An explicit verified schema gate is required before use.
 */
final class OnSiteApprovedSessionRepository {
  public function __construct(private PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function forEmployee(string $verifiedPersonId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $verifiedPersonId)) {
      throw new \InvalidArgumentException('Verified employee required');
    }
    $stmt = $this->db->prepare(
      "SELECT person_id, in_event_id, out_event_id, approval_status,
              DATE_FORMAT(approved_at_utc, '%Y-%m-%dT%H:%i:%sZ') AS approved_at
         FROM office_onsite_reviewed_sessions
        WHERE person_id = ? AND approval_status = 'approved'
          AND approved_at_utc IS NOT NULL
          AND in_event_id IS NOT NULL AND out_event_id IS NOT NULL"
    );
    $stmt->execute([$verifiedPersonId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
