-- REVIEW ONLY: no automatic grants; verify parent schema before applying.
CREATE TABLE IF NOT EXISTS office_building_element_grants (
 operator_id BIGINT UNSIGNED NOT NULL,
 building_id BIGINT UNSIGNED NOT NULL,
 opportunity_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (operator_id,building_id,opportunity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
