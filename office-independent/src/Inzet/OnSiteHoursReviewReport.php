<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only reporting over projected OnSite sessions and stored reviews.
 * Caller supplies only projects already authorized for the current operator.
 */
final class OnSiteHoursReviewReport {
  public function __construct(private readonly PDO $db) {}

  private static function validDate(string $value): bool {
    $parts = explode('-', $value);
    return count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
  }

  /**
   * @param list<array<string,mixed>> $sessions Authorized OnSite sessions from the project overview.
   * @param array{project_id?:int,person_id?:int,status?:string,from?:string,to?:string,reviewer?:string} $filters
   * @return array{rows:list<array<string,mixed>>,total_original_seconds:int,total_reviewed_seconds:int}
   */
  public function filter(array $sessions, array $filters = []): array {
    $status = (string)($filters['status'] ?? '');
    if ($status !== '' && !in_array($status, ['pending','approved','rejected'], true)) {
      throw new \InvalidArgumentException('Ongeldig statusfilter.');
    }
    foreach (['from','to'] as $dateKey) {
      if (isset($filters[$dateKey]) && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)$filters[$dateKey]) || !self::validDate((string)$filters[$dateKey]))) {
        throw new \InvalidArgumentException('Ongeldig datumfilter.');
      }
    }
    if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) throw new \InvalidArgumentException('Begindatum ligt na einddatum.');
    $keys = [];
    foreach ($sessions as $session) {
      if (!isset($session['in_event_id'], $session['out_event_id'])) continue;
      $keys[(string)$session['in_event_id'].'|'.(string)$session['out_event_id']] = true;
    }
    if ($keys === []) return ['rows'=>[], 'total_original_seconds'=>0, 'total_reviewed_seconds'=>0];
    // Reviews are retrieved only for event IDs already visible in the authorized input.
    $reviewByKey = [];
    $inIds = array_values(array_unique(array_map(static fn(array $s):string => (string)$s['in_event_id'], array_filter(
      $sessions, static fn(array $s):bool => isset($s['in_event_id'], $s['out_event_id'])
    ))));
    foreach (array_chunk($inIds, 250) as $chunk) {
      $stmt = $this->db->prepare(
        'SELECT person_id, project_id, in_event_id, out_event_id, approval_status, reviewed_seconds,
                reviewer_operator_id, reviewed_at, reason
         FROM office_onsite_hours_reviews WHERE in_event_id IN (' .
        implode(',', array_fill(0, count($chunk), '?')) . ')'
      );
      $stmt->execute($chunk);
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
        $reviewByKey[(string)$review['in_event_id'].'|'.(string)$review['out_event_id']] = $review;
      }
    }
    $rows = [];
    $originalTotal = 0;
    $reviewedTotal = 0;
    foreach ($sessions as $session) {
      $projectId = (int)($session['project_id'] ?? 0);
      $personId = (int)($session['person_id'] ?? 0);
      if (isset($filters['project_id']) && $projectId !== (int)$filters['project_id']) continue;
      if (isset($filters['person_id']) && $personId !== (int)$filters['person_id']) continue;
      $day = substr((string)($session['started_at'] ?? ''), 0, 10);
      if (isset($filters['from']) && $day < $filters['from']) continue;
      if (isset($filters['to']) && $day > $filters['to']) continue;
      $key = (string)($session['in_event_id'] ?? '').'|'.(string)($session['out_event_id'] ?? '');
      $review = $reviewByKey[$key] ?? null;
      // Guard against cross-project/person review records with matching event IDs.
      if ($review !== null && ((int)$review['person_id'] !== $personId
        || (string)$review['project_id'] !== (string)$projectId)) $review = null;
      $rowStatus = $review === null ? 'pending' : (string)$review['approval_status'];
      if ($status !== '' && $rowStatus !== $status) continue;
      if (isset($filters['reviewer']) && (string)($review['reviewer_operator_id'] ?? '') !== $filters['reviewer']) continue;
      $original = (int)($session['duration_seconds'] ?? 0);
      $accepted = $rowStatus === 'approved' ? (int)$review['reviewed_seconds'] : 0;
      $rows[] = $session + [
        'review_status'=>$rowStatus, 'original_seconds'=>$original,
        'accepted_seconds'=>$accepted, 'difference_seconds'=>$rowStatus === 'approved' ? $accepted-$original : null,
        'reviewer_operator_id'=>$review['reviewer_operator_id'] ?? null,
        'reviewed_at'=>$review['reviewed_at'] ?? null,
        'review_reason'=>$review['reason'] ?? null,
      ];
      $originalTotal += $original;
      $reviewedTotal += $accepted;
    }
    return ['rows'=>$rows, 'total_original_seconds'=>$originalTotal,
      'total_reviewed_seconds'=>$reviewedTotal];
  }
}
