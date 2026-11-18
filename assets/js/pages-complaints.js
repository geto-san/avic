
AVIC.boot(async session => {
  const result = await API.get('config/api/complaints.php');
  if (!result.ok) return UI.toast(result.data?.message || 'Could not load complaints.', 'bad');
  const claimant = session.role === 'claimant';
  const form = document.getElementById('complaint-form');
  form.hidden = !claimant;
  if (claimant) {
    form.elements.claim_id.innerHTML = '<option value="">Choose a claim</option>' + result.data.claims.map(claim => '<option value="' + claim.id + '">' + UI.esc(claim.claim_number) + '</option>').join('');
    form.elements.claim_id.value = UI.qs('claim') || '';
    form.onsubmit = async event => {
      event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
      const response = await API.post('config/api/complaints.php', Object.fromEntries(new FormData(form)));
      button.disabled = false;
      if (!response.ok) return UI.toast(response.data?.message || 'Could not register the complaint.', 'bad');
      location.reload();
    };
  }
  const rows = result.data.complaints;
  document.getElementById('complaint-list').innerHTML = rows.length ? rows.map(row =>
    '<article class="panel"><div class="panel__head"><h2>Complaint #' + row.id + ' · ' + UI.esc(row.claim_number) + '</h2><span class="tag">' + UI.esc(row.status.replaceAll('_',' ')) + '</span></div>' +
    '<div class="panel__body"><p class="muted">Received ' + UI.dateTime(row.created_at) + ' · Reviewer: ' + UI.esc(row.reviewer_name) + '</p>' +
    '<p class="preserve-lines">' + UI.esc(row.details) + '</p><dl class="kv"><dt>Original claim status</dt><dd>' + UI.esc(AVIC.labels.status[row.claim_status] || row.claim_status) +
    '</dd><dt>Approved amount</dt><dd>' + UI.money(row.approved_amount) + '</dd><dt>Rejection reason</dt><dd>' + UI.esc(row.rejection_reason || 'Not applicable') + '</dd></dl>' +
    '<details><summary>Claim evidence and assessment context</summary><p>' + UI.date(row.incident_date) + ' · ' + UI.esc(row.incident_description) + '</p><dl class="kv"><dt>Claimed</dt><dd>' + UI.money(row.estimated_damage) +
    '</dd><dt>Garage quote</dt><dd>' + UI.money(row.quoted_amount) + '</dd><dt>Coverage limit</dt><dd>' + UI.money(row.coverage_limit) + '</dd></dl>' +
    '<div class="btnrow">' + row.documents.map(doc => doc.has_file ? '<a class="btn btn--sm" target="_blank" rel="noopener" href="' + AVIC.url('config/uploads.php?doc=' + doc.id) + '">' + UI.esc(doc.name) + (doc.verified ? ' (verified)' : ' (not verified)') + '</a>' : '<p class="muted">' + UI.esc(doc.name) + ' — no file attached</p>').join('') + '</div></details>' +
    (row.response ? '<h3>Review outcome</h3><p class="preserve-lines">' + UI.esc(row.response) + '</p><p class="muted">Resolved ' + UI.dateTime(row.resolved_at) + '</p>' : '') +
    (!claimant && row.status !== 'resolved' ? '<form data-complaint="' + row.id + '">' + (row.status === 'under_review' ? '<div class="field"><label for="response-' + row.id + '">Investigation outcome and explanation</label><textarea id="response-' + row.id + '" name="response" maxlength="2000" required></textarea></div>' : '') +
      '<button class="btn btn--primary" type="submit">' + (row.status === 'open' ? 'Start complaint review' : 'Resolve complaint') + '</button></form>' : '') + '</div></article>'
  ).join('') : '<div class="empty"><h2>No complaints</h2><p>' + (claimant ? 'Any complaint you submit will appear here with its reviewer and outcome.' : 'No complaints are assigned to you.') + '</p></div>';
  document.querySelectorAll('[data-complaint]').forEach(form => form.onsubmit = async event => {
    event.preventDefault(); const button = form.querySelector('button'); button.disabled = true;
    const response = await API.post('config/api/complaints.php', {id:+form.dataset.complaint, action:form.elements.response ? 'resolve' : 'start_review', ...Object.fromEntries(new FormData(form))});
    button.disabled = false;
    if (!response.ok) return UI.toast(response.data?.message || 'Could not update the complaint.', 'bad');
    location.reload();
  });
});
