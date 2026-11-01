<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Project\OfficeNativeProjectAccess;
use PDO;

/**
 * Read-only project hours overview. Uses raw OnSite events as evidence,
 * projects sessions in memory and never approves or books hours.
 */
final class OfficeInzetHoursOverview {
  public function __construct(private readonly PDO $db) {}

  /** @return array{available:bool,sessions:list<array<string,mixed>>,exceptions:list<array<string,mixed>>} */
  public function forProject(string $operatorId, int $projectId): array {
    if (!(new OfficeNativeProjectAccess($this->db))->canRead($operatorId, $projectId)) {
      throw new \DomainException('Geen toegang tot projectinzet.');
    }
    // Missing tables are an unconfigured module, not an empty timesheet.
    $check = $this->db->query("SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'office_inzet_clock_events'");
    if ((int)$check->fetchColumn() !== 1) {
      return ['available'=>false,'sessions'=>[],'exceptions'=>[]];
    }
    $query = $this->db->prepare(
      'SELECT event_id, person_id, project_id, action, occurred_at
       FROM office_inzet_clock_events
       WHERE person_id IN (
         SELECT DISTINCT person_id FROM office_inzet_clock_events WHERE project_id = :project_id
       )
       ORDER BY occurred_at, event_id LIMIT 10000'
    );
    $query->execute([':project_id'=>$projectId]);
    $events = $query->fetchAll(PDO::FETCH_ASSOC);
    // A project-only event stream cannot reliably pair sessions when a worker
    // changes projects. Keep these projections explicitly provisional.
    $projected = (new OnSitePresenceProjector())->project($events);
    // The projector needs cross-project events to pair transitions correctly.
    // Never return another project's sessions or exceptions to this viewer.
    $scoped = static function (array $rows) use ($projectId): array {
      return array_values(array_filter($rows, static function (array $row) use ($projectId): bool {
        return isset($row['project_id']) && (int)$row['project_id'] === $projectId;
      }));
    };
    return ['available'=>true,'sessions'=>$scoped($projected['sessions']),'exceptions'=>$scoped($projected['exceptions'])];
  }
}
