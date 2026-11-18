// Shared notifications and claim-workflow panels.

AVIC.pages = AVIC.pages || {};

AVIC.pages.notifications = function (s) {
  const list = AVIC.notificationsFor(s).sort((a, b) => b.created_at.localeCompare(a.created_at));
  const host = document.getElementById('notif-list');
  const unread = list.filter(n => !n.is_read).length;

  function destination(notification) {
    if (/complaint/i.test(notification.title) && s.role !== 'garage') return 'complaints.html?claim=' + (notification.claim_id || '');
    if (notification.claim_id) {
      const claim = AVIC.claim(+notification.claim_id);
      if (s.role === 'claimant') {
        if (/documents needed/i.test(notification.title) && claim?.status === 'pending_docs') return 'document-upload.html?claim=' + notification.claim_id;
        return 'claim-detail.html?id=' + notification.claim_id;
      }
      if (s.role === 'adjuster') return claim ? 'review.html?id=' + notification.claim_id : 'complaints.html?claim=' + notification.claim_id;
      return 'work-orders.html';
    }
    return s.role !== 'garage' && /policy/i.test(notification.title) ? 'policies.html' : 'dashboard.html';
  }
  host.innerHTML = list.length ? list.map(n =>
    '<div class="notif notification-row' + (n.is_read ? '' : ' is-unread') + '">' +
      '<a class="notification-link" data-open-notification="' + n.id + '" href="' + destination(n) + '">' +
        '<span class="notif__t">' + UI.esc(n.title) + '</span>' +
        '<span class="notif__m">' + UI.esc(n.message) + '</span>' +
        '<span class="notif__d">' + UI.dateTime(n.created_at) + ' · ' + (n.is_read ? 'Read' : 'Unread') + '</span>' +
      '</a>' + (n.is_read ? '' : '<button class="btn btn--sm" data-read="' + n.id + '">Mark read</button>') + '</div>'
  ).join('') : '<div class="empty"><h2>No notifications</h2><p>Updates about your claims will appear here.</p></div>';
  host.querySelectorAll('[data-open-notification]').forEach(link => link.onclick = async event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    const response = await API.post('config/api/notifications.php', {action:'read', id:+link.dataset.openNotification});
    if (response.status !== 401) location.href = link.href;
  });
  host.querySelectorAll('[data-read]').forEach(b => b.onclick = () => {
    const id = +b.dataset.read;
    API.post('config/api/notifications.php', { action: 'read', id }).then(r => {
      if (!r.ok) return UI.toast((r.data?.message) || 'Could not update the notification.', 'bad');
      AVIC.rerender(AVIC.pages.notifications);
      UI.toast('Marked as read.');
    });
  });

  const all = document.getElementById('mark-all');
  if (all) all.disabled = !unread;
  if (all) all.onclick = () => {
    all.disabled = true;
    API.post('config/api/notifications.php', { action: 'read_all' }).then(r => {
      if (!r.ok) return UI.toast((r.data?.message) || 'Could not clear notifications.', 'bad');
      AVIC.rerender(AVIC.pages.notifications);
      UI.toast('All notifications marked read.');
    });
  };
};
// Guidance uses existing claim states; the PHP endpoints remain the authority.
UI.claimNextStep = function (claim, role) {
  const claimant = {
    draft: 'Finish the claim form and submit it for review.',
    submitted: 'Your claim is waiting for the assigned adjuster. You can follow its progress here.',
    under_review: 'The adjuster is checking your policy, documents and repair costs.',
    pending_docs: 'Read Document requests below, then choose Add the missing documents above.',
    approved: 'Review the settlement offer and confirm your payment details below. You can dispute the offer if you disagree.',
    paid: 'Your payout is marked complete. Keep your claim summary for your records.',
    rejected: 'Read the rejection reason in Progress. Contact your assigned adjuster if you need clarification.',
    closed: 'This case is closed. Your progress, documents and payout remain available here.'
  };
  const adjuster = {
    submitted: 'Start review, check the evidence and policy, then record your decision.',
    under_review: 'Check documents and the garage quote before recording your decision.',
    pending_docs: 'Wait for the requested documents, verify new evidence, then record your decision.',
    approved: 'Wait for settlement acceptance, then record payment processing and verified completion below.',
    paid: 'Confirm the case is complete, then close the claim.',
    rejected: 'Check that the rejection reason is clear, then close the case.',
    closed: 'This case is closed. Its records remain available for reference.'
  };
  let host = document.getElementById('next-action');
  if (!host) {
    host = document.createElement('section'); host.id = 'next-action'; host.className = 'next-action';
    host.setAttribute('aria-labelledby', 'next-action-title');
    const actions = document.querySelector('.page > .filters');
    if (actions) actions.insertAdjacentElement('afterend', host);
    else document.querySelector('.page').prepend(host);
  }
  host.innerHTML = '<h2 id="next-action-title">Next step</h2><p>' + UI.esc((role === 'adjuster' ? adjuster : claimant)[claim.status] || 'Review the claim details below.') + '</p>';
  if (role === 'claimant' && claim.status === 'approved' && (AVIC.settlements || []).some(offer => +offer.claim_id === claim.id && offer.status !== 'accepted')) {
    host.insertAdjacentHTML('beforeend', '<a class="btn" href="#settlement-form">Review settlement offer</a>');
  }
  if (role === 'adjuster' && ['submitted', 'under_review', 'pending_docs'].includes(claim.status)) {
    const docs = AVIC.docsFor(claim.id);
    const verified = docs.filter(doc => doc.is_verified).length;
    const quote = AVIC.estimatesFor(claim.id)[0];
    host.innerHTML += '<p class="muted">Review preparation: ' + verified + ' of ' + docs.length + ' documents verified; ' +
      (quote ? 'garage quote ' + UI.esc(quote.status) : 'no garage quote recorded') + '. Coverage checks are shown below; final approval is validated by the server.</p>';
  }
};

// Public requests are separate from internal adjuster review notes.
UI.renderDocumentRequests = function (claim) {
  let host = document.getElementById('document-requests');
  if (!host) {
    host = document.createElement('section'); host.id = 'document-requests'; host.className = 'panel';
    document.getElementById('next-action').insertAdjacentElement('afterend', host);
  }
  const requests = (AVIC.documentRequests || []).filter(row => +row.claim_id === claim.id);
  host.hidden = !requests.length;
  host.innerHTML = '<div class="panel__head"><h2>Document requests</h2></div><div class="panel__body">' + requests.map(row =>
    '<div class="request-record"><p><strong>' + (row.status === 'open' ? 'Requested' : 'Response uploaded — awaiting assessment') + '</strong> · ' + UI.dateTime(row.created_at) + '</p><p class="preserve-lines">' + UI.esc(row.details) + '</p></div>').join('') + '</div>';
  if (AVIC.session().role === 'claimant' && claim.status === 'pending_docs' && requests.some(row => row.status === 'open')) {
    host.insertAdjacentHTML('beforeend', '<div class="panel__foot"><a class="btn btn--primary" href="document-upload.html?claim=' + claim.id + '">Upload requested documents</a></div>');
  }
};

UI.renderPaymentEvidence = function (host, payout) {
  const evidence = payout && (AVIC.paymentEvidence || []).find(row => +row.payout_id === +payout.id);
  if (evidence) host.insertAdjacentHTML('beforeend', '<h3>Payment verification</h3><dl class="kv"><dt>External reference</dt><dd>' + UI.esc(evidence.external_reference) +
    '</dd><dt>Verified on</dt><dd>' + UI.dateTime(evidence.confirmed_at) + '</dd><dt>Verification record</dt><dd class="preserve-lines">' + UI.esc(evidence.verification_note) + '</dd></dl>');
};

UI.renderSettlement = function (claim, host) {
  if (claim.status !== 'approved') return;
  const offer = (AVIC.settlements || []).find(row => +row.claim_id === claim.id);
  if (!offer) return;
  if (offer.status === 'accepted') {
    host.insertAdjacentHTML('beforeend', '<p><strong>Settlement accepted</strong> · ' + UI.dateTime(offer.accepted_at) + '</p><p>' + UI.esc(offer.account_name) + ' · ' + UI.esc(offer.account_number) + '</p>');
    return;
  }
  host.insertAdjacentHTML('beforeend', '<h3>Settlement offer</h3><p>Review the approved amount above. Accept only if you agree and the payment details are correct. A dispute is reviewed separately.</p>' +
    (offer.status === 'disputed' ? '<p class="muted">Offer disputed. Resolve the complaint before accepting.</p>' : '') +
    '<form id="settlement-form"><div class="field"><label for="settlement-method">Payment method</label><select id="settlement-method" name="payment_method"><option value="mobile_money">Mobile money</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option></select></div>' +
    '<div class="field"><label for="settlement-name">Payee name</label><input type="text" id="settlement-name" name="account_name" maxlength="100" required></div>' +
    '<div class="field"><label for="settlement-account">Account / mobile number / cheque delivery address</label><input type="text" id="settlement-account" name="account_number" maxlength="60" required></div>' +
    '<div class="field"><label for="settlement-bank">Bank name (bank transfers only)</label><input type="text" id="settlement-bank" name="bank_name" maxlength="100"></div>' +
    '<label class="check"><input id="settlement-confirmed" type="checkbox" required> I accept the approved amount and confirm these payment details.</label>' +
    '<div class="btnrow"><button class="btn btn--primary" type="submit">Accept settlement</button><button class="btn" id="dispute-offer" type="button">Dispute offer</button></div></form>');
  const form = document.getElementById('settlement-form');
  form.elements.payment_method.onchange = () => { form.elements.bank_name.required = form.elements.payment_method.value === 'bank_transfer'; };
  form.onsubmit = async event => {
    event.preventDefault();
    const button = form.querySelector('[type=submit]'); button.disabled = true;
    const response = await API.post('config/api/settlements.php', {claim_id: claim.id, action:'accept', confirmed:true, ...Object.fromEntries(new FormData(form))});
    button.disabled = false;
    if (!response.ok) return UI.toast(response.data?.message || 'Could not accept the settlement.', 'bad');
    UI.toast('Settlement accepted.', 'ok'); AVIC.rerender(Claimant.detail);
  };
  document.getElementById('dispute-offer').onclick = () => UI.modal({title:'Dispute settlement offer', confirm:'Submit dispute',
    body:'<form><div class="field"><label for="dispute-details">What should be reviewed?</label><textarea id="dispute-details" name="details" maxlength="2000" required></textarea></div></form>',
    onConfirm: async data => {
      const response = await API.post('config/api/settlements.php', {claim_id:claim.id, action:'dispute', ...data});
      if (!response.ok) { UI.toast(response.data?.message || 'Could not submit the dispute.', 'bad'); return false; }
      location.href = 'complaints.html?claim=' + claim.id;
    }
  });
};
