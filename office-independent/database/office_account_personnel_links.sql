-- Office-test only. Review parent column types and back up before applying.
-- No automatic identity matching, account creation or permission grants.
CREATE TABLE IF NOT EXISTS office_account_personnel_links (
  operator_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  linked_by_operator_id BIGINT UNSIGNED NOT NULL,
  linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account_personnel_person (person_id),
  CONSTRAINT fk_account_personnel_operator FOREIGN KEY (operator_id) REFERENCES office_operator_accounts(id),
  CONSTRAINT fk_account_personnel_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
  CONSTRAINT fk_account_personnel_actor FOREIGN KEY (linked_by_operator_id) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
