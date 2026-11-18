
AVIC.boot(async function () {
  const form = document.getElementById('policy-form');
  const message = document.getElementById('policy-message');
  const button = form.querySelector('button[type="submit"]');

  async function loadPolicies() {
    button.disabled = true;
    const response = await API.get('config/api/policies.php');
    if (!response.ok) {
      message.textContent = response.data?.message || 'Could not load policies. Reload to try again.';
      message.focus();
      return;
    }
    form.elements.user_id.innerHTML = '<option value="">Choose a claimant</option>' + response.data.claimants.map(user =>
      '<option value="' + user.id + '">' + UI.esc(user.full_name + ' — ' + user.email) + '</option>').join('');
    if (!response.data.claimants.length) message.textContent = 'A claimant must register before you can issue cover.';
    button.disabled = !response.data.claimants.length;
    UI.table('#issued-policies', {
      rows: response.data.policies,
      cols: [
        {label: 'Policy', cell: policy => UI.esc(policy.policy_number)},
        {label: 'Claimant', cell: policy => UI.esc(policy.full_name)},
        {label: 'Vehicle', cell: policy => UI.esc(policy.vehicle_plate)},
        {label: 'Cover limit', cell: policy => UI.money(policy.coverage_limit)},
        {label: 'Period', cell: policy => UI.date(policy.start_date) + ' to ' + UI.date(policy.end_date)}
      ],
      emptyTitle: 'No policies issued yet'
    });
  }

  form.onsubmit = async event => {
    event.preventDefault();
    form.querySelectorAll('.has-error').forEach(field => field.classList.remove('has-error'));
    form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
    message.textContent = 'Issuing policy…';
    button.disabled = true;
    const response = await API.post('config/api/policies.php', Object.fromEntries(new FormData(form)));
    button.disabled = false;
    if (!response.ok) {
      message.textContent = response.data?.message || 'Could not issue the policy. Check your connection and try again.';
      Object.entries(response.data?.errors || {}).forEach(([name, error]) => {
        const input = form.elements[name];
        if (!input) return;
        input.setAttribute('aria-invalid', 'true');
        input.closest('.field').classList.add('has-error');
        const hint = document.getElementById(name + '-error');
        if (hint) hint.textContent = error;
      });
      message.focus();
      return;
    }
    form.reset();
    message.textContent = 'Policy ' + response.data.policy_number + ' issued. The claimant can now view it under Policies.';
    message.focus();
    await loadPolicies();
  };
  await loadPolicies();
  await UI.loadPolicyRegistrations(AVIC.session(), null, loadPolicies);
});
