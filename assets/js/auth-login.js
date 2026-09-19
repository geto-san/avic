document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  if (!form) return;

  const reasonEl = document.getElementById('reason');
  const note = (kind, msg) => {
    if (reasonEl) reasonEl.innerHTML = '<div class="note note--' + kind + '">' + msg + '</div>';
  };

  if (UI.qs('reason') === 'signin') note('warn', 'Sign in to open that page.');

  /* already signed in? go straight to that desk */
  const live = AVIC.session();
  if (live) { location.replace(AVIC.homeFor(live.role)); return; }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!UI.validate(form)) return UI.toast('Fill in the highlighted fields.', 'bad');

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Signing in…';

    try {
      const res = await fetch('../../config/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: form.elements.email.value.trim(),
          password: form.elements.password.value,
          remember: form.elements.remember.checked,
        }),
      });
      const data = await res.json().catch(() => ({}));

      if (res.status === 429) return note('stop', UI.esc(data.message || 'Too many attempts. Try again later.'));
      if (res.status === 403) return note('stop', UI.esc(data.message || 'Your account is not active yet.'));
      if (!res.ok) return note('stop', UI.esc(data.message || 'Could not sign in.'));

      const s = AVIC.startServerSession(data.user);
      const intended = sessionStorage.getItem('avic.intended');
      sessionStorage.removeItem('avic.intended');
      location.href = intended || AVIC.homeFor(s.role);
    } catch (err) {
      note('stop', 'Network error — please try again.');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Sign in';
    }
  });
});