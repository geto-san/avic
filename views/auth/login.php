<div class="auth-card">
    <div class="auth-logo">
        <div class="badge">AVIC</div>
        <h1>Sign In</h1>
        <p>Access your AVIC Portal account</p>
    </div>
    <form id="login-form" class="auth-form" novalidate>
        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" required>
            </div>
            <div class="field-error" hidden>Please enter a valid email address.</div>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="toggle-password"><i class="fas fa-eye"></i></button>
            </div>
            <div class="field-error" hidden>Password is required.</div>
        </div>
        <div class="form-row">
            <label class="checkbox-label"><input type="checkbox" name="remember"> Remember me</label>
            <a href="/forgot-password" class="link">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
    <div class="auth-footer">
        <span>Don't have an account?</span>
        <a href="/register" class="link">Create one</a>
    </div>
    <div class="auth-role-switch">
        <span class="divider-label">Demo role preview</span>
        <div class="role-switch">
            <button class="role-chip" data-role="claimant">Claimant</button>
            <button class="role-chip" data-role="adjuster">Adjuster</button>
            <button class="role-chip" data-role="garage">Garage</button>
            <button class="role-chip" data-role="admin">Admin</button>
        </div>
    </div>
</div>