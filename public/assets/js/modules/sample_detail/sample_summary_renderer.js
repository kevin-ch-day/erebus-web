(function() {
  "use strict";
  const App = window.App;
  const SampleDetail = window.SampleDetail;
  if (App && SampleDetail) {
    let buildRows = function(items) {
      return items.filter((row) => row && row.keep !== false).map((row) => {
        const value = row.rendered !== void 0 ? row.rendered : esc(fmt(row.value));
        return `<div class="detail-row"><div class="detail-label">${esc(row.label)}</div><div class="detail-value${row.mono ? " mono" : ""}">${value}</div></div>`;
      }).join("");
    }, buildHashLines = function(sample) {
      const shaValue = fmt(sample.sha256);
      const hashRows = [
        { label: "MD5", value: sample.md5, copy: true },
        { label: "SHA-1", value: sample.sha1, copy: true },
        { label: "SHA-256", value: shaValue, copy: true },
        { label: "Vhash", value: sample.vhash },
        { label: "SSDEEP", value: sample.ssdeep },
        { label: "TLSH", value: sample.tlsh },
        { label: "Permhash", value: sample.permhash }
      ];
      return hashRows.filter((row) => row.copy || hasDisplayValue(row.value)).map((row) => {
        const val = fmt(row.value, "--");
        const copyBtn = row.copy && val !== "--" ? ` <button class="copy-btn" type="button" data-copy="${esc(val)}" title="Copy ${esc(row.label)}">Copy</button>` : "";
        return `<div class="detail-row"><div class="detail-label">${esc(row.label)}</div><div class="detail-value mono">${esc(val)}${copyBtn}</div></div>`;
      }).join("");
    }, buildPanel = function(title, rows, className = "") {
      const panelClass = ["sample-summary-panel", className].filter(Boolean).join(" ");
      return `
      <section class="${panelClass}">
        <div class="sample-summary-panel-title">${esc(title)}</div>
        <div class="sample-summary-panel-body">
          ${rows}
        </div>
      </section>
    `;
    };
    const esc = App.escapeHtml;
    const fmt = App.fmt;
    const fmtUtc = SampleDetail.fmtUtc || ((value) => value ? App.formatUtc(value) : "--");
    const hasDisplayValue = SampleDetail.hasDisplayValue || ((value) => {
      return value !== null && value !== void 0 && String(value).trim() !== "" && String(value).trim() !== "--";
    });
    SampleDetail.renderSummary = (summaryEl, sample, options = {}) => {
      if (!summaryEl || !sample) return;
      const vtGuiLink = sample.vt_gui_url ? `<a class="table-link" href="${esc(String(sample.vt_gui_url))}" target="_blank" rel="noopener">Open</a>` : "--";
      const editBtnHtml = options.showEditButton ? '<button class="btn btn-small" type="button" id="sample-edit-open">Edit metadata</button>' : "";
      const identityRows = buildRows([
        { label: "Sample ID", value: sample.sample_id, keep: true },
        { label: "Label", value: sample.sample_label || "Unknown", keep: true },
        { label: "Catalog created", value: fmtUtc(sample.catalog_created_at_utc), keep: hasDisplayValue(sample.catalog_created_at_utc) },
        { label: "Catalog updated", value: fmtUtc(sample.catalog_updated_at_utc), keep: hasDisplayValue(sample.catalog_updated_at_utc) },
        { label: "VT GUI", rendered: vtGuiLink, keep: vtGuiLink !== "--" }
      ]);
      summaryEl.innerHTML = `
      <div class="sample-summary-header">
        <div class="sample-summary-title">Sample Summary</div>
        ${editBtnHtml}
      </div>
      <div class="sample-summary-panels">
        ${buildPanel("Catalog record", identityRows, "sample-summary-panel-identity")}
        ${buildPanel("Hash properties", buildHashLines(sample), "sample-summary-panel-hashes")}
      </div>
    `;
      const editBtn = summaryEl.querySelector("#sample-edit-open");
      if (editBtn instanceof HTMLButtonElement && typeof options.onEdit === "function") {
        editBtn.addEventListener("click", () => options.onEdit?.(sample));
      }
    };
    SampleDetail.bindSummaryCopy = (summaryEl) => {
      if (!summaryEl) return;
      summaryEl.addEventListener("click", (event) => {
        const target = event.target;
        const button = target instanceof Element ? target.closest(".copy-btn") : null;
        if (!(button instanceof HTMLElement)) return;
        const value = button.getAttribute("data-copy") || "";
        if (!value) return;
        App.copyText(value);
      });
    };
  }
})();
