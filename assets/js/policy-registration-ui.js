
UI.loadPolicyRegistrations = async function (session, onEdit, onReviewed) {
  const host = document.getElementById('policy-registrations');
  const response = await API.get('config/api/policy-registrations.php');
  if (!response.ok) { host.innerHTML = '<p>Could not load policy registrations. Reload to try again.</p>'; return; }
  const claimant = session.role === 'claimant';
  host.innerHTML = response.data.registrations.map(row => {
    const policy = JSON.parse(row.details);
    return '<article class="panel"><div class="panel__head"><h2>' + UI.esc(row.policy_number) + '</h2><span class="tag">' + ({pending:'Awaiting verification',verified:'Verified',rejected:'Needs correction'}[row.status]) + '</span></div>' +
      '<div class="panel__body"><dl class="kv"><dt>Insurer</dt><dd>' + UI.esc(row.insurer_name) + '</dd><dt>Policyholder</dt><dd>' + UI.esc(row.full_name) + '</dd><dt>Vehicle</dt><dd>' + UI.esc(policy.vehicle_plate + ' · ' + policy.vehicle_make + ' ' + policy.vehicle_model) +
      '</dd><dt>Cover</dt><dd>' + UI.esc(AVIC.labels.coverage[policy.coverage_type]) + ' · ' + UI.money(policy.coverage_limit) + '</dd><dt>Period</dt><dd>' + UI.date(policy.start_date) + ' to ' + UI.date(policy.end_date) + '</dd></dl>' +
      (row.review_notes ? '<p class="preserve-lines">' + UI.esc(row.review_notes) + '</p>' : '') +
      (claimant && row.status === 'rejected' ? '<button class="btn" type="button" data-edit-registration="' + row.id + '">Edit and resubmit</button>' : '') +
      (!claimant && row.status === 'pending' ? '<form data-review-registration="' + row.id + '"><div class="field"><label for="registration-notes-' + row.id + '">Verification result or correction needed</label><textarea id="registration-notes-' + row.id + '" name="review_notes" maxlength="2000" placeholder="Record how the insurer/policy details were checked, or explain what must be corrected." required></textarea></div><div class="btnrow"><button class="btn btn--primary" name="action" value="verify">Verify policy</button><button class="btn" name="action" value="reject">Return for correction</button></div><p role="status"></p></form>' : '') + '</div></article>';
  }).join('') || (claimant ? '<p class="muted">You have no policy registrations awaiting review.</p>' : '<p class="muted">No existing-policy registrations to review.</p>');
  host.querySelectorAll('[data-edit-registration]').forEach(button => button.onclick = () => onEdit(response.data.registrations.find(row => +row.id === +button.dataset.editRegistration)));
  host.querySelectorAll('[data-review-registration]').forEach(form => form.onsubmit = async event => {
    event.preventDefault();
    const action = event.submitter.value;
    const buttons = [...form.querySelectorAll('button')]; buttons.forEach(button => button.disabled=true);
    const feedback = form.querySelector('[role=status]'); feedback.textContent='Saving review…';
    const result = await API.post('config/api/policy-registrations.php', {id:+form.dataset.reviewRegistration,action,review_notes:form.elements.review_notes.value});
    buttons.forEach(button => button.disabled=false);
    if (!result.ok) { feedback.textContent=result.data?.message || 'Could not save the review.'; return; }
    await UI.loadPolicyRegistrations(session, onEdit, onReviewed); if(onReviewed) await onReviewed(); UI.toast(action === 'verify' ? 'Policy verified and ready for claims.' : 'Policy returned for correction.','ok');
  });
  return response.data.registrations;
};
