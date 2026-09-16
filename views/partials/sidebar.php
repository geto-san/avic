<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="badge">PORTAL</div>
        <h1>AVIC Portal</h1>
        <p>Auto Vehicle Insurance Claims</p>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <div class="sidebar-label">Main</div>
            <a href="/dashboard" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="/claims"><i class="fas fa-file-alt"></i> My Claims</a>
            <a href="/claims/create"><i class="fas fa-plus-circle"></i> New Claim</a>
            <a href="/policies"><i class="fas fa-shield-alt"></i> Policies</a>
        </div>
        
        <!-- Adjuster Menu (Hidden for non-adjusters) -->
        <div class="sidebar-section adjuster-menu">
            <div class="sidebar-label">Adjuster</div>
            <a href="/adjuster/queue"><i class="fas fa-tasks"></i> Review Queue</a>
            <a href="/adjuster/reviews"><i class="fas fa-clipboard-check"></i> My Reviews</a>
        </div>

        <!-- Admin Menu (Hidden for non-admins) -->
        <div class="sidebar-section admin-menu">
            <div class="sidebar-label">Administration</div>
            <a href="/admin/users"><i class="fas fa-users-cog"></i> User Management</a>
            <a href="/admin/claims"><i class="fas fa-folder-open"></i> All Claims</a>
            <a href="/admin/reports"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="/admin/settings"><i class="fas fa-cog"></i> Settings</a>
        </div>
        
        <!-- Garage Menu (Hidden for non-garages) -->
        <div class="sidebar-section garage-menu">
            <div class="sidebar-label">Garage</div>
            <a href="/garage/estimates"><i class="fas fa-tools"></i> Estimates</a>
        </div>
    </nav>
</aside>
