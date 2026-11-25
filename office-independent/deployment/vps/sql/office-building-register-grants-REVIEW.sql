-- REVIEW ONLY: verify operator and building PK types before applying on Office-test.
-- No existing project memberships are modified.
CREATE TABLE office_building_register_grants (
    building_id BIGINT UNSIGNED NOT NULL,
    operator_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (building_id, operator_id),
    KEY idx_building_register_operator (operator_id),
    CONSTRAINT fk_building_register_building FOREIGN KEY (building_id) REFERENCES office_buildings(id),
    CONSTRAINT fk_building_register_operator FOREIGN KEY (operator_id) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
