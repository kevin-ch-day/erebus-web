(function() {
  "use strict";
  function readSelectedOption(select) {
    if (!select) return null;
    return select.options[select.selectedIndex] || null;
  }
  function readSelectedOptionText(select) {
    const option = readSelectedOption(select);
    return option ? String(option.textContent || "").trim() : "";
  }
  function readSelectedOptionTz(select) {
    const option = readSelectedOption(select);
    return option ? String(option.dataset.tz || "") : "";
  }
  function readSelectedOptionKey(select) {
    return String(select?.value || "").trim();
  }
  function syncOperatorClockDatasets(primaryKey, primaryTz, secondaryKey, secondaryTz) {
    document.documentElement.dataset.displayTz = primaryTz || "UTC";
    document.documentElement.dataset.displayTzKey = primaryKey || "";
    document.documentElement.dataset.secondaryTz = secondaryKey === "none" ? "" : secondaryTz;
    document.documentElement.dataset.secondaryTzKey = secondaryKey === "none" ? "" : secondaryKey;
    window.dispatchEvent(new Event("topbar-tz-change"));
  }
  const primarySelect = document.getElementById("tz");
  const secondarySelect = document.getElementById("tz_secondary");
  const primaryPreview = document.getElementById("time-primary-preview");
  const secondaryPreview = document.getElementById("time-secondary-preview");
  const usDefaultButtons = Array.from(document.querySelectorAll("[data-primary-tz-key]"));
  if (primarySelect && secondarySelect) {
    let syncPageState = function() {
      const primaryLabel = readSelectedOptionText(primaryClockSelect);
      const primaryTz = readSelectedOptionTz(primaryClockSelect);
      const secondaryLabel = readSelectedOptionText(secondaryClockSelect);
      const secondaryTz = readSelectedOptionTz(secondaryClockSelect);
      const secondaryKey = readSelectedOptionKey(secondaryClockSelect);
      const primaryKey = readSelectedOptionKey(primaryClockSelect);
      syncOperatorClockDatasets(primaryKey, primaryTz, secondaryKey, secondaryTz);
      if (primaryPreview) {
        primaryPreview.textContent = `Primary clock: ${primaryLabel || "--"}`;
      }
      if (secondaryPreview) {
        secondaryPreview.textContent = secondaryKey === "none" ? "Second clock: disabled" : `Second clock: ${secondaryLabel || "--"}`;
      }
    }, ensureDistinctSecondary = function() {
      if (readSelectedOptionKey(primaryClockSelect) === readSelectedOptionKey(secondaryClockSelect)) {
        secondaryClockSelect.value = "none";
      }
    };
    const primaryClockSelect = primarySelect;
    const secondaryClockSelect = secondarySelect;
    primaryClockSelect.addEventListener("change", () => {
      ensureDistinctSecondary();
      syncPageState();
    });
    secondaryClockSelect.addEventListener("change", () => {
      ensureDistinctSecondary();
      syncPageState();
    });
    usDefaultButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const nextKey = String(button.dataset.primaryTzKey || "").trim();
        if (!nextKey) return;
        primaryClockSelect.value = nextKey;
        ensureDistinctSecondary();
        syncPageState();
      });
    });
    syncPageState();
  }
})();
