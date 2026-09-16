<div class="page-head">
    <div>
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">Approve, suspend, and manage portal users</p>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="filter-group">
            <div class="search-box"><i class="fas fa-search"></i><input type="text" id="user-search" placeholder="Search name, email, role..."></div>
            <select id="filter-role">
                <option value="">All Roles</option>
                <option value="claimant">Claimant</option>
                <option value="adjuster">Adjuster</option>
                <option value="garage">Garage</option>
                <option value="admin">Admin</option>
            </select>
            <select id="filter-status">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <tr>
                    <td><div class="user-cell"><div class="avatar sm"><span>AW</span></div><div><strong>AutoHub Workshops</strong><small>garage@autohub.com</small></div></div></td>
                    <td><span class="pill pill-violet">Garage</span></td>
                    <td><span class="pill pill-amber">Pending</span></td>
                    <td class="muted-text">Never</td>
                    <td>Sep 09, 2026</td>
                    <td><div class="row-actions"><button class="btn btn-sm btn-success" data-action="approve">Approve</button><button class="btn btn-sm btn-outline" data-action="suspend">Suspend</button></div></td>
                </tr>
                <tr>
                    <td><div class="user-cell"><div class="avatar sm"><span>JM</span></div><div><strong>Joseph Mugisha</strong><small>joseph@avic.ug</small></div></div></td>
                    <td><span class="pill pill-blue">Adjuster</span></td>
                    <td><span class="pill pill-green">Active</span></td>
                    <td>Sep 12, 2026 · 8:41am</td>
                    <td>Jun 02, 2026</td>
                    <td><div class="row-actions"><button class="btn btn-sm btn-outline" data-action="suspend">Suspend</button></div></td>
                </tr>
                <tr>
                    <td><div class="user-cell"><div class="avatar sm"><span>JD</span></div><div><strong>John Doe</strong><small>john@example.com</small></div></div></td>
                    <td><span class="pill pill-green">Claimant</span></td>
                    <td><span class="pill pill-green">Active</span></td>
                    <td>Sep 13, 2026 · 9:12am</td>
                    <td>May 01, 2026</td>
                    <td><div class="row-actions"><button class="btn btn-sm btn-outline" data-action="suspend">Suspend</button><button class="btn btn-sm btn-outline" data-action="impersonate">View as</button></div></td>
                </tr>
                <tr>
                    <td><div class="user-cell"><div class="avatar sm"><span>SN</span></div><div><strong>Sarah Namuli</strong><small>sarah@example.com</small></div></div></td>
                    <td><span class="pill pill-green">Claimant</span></td>
                    <td><span class="pill pill-rose">Suspended</span></td>
                    <td>Aug 30, 2026 · 3:30pm</td>
                    <td>Jun 18, 2026</td>
                    <td><div class="row-actions"><button class="btn btn-sm btn-success" data-action="restore">Restore</button></div></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop hidden" id="impersonate-modal">
    <div class="modal">
        <h3>View as John Doe</h3>
        <p class="muted-text">You are impersonating this user. All actions will be logged. Click "Exit" to return to your admin session.</p>
        <div class="modal-actions">
            <button class="btn btn-outline" id="exit-impersonate">Exit Impersonation</button>
            <button class="btn btn-primary" id="confirm-impersonate">Continue</button>
        </div>
    </div>
</div>