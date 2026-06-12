(function() {
  "use strict";
  const App = window.App;
  const SampleDetail = window.SampleDetail || (window.SampleDetail = {});
  if (App) {
    SampleDetail.fmtUtc = (value) => {
      return value ? App.formatUtc(value) : "--";
    };
    SampleDetail.formatBytes = (value) => {
      const raw = Number(value);
      if (!Number.isFinite(raw) || raw <= 0) return "--";
      const mb = raw / (1024 * 1024);
      return `${mb.toFixed(2)} MB (${raw} bytes)`;
    };
    SampleDetail.titleCase = (value) => {
      const raw = String(value || "").trim();
      if (!raw) return "--";
      return raw.split(/\s+/).map((part) => {
        return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
      }).join(" ");
    };
    SampleDetail.hasDisplayValue = (value) => {
      if (value === null || value === void 0) return false;
      if (typeof value === "number") return Number.isFinite(value);
      if (typeof value === "boolean") return true;
      const raw = String(value).trim();
      return raw !== "" && raw !== "--" && raw.toLowerCase() !== "null" && raw.toLowerCase() !== "undefined";
    };
  }
})();
