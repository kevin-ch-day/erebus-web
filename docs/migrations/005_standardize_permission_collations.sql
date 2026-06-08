-- Standardize permission-related collations to avoid join mismatches.
-- Scope: permission_string joins + evidence bucket/classification labels.

ALTER TABLE android_permission_dict_unknown
    MODIFY permission_string VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE android_permission_dict_queue
    MODIFY permission_string VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE android_permission_enrich_vt_event
    MODIFY permission_string VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE android_permission_obs_sample
    MODIFY permission_string VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY classification ENUM('AOSP','GOOGLE','OEM','APP_DEFINED','UNKNOWN')
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UNKNOWN',
    MODIFY bucket VARCHAR(32)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

ALTER TABLE android_permission_triage_audit
    MODIFY permission_string VARCHAR(255)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
