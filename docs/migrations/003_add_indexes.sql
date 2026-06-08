-- Performance indexes for common filters and joins.

CREATE INDEX idx_unknown_permission_string ON android_permission_dict_unknown (permission_string);
CREATE INDEX idx_unknown_triage_status ON android_permission_dict_unknown (triage_status);

CREATE INDEX idx_enrich_permission_string ON android_permission_enrich_vt_event (permission_string);
CREATE INDEX idx_enrich_ingested_at ON android_permission_enrich_vt_event (ingested_at_utc);
CREATE INDEX idx_enrich_sample_id ON android_permission_enrich_vt_event (sample_id);

CREATE INDEX idx_obs_permission_string ON android_permission_obs_sample (permission_string);
CREATE INDEX idx_obs_observed_at ON android_permission_obs_sample (observed_at_utc);
CREATE INDEX idx_obs_classification ON android_permission_obs_sample (classification);
CREATE INDEX idx_obs_bucket ON android_permission_obs_sample (bucket);
