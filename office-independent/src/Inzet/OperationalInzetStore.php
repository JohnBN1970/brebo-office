<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Private SQLite store for operational planning and submitted time entries.
 * The caller must enforce Office authentication and project-level permissions.
 */
final class OperationalInzetStore {
  public function __construct(private PDO $db) {
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function migrate(): void {
    $this->db->exec('PRAGMA foreign_keys = ON');
    $this->db->exec('CREATE TABLE IF NOT EXISTS inzet_planning (
      id TEXT PRIMARY KEY, project_id TEXT NOT NULL, person_id TEXT NOT NULL,
      work_date TEXT NOT NULL, planned_minutes INTEGER NOT NULL CHECK(planned_minutes BETWEEN 0 AND 1440),
      revision INTEGER NOT NULL DEFAULT 1, updated_at TEXT NOT NULL
    )');
    $this->db->exec('CREATE TABLE IF NOT EXISTS inzet_registration (
      id TEXT PRIMARY KEY, planning_id TEXT NOT NULL REFERENCES inzet_planning(id),
      registered_minutes INTEGER NOT NULL CHECK(registered_minutes BETWEEN 0 AND 1440),
      work_description TEXT NOT NULL, state TEXT NOT NULL DEFAULT \'submitted\'
        CHECK(state IN (\'submitted\',\'needs_correction\',\'approved\',\'rejected\')),
      revision INTEGER NOT NULL DEFAULT 1, updated_at TEXT NOT NULL
    )');
    $this->db->exec('CREATE TABLE IF NOT EXISTS inzet_operational_audit (
      id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT NOT NULL, entity_id TEXT NOT NULL,
      action TEXT NOT NULL, actor_id TEXT NOT NULL, reason TEXT NOT NULL,
      created_at TEXT NOT NULL
    )');
  }

  public function addPlanning(string $id, string $project, string $person, string $date, int $minutes, string $actor): void {
    self::nonempty($id, $project, $person, $actor);
    self::date($date);
    self::duration($minutes);
    $this->db->beginTransaction();
    try {
      $q = $this->db->prepare('INSERT INTO inzet_planning(id,project_id,person_id,work_date,planned_minutes,updated_at) VALUES(?,?,?,?,?,?)');
      $q->execute([$id,$project,$person,$date,$minutes,gmdate('c')]);
      $this->audit('planning',$id,'created',$actor,'');
      $this->db->commit();
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
  }

  public function submit(string $id, string $planningId, int $minutes, string $description, string $actor): void {
    self::nonempty($id, $planningId, $description, $actor);
    self::duration($minutes);
    $this->db->beginTransaction();
    try {
      $q = $this->db->prepare('INSERT INTO inzet_registration(id,planning_id,registered_minutes,work_description,updated_at) VALUES(?,?,?,?,?)');
      $q->execute([$id,$planningId,$minutes,$description,gmdate('c')]);
      $this->audit('registration',$id,'submitted',$actor,'');
      $this->db->commit();
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
  }

  public function decide(string $id, int $expectedRevision, string $state, string $actor, string $reason): void {
    if (!in_array($state, ['approved','rejected','needs_correction'], true)) throw new RuntimeException('Invalid decision');
    self::nonempty($id, $actor, $reason);
    $this->db->beginTransaction();
    try {
      $q = $this->db->prepare('UPDATE inzet_registration SET state=?, revision=revision+1, updated_at=? WHERE id=? AND revision=? AND state=\'submitted\'');
      $q->execute([$state,gmdate('c'),$id,$expectedRevision]);
      if ($q->rowCount() !== 1) throw new RuntimeException('Stale or invalid registration state');
      $this->audit('registration',$id,$state,$actor,$reason);
      $this->db->commit();
    } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
  }

  private function audit(string $type, string $id, string $action, string $actor, string $reason): void {
    $q = $this->db->prepare('INSERT INTO inzet_operational_audit(entity_type,entity_id,action,actor_id,reason,created_at) VALUES(?,?,?,?,?,?)');
    $q->execute([$type,$id,$action,$actor,$reason,gmdate('c')]);
  }

  private static function nonempty(string ...$values): void {
    foreach ($values as $value) if (trim($value) === '') throw new RuntimeException('Required field missing');
  }

  private static function duration(int $minutes): void {
    if ($minutes < 0 || $minutes > 1440) throw new RuntimeException('Invalid minutes');
  }

  private static function date(string $date): void {
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new RuntimeException('Invalid work date');
  }
}
