<div class="page-head">
    <div>
        <h1 class="page-title">Review Queue</h1>
        <p class="page-subtitle">Claims awaiting your decision</p>
    </div>
    <span class="result-count"><strong>7</strong> claims in queue</span>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div class="stat-content"><span class="stat-value">2</span><span class="stat-label">Overdue (SLA breached)</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-tasks"></i></div>
        <div class="stat-content"><span class="stat-value">5</span><span class="stat-label">Assigned to you</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon violet"><i class="fas fa-check-double"></i></div>
        <div class="stat-content"><span class="stat-value">12</span><span class="stat-label">Completed this week</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-tachometer-alt"></i></div>
        <div class="stat-content"><span class="stat-value">2.4d</span><span class="stat-label">Avg resolution time</span></div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="filter-group">
            <div class="search-box"><i class="fas fa-search"></i><input type="text" id="queue-search" placeholder="Search claim no., claimant..."></div>
            <select id="filter-priority">
                <option value="">All Priorities</option>
                <option value="overdue">Overdue</option>
                <option value="sla">Within SLA</option>
            </select>
            <select id="filter-sort">
                <option value="oldest">Sort: Oldest first</option>
                <option value="newest">Sort: Newest first</option>
                <option value="highest">Sort: Highest amount</option>
            </select>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Claim No.</th><th>Claimant</th><th>Type</th><th>Submitted</th><th>Est. Amount</th><th>SLA</th><th>Priority</th><th></th></tr>
            </thead>
            <tbody>
                <tr class="clickable clickable-overdue" data-link="/adjuster/review/CLM-2026-00015">
                    <td><strong>CLM-2026-00015</strong></td>
                    <td>Sarah Namuli</td>
                    <td>Collision</td>
                    <td>Aug 28, 2026</td>
                    <td>UGX 3,200,000</td>
                    <td><span class="sla-countdown overdue">13d 4h overdue</span></td>
                    <td><span class="pill pill-rose">High</span></td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/adjuster/review/CLM-2026-00012">
                    <td><strong>CLM-2026-00012</strong></td>
                    <td>John Doe</td>
                    <td>Collision</td>
                    <td>Sep 03, 2026</td>
                    <td>UGX 2,450,000</td>
                    <td><span class="sla-countdown warn">8d left</span></td>
                    <td><span class="pill pill-blue">Medium</span></td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/adjuster/review/CLM-2026-00014">
                    <td><strong>CLM-2026-00014</strong></td>
                    <td>David Ochieng</td>
                    <td>Theft</td>
                    <td>Sep 05, 2026</td>
                    <td>UGX 12,500,000</td>
                    <td><span class="sla-countdown warn">10d left</span></td>
                    <td><span class="pill pill-violet">High</span></td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/adjuster/review/CLM-2026-00016">
                    <td><strong>CLM-2026-00016</strong></td>
                    <td>Amina Kayondo</td>
                    <td>Fire</td>
                    <td>Sep 09, 2026</td>
                    <td>UGX 5,800,000</td>
                    <td><span class="sla-countdown ok">12d left</span></td>
                    <td><span class="pill pill-green">Normal</span></td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>