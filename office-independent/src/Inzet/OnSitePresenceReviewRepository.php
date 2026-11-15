<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use InvalidArgumentException;

/**
 * Read-only evidence for a project manager. No raw coordinates and no approved hours.
 * The project membership is checked inside the SQL query, not in the controller.
 */
final class OnSitePresenceReviewRepository {
  public function __construct(private PDO $db) {}

  /** Full project history through the requested week's end; fail closed on oversized history. */
  public function forManagerWeek(string $operatorId, string $projectId, string $endUtc, int $limit=10000): array {
    if ($operatorId==='' || strlen($operatorId)>128
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || $limit<1 || $limit>10000
      || !preg_match('/^20[0-9]{2}-[01][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/D',$endUtc)) {
      throw new InvalidArgumentException('invalid_presence_week_context');
    }
    $stmt=$this->db->prepare(
      "SELECT e.event_id,e.person_id,e.project_id,e.zone_id,e.event_kind,e.occurred_at_utc
       FROM office_onsite_event_inbox e
       WHERE e.project_id=? AND e.occurred_at_utc<?
         AND EXISTS (
           SELECT 1 FROM office_operator_accounts a
           JOIN office_project_memberships m ON m.operator_id=a.id
           WHERE a.id=? AND a.active=1 AND m.project_id=? AND m.permission='manage'
         )
       ORDER BY e.occurred_at_utc DESC,e.event_id DESC LIMIT ".($limit+1)
    );
    $stmt->execute([$projectId,$endUtc,$operatorId,$projectId]);
    $events=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($events)>$limit) throw new \RuntimeException('presence_week_history_incomplete');
    return (new OnSitePresenceIntervalProjector())->project($events);
  }

  public function forManager(string $operatorId, string $projectId, int $limit=250): array {
    // Authenticated Office account IDs are opaque varchar(128), just as in the session and map gates.
    if ($operatorId === '' || strlen($operatorId)>128
      || !preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || $limit<1 || $limit>500) {
      throw new InvalidArgumentException('invalid_presence_review_context');
    }
    $stmt=$this->db->prepare(
      "SELECT e.event_id,e.person_id,e.project_id,e.zone_id,e.event_kind,e.occurred_at_utc
       FROM office_onsite_event_inbox e
       WHERE e.project_id=?
         AND EXISTS (
           SELECT 1 FROM office_operator_accounts a
           INNER JOIN office_project_memberships m ON m.operator_id=a.id
           WHERE a.id=? AND a.active=1 AND m.project_id=?
             AND m.permission='manage'
         )
       ORDER BY e.occurred_at_utc DESC,e.event_id DESC LIMIT ".($limit+1)
    );
    $stmt->execute([$projectId,$operatorId,$projectId]);
    $events=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($events)>$limit) throw new \RuntimeException('presence_review_window_incomplete');
    return (new OnSitePresenceIntervalProjector())->project($events);
  }
}
