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
  public function forProject(int $operatorId, int $projectId): array {
    if (!(new OfficeNativeProjectAccess($this->db))->canRead($operatorId, $projectId)) {
      throw new \DomainException('Geen toegang tot projectinzet.');
    }
    // Prefer authenticated OnSite inbox evidence. No writes, approvals or payroll bookings.
    $inboxCheck = $this->db->query("SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'office_onsite_event_inbox'");
    if ((int) $inboxCheck->fetchColumn() === 1) {
      require_once __DIR__ . '/OnSitePresenceIntervalProjector.php';
      $inboxQuery = $this->db->prepare(
        'SELECT event_id, person_id, project_id, zone_id, event_kind, occurred_at_utc
         FROM office_onsite_event_inbox
         WHERE project_id = :project_id
         ORDER BY occurred_at_utc, event_id LIMIT 10000'
      );
      $inboxQuery->execute([':project_id' => (string) $projectId]);
      $inboxEvents = $inboxQuery->fetchAll(PDO::FETCH_ASSOC);
      $projection = (new OnSitePresenceIntervalProjector())->project($inboxEvents);
      $sessions = [];
      foreach ($projection['intervals'] as $interval) {
        $sessions[] = [
          'person_id' => (int) $interval['person_id'],
          'project_id' => (int) $interval['project_id'],
          'in_event_id' => $interval['arrival_event_id'],
          'out_event_id' => $interval['departure_event_id'],
          'started_at' => $interval['entered_at'],
          'ended_at' => $interval['left_at'],
          'duration_seconds' => $interval['elapsed_seconds'],
          'approval_status' => 'pending',
        ];
      }
      $exceptions = [];
      foreach ($projection['exceptions'] as $exception) {
        $exceptions[] = $exception + ['project_id' => $projectId, 'person_id' => 0];
      }
      foreach ($projection['active'] as $active) {
        $exceptions[] = [
          'reason' => 'open_session',
          'event_id' => $active['arrival_event_id'],
          'person_id' => (int) $active['person_id'],
          'project_id' => (int) $active['project_id'],
        ];
      }
      return ['available' => true, 'sessions' => $sessions, 'exceptions' => $exceptions];
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
