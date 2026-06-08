(function() {
  "use strict";

  const issueLabelMap = {
    generic_signal: "generic_policy_hold",
    signal_overlap: "signal_overlap",
    alias_candidate: "alias_candidate",
    alias_resolved: "alias_resolved",
    semantic_conflict: "semantic_conflict",
    placeholder_catalog: "placeholder_catalog",
    short_signal_token: "short_signal_token",
    generic_signal_token: "generic_policy_hold"
  };

  const fixActionLabelMap = {
    canonicalize_catalog_alias: "canonicalize_alias",
    canonicalize_to_signal_family: "use_signal_family",
    hold_generic_signal: "hold_generic_signal",
    hold_signal_overlap: "hold_signal_overlap",
    keep_catalog_use_alias_map: "keep_catalog_alias_map"
  };

  const decisionModeLabelMap = {
    repair_after_alias_review: "alias_review",
    ask_why_first: "why_first",
    hold_generic_signal: "policy_hold",
    hold_signal_overlap: "overlap_hold",
    keep_as_is: "keep_as_is",
    monitor_only: "monitor_only"
  };

  const mismatchLabelMap = {
    resolved_catalog_truth_vs_noisy_signal: "noisy_signal_mismatch",
    unresolved_governance_gap: "unresolved_authority",
    true_semantic_conflict: "semantic_conflict",
    generic_signal_token: "generic_policy_hold",
    projection_without_persisted_fact: "projection_materialization_debt"
  };

  function labelFromMap(map, value, fallback = "--") {
    const key = String(value || "").trim();
    return map[key] || key || fallback;
  }

  window.FamilyTaxonomyLabels = {
    issue(value) {
      return labelFromMap(issueLabelMap, value);
    },
    fixAction(value) {
      return labelFromMap(fixActionLabelMap, value);
    },
    decisionMode(value) {
      return labelFromMap(decisionModeLabelMap, value);
    },
    mismatch(value) {
      return labelFromMap(mismatchLabelMap, value);
    }
  };
})();
