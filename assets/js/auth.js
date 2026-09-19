(function () {
  const page = document.body.dataset.page;

  /* ---------------- login ---------------- */
  if (page === 'login') {
    /* already signed in? go straight to that desk */
    const live = AVIC.session();
    if (live && !UI.qs('reason')) location.replace(AVIC.homeFor(live.role));

    if (!AVIC.storageOk) {
      document.getElementById('reason').innerHTML =
        '<div class="note note--stop">This browser will not keep a session for pages opened straight from a folder. ' +
        'Serve the folder over a local web server — <span class="mono">python3 -m http.server</span> in the project root, ' +
        'or drop it in your XAMPP <span class="mono">htdocs</span> — then open it through <span class="mono">localhost</span>.</div>';
    }

    const reason = UI.qs('reason');
    if (reason === 'signin') {
      document.getElementById('reason').innerHTML =
        '<div class="note note--warn">Sign in to open that page.</div>';
    }

    /* demo desks */
    const demos = [1].map(AVIC.user);
    document.getElementById('demo-users').innerHTML = demos.map(u =>
      '<button class="demo-user" data-id="' + u.id + '" style="--pill:' + AVIC.roles[u.role].accent + '">' +
        '<span class="demo-user__dot"></span>' +
        '<span><span class="demo-user__t">' + UI.esc(AVIC.roles[u.role].label) + '</span>' +
        '<span class="demo-user__d"> · ' + UI.esc(u.full_name) + '</span></span>' +
        '<span class="demo-user__go">Open</span>' +
      '</button>').join('');

    document.querySelectorAll('.demo-user').forEach(b => b.onclick = () => {
      const s = AVIC.signIn(+b.dataset.id);
      location.href = AVIC.homeFor(s.role);
    });

    const form = document.getElementById('login-form');
    form.onsubmit = e => {
      e.preventDefault();
      if (!UI.validate(form)) return;
      const u = AVIC.users.find(x => x.email.toLowerCase() === form.elements.email.value.trim().toLowerCase());
      if (!u) {
        document.getElementById('reason').innerHTML =
          '<div class="note note--stop">No account uses that email. Try one of the demo desks below.</div>';
        return;
      }
      if (u.status !== 'active') {
        document.getElementById('reason').innerHTML =
          '<div class="note note--stop">This account is ' + u.status + '. An administrator has to activate it before you can sign in.</div>';
        return;
      }
      const s = AVIC.signIn(u.id);
      const intended = sessionStorage.getItem('avic.intended');
      sessionStorage.removeItem('avic.intended');
      location.href = intended || AVIC.homeFor(s.role);
    };
  }

  /* ---------------- register ---------------- */
  if (page === 'register') {
    const form = document.getElementById('register-form');
    document.querySelectorAll('.choice[data-role]').forEach(ch => ch.onclick = () => {
      document.querySelectorAll('.choice[data-role]').forEach(x => x.classList.remove('is-on'));
      ch.classList.add('is-on');
      form.elements.role.value = ch.dataset.role;
      document.getElementById('garage-fields').classList.toggle('hidden', ch.dataset.role !== 'garage');
    });

    form.onsubmit = e => {
      e.preventDefault();
      if (!UI.validate(form)) return UI.toast('Fill in the highlighted fields.', 'bad');
      if (!form.elements.role.value) return UI.toast('Choose whether you are a claimant or a garage.', 'bad');
      if (form.elements.password.value !== form.elements.password2.value) {
        form.elements.password2.closest('.field').classList.add('has-error');
        return UI.toast('The two passwords do not match.', 'bad');
      }
      document.getElementById('done').classList.remove('hidden');
      form.closest('.panel').classList.add('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };
  }

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
