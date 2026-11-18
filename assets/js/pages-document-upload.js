
AVIC.boot(function renderUpload() {
  const claim = AVIC.claim(+UI.qs('claim'));
  if (!AVIC.canSeeClaim(AVIC.session(), claim)) { location.replace(AVIC.url('pages/errors/403.html')); return; }
  document.getElementById('back-to-claim').href = 'claim-detail.html?id=' + claim.id;
  const requests = AVIC.documentRequests.filter(row => +row.claim_id === claim.id);
  const items = AVIC.requestedDocuments.filter(row => +row.claim_id === claim.id);
  if (!requests.length && claim.status === 'pending_docs') {
    requests.push({id:0,status:'open',details:'No document name was recorded for this older request. Upload only what your adjuster asked for; contact them if you need clarification.'});
    items.push({id:0,request_id:0,document_name:'Supporting document'});
  }
  const limit = Math.min(12, +AVIC.settings.max_upload_mb || 10);
  document.getElementById('requested-upload-list').innerHTML = items.length ? requests.map(request =>
    '<section class="panel"><div class="panel__head"><h2>Requested documents</h2><span class="muted">' + UI.dateTime(request.created_at) + '</span></div>' +
    '<div class="panel__body"><p class="mono">' + UI.esc(claim.claim_number) + '</p><p class="preserve-lines">' + UI.esc(request.details) + '</p>' + items.filter(item => +item.request_id === +request.id).map(item => {
      const supplied = !!item.document_id;
      const canUpload = !supplied && request.status === 'open' && claim.status === 'pending_docs';
      return '<div class="request-record"><h3>' + UI.esc(item.document_name) + '</h3>' + (supplied
        ? '<p>Uploaded ' + UI.dateTime(item.uploaded_at) + ' — awaiting the adjuster’s assessment.</p><a class="btn" href="' + AVIC.url('config/uploads.php?doc=' + item.document_id) + '" target="_blank" rel="noopener">View uploaded document</a>'
        : canUpload ? '<form data-requested-document="' + item.id + '"><div class="field"><label for="requested-file-' + item.id + '">Choose ' + UI.esc(item.document_name) + '</label>' +
          '<input type="file" id="requested-file-' + item.id + '" name="files[]" accept="image/jpeg,image/png,image/webp,application/pdf" required aria-describedby="file-help-' + item.id + '">' +
          '<p class="hint" id="file-help-' + item.id + '">JPEG, PNG, WEBP or PDF. Maximum ' + limit + ' MB. One file for this document.</p></div>' +
          '<p class="upload-feedback" role="status"></p><button class="btn btn--primary" type="submit">Upload document</button></form>'
          : '<p class="muted">This request is no longer open.</p>') + '</div>';
    }).join('') + '</div></section>').join('') : '<div class="empty"><h2>No outstanding document requests</h2><p>Return to the claim to see its current status.</p></div>';
  document.querySelectorAll('[data-requested-document]').forEach(form => {
    const input = form.querySelector('input'), feedback = form.querySelector('[role=status]'), button = form.querySelector('button');
    input.onchange = () => {
      input.setCustomValidity('');
      const file = input.files[0];
      if (!file) { feedback.textContent = ''; return; }
      const valid = ['image/jpeg','image/png','image/webp','application/pdf'].includes(file.type) && file.size > 0 && file.size <= limit * 1048576;
      input.setCustomValidity(valid ? '' : 'Choose a supported file within ' + limit + ' MB.');
      feedback.textContent = valid ? file.name + ' · ' + UI.size(file.size) : input.validationMessage;
      if (!valid) input.reportValidity();
    };
    form.onsubmit = async event => {
      event.preventDefault(); button.disabled = true; input.disabled = true;
      const data = new FormData(); data.append('requested_document_id', form.dataset.requestedDocument); data.append('claim_id', claim.id); data.append('files[]', input.files[0]);
      feedback.textContent = 'Uploading document…';
      const response = await API.postForm('config/api/document-responses.php', data);
      button.disabled = false; input.disabled = false;
      if (!response.ok) { feedback.textContent = response.data?.message || 'Upload failed. Your selection is kept so you can retry.'; return; }
      await AVIC.refresh(); renderUpload();
      const message = document.getElementById('upload-result');
      message.textContent = response.data.complete ? 'All requested documents uploaded. Your claim is back with the adjuster.' : 'Document uploaded. You can supply the remaining requested documents below.';
      message.focus();
    };
  });
});
