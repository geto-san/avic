(function () {
  const page = document.body.dataset.page;

  /* ---------------- forgot / reset ---------------- */
  if (page === 'forgot-password') {
    const form = document.getElementById('forgot-form');
    form.onsubmit = e => {
      e.preventDefault();
      if (!UI.validate(form)) return;
      document.getElementById('sent').classList.remove('hidden');
      form.closest('.panel').classList.add('hidden');
    };
  }

  if (page === 'reset-password') {
    const form = document.getElementById('reset-form');
    const bar = document.getElementById('strength');
    form.elements.password.oninput = () => {
      const v = form.elements.password.value;
      const score = [v.length >= 8, /[A-Z]/.test(v), /[0-9]/.test(v), /[^A-Za-z0-9]/.test(v)].filter(Boolean).length;
      const words = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
      bar.textContent = v ? words[score] : '';
    };
    form.onsubmit = e => {
      e.preventDefault();
      if (!UI.validate(form)) return;
      if (form.elements.password.value !== form.elements.password2.value) {
        form.elements.password2.closest('.field').classList.add('has-error');
        return UI.toast('The two passwords do not match.', 'bad');
      }
      UI.toast('Password changed in the prototype. Sign in again.', 'ok');
      setTimeout(() => location.href = 'login.html', 1000);
    };
  }
})();
