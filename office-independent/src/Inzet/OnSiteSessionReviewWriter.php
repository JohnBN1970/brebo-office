<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Office-native transactional review write. Never creates schema or derives
 * approval from geofence events. Caller must authorize reviewer and employee.
 */
final class OnSiteSessionReviewWriter {
  public function __construct(private PDO $db) {}

  public function record(
    string $verifiedPersonId,
    string $inEventId,
    string $outEventId,
    string $decision,
    int $reviewedSeconds,
    int $reviewerId,
    ?string $reason = null
  ): void {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $verifiedPersonId)
      || !preg_match('/^[a-fA-F0-9-]{36}$/D', $inEventId)
      || !preg_match('/^[a-fA-F0-9-]{36}$/D', $outEventId)
      || $inEventId === $outEventId || $reviewerId < 1
      || !in_array($decision, ['approved', 'rejected'], true)
      || $reviewedSeconds < 0 || $reviewedSeconds > 86400) {
      throw new \InvalidArgumentException('Invalid review request');
    }
    $reason = trim((string)$reason);
    if (strlen($reason) > 2000 || ($decision === 'rejected' && $reason === '')) {
      throw new \InvalidArgumentException('Invalid review reason');
    }

    $this->db->beginTransaction();
    try {
      // Lock original evidence to prevent a review of mismatched identities.
      $query = $this->db->prepare(
        'SELECT event_id, person_id, project_id, zone_id, event_kind, occurred_at_utc
         FROM office_onsite_event_inbox WHERE event_id IN (?, ?) FOR UPDATE'
      );
      $query->execute([$inEventId, $outEventId]);
      $events = [];
      foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $events[(string)$row['event_id']] = $row;
      $start = $events[$inEventId] ?? null;
      $end = $events[$outEventId] ?? null;
      if (!$start || !$end
        || (string)$start['person_id'] !== $verifiedPersonId
        || (string)$end['person_id'] !== $verifiedPersonId
        || (string)$start['project_id'] !== (string)$end['project_id']
        || (string)$start['zone_id'] !== (string)$end['zone_id']
        || strtolower((string)$start['event_kind']) !== 'in'
        || strtolower((string)$end['event_kind']) !== 'out') {
        throw new RuntimeException('Session evidence does not match verified employee');
      }
      $started = strtotime((string)$start['occurred_at_utc']);
      $ended = strtotime((string)$end['occurred_at_utc']);
      if ($started === false || $ended === false || $ended <= $started || $ended - $started > 86400) {
        throw new RuntimeException('Invalid completed session');
      }
      $existing = $this->db->prepare(
        'SELECT id FROM office_onsite_reviewed_sessions
         WHERE in_event_id IN (?, ?) OR out_event_id IN (?, ?) FOR UPDATE'
      );
      $existing->execute([$inEventId, $outEventId, $inEventId, $outEventId]);
      if ($existing->fetchColumn() !== false) {
        throw new RuntimeException('Session already reviewed');
      }
      $insert = $this->db->prepare(
        'INSERT INTO office_onsite_reviewed_sessions
         (person_id, in_event_id, out_event_id, approval_status, reviewed_seconds,
          approved_at_utc, reviewed_by, review_reason)
         VALUES (?, ?, ?, ?, ?, IF(? = \'approved\', UTC_TIMESTAMP(), NULL), ?, ?)'
      );
      $insert->execute([
        $verifiedPersonId, $inEventId, $outEventId, $decision,
        $reviewedSeconds, $decision, $reviewerId, $reason === '' ? null : $reason,
      ]);
      $this->db->commit();
    } catch (\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }
}
