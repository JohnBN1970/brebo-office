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

  public function forManager(string $operatorId, string $projectId, int $limit=250): array {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$operatorId)
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
       ORDER BY e.occurred_at_utc DESC,e.event_id DESC LIMIT ".(int)$limit
    );
    $stmt->execute([$projectId,$operatorId,$projectId]);
    $events=$stmt->fetchAll(PDO::FETCH_ASSOC);
    return (new OnSitePresenceIntervalProjector())->project($events);
  }
}
