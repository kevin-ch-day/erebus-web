-- Create queue table for permission dictionary updates.

CREATE TABLE IF NOT EXISTS android_permission_dict_queue (
    queue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_string VARCHAR(255) NOT NULL,
    queue_action VARCHAR(32) NOT NULL,
    proposed_bucket VARCHAR(64) NULL,
    proposed_classification VARCHAR(64) NULL,
    triage_status VARCHAR(64) NULL,
    notes TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    requested_by VARCHAR(128) NULL,
    updated_by VARCHAR(128) NULL,
    processed_by VARCHAR(128) NULL,
    source_system VARCHAR(64) NULL,
    error_message TEXT NULL,
    queued_at_utc DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    updated_at_utc DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    processed_at_utc DATETIME NULL,
    PRIMARY KEY (queue_id),
    UNIQUE KEY uq_perm_queue_permission (permission_string),
    KEY idx_perm_queue_status (status),
    KEY idx_perm_queue_updated (updated_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
