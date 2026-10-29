<div class="auth-wrap"><div class="card">
  <h1>Sign in</h1>
  <p class="muted">Report a loss or work your claims.</p>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/login">
    <?= Avic\Core\Csrf::field() ?>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary">Sign in</button>
  </form>
  <p class="small muted">New policyholder? <a href="/register">Create an account</a>. Adjusters are added by the claims office.</p>
</div></div>
