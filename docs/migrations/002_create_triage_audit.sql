-- Audit history for triage status updates.

CREATE TABLE IF NOT EXISTS android_permission_triage_audit (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_string VARCHAR(255) NOT NULL,
    new_status VARCHAR(64) NOT NULL,
    notes TEXT NULL,
    operator VARCHAR(128) NULL,
    created_at_utc DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
    PRIMARY KEY (audit_id),
    KEY idx_triage_audit_permission (permission_string),
    KEY idx_triage_audit_created (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
