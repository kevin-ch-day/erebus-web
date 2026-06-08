-- Align permission queue schema with current app expectations.

-- Allow flexible status values (queued/applied/error/etc.).
ALTER TABLE android_permission_dict_queue
    MODIFY queue_action VARCHAR(32) NOT NULL DEFAULT 'defer',
    MODIFY status VARCHAR(32) NOT NULL DEFAULT 'queued';

-- Ensure queued_at_utc exists (used by update logic).
ALTER TABLE android_permission_dict_queue
    ADD COLUMN IF NOT EXISTS queued_at_utc DATETIME NOT NULL DEFAULT UTC_TIMESTAMP() AFTER error_message;

-- Backfill queued_at_utc from existing timestamps when missing.
UPDATE android_permission_dict_queue
SET queued_at_utc = COALESCE(queued_at_utc, created_at_utc, updated_at_utc, UTC_TIMESTAMP())
WHERE queued_at_utc IS NULL OR queued_at_utc = '0000-00-00 00:00:00';
