<div class="auth-card">
    <div class="auth-logo">
        <div class="badge">AVIC</div>
        <h1>Reset Password</h1>
        <p>Enter your email and we'll send you a reset link</p>
    </div>
    <div class="alert alert-info hidden" id="reset-info"><i class="fas fa-paper-plane"></i> If an account exists, a reset link has been sent.</div>
    <form id="forgot-form" class="auth-form" novalidate>
        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="field-error" hidden>Please enter a valid email address.</div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
    </form>
    <div class="auth-footer">
        <a href="/login" class="link"><i class="fas fa-arrow-left"></i> Back to sign in</a>
    </div>
</div>