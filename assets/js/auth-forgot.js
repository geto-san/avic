document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('forgot-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!UI.validate(form)) return;

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Sending…';

    try {
      await fetch('../../config/auth/forgot-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: form.elements.email.value.trim() }),
      });
      // Always show the same "check your email" screen. The server
      // deliberately never reports whether the address was found, so the
      // client can't (and shouldn't try to) branch on that either.
      document.getElementById('sent').classList.remove('hidden');
      form.closest('.panel').classList.add('hidden');
    } catch (err) {
      UI.toast('Network error — please try again.', 'bad');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Send the reset link';
    }
  });
});
