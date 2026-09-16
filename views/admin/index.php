<div class="page-head">
    <div>
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="page-subtitle">System-wide overview</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div class="stat-content"><span class="stat-value">142</span><span class="stat-label">Registered Users</span></div>
        <span class="stat-trend up"><i class="fas fa-arrow-up"></i> 12 new</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon violet"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-content"><span class="stat-value">87</span><span class="stat-label">Total Claims</span></div>
        <span class="stat-trend up"><i class="fas fa-arrow-up"></i> +9</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-content"><span class="stat-value">UGX 86.4M</span><span class="stat-label">Total Paid Out</span></div>
        <span class="stat-trend neutral"><i class="fas fa-minus"></i> this quarter</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div class="stat-content"><span class="stat-value">4.1d</span><span class="stat-label">Avg Resolution Time</span></div>
        <span class="stat-trend up"><i class="fas fa-arrow-down"></i> improved</span>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><h3>Claims by Status</h3><select class="filter-select" id="range-select"><option>Last 30 days</option><option>This quarter</option><option>Year to date</option></select></div>
        <div class="chart-wrap" id="claims-chart">
            <canvas width="600" height="300"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>System Alerts</h3></div>
        <div class="alert-list">
            <div class="alert-item danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div><strong>SLA breach</strong><span>CLM-2026-00015 is 13d overdue.</span></div>
            </div>
            <div class="alert-item warning">
                <i class="fas fa-clock"></i>
                <div><strong>Pending approvals</strong><span>3 garages awaiting account approval.</span></div>
            </div>
            <div class="alert-item info">
                <i class="fas fa-server"></i>
                <div><strong>Storage</strong><span>Uploads at 72% of 5GB allowance.</span></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Recent Activity</h3><a href="/admin/reports" class="btn btn-sm btn-outline">Full Reports</a></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Action</th><th>Entity</th><th>Time</th><th>IP</th></tr></thead>
            <tbody>
                <tr><td>John Doe</td><td>claim.status_changed</td><td>CLM-2026-00012</td><td>Sep 07, 2026 11:30am</td><td>192.168.1.7</td></tr>
                <tr><td>Admin</td><td>user.role_changed</td><td>user#81</td><td>Sep 06, 2026 4:12pm</td><td>192.168.1.2</td></tr>
                <tr><td>AutoHub</td><td>estimate.submitted</td><td>CLM-2026-00012</td><td>Sep 06, 2026 2:05pm</td><td>192.168.1.33</td></tr>
                <tr><td>Sarah Namuli</td><td>claim.submitted</td><td>CLM-2026-00015</td><td>Sep 05, 2026 9:01am</td><td>102.211.40.9</td></tr>
            </tbody>
        </table>
    </div>
</div>