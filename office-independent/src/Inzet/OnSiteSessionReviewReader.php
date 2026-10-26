<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Office-native read-only session workbench. Evidence is not approved time.
 * Pairing is deliberately conservative: unmatched, duplicate or overlapping
 * events are excluded from automatic approval.
 */
final class OnSiteSessionReviewReader {
  public function __construct(private PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function forEmployee(string $verifiedPersonId): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $verifiedPersonId)) {
      throw new \InvalidArgumentException('Invalid employee');
    }
    $stmt = $this->db->prepare(
      'SELECT event_id, person_id, project_id, zone_id, event_kind, occurred_at_utc
       FROM office_onsite_event_inbox WHERE person_id = ?
       ORDER BY occurred_at_utc ASC, event_id ASC'
    );
    $stmt->execute([$verifiedPersonId]);
    $pending = [];
    $sessions = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
      $key = (string)$event['project_id'] . ':' . (string)$event['zone_id'];
      $kind = (string)$event['event_kind'];
      if ($kind === 'in') {
        // Two IN events without OUT make the stream ambiguous.
        $pending[$key] = isset($pending[$key]) ? false : $event;
        continue;
      }
      if ($kind !== 'out' || !isset($pending[$key])) continue;
      $start = $pending[$key];
      unset($pending[$key]);
      if (!is_array($start)) continue;
      $begin = strtotime((string)$start['occurred_at_utc']);
      $end = strtotime((string)$event['occurred_at_utc']);
      if ($begin === false || $end === false || $end <= $begin || $end - $begin > 86400) continue;
      $sessions[] = [
        'person_id'=>$verifiedPersonId,
        'project_id'=>(string)$event['project_id'],
        'zone_id'=>(string)$event['zone_id'],
        'in_event_id'=>(string)$start['event_id'],
        'out_event_id'=>(string)$event['event_id'],
        'started_at'=>(string)$start['occurred_at_utc'],
        'ended_at'=>(string)$event['occurred_at_utc'],
        'duration_seconds'=>$end - $begin,
        'approval_status'=>'pending',
      ];
    }
    return $sessions;
  }
}
