<div class="dashboard">
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <span class="stat-value">3</span>
                <span class="stat-label">Active Claims</span>
            </div>
            <span class="stat-trend up"><i class="fas fa-arrow-up"></i> 2 this month</span>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <span class="stat-value">2</span>
                <span class="stat-label">Approved Claims</span>
            </div>
            <span class="stat-trend up"><i class="fas fa-arrow-up"></i> +1</span>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content">
                <span class="stat-value">4</span>
                <span class="stat-label">Under Review</span>
            </div>
            <span class="stat-trend neutral"><i class="fas fa-minus"></i> unchanged</span>
        </div>
        <div class="stat-card">
            <div class="stat-icon violet"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <span class="stat-value">UGX 4.2M</span>
                <span class="stat-label">Total Payouts</span>
            </div>
            <span class="stat-trend down"><i class="fas fa-arrow-down"></i> -8%</span>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <h3>Recent Claims</h3>
                <a href="/claims" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Claim No.</th><th>Type</th><th>Incident Date</th><th>Status</th><th>Amount</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr class="clickable" data-link="/claims/CLM-2026-00012">
                            <td><strong>CLM-2026-00012</strong></td>
                            <td>Collision</td>
                            <td>Sep 02, 2026</td>
                            <td><span class="pill pill-blue">Under Review</span></td>
                            <td>UGX 2,450,000</td>
                            <td><i class="fas fa-chevron-right"></i></td>
                        </tr>
                        <tr class="clickable" data-link="/claims/CLM-2026-00011">
                            <td><strong>CLM-2026-00011</strong></td>
                            <td>Theft</td>
                            <td>Aug 19, 2026</td>
                            <td><span class="pill pill-amber">Pending Docs</span></td>
                            <td>UGX 8,100,000</td>
                            <td><i class="fas fa-chevron-right"></i></td>
                        </tr>
                        <tr class="clickable" data-link="/claims/CLM-2026-00008">
                            <td><strong>CLM-2026-00008</strong></td>
                            <td>Vandalism</td>
                            <td>Jul 27, 2026</td>
                            <td><span class="pill pill-green">Paid</span></td>
                            <td>UGX 1,175,500</td>
                            <td><i class="fas fa-chevron-right"></i></td>
                        </tr>
                        <tr class="clickable" data-link="/claims/CLM-2026-00003">
                            <td><strong>CLM-2026-00003</strong></td>
                            <td>Collision</td>
                            <td>Jun 12, 2026</td>
                            <td><span class="pill pill-rose">Rejected</span></td>
                            <td>—</td>
                            <td><i class="fas fa-chevron-right"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="/claims/create" class="quick-action primary">
                    <i class="fas fa-file-medical-alt"></i>
                    <strong>File New Claim</strong>
                    <span>Start the multi-step wizard</span>
                </a>
                <a href="/claims/CLM-2026-00011" class="quick-action">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <strong>Upload Documents</strong>
                    <span>Complete pending requests</span>
                </a>
                <a href="/policies" class="quick-action">
                    <i class="fas fa-shield-alt"></i>
                    <strong>View Policies</strong>
                    <span>Check your coverage</span>
                </a>
                <a href="#" class="quick-action">
                    <i class="fas fa-file-pdf"></i>
                    <strong>Download Statement</strong>
                    <span>Monthly claims summary</span>
                </a>
            </div>
        </div>
    </div>
</div>