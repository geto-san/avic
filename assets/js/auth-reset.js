document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('reset-form');
  if (!form) return;

  const token = new URLSearchParams(location.search).get('token');

  const bar = document.getElementById('strength');
  form.elements.password.oninput = () => {
    const v = form.elements.password.value;
    const score = [v.length >= 8, /[A-Z]/.test(v), /\d/.test(v), /[^A-Za-z0-9]/.test(v)].filter(Boolean).length;
    const words = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
    bar.textContent = v ? words[score] : '';
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!UI.validate(form)) return;
    if (form.elements.password.value !== form.elements.password2.value) {
      form.elements.password2.closest('.field').classList.add('has-error');
      return UI.toast('The two passwords do not match.', 'bad');
    }
    if (!token) return UI.toast('This link is missing its token. Request a new one.', 'bad');

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
      const res = await fetch('../../config/auth/reset-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          password: form.elements.password.value,
          password2: form.elements.password2.value,
        }),
      });
      const data = await res.json();

      if (!res.ok) {
        UI.toast(data.message || 'Could not reset your password.', 'bad');
        return;
      }
      UI.toast(data.message || 'Password changed. Sign in again.', 'ok');
      setTimeout(() => location.href = 'login.html', 1200);
    } catch (err) {
      console.error('[reset-password] request failed:', err);
      UI.toast('Network error — please try again.', 'bad');
    } finally {
      btn.disabled = false;
    }
  });
});
