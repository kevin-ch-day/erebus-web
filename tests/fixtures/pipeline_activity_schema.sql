CREATE TABLE schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(191) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO schema_migrations (version)
VALUES ('erebus-web-ci-fixture-v1');

CREATE TABLE virustotal_run_ledger (
    run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    started_at_utc DATETIME(6) NULL,
    finished_at_utc DATETIME(6) NULL,
    db_name VARCHAR(191) NULL,
    key_id VARCHAR(191) NULL,
    processed_count INT UNSIGNED NOT NULL DEFAULT 0,
    ok_count INT UNSIGNED NOT NULL DEFAULT 0,
    no_data_count INT UNSIGNED NOT NULL DEFAULT 0,
    retry_wait_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_count INT UNSIGNED NOT NULL DEFAULT 0,
    stopped_reason VARCHAR(191) NULL,
    tool_version VARCHAR(191) NULL,
    schema_version VARCHAR(191) NULL,
    perm_taxonomy_version VARCHAR(191) NULL,
    PRIMARY KEY (run_id)
) ENGINE=InnoDB;

INSERT INTO virustotal_run_ledger (
    started_at_utc,
    finished_at_utc,
    db_name,
    key_id,
    processed_count,
    ok_count,
    no_data_count,
    retry_wait_count,
    error_count,
    stopped_reason,
    tool_version,
    schema_version,
    perm_taxonomy_version
) VALUES (
    UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE,
    UTC_TIMESTAMP(6),
    'erebus_web_ci',
    'fixture-key',
    1,
    1,
    0,
    0,
    0,
    'complete',
    'ci-fixture',
    'erebus-web-ci-fixture-v1',
    'permission-ci-fixture-v1'
);
