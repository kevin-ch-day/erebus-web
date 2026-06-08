(() => {
  if (!window.App || !window.SampleDetail) return;
  const SampleDetail = window.SampleDetail;
  const fmt = App.fmt;

  SampleDetail.createEditor = (options) => {
    const {
      modalEl,
      closeBtn,
      saveBtn,
      metaEl,
      idEl,
      shaEl,
      packageEl,
      labelEl,
      familyEl,
      primaryEl,
      subtypeEl,
      statusEl,
      updateEndpoint,
      onSaved,
    } = options;

    let currentSample = null;

    function open(sample) {
      if (!modalEl || !sample) return;
      currentSample = sample;
      if (metaEl) metaEl.textContent = 'Update label and classification values.';
      if (idEl) idEl.textContent = fmt(sample.sample_id, '--');
      if (shaEl) shaEl.textContent = fmt(sample.sha256, '--');
      if (packageEl) packageEl.textContent = fmt(sample.android_package_name, '--');
      if (labelEl) labelEl.value = fmt(sample.sample_label, '');
      if (familyEl) familyEl.value = fmt(sample.family_label, '');
      if (primaryEl) primaryEl.value = fmt(sample.classification_primary, '');
      if (subtypeEl) subtypeEl.value = fmt(sample.classification_subtype, '');
      if (statusEl) statusEl.textContent = '';
      modalEl.style.display = 'flex';
    }

    function close() {
      if (modalEl) modalEl.style.display = 'none';
    }

    async function save() {
      if (!updateEndpoint || !currentSample) return;
      if (statusEl) statusEl.textContent = 'Saving...';
      if (saveBtn) saveBtn.disabled = true;
      const params = new URLSearchParams({
        sample_id: fmt(currentSample.sample_id, ''),
        sample_label: labelEl ? labelEl.value.trim() : '',
        family_label: familyEl ? familyEl.value.trim() : '',
        classification_primary: primaryEl ? primaryEl.value.trim() : '',
        classification_subtype: subtypeEl ? subtypeEl.value.trim() : '',
      });
      try {
        const res = await App.postForm(updateEndpoint, Object.fromEntries(params.entries()));
        if (!res.ok) {
          const msg = res.error || 'Save failed.';
          if (statusEl) statusEl.textContent = `${msg} (HTTP ${res.status || 0})`;
          return;
        }
        if (statusEl) statusEl.textContent = 'Saved.';
        close();
        if (typeof onSaved === 'function') {
          onSaved();
        }
      } catch (e) {
        if (statusEl) statusEl.textContent = e && e.message ? e.message : 'Save failed.';
      } finally {
        if (saveBtn) saveBtn.disabled = false;
      }
    }

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (modalEl) {
      modalEl.addEventListener('click', (event) => {
        if (event.target === modalEl) {
          close();
        }
      });
    }
    if (saveBtn) saveBtn.addEventListener('click', save);

    return { open, close };
  };
})();
