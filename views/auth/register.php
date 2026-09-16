<div class="auth-card">
    <div class="auth-logo">
        <div class="badge">AVIC</div>
        <h1>Create Account</h1>
        <p>Join the AVIC claims portal</p>
    </div>
    <form id="register-form" class="auth-form" novalidate>
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <div class="input-wrap">
                <i class="fas fa-user"></i>
                <input type="text" id="full_name" name="full_name" placeholder="e.g. John Kamugisha" required>
            </div>
            <div class="field-error" hidden>Full name is required.</div>
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrap">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="field-error" hidden>Please enter a valid email address.</div>
        </div>
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <div class="input-wrap">
                <i class="fas fa-phone"></i>
                <input type="tel" id="phone" name="phone" placeholder="+256 700 000000">
            </div>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Min 8 characters" minlength="8" required>
                <button type="button" class="toggle-password"><i class="fas fa-eye"></i></button>
            </div>
            <div class="field-error" hidden>Password must be at least 8 characters.</div>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" minlength="8" required>
            </div>
            <div class="field-error" hidden>Passwords do not match.</div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>
    <div class="auth-footer">
        <span>Already have an account?</span>
        <a href="/login" class="link">Sign in</a>
    </div>
</div>