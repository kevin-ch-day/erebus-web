<?php
// app/database/queries/health_queries.php
declare(strict_types=1);

function sql_utc_now(): string
{
    return "SELECT UTC_TIMESTAMP() AS utc_now";
}

function sql_system_control(): string
{
    return "
        SELECT
            control_id,
            hold_until_utc,
            hold_reason_code,
            last_429_at_utc,
            last_network_error_at_utc,
            record_updated_at_utc
        FROM virustotal_system_control
        WHERE control_id = 1
    ";
}

function sql_status_breakdown(): string
{
    return "
        SELECT vt_status_code, COUNT(*) AS count
        FROM virustotal_sample_state
        GROUP BY vt_status_code
        ORDER BY count DESC
    ";
}

function sql_reason_breakdown(int $limit = 20): string
{
    $limit = max(1, min(200, $limit));
    return "
        SELECT reason_code, COUNT(*) AS count
        FROM virustotal_sample_state
        WHERE reason_code IS NOT NULL
        GROUP BY reason_code
        ORDER BY count DESC
        LIMIT {$limit}
    ";
}

function sql_count_eligible_now(): string
{
    return "
        SELECT COUNT(*) AS eligible_now
        FROM virustotal_sample_state
        WHERE claim_token IS NULL
          AND (
            vt_status_code IN ('NEW','QUEUED')
            OR (
                vt_status_code IN ('REANALYZE','RETRY_WAIT')
                AND (next_eligible_at_utc IS NULL OR next_eligible_at_utc <= UTC_TIMESTAMP())
            )
          )
          AND vt_status_code <> 'QUARANTINED'
    ";
}

function sql_count_processing_now(): string
{
    return "
        SELECT COUNT(*) AS processing_now
        FROM virustotal_sample_state
        WHERE vt_status_code = 'PROCESSING'
          AND claim_token IS NOT NULL
    ";
}

function sql_count_error(): string
{
    return "
        SELECT COUNT(*) AS error_count
        FROM virustotal_sample_state
        WHERE vt_status_code = 'ERROR'
    ";
}

function sql_count_retry_wait(): string
{
    return "
        SELECT COUNT(*) AS retry_wait_count
        FROM virustotal_sample_state
        WHERE vt_status_code = 'RETRY_WAIT'
    ";
}

function sql_count_stale_claims(): string
{
    // stale minutes will be bound as a parameter in services
    return "
        SELECT COUNT(*) AS stale_claims
        FROM virustotal_sample_state
        WHERE claim_token IS NOT NULL
          AND claimed_at_utc < DATE_SUB(UTC_TIMESTAMP(), INTERVAL :mins MINUTE)
    ";
}
