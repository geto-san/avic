<?php
$field = function (string $name, string $label, string $type = 'text', string $auto = '') use ($old, $errors) {
    $err = $errors[$name] ?? null;
    $val = $type === 'password' ? '' : ($old[$name] ?? '');
    echo '<div class="field' . ($err ? ' invalid' : '') . '"><label for="' . $name . '">' . e($label) . '</label>'
        . '<input id="' . $name . '" type="' . $type . '" name="' . $name . '" value="' . e($val) . '"' . ($auto ? ' autocomplete="' . $auto . '"' : '') . '>'
        . ($err ? '<div class="err">' . e($err) . '</div>' : '') . '</div>';
};
?>
<div class="auth-wrap"><div class="card">
  <h1>Create an account</h1>
  <p class="muted">For policyholders. You will link your policy next.</p>
  <form method="post" action="/register" novalidate>
    <?= Avic\Core\Csrf::field() ?>
    <?php $field('full_name', 'Full name', 'text', 'name'); $field('email', 'Email', 'email', 'username'); $field('phone', 'Phone (optional)', 'tel', 'tel'); ?>
    <?php $field('password', 'Password (8+ characters)', 'password', 'new-password'); $field('password2', 'Repeat password', 'password', 'new-password'); ?>
    <button class="btn btn-primary">Create account</button>
  </form>
  <p class="small muted">Already registered? <a href="/login">Sign in</a>.</p>
</div></div>
