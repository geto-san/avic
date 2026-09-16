/* ══════════════════════════════════════════════
   AVIC Portal — App JS (UI interactions only, no backend)
   ══════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ── Helpers ── */
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ── SEMANTIC GREETING ── */
  function setGreeting() {
    const el = $('.page-subtitle');
    if (!el || el.textContent !== '') return;
    const h = new Date().getHours();
    const greet = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
    el.textContent = `${greet}, welcome to your dashboard`;
  }

  /* ── SIDEBAR TOGGLE (mobile) ── */
  function initSidebar() {
    const btn = $('.sidebar-toggle');
    const sidebar = $('.sidebar');
    if (!btn || !sidebar) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  /* ── DROPDOWNS (notifications & user) ── */
  function initDropdowns() {
    $$('.notif-btn').forEach(btn => {
      const wrapper = btn.closest('.notif-wrapper');
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        wrapper.querySelector('.notif-dropdown').classList.toggle('hidden');
      });
    });
    $$('.user-btn').forEach(btn => {
      const wrapper = btn.closest('.user-wrapper');
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        wrapper.querySelector('.user-dropdown').classList.toggle('hidden');
      });
    });
    document.addEventListener('click', () => {
      $$('.notif-dropdown, .user-dropdown').forEach(d => d.classList.add('hidden'));
    });
  }

  /* ── CLICKABLE TABLES ── */
  function initClickableRows() {
    $$('.clickable[data-link]').forEach(row => {
      row.addEventListener('click', (e) => {
        if (e.target.closest('button, a, input, select')) return;
        const link = row.getAttribute('data-link');
        if (link) location.href = link;
      });
    });
  }

  /* ── PASSWORD TOGGLE ── */
  function initPasswordToggle() {
    $$('.toggle-password').forEach(btn => {
      btn.addEventListener('click', () => {
        const input = btn.parentElement.querySelector('input[type="password"], input[type="text"]');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
      });
    });
  }

  /* ── AUTH FORM CLIENT-SIDE VALIDATION ── */
  function initAuthForm() {
    const form = $('#login-form, #register-form, #forgot-form');
    if (!form) return;
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;
      $$('input[required]', form).forEach(input => {
        let ok = input.value.trim() !== '';
        if (ok && input.type === 'email') ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim());
        if (ok && input.name === 'password' && input.minLength) ok = input.value.length >= +input.minLength;
        if (ok && input.name === 'confirm_password') {
          const pw = form.querySelector('input[name="password"]');
          ok = pw && input.value === pw.value;
        }
        const err = input.closest('.form-group').querySelector('.field-error');
        input.classList.toggle('invalid', !ok);
        if (err) err.hidden = ok;
        if (!ok) valid = false;
      });
      if (!valid) return;

      const info = $('#reset-info');
      if (info) { info.classList.remove('hidden'); form.classList.add('hidden'); return; }

      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
      }
      /* No backend — simulate success. Replace with real POST in Phase 1. */
      setTimeout(() => {
        location.href = '/dashboard';
      }, 700);
    });
  }

  /* ── LOGIN ROLE CHIPS (demo preview) ── */
  function initRoleChips() {
    const chips = $$('.role-chip[data-role]');
    if (!chips.length) return;
    chips.forEach(chip => {
      chip.addEventListener('click', () => {
        chips.forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        const role = chip.getAttribute('data-role');
        const email = $('#email');
        const pw = $('#password');
        if (email) email.value = `${role}@avic.demo`;
        if (pw) pw.value = 'demo1234';
      });
    });
  }

  /* ── CLAIM WIZARD ── */
  function initWizard() {
    const form = $('#claim-form');
    if (!form) return;
    const steps = $$('.wizard-step');
    const numSteps = steps.length;
    let current = 1;

    const indicator = $$('.step-indicator li');
    const progressLabel = $('#step-progress-label');
    const prevBtn = $('#prev-step');
    const nextBtn = $('#next-step');
    const submitBtn = $('#submit-step');
    const autosaveStatus = $('#autosave-status');

    function renderStep(n) {
      steps.forEach((el, i) => el.classList.toggle('active', i + 1 === n));
      indicator.forEach((li, i) => {
        li.classList.toggle('done', i + 1 < n);
        li.classList.toggle('current', i + 1 === n);
      });
      window.scrollTo({ top: 0, behavior: 'smooth' });
      if (progressLabel) progressLabel.textContent = `Step ${n} of ${numSteps}`;
      prevBtn.disabled = n === 1;
      nextBtn.hidden = n === numSteps;
      if (submitBtn) submitBtn.hidden = n !== numSteps;
    }

    function stepFields() {
      return $$(`.wizard-step[data-step="${current}"] input[required], .wizard-step[data-step="${current}"] textarea[required], .wizard-step[data-step="${current}"] select[required]`);
    }

    function validateStep() {
      let valid = true;
      stepFields().forEach(f => {
        const ok = f.value.trim() !== '';
        f.classList.toggle('invalid', !ok);
        const err = f.closest('.form-group, .policy-lookup, .checkbox-label');
        const errEl = err ? err.querySelector('.field-error') : null;
        if (errEl) errEl.hidden = ok;
        if (!ok) valid = false;
      });
      const agree = $('#agree');
      if (agree && agree.closest('.wizard-step').dataset.step === String(current)) {
        const ok = agree.checked;
        agree.classList.toggle('invalid', !ok);
        const err = $('#agree-error');
        if (err) err.hidden = ok;
        if (!ok) valid = false;
      }
      return valid;
    }

    if (nextBtn) nextBtn.addEventListener('click', () => {
      if (!validateStep()) return;
      if (current < numSteps) { current++; renderStep(current); }
    });
    if (prevBtn) prevBtn.addEventListener('click', () => {
      if (current > 1) { current--; renderStep(current); }
    });
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (!validateStep()) return;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
      setTimeout(() => {
        location.href = '/claims?submitted=1';
      }, 900);
    });

    /* Live validation on blur */
    stepFields().forEach(f => { }); /* static binding done below */
    $$('.wizard-step input, .wizard-step textarea, .wizard-step select').forEach(f => {
      f.addEventListener('blur', () => {
        if (!f.value.trim()) {
          f.classList.add('invalid');
          const err = f.closest('.form-group, .policy-lookup, .checkbox-label');
          const errEl = err ? err.querySelector('.field-error') : null;
          if (errEl) errEl.hidden = false;
        } else {
          f.classList.remove('invalid');
        }
      });
    });

    /* ── Policy lookup (simulated) ── */
    const policyLookupInput = $('#policy-search');
    const policyLookupResult = $('#policy-result');
    const lookupError = document.querySelector('.policy-lookup .field-error');
    const lookupButton = $('.lookup-btn');
    function runPolicyLookup() {
      if (!policyLookupInput) return;
      const value = policyLookupInput.value.trim();
      if (!value) {
        if (lookupError) lookupError.hidden = false;
        policyLookupInput.classList.add('invalid');
        return;
      }
      policyLookupInput.classList.remove('invalid');
      if (lookupError) lookupError.hidden = true;
      if (policyLookupResult) policyLookupResult.classList.remove('hidden');
    }
    if (lookupButton) lookupButton.addEventListener('click', runPolicyLookup);
    if (policyLookupInput) policyLookupInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); runPolicyLookup(); }
    });

    /* ── Segmented control (driveable) ── */
    $$('.segmented').forEach(group => {
      $$('button', group).forEach(btn => {
        btn.addEventListener('click', () => {
          $$('button', group).forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
        });
      });
    });

    /* ── Char counter ── */
    const desc = $('#incident_description');
    const counter = $('#char-count');
    if (desc && counter) {
      desc.addEventListener('input', () => { counter.textContent = desc.value.length; });
    }

    /* ── Auto-save draft to sessionStorage ── */
    function saveDraft() {
      const data = {};
      $$('input[name], textarea[name], select[name]', form).forEach(f => data[f.name] = f.value);
      sessionStorage.setItem('claim_draft', JSON.stringify(data));
      if (autosaveStatus) {
        autosaveStatus.classList.remove('saving');
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        autosaveStatus.innerHTML = '<i class="fas fa-check-circle"></i> <span>Autosaved ' + time + '</span>';
      }
    }
    function markSaving() {
      if (autosaveStatus) autosaveStatus.classList.add('saving');
    }
    function restoreDraft() {
      let saved = null;
      try { saved = JSON.parse(sessionStorage.getItem('claim_draft') || 'null'); } catch (err) { saved = null; }
      if (!saved) return;
      Object.entries(saved).forEach(([k, v]) => {
        const f = form.querySelector(`[name="${k}"]`);
        if (f && v) f.value = v;
      });
    }
    restoreDraft();
    form.addEventListener('input', markSaving);
    let saveTimer;
    form.addEventListener('input', () => {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(saveDraft, 1200);
    });

    /* ── Upload zone (drag & drop + preview) ── */
    const uploadZone = $('#upload-zone');
    const fileInput = $('#file-input');
    const preview = $('#upload-preview');
    let files = [];
    const MAX_FILES = 8;
    const MAX_SIZE = 10 * 1024 * 1024;

    if (uploadZone && fileInput) {
      uploadZone.addEventListener('click', () => fileInput.click());
      fileInput.addEventListener('change', () => { addFiles(Array.from(fileInput.files)); fileInput.value = ''; });
      ['dragover', 'dragenter'].forEach(evt => uploadZone.addEventListener(evt, (e) => {
        e.preventDefault(); uploadZone.classList.add('dragover');
      }));
      ['dragleave', 'drop'].forEach(evt => uploadZone.addEventListener(evt, (e) => {
        e.preventDefault(); uploadZone.classList.remove('dragover');
      }));
      uploadZone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files) addFiles(Array.from(e.dataTransfer.files));
      });
    }

    function addFiles(incoming) {
      incoming.forEach(f => {
        if (files.length >= MAX_FILES) { markUploadError(`Maximum ${MAX_FILES} files allowed.`); return; }
        if (f.size > MAX_SIZE) { markUploadError(`${f.name} exceeds 10MB.`); return; }
        files.push(f);
      });
      renderPreview();
    }
    function markUploadError(msg) {
      const status = $('#autosave-status');
      if (status) {
        status.classList.add('saving');
        status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>' + msg + '</span>';
      }
    }
    function renderPreview() {
      if (!preview) return;
      preview.innerHTML = '';
      files.forEach((f, i) => {
        const item = document.createElement('div');
        item.className = 'upload-preview-item';
        const isImage = f.type.startsWith('image/');
        const url = isImage ? URL.createObjectURL(f) : null;
        const size = (f.size / (1024 * 1024)).toFixed(1);
        item.innerHTML = isImage
          ? '<img src="' + url + '" alt="">'
          : '<div class="file-thumb"><i class="fas fa-file-pdf"></i></div>';
        item.insertAdjacentHTML('beforeend',
          '<div class="preview-meta"><strong>' + escapeHtml(f.name) + '</strong><span>' + size + ' MB</span></div>' +
          '<button type="button" class="remove-file" data-index="' + i + '"><i class="fas fa-times"></i></button>');
        preview.appendChild(item);
      });
      $$('.remove-file', preview).forEach(btn => {
        btn.addEventListener('click', () => {
          files.splice(+btn.getAttribute('data-index'), 1);
          renderPreview();
        });
      });
      if (files.length) {
        saveDraft();
      }
    }
    function escapeHtml(s) {
      const d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    /* Compact estimate upload */
    const estUpload = $('#estimate-upload');
    const estFile = $('#estimate-file');
    if (estUpload && estFile) {
      estUpload.addEventListener('click', () => estFile.click());
      estFile.addEventListener('change', () => {
        if (estFile.files.length) {
          const f = estFile.files[0];
          estUpload.querySelector('p').innerHTML = '<strong>' + escapeHtml(f.name) + '</strong> attached';
          estUpload.querySelector('small').textContent = (f.size / 1024 / 1024).toFixed(1) + ' MB';
          estUpload.classList.add('dragover');
        }
      });
    }

    /* ── Garage itemized rows ── */
    const addLine = $('#add-line');
    const removeLine = $$('.remove-line');
    if (addLine) {
      addLine.addEventListener('click', () => {
        const rows = $('#itemized-rows');
        const row = document.createElement('div');
        row.className = 'itemized-row';
        row.innerHTML =
          '<input type="text" placeholder="Item (e.g. windshield)">' +
          '<input type="text" placeholder="Qty">' +
          '<input type="number" placeholder="Unit price (UGX)">' +
          '<button type="button" class="icon-btn remove-line"><i class="fas fa-trash"></i></button>';
        rows.appendChild(row);
        row.querySelector('.remove-line').addEventListener('click', () => row.remove());
        rows.addEventListener('input', calcEstimateTotal);
        calcEstimateTotal();
      });
    }
    if (removeLine.length) {
      removeLine.forEach(btn => btn.addEventListener('click', (e) => {
        e.target.closest('.itemized-row').remove();
        calcEstimateTotal();
      }));
    }
    function calcEstimateTotal() {
      const rows = $$('#itemized-rows .itemized-row');
      let total = 0;
      rows.forEach(r => {
        const qty = parseFloat(r.querySelectorAll('input')[1].value) || 0;
        const price = parseFloat(r.querySelectorAll('input')[2].value) || 0;
        total += qty * price;
      });
      const totEl = $('#estimate-total');
      if (totEl) totEl.textContent = 'UGX ' + total.toLocaleString();
    }
    ['parts_cost', 'labor_cost', 'other_cost'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', () => {
        const p = parseFloat($('#parts_cost').value) || 0;
        const l = parseFloat($('#labor_cost').value) || 0;
        const o = parseFloat($('#other_cost').value) || 0;
        const tot = $('#estimate-total');
        if (tot) tot.textContent = 'UGX ' + (p + l + o).toLocaleString();
      });
    });

    /* ── Review summary (step 5) ── */
    const reviewSummary = $('#review-summary');
    if (reviewSummary) {
      form.addEventListener('input', () => { if (current === numSteps) paintReviewSummary(); });
      function paintReviewSummary() {
        const g = (id) => (document.getElementById(id) || {}).value || '—';
        const sel = (id) => { const el = document.getElementById(id); return el && el.options ? el.options[el.selectedIndex]?.text || '—' : '—'; };
        const drive = $('.segmented button.active')?.getAttribute('data-value') || '—';
        const selectedType = $('.upload-type-tabs button.active')?.getAttribute('data-type') || '—';
        reviewSummary.innerHTML =
          '<div class="summary-block"><h4>Policy &amp; Vehicle</h4><dl>' +
          '<dt>Policy</dt><dd>' + escapeHtml(g('policy-search') || 'POL-10293') + '</dd>' +
          '<dt>Vehicle</dt><dd>Toyota Land Cruiser Prado (UAY 452J)</dd>' +
          '<dt>Coverage</dt><dd>Comprehensive</dd>' +
          '<dt>Coverage Limit</dt><dd>UGX 40,000,000</dd>' +
          '</dl></div>' +
          '<div class="summary-block"><h4>Incident</h4><dl>' +
          '<dt>Date</dt><dd>' + escapeHtml(g('incident_date')) + '</dd>' +
          '<dt>Type</dt><dd>' + escapeHtml(sel('claim_type')) + '</dd>' +
          '<dt>Location</dt><dd>' + escapeHtml(g('incident_location')) + '</dd>' +
          '<dt>Police Ref</dt><dd>' + escapeHtml(g('police_report_ref')) + '</dd>' +
          '</dl></div>' +
          '<div class="summary-block"><h4>Damage</h4><dl>' +
          '<dt>Est. Damage</dt><dd>UGX ' + escapeHtml(Number(g('estimated_damage') || 0).toLocaleString()) + '</dd>' +
          '<dt>Driveable</dt><dd>' + escapeHtml(drive) + '</dd>' +
          '</dl></div>' +
          '<div class="summary-block"><h4>Documents</h4><dl>' +
          '<dt>Files Attached</dt><dd>' + files.length + ' (' + escapeHtml(selectedType) + ')</dd>' +
          '</dl></div>' +
          '<div class="summary-block"><h4>Description</h4><p class="muted-text">' + escapeHtml(g('incident_description')) + '</p></div>';
      }
      const showBtn = nextBtn;
      if (showBtn) {
        const origClick = showBtn.onclick;
        showBtn.addEventListener('click', () => { if (current === numSteps - 1) setTimeout(paintReviewSummary, 0); });
      }
      form.addEventListener('change', () => { if (current === numSteps) paintReviewSummary(); });
    }

    renderStep(1);
  }

  /* ── REVIEW PAGE (adjuster decision) ── */
  function initReviewForm() {
    const form = $('#review-form');
    if (!form) return;
    const reason = $('#reason');
    const reasonLabel = $('#reason-label');
    const amountGroup = $('#amount-group');

    $$('input[name="decision"]', form).forEach(radio => {
      radio.addEventListener('change', () => {
        const val = form.querySelector('input[name="decision"]:checked').value;
        if (reason) {
          reason.hidden = !(val === 'reject' || val === 'docs');
          reason.required = (val === 'reject' || val === 'docs');
          reasonLabel.hidden = reason.hidden;
        }
        if (amountGroup) amountGroup.style.display = val === 'approve' ? '' : 'none';
      });
    });

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;
      const notes = $('#review_notes');
      const noteErr = notes.closest('.form-group').querySelector('.field-error');
      const okNotes = notes.value.trim() !== '';
      notes.classList.toggle('invalid', !okNotes);
      if (noteErr) noteErr.hidden = okNotes;
      if (!okNotes) valid = false;

      const dec = form.querySelector('input[name="decision"]:checked').value;
      if ((dec === 'reject' || dec === 'docs') && reason && !reason.value.trim()) {
        reason.classList.add('invalid');
        valid = false;
      }
      if (!valid) return;
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
      setTimeout(() => { location.href = '/adjuster/queue'; }, 900);
    });
  }

  /* ── PAYOUT MODAL ── */
  function initPayoutModal() {
    const modal = $('#payout-modal');
    if (!modal) return;
    const claimEl = $('#payout-modal-claim');
    $$('[data-process-payout]').forEach(btn => {
      btn.addEventListener('click', () => {
        const claim = btn.getAttribute('data-process-payout');
        if (claimEl) claimEl.textContent = claim;
        modal.classList.remove('hidden');
      });
    });
    $('#cancel-payout').addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });
    const form = $('#payout-form');
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;
      $$('input[required], select[required]', form).forEach(f => {
        const ok = f.value.trim() !== '';
        f.classList.toggle('invalid', !ok);
        if (!ok) valid = false;
      });
      if (!valid) return;
      modal.classList.add('hidden');
      showToast('Payout initiated successfully.');
    });
  }

  /* ── IMPERSONATE MODAL (admin) ── */
  function initImpersonate() {
    const modal = $('#impersonate-modal');
    if (!modal) return;
    $$('[data-action="impersonate"]').forEach(btn => {
      btn.addEventListener('click', () => modal.classList.remove('hidden'));
    });
    $('#exit-impersonate').addEventListener('click', () => modal.classList.add('hidden'));
    $('#confirm-impersonate').addEventListener('click', () => {
      modal.classList.add('hidden');
      showToast('You are now viewing as the selected user. (Demo)');
    });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });
  }

  /* ── FILTERS (tables) ── */
  function initTableFilters() {
    const search = $('#claim-search, #queue-search, #user-search');
    if (search) {
      search.addEventListener('input', applyFilters);
    }
    const status = $('#filter-status');
    const type = $('#filter-type');
    const reset = $('#reset-filters');
    if (status) status.addEventListener('change', applyFilters);
    if (type) type.addEventListener('change', applyFilters);
    if (reset) reset.addEventListener('click', () => {
      if (status) status.value = '';
      if (type) type.value = '';
      if (search) search.value = '';
      applyFilters();
    });
    applyFilters();

    function applyFilters() {
      const rows = $$('.claims-table tbody tr');
      if (!rows.length) return;
      const q = (search && search.value.toLowerCase()) || '';
      let visible = 0;
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        let ok = text.includes(q);
        if (ok && status && status.value) ok = text.includes(status.value.replace('_', ' '));
        if (ok && type && type.value && $$('.badge-type', row).length) {
          ok = $$('.badge-type', row)[0].textContent.trim().toLowerCase() === type.value;
        }
        row.style.display = ok ? '' : 'none';
        if (ok) visible++;
      });
      const count = $('#result-count');
      if (count && $('.claims-table')) count.textContent = visible + ' of ' + rows.length + ' claims';
    }
  }

  /* ── TOAST ── */
  function showToast(msg) {
    let toast = $('#toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.style.cssText =
        'position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);' +
        'background:#1E293B;color:#fff;padding:12px 20px;border-radius:10px;font-size:13.5px;' +
        'opacity:0;transition:all .3s ease;z-index:1000;box-shadow:0 10px 30px rgba(0,0,0,.3);' +
        'pointer-events:none;';
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    requestAnimationFrame(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(-50%) translateY(0)'; });
    clearTimeout(toast._t);
    toast._t = setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(-50%) translateY(20px)';
    }, 2800);
  }

  /* ── SIMPLE CHART (admin claims by status) — Canvas bar chart ── */
  function initChart() {
    const wrap = $('#claims-chart canvas');
    if (!wrap) return;
    const ctx = wrap.getContext('2d');
    const data = [
      { label: 'Submitted', value: 14, color: '#2563EB' },
      { label: 'Review', value: 23, color: '#7C3AED' },
      { label: 'Docs', value: 9, color: '#D97706' },
      { label: 'Approved', value: 18, color: '#059669' },
      { label: 'Paid', value: 21, color: '#10b981' },
      { label: 'Closed', value: 2, color: '#94A3B8' },
    ];
    const W = 600, H = 300, pad = 40, base = H - pad;
    const bw = (W - pad * 2 - (data.length - 1) * 20) / data.length;
    const max = Math.max(...data.map(d => d.value)) * 1.15;
    ctx.clearRect(0, 0, W, H);
    ctx.strokeStyle = '#E2E8F0'; ctx.fillStyle = '#64748B';
    ctx.lineWidth = 1; ctx.font = '11px Inter, sans-serif';
    for (let i = 0; i <= 4; i++) {
      const y = pad - 6 + (base - pad) * i / 4;
      ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W - 20, y); ctx.stroke();
      ctx.fillText(Math.round(max * (4 - i) / 4), 8, y + 4);
    }
    data.forEach((d, i) => {
      const h = (d.value / max) * (base - pad);
      const x = pad + i * (bw + 20);
      ctx.fillStyle = d.color;
      roundRect(ctx, x, base - h, bw, h, 6); ctx.fill();
      ctx.fillStyle = '#1E293B'; ctx.font = '600 11px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(d.value, x + bw / 2, base - h - 6);
      ctx.fillStyle = '#64748B'; ctx.font = '11px Inter, sans-serif';
      ctx.fillText(d.label, x + bw / 2, base + 16);
    });
    ctx.textAlign = 'left';
    const rangeSel = $('#range-select');
    if (rangeSel) rangeSel.addEventListener('change', () => showToast('Chart data refreshed (demo).'));
  }
  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  /* ── DOC VIEWER (review page) ── */
  function initDocViewer() {
    const btns = $$('[data-view-doc]');
    if (!btns.length) return;
    let backdrop = $('#doc-viewer');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'doc-viewer';
      backdrop.className = 'modal-backdrop hidden';
      backdrop.innerHTML =
        '<div class="modal viewer-modal">' +
        '<div class="card-header"><h3>Document Viewer</h3><button class="icon-btn" id="close-viewer"><i class="fas fa-times"></i></button></div>' +
        '<div class="viewer-body"><div class="viewer-placeholder"><i class="fas fa-file-image"></i><p>Document preview — wired to the secure file proxy in Phase 3.</p></div></div>' +
        '</div>';
      document.body.appendChild(backdrop);
    }
    btns.forEach(btn => btn.addEventListener('click', () => {
      backdrop.classList.remove('hidden');
      $('#close-viewer').addEventListener('click', () => backdrop.classList.add('hidden'));
      $('#doc-viewer').addEventListener('click', (e) => { if (e.target === backdrop) backdrop.classList.add('hidden'); });
    }));
  }

  /* ── NOTIFICATION POLL (simulated) ── */
  function initNotifyPoll() {
    const badge = $('#notif-badge');
    const list = $('#notif-list');
    if (!badge) return;
    /* Demo: heartbeat mutation of the badge — swap for fetch('/api/notifications') later */
    setInterval(() => {
      const n = Math.floor(Math.random() * 4);
      badge.textContent = n || '';
      badge.style.display = n ? 'flex' : 'none';
    }, 45000);
    void list;
  }

  /* ── LIVE DRAG-AND-DROP FILE COUNT on preview update: keep toast available ── */
  window.AVIC = { $, $$, showToast, escapeHtml };

  /* ── INIT ── */
  document.addEventListener('DOMContentLoaded', () => {
    setGreeting();
    initSidebar();
    initDropdowns();
    initClickableRows();
    initPasswordToggle();
    initAuthForm();
    initRoleChips();
    initWizard();
    initReviewForm();
    initPayoutModal();
    initImpersonate();
    initTableFilters();
    initChart();
    initDocViewer();
    initNotifyPoll();
  });
})();