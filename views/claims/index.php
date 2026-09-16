<div class="page-head">
    <div>
        <h1 class="page-title">My Claims</h1>
        <p class="page-subtitle">Track and manage all your insurance claims</p>
    </div>
    <a href="/claims/create" class="btn btn-primary"><i class="fas fa-plus"></i> New Claim</a>
</div>

<div class="card">
    <div class="filter-bar">
        <div class="filter-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="claim-search" placeholder="Search claim number, type...">
            </div>
            <select id="filter-status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
                <option value="under_review">Under Review</option>
                <option value="pending_docs">Pending Docs</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="paid">Paid</option>
                <option value="closed">Closed</option>
            </select>
            <select id="filter-type" class="filter-select">
                <option value="">All Types</option>
                <option value="collision">Collision</option>
                <option value="theft">Theft</option>
                <option value="vandalism">Vandalism</option>
                <option value="fire">Fire</option>
                <option value="natural_disaster">Natural Disaster</option>
                <option value="other">Other</option>
            </select>
            <button class="btn btn-outline" id="reset-filters">Reset</button>
        </div>
        <span class="result-count" id="result-count"></span>
    </div>

    <div class="table-wrap">
        <table class="claims-table">
            <thead>
                <tr>
                    <th>Claim No.</th>
                    <th>Type</th>
                    <th>Incident Date</th>
                    <th>Policy</th>
                    <th>Est. Damage</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr class="clickable" data-link="/claims/CLM-2026-00012">
                    <td><strong>CLM-2026-00012</strong></td>
                    <td><span class="badge-type">Collision</span></td>
                    <td>Sep 02, 2026</td>
                    <td>POL-10293</td>
                    <td>UGX 2,450,000</td>
                    <td><span class="pill pill-blue">Under Review</span></td>
                    <td>Sep 03, 2026</td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/claims/CLM-2026-00011">
                    <td><strong>CLM-2026-00011</strong></td>
                    <td><span class="badge-type">Theft</span></td>
                    <td>Aug 19, 2026</td>
                    <td>POL-10293</td>
                    <td>UGX 8,100,000</td>
                    <td><span class="pill pill-amber">Pending Docs</span></td>
                    <td>Aug 20, 2026</td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/claims/CLM-2026-00008">
                    <td><strong>CLM-2026-00008</strong></td>
                    <td><span class="badge-type">Vandalism</span></td>
                    <td>Jul 27, 2026</td>
                    <td>POL-10290</td>
                    <td>UGX 1,175,500</td>
                    <td><span class="pill pill-green">Paid</span></td>
                    <td>Jul 28, 2026</td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/claims/CLM-2026-00003">
                    <td><strong>CLM-2026-00003</strong></td>
                    <td><span class="badge-type">Collision</span></td>
                    <td>Jun 12, 2026</td>
                    <td>POL-10288</td>
                    <td>—</td>
                    <td><span class="pill pill-rose">Rejected</span></td>
                    <td>Jun 13, 2026</td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
                <tr class="clickable" data-link="/claims/CLM-2026-00001">
                    <td><strong>CLM-2026-00001</strong></td>
                    <td><span class="badge-type">Fire</span></td>
                    <td>May 08, 2026</td>
                    <td>POL-10275</td>
                    <td>UGX 5,660,000</td>
                    <td><span class="pill pill-green">Approved</span></td>
                    <td>May 09, 2026</td>
                    <td><i class="fas fa-chevron-right"></i></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>