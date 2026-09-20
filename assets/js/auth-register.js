document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('register-form');
  if (!form) return;

  const roleInput = form.elements.role;
  const garageFields = document.getElementById('garage-fields');
  const doneSection = document.getElementById('done');

  document.querySelectorAll('.choice[data-role]').forEach(ch => {
    ch.addEventListener('click', () => {
      document.querySelectorAll('.choice[data-role]').forEach(x => x.classList.remove('is-on'));
      ch.classList.add('is-on');
      roleInput.value = ch.dataset.role;
      garageFields.classList.toggle('hidden', ch.dataset.role !== 'garage');
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!UI.validate(form)) return UI.toast('Fill in the highlighted fields.', 'bad');
    if (!roleInput.value) return UI.toast('Choose whether you are a claimant or a garage.', 'bad');
    if (form.elements.password.value.length < 8) {
      form.elements.password.closest('.field').classList.add('has-error');
      return UI.toast('Password must be at least 8 characters.', 'bad');
    }
    if (form.elements.password.value !== form.elements.password2.value) {
      form.elements.password2.closest('.field').classList.add('has-error');
      return UI.toast('The two passwords do not match.', 'bad');
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account…';

    const payload = {
      role: roleInput.value,
      full_name: form.elements.full_name.value.trim(),
      email: form.elements.email.value.trim(),
      phone: form.elements.phone.value.trim(),
      password: form.elements.password.value,
      password2: form.elements.password2.value,
      garage_address: form.elements.garage_address ? form.elements.garage_address.value.trim() : null,
      trading_licence: form.elements.trading_licence ? form.elements.trading_licence.value.trim() : null,
    };

    try {
      const res = await fetch('../../config/auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!res.ok) {
        if (data.errors) {
          Object.entries(data.errors).forEach(([name, msg]) => {
            const field = form.elements[name];
            if (field) field.closest('.field')?.classList.add('has-error');
          });
        }
        UI.toast(data.message || 'Could not create account.', 'bad');
        return;
      }

      doneSection.classList.remove('hidden');
      form.closest('.panel').classList.add('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      console.error('[register] request failed:', err);
      UI.toast('Network error — please try again.', 'bad');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Create account';
    }
  });
});