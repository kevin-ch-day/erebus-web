<?php
// app/database/queries/samples_queries.php
declare(strict_types=1);

function sql_samples_list_base(): string
{
    return "
        SELECT
            c.sample_id,
            c.sha256,
            RIGHT(c.sha256, 8) AS sha8,
            c.sample_label,
            c.family_label,
            sig.popular_threat_name,
            sig.popular_threat_label,
            c.platform,
            c.classification_primary,
            c.classification_subtype,
            CASE
                WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                     AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                    THEN 'unlabeled'
                WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                     AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                    THEN 'catalog_only'
                WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                     AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NOT NULL
                    THEN 'signal_only'
                WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) = LOWER(TRIM(COALESCE(sig.popular_threat_name, '')))
                    THEN 'aligned'
                ELSE 'mismatch'
            END AS family_alignment_status,
            s.vt_status_code,
            s.reason_code,
            s.next_eligible_at_utc,
            s.attempt_count,
            s.last_attempt_at_utc,
            s.claim_token,
            s.claimed_at_utc,
            s.claimed_by_host,
            s.claimed_by_user,
            s.claimed_by_pid,
            s.last_http_status,
            s.last_error_category,
            s.last_error_message,
            s.last_run_id,
            s.last_key_id,
            ss.vt_malicious_count AS malicious,
            ss.vt_suspicious_count AS suspicious,
            ss.vt_undetected_count AS undetected,
            ss.vt_harmless_count AS harmless
        FROM malware_sample_catalog c
        LEFT JOIN virustotal_sample_signal_current sig ON sig.sample_id = c.sample_id
        LEFT JOIN virustotal_sample_state s ON s.sha256 = c.sha256
        LEFT JOIN virustotal_sample_scan_summary ss ON ss.sample_id = c.sample_id
    ";
}

function sql_samples_count_base(): string
{
    return "
        SELECT COUNT(*) AS total_count
        FROM malware_sample_catalog c
        LEFT JOIN virustotal_sample_signal_current sig ON sig.sample_id = c.sample_id
        LEFT JOIN virustotal_sample_state s ON s.sha256 = c.sha256
    ";
}

function sql_sample_detail_by_id(): string
{
    return "
        SELECT
            c.sample_id,
            c.sha256,
            h.md5,
            h.sha1,
            h.vhash,
            h.ssdeep,
            h.tlsh,
            h.permhash,
            c.sample_label,
            c.family_label,
            c.classification_primary,
            c.classification_subtype,
            c.platform,
            c.artifact_type,
            c.file_extension,
            c.mime_type,
            c.file_size_bytes,
            c.vt_suggested_label,
            c.vt_first_seen_itw_date,
            c.vt_first_submission_at_utc,
            c.vt_gui_url,
            sig.popular_threat_label,
            sig.popular_threat_category,
            sig.popular_threat_name,
            sig.parse_version AS vt_signal_parse_version,
            v.vt_last_analysis_date AS vt_last_analysis_at_utc,
            v.updated_at AS vt_summary_updated_at_utc,
            v.vt_times_submitted,
            v.vt_unique_sources,
            c.android_package_name,
            c.android_launcher_activity,
            c.android_min_sdk,
            c.android_target_sdk,
            c.android_receiver_count,
            c.android_activity_count,
            c.android_service_count,
            c.android_provider_count,
            c.android_library_count,
            c.android_permission_count,
            c.record_created_at_utc AS catalog_created_at_utc,
            c.record_updated_at_utc AS catalog_updated_at_utc,
            s.vt_status_code,
            s.reason_code,
            s.next_eligible_at_utc,
            s.attempt_count,
            s.last_attempt_at_utc,
            s.claim_token,
            s.claimed_at_utc,
            s.claimed_by_host,
            s.claimed_by_user,
            s.claimed_by_ip,
            s.claimed_by_pid,
            s.last_http_status,
            s.last_error_category,
            s.last_error_message,
            s.last_run_id,
            s.last_key_id,
            s.record_created_at_utc AS state_created_at_utc,
            s.record_updated_at_utc AS state_updated_at_utc
        FROM malware_sample_catalog c
        LEFT JOIN malware_artifact_hash_registry h ON h.sha256 = c.sha256
        LEFT JOIN virustotal_sample_scan_summary v ON v.sample_id = c.sample_id
        LEFT JOIN virustotal_sample_signal_current sig ON sig.sample_id = c.sample_id
        LEFT JOIN virustotal_sample_state s ON s.sha256 = c.sha256
        WHERE c.sample_id = :sample_id
        LIMIT 1
    ";
}

function sql_sample_detail_by_sha(): string
{
    return "
        SELECT
            c.sample_id,
            c.sha256,
            h.md5,
            h.sha1,
            h.vhash,
            h.ssdeep,
            h.tlsh,
            h.permhash,
            c.sample_label,
            c.family_label,
            c.classification_primary,
            c.classification_subtype,
            c.platform,
            c.artifact_type,
            c.file_extension,
            c.mime_type,
            c.file_size_bytes,
            c.vt_suggested_label,
            c.vt_first_seen_itw_date,
            c.vt_first_submission_at_utc,
            c.vt_gui_url,
            sig.popular_threat_label,
            sig.popular_threat_category,
            sig.popular_threat_name,
            sig.parse_version AS vt_signal_parse_version,
            v.vt_last_analysis_date AS vt_last_analysis_at_utc,
            v.updated_at AS vt_summary_updated_at_utc,
            v.vt_times_submitted,
            v.vt_unique_sources,
            c.android_package_name,
            c.android_launcher_activity,
            c.android_min_sdk,
            c.android_target_sdk,
            c.android_receiver_count,
            c.android_activity_count,
            c.android_service_count,
            c.android_provider_count,
            c.android_library_count,
            c.android_permission_count,
            c.record_created_at_utc AS catalog_created_at_utc,
            c.record_updated_at_utc AS catalog_updated_at_utc,
            s.vt_status_code,
            s.reason_code,
            s.next_eligible_at_utc,
            s.attempt_count,
            s.last_attempt_at_utc,
            s.claim_token,
            s.claimed_at_utc,
            s.claimed_by_host,
            s.claimed_by_user,
            s.claimed_by_ip,
            s.claimed_by_pid,
            s.last_http_status,
            s.last_error_category,
            s.last_error_message,
            s.last_run_id,
            s.last_key_id,
            s.record_created_at_utc AS state_created_at_utc,
            s.record_updated_at_utc AS state_updated_at_utc
        FROM malware_sample_catalog c
        LEFT JOIN malware_artifact_hash_registry h ON h.sha256 = c.sha256
        LEFT JOIN virustotal_sample_scan_summary v ON v.sample_id = c.sample_id
        LEFT JOIN virustotal_sample_signal_current sig ON sig.sample_id = c.sample_id
        LEFT JOIN virustotal_sample_state s ON s.sha256 = c.sha256
        WHERE c.sha256 = :sha256
        LIMIT 1
    ";
}

function sql_sample_metadata_update(): string
{
    return "
        UPDATE malware_sample_catalog
        SET
            sample_label = :sample_label,
            family_label = :family_label,
            classification_primary = :classification_primary,
            classification_subtype = :classification_subtype,
            record_updated_at_utc = UTC_TIMESTAMP()
        WHERE sample_id = :sample_id
    ";
}
