<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Office-native review ledger. Never infers approval from geofence evidence.
 * Schema is migration-controlled; this repository never runs DDL.
 */
final class OnSiteHoursApprovalRepository {
  public function __construct(private PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function approvedSessionsForPerson(string $verifiedPersonId): array {
    if (!ctype_digit($verifiedPersonId) || (int)$verifiedPersonId < 1) {
      throw new \InvalidArgumentException('Verified employee required');
    }
    $stmt = $this->db->prepare(
      'SELECT person_id, in_event_id, out_event_id, approval_status, approved_at
       FROM office_onsite_hours_reviews
       WHERE person_id = ? AND approval_status = ? AND approved_at IS NOT NULL
       ORDER BY approved_at DESC'
    );
    $stmt->execute([$verifiedPersonId, 'approved']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      // MySQL DATETIME is UTC by schema contract.
      if (is_string($row['approved_at']) && preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D', $row['approved_at'])) {
        $row['approved_at'] = str_replace(' ', 'T', $row['approved_at']) . 'Z';
      }
    }
    unset($row);
    return $rows;
  }
}
