
AVIC.boot(async function (session) {
  const form=document.getElementById('policy-form'), message=document.getElementById('policy-message');
  const editor=document.getElementById('policy-editor'), status=document.getElementById('policy-status');
  async function load() {
    const policies=AVIC.policiesFor(session);
    document.getElementById('policy-list').innerHTML=policies.length ? '<h2>Verified policies</h2>' + policies.map(policy=>
      '<article class="panel"><div class="panel__head"><h3>' + UI.esc(policy.policy_number) + '</h3></div><div class="panel__body"><p>' + UI.esc(policy.vehicle_plate) + ' · ' + UI.esc(policy.vehicle_make + ' ' + policy.vehicle_model) + '</p><p>' + UI.date(policy.start_date) + ' to ' + UI.date(policy.end_date) + ' · ' + UI.money(policy.coverage_limit) + '</p>' +
      (policy.status === 'active' ? '<a class="btn btn--primary" href="claim-new.html?policy=' + policy.id + '">File a claim on this policy</a>' : '<p>' + UI.esc(policy.status) + ' — unavailable for new claims.</p>') + '</div></article>').join('') : '<p class="muted">You have no verified policies yet. Add your existing policy above.</p>';
    const registrations=await UI.loadPolicyRegistrations(session, row=>{
      editor.open=true;
      form.reset(); Object.entries(JSON.parse(row.details)).forEach(([name,value])=>{if(form.elements[name])form.elements[name].value=value ?? '';});
      form.elements.id.value=row.id;form.elements.policy_number.value=row.policy_number;form.elements.insurer_name.value=row.insurer_name;
      message.textContent='Correct the policy details and submit again.';form.elements.policy_number.focus();
    });
    editor.open=!policies.length && !registrations?.length;
    if(!policies.length && registrations?.length) document.getElementById('policy-list').innerHTML='<p class="muted">A verified policy will become available here for filing claims.</p>';
  }
  form.onsubmit=async event=>{
    event.preventDefault(); const button=form.querySelector('[type=submit]');button.disabled=true;message.textContent='Submitting policy for verification…';
    form.querySelectorAll('.has-error').forEach(field=>field.classList.remove('has-error'));
    form.querySelectorAll('[aria-invalid]').forEach(input=>input.removeAttribute('aria-invalid'));
    const result=await API.post('config/api/policy-registrations.php',Object.fromEntries(new FormData(form)));button.disabled=false;
    if(!result.ok){message.textContent=result.data?.message || 'Could not submit the policy.';Object.entries(result.data?.errors || {}).forEach(([name,error])=>{const input=form.elements[name];if(!input)return;input.setAttribute('aria-invalid','true');input.closest('.field').classList.add('has-error');const hint=document.getElementById(name+'-error');if(hint)hint.textContent=error;});message.focus();return;}
    form.reset();message.textContent='';status.textContent='Policy received. An adjuster will verify it before it can be used for a claim.';await load();status.focus();
  };
  await load();
});
