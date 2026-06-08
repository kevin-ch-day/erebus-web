(() => {
  if (!window.App || !window.SampleDetail) return;
  const SampleDetail = window.SampleDetail;
  const esc = App.escapeHtml;
  const fmt = App.fmt;

  function buildHashLines(sample) {
    const shaValue = fmt(sample.sha256);
    const hashRows = [
      { label: 'MD5', value: sample.md5, copy: true },
      { label: 'SHA-1', value: sample.sha1, copy: true },
      { label: 'SHA-256', value: shaValue, copy: true },
      { label: 'Vhash', value: sample.vhash },
      { label: 'SSDEEP', value: sample.ssdeep },
      { label: 'TLSH', value: sample.tlsh },
      { label: 'Permhash', value: sample.permhash },
    ];
    return hashRows.map((row) => {
      const val = fmt(row.value, '--');
      const copyBtn = row.copy && val !== '--'
        ? ` <button class="copy-btn" type="button" data-copy="${esc(val)}" title="Copy ${esc(row.label)}">Copy</button>`
        : '';
      return `<div class="detail-row"><div class="detail-label">${esc(row.label)}</div><div class="detail-value mono">${esc(val)}${copyBtn}</div></div>`;
    }).join('');
  }

  function buildClassificationRows(sample) {
    return [
      ['Primary', sample.classification_primary],
      ['Subtype', sample.classification_subtype],
      ['VT suggested label', sample.vt_suggested_label],
    ].map(([label, value]) => {
      return `<div class="detail-row"><div class="detail-label">${esc(label)}</div><div class="detail-value">${esc(fmt(value))}</div></div>`;
    }).join('');
  }

  function buildFileRows(sample) {
    return [
      ['Type', SampleDetail.titleCase(sample.platform || sample.artifact_type)],
      ['File extension', sample.file_extension],
      ['MIME type', sample.mime_type],
      ['File size', SampleDetail.formatBytes(sample.file_size_bytes)],
    ].map(([label, value]) => {
      return `<div class="detail-row"><div class="detail-label">${esc(label)}</div><div class="detail-value">${esc(fmt(value))}</div></div>`;
    }).join('');
  }

  SampleDetail.renderSummary = (summaryEl, sample, options = {}) => {
    if (!summaryEl || !sample) return;
    const vtGuiLink = sample.vt_gui_url
      ? `<a class="table-link" href="${esc(String(sample.vt_gui_url))}" target="_blank" rel="noopener">Open</a>`
      : '--';
    const editBtnHtml = options.showEditButton
      ? '<button class="btn btn-small" type="button" id="sample-edit-open">Edit metadata</button>'
      : '';
    summaryEl.innerHTML = `
      <div class="sample-summary-header">
        <div class="sample-summary-title">Sample Summary</div>
        ${editBtnHtml}
      </div>
      <div class="sample-summary-meta">
        <div class="detail-row"><div class="detail-label">Sample ID</div><div class="detail-value">${esc(fmt(sample.sample_id))}</div></div>
        <div class="detail-row"><div class="detail-label">Label</div><div class="detail-value">${esc(fmt(sample.sample_label, 'Unknown'))}</div></div>
        <div class="detail-row"><div class="detail-label">Family</div><div class="detail-value">${esc(fmt(sample.family_label))}</div></div>
        <div class="detail-card-subtitle">Sample Classification</div>
        ${buildClassificationRows(sample)}
        <div class="detail-card-subtitle">File Properties</div>
        ${buildFileRows(sample)}
        <div class="detail-card-subtitle">Hash Properties</div>
        ${buildHashLines(sample)}
        <div class="detail-row"><div class="detail-label">VT GUI</div><div class="detail-value">${vtGuiLink}</div></div>
      </div>
    `;

    const editBtn = summaryEl.querySelector('#sample-edit-open');
    if (editBtn && typeof options.onEdit === 'function') {
      editBtn.addEventListener('click', () => options.onEdit(sample));
    }
  };

  SampleDetail.bindSummaryCopy = (summaryEl) => {
    if (!summaryEl) return;
    summaryEl.addEventListener('click', (event) => {
      const target = event.target;
      const button = target && target.closest ? target.closest('.copy-btn') : null;
      if (!button) return;
      const value = button.getAttribute('data-copy') || '';
      if (!value) return;
      App.copyText(value);
    });
  };
})();
