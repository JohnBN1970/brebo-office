-- PREPARATION ONLY: install via reviewed, guarded Office-test migration.
-- Existing Office projects, users, documents, tasks, procurement and personnel remain canonical.
CREATE TABLE IF NOT EXISTS office_project_start_dossiers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  scope_type VARCHAR(16) NOT NULL,
  scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  project_leader_user_id BIGINT UNSIGNED NOT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(40) NOT NULL DEFAULT 'draft',
  items_json JSON NOT NULL,
  critical_open_points INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_start_scope (project_id,scope_type,scope_id),
  KEY idx_start_status (project_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS office_project_start_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dossier_id BIGINT UNSIGNED NOT NULL,
  dossier_revision INT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  decision VARCHAR(24) NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_start_approval_revision (dossier_id,dossier_revision,id),
  CONSTRAINT fk_start_approval_dossier FOREIGN KEY (dossier_id) REFERENCES office_project_start_dossiers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Future migration: versioned document refs, Q&A, actions, meeting records and
-- immutable change-event audit; no activation until all are implemented.

CREATE TABLE IF NOT EXISTS office_project_start_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dossier_id BIGINT UNSIGNED NOT NULL,
  dossier_revision INT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  payload_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_start_event_history (dossier_id,id),
  CONSTRAINT fk_start_event_dossier FOREIGN KEY (dossier_id) REFERENCES office_project_start_dossiers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_project_start_questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dossier_id BIGINT UNSIGNED NOT NULL,
  created_revision INT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  decision_text TEXT NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  due_date DATE NULL,
  is_critical TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_kickstart_questions (dossier_id,id),
  CONSTRAINT fk_start_question_dossier FOREIGN KEY (dossier_id) REFERENCES office_project_start_dossiers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
