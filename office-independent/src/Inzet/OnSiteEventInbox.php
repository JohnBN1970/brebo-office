<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
use InvalidArgumentException;

final class OnSiteEventInbox {
  public function __construct(private PDO $db) {
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function migrate(): void {
    $this->db->exec("CREATE TABLE IF NOT EXISTS onsite_event_inbox (
      event_id TEXT PRIMARY KEY, employee_id TEXT NOT NULL,
      project_id TEXT NOT NULL, zone_id TEXT NOT NULL,
      kind TEXT NOT NULL, occurred_at TEXT NOT NULL,
      received_at TEXT NOT NULL)");
    $this->db->exec("CREATE INDEX IF NOT EXISTS onsite_event_employee_time
      ON onsite_event_inbox(employee_id,occurred_at)");
  }

  /** Identity and project-zone authorization must be verified by the API caller. */
  public function receive(array $event): bool {
    foreach (['event_id','employee_id','project_id','zone_id','kind','occurred_at'] as $key) {
      if (!isset($event[$key]) || !is_string($event[$key]) || trim($event[$key]) === '') {
        throw new InvalidArgumentException('Missing event field');
      }
    }
    if (!in_array($event['kind'], ['in','out'], true)) {
      throw new InvalidArgumentException('Invalid event kind');
    }
    try { new \DateTimeImmutable($event['occurred_at']); }
    catch (\Throwable) { throw new InvalidArgumentException('Invalid timestamp'); }
    $q = $this->db->prepare("INSERT OR IGNORE INTO onsite_event_inbox
      (event_id,employee_id,project_id,zone_id,kind,occurred_at,received_at)
      VALUES (?,?,?,?,?,?,?)");
    $q->execute([$event['event_id'],$event['employee_id'],$event['project_id'],
      $event['zone_id'],$event['kind'],$event['occurred_at'],gmdate('c')]);
    if ($q->rowCount() === 1) return true;
    $q = $this->db->prepare("SELECT employee_id,project_id,zone_id,kind,occurred_at
      FROM onsite_event_inbox WHERE event_id=?");
    $q->execute([$event['event_id']]);
    $saved = $q->fetch(PDO::FETCH_ASSOC);
    foreach (['employee_id','project_id','zone_id','kind','occurred_at'] as $key) {
      if (($saved[$key] ?? null) !== $event[$key]) {
        throw new InvalidArgumentException('Conflicting event ID');
      }
    }
    return false;
  }

  public function eventsForEmployee(string $employeeId): array {
    $q = $this->db->prepare("SELECT event_id,employee_id,project_id,zone_id,kind,occurred_at
      FROM onsite_event_inbox WHERE employee_id=? ORDER BY occurred_at,event_id");
    $q->execute([$employeeId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
  }
}
