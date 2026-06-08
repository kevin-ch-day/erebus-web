<?php
// app/database/queries/runs_queries.php
declare(strict_types=1);

function sql_run_ledger_by_id(): string
{
    return "
        SELECT
            run_id,
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
        FROM virustotal_run_ledger
        WHERE run_id = :run_id
        LIMIT 1
    ";
}

function sql_run_ledger_list_base(): string
{
    return "
        SELECT
            run_id,
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
        FROM virustotal_run_ledger
    ";
}

function sql_run_ledger_count_base(): string
{
    return "
        SELECT COUNT(*) AS total_count
        FROM virustotal_run_ledger
    ";
}

function sql_perm_taxonomy_latest(): string
{
    return "
        SELECT
            perm_taxonomy_version,
            finished_at_utc
        FROM virustotal_run_ledger
        WHERE perm_taxonomy_version IS NOT NULL
          AND finished_at_utc IS NOT NULL
          AND ok_count > 0
        ORDER BY finished_at_utc DESC
        LIMIT 1
    ";
}
