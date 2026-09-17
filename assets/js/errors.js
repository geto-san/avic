/* AVIC Portal — 403 and 404 screens. The 403 names both desks. */

(function () {
  const s = AVIC.session();
  const acts = document.getElementById('acts');
  const home = s ? AVIC.homeFor(s.role) : AVIC.url('pages/auth/login.html');
  const homeLabel = s ? 'Back to my dashboard' : 'Go to sign in';

  if (acts) {
    acts.innerHTML = '<a class="btn btn--primary btn--sm" href="' + home + '">' + homeLabel + '</a>' +
      (s ? '<button class="btn btn--sm" id="out">Sign out</button>' : '');
    const out = document.getElementById('out');
    if (out) out.onclick = () => AVIC.signOut();
  }

  const why = document.getElementById('why');
  if (!why) return;

  const you = UI.qs('you'), need = UI.qs('need'), reason = UI.qs('reason');
  const nameOf = r => (AVIC.roles[r] ? AVIC.roles[r].label : r);

  let text;
  if (reason === 'not-yours') {
    text = 'That claim was filed by another policy holder. Claimants can only open claims filed on their own account.';
  } else if (reason === 'not-assigned') {
    text = 'That claim is assigned to a different adjuster. Ask an administrator to reassign it if it should be on your desk.';
  } else if (reason === 'no-work-order') {
    text = 'Your workshop does not hold a work order for that claim, so its vehicle details are not available to you.';
  } else if (reason === 'status') {
    text = 'Your account is ' + UI.esc(UI.qs('was') || 'not active') + '. An administrator has to activate it before you can use the portal.';
  } else if (you && need) {
    text = 'You are signed in as a ' + nameOf(you).toLowerCase() + '. That page is part of the ' +
      need.split('|').map(nameOf).join(' or ').toLowerCase() + ' desk.';
  } else {
    text = 'You do not have access to that page.';
  }

  why.innerHTML = '<p>' + UI.esc(text) + '</p>' +
    (s ? '<dl class="kv"><dt>Signed in as</dt><dd>' + UI.esc(s.name) + ' · ' + UI.esc(AVIC.roles[s.role].label) + '</dd>' +
         '<dt>Recorded</dt><dd>' + new Date().toLocaleString('en-GB') + '</dd></dl>' : '');
})();
