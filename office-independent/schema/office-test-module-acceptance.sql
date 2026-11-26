-- Office-test only. Apply only after verifying SELECT DATABASE() = brebo_office_test.
CREATE TABLE IF NOT EXISTS office_test_module_acceptance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_key VARCHAR(100) NOT NULL,
    test_key VARCHAR(100) NOT NULL,
    release_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('pending','passed','failed','retest') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    operator_id BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_acceptance_latest (module_key,test_key,release_sha,id),
    KEY ix_acceptance_release (release_sha,module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
