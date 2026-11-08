<?php
declare(strict_types=1);
namespace Brebo\Office\Project\Workspace;
final class Schema {
  public static function statements(bool $sqlite=false):array{
    $id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';$tail=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return array_map(static fn($s)=>'CREATE TABLE '.$s.$tail,[
      'office_project_control_settings (project_id BIGINT NOT NULL PRIMARY KEY, planned_start VARCHAR(10) NULL, planned_end VARCHAR(10) NULL, actual_start VARCHAR(10) NULL, actual_end VARCHAR(10) NULL, scope_note TEXT NOT NULL, revision INTEGER NOT NULL, operator_id VARCHAR(128) NOT NULL, updated_at VARCHAR(32) NOT NULL)',
      "office_project_control_milestones (id $id, project_id BIGINT NOT NULL, title VARCHAR(200) NOT NULL, planned_date VARCHAR(10) NOT NULL, actual_date VARCHAR(10) NULL, target_units INTEGER NULL, revision INTEGER NOT NULL DEFAULT 1, archived INTEGER NOT NULL DEFAULT 0, command_key VARCHAR(64) NOT NULL, operator_id VARCHAR(128) NOT NULL, updated_at VARCHAR(32) NOT NULL, UNIQUE(project_id,command_key))",
      "office_project_control_progress (id $id, project_id BIGINT NOT NULL, measured_date VARCHAR(10) NOT NULL, basis VARCHAR(16) NOT NULL, completed_units INTEGER NULL, total_units INTEGER NULL, percent_units INTEGER NOT NULL, evidence_reference VARCHAR(255) NOT NULL, command_key VARCHAR(64) NOT NULL, operator_id VARCHAR(128) NOT NULL, created_at VARCHAR(32) NOT NULL, UNIQUE(project_id,command_key))",
      'office_project_control_forecasts (project_id BIGINT NOT NULL PRIMARY KEY, direct_cents BIGINT NOT NULL, labor_cents BIGINT NOT NULL, overhead_cents BIGINT NOT NULL, evidence_reference VARCHAR(255) NOT NULL, as_of VARCHAR(10) NOT NULL, revision INTEGER NOT NULL, operator_id VARCHAR(128) NOT NULL, updated_at VARCHAR(32) NOT NULL)',
      "office_project_control_audit (id $id, project_id BIGINT NOT NULL, operator_id VARCHAR(128) NOT NULL, event VARCHAR(64) NOT NULL, entity_id BIGINT NULL, detail_json TEXT NOT NULL, created_at VARCHAR(32) NOT NULL)",
      "office_project_tasks (id $id, project_id BIGINT NOT NULL, title VARCHAR(200) NOT NULL, description TEXT NOT NULL, starts_on VARCHAR(10) NOT NULL, due_date VARCHAR(10) NOT NULL, baseline_start VARCHAR(10) NOT NULL, baseline_end VARCHAR(10) NOT NULL, status VARCHAR(16) NOT NULL, assignee_person_id BIGINT NULL, estimated_minutes INTEGER NOT NULL DEFAULT 0, progress_percent INTEGER NOT NULL DEFAULT 0, reason TEXT NOT NULL, completed_on VARCHAR(10) NULL, revision INTEGER NOT NULL, command_key VARCHAR(64) NOT NULL, operator_id VARCHAR(128) NOT NULL, updated_at VARCHAR(32) NOT NULL, UNIQUE(project_id,command_key))",
      'office_project_task_dependencies (project_id BIGINT NOT NULL, task_id BIGINT NOT NULL, predecessor_id BIGINT NOT NULL, PRIMARY KEY(project_id,task_id,predecessor_id))',
    ]);
  }
}
