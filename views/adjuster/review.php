<div class="review-page">
    <div class="detail-head">
        <div>
            <a href="/adjuster/queue" class="back-link"><i class="fas fa-arrow-left"></i> Back to Queue</a>
            <div class="detail-title-row">
                <h1 class="page-title">CLM-2026-00012</h1>
                <span class="pill pill-blue">Under Review</span>
                <span class="sla-countdown warn">8d left before SLA breach</span>
            </div>
            <p>Filed by John Doe · Sep 03, 2026</p>
        </div>
    </div>

    <div class="review-grid">
        <div class="review-left">
            <div class="card">
                <div class="card-header">
                    <h3>Claim Details</h3>
                </div>
                <dl class="detail-list">
                    <dt>Claimant</dt><dd>John Doe · john@example.com</dd>
                    <dt>Vehicle</dt><dd>Toyota Land Cruiser Prado · 2019</dd>
                    <dt>Plate / VIN</dt><dd>UAY 452J / JTEBU4BF9K5XXXXXX</dd>
                    <dt>Policy</dt><dd>POL-10293 · Comprehensive · Limit UGX 40M</dd>
                    <dt>Claim Type</dt><dd>Collision</dd>
                    <dt>Incident</dt><dd>Sep 02, 2026 · Nakawa Roundabout</dd>
                    <dt>Police Ref</dt><dd>KLA/CRB/2026/12345</dd>
                    <dt>Claimant Est.</dt><dd>UGX 2,450,000</dd>
                </dl>
                <div class="divider"></div>
                <h4 class="label-mini">Claimant Description</h4>
                <p class="muted-text">Rear-end collision while stopped at traffic lights. Other party failed to brake in time. Three witnesses on scene. No injuries reported.</p>
                <div class="divider"></div>
                <h4 class="label-mini">Garage Estimate</h4>
                <div class="estimate-breakdown">
                    <div><span>Parts</span><strong>UGX 1,520,000</strong></div>
                    <div><span>Labor</span><strong>UGX 730,000</strong></div>
                    <div><span>Other</span><strong>UGX 200,000</strong></div>
                    <div class="total"><span>Total</span><strong>UGX 2,450,000</strong></div>
                </div>
                <p class="muted-text small">Submitted by AutoHub Workshops · Sep 07, 2026 · est. 5 repair days</p>
            </div>
            <div class="card">
                <div class="card-header"><h3>Documents</h3></div>
                <div class="document-grid">
                    <div class="doc-card">
                        <div class="doc-icon pdf"><i class="fas fa-file-pdf"></i></div>
                        <div class="doc-meta"><strong>police_report_scan.pdf</strong><span>Police Report · 1.2 MB</span></div>
                        <div class="doc-status"><span class="pill pill-green">Verified</span></div>
                        <button class="icon-btn" data-view-doc><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="doc-card">
                        <div class="doc-icon img"><i class="fas fa-image"></i></div>
                        <div class="doc-meta"><strong>damage_front.jpg</strong><span>Accident Photo · 3.4 MB</span></div>
                        <div class="doc-status"><span class="pill pill-amber">Unverified</span></div>
                        <button class="icon-btn" data-view-doc><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="doc-card">
                        <div class="doc-icon img"><i class="fas fa-image"></i></div>
                        <div class="doc-meta"><strong>damage_rear.jpg</strong><span>Accident Photo · 2.8 MB</span></div>
                        <div class="doc-status"><span class="pill pill-amber">Unverified</span></div>
                        <button class="icon-btn" data-view-doc><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="review-right">
            <div class="card">
                <div class="card-header"><h3>Make a Decision</h3></div>
                <form id="review-form" class="review-form" novalidate>
                    <div class="form-group">
                        <label for="decision">Decision <span class="req">*</span></label>
                        <div class="decision-options">
                            <label class="decision-option">
                                <input type="radio" name="decision" value="approve" checked>
                                <span class="decision-box approve"><i class="fas fa-check"></i><strong>Approve</strong><small>Accept, proceed to payout</small></span>
                            </label>
                            <label class="decision-option">
                                <input type="radio" name="decision" value="reject">
                                <span class="decision-box reject"><i class="fas fa-times"></i><strong>Reject</strong><small>Deny this claim</small></span>
                            </label>
                            <label class="decision-option">
                                <input type="radio" name="decision" value="docs">
                                <span class="decision-box docs"><i class="fas fa-folder-open"></i><strong>Request Docs</strong><small>Ask claimant for more</small></span>
                            </label>
                            <label class="decision-option">
                                <input type="radio" name="decision" value="escalate">
                                <span class="decision-box escalate"><i class="fas fa-exclamation-triangle"></i><strong>Escalate</strong><small>Flag for admin</small></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group" id="amount-group">
                        <label for="recommended_amount">Approved Amount (UGX)</label>
                        <div class="input-wrap"><i class="fas fa-money-bill"></i><input type="number" id="recommended_amount" name="recommended_amount" min="0" step="10000" value="2450000"></div>
                        <small class="hint">Cannot exceed policy limit minus prior payouts (UGX 37,550,000 available).</small>
                    </div>
                    <div class="form-group">
                        <label for="review_notes">Review Notes <span class="req">*</span></label>
                        <textarea id="review_notes" name="review_notes" rows="5" required></textarea>
                        <div class="field-error" hidden>Notes are required.</div>
                    </div>
                    <div class="form-group">
                        <label for="reason" id="reason-label">Rejection / Request Reason</label>
                        <textarea id="reason" name="reason" rows="3" hidden></textarea>
                    </div>
                    <div class="review-form-actions">
                        <button type="button" class="btn btn-outline" id="mark-verified">Mark Documents Verified</button>
                        <button type="submit" class="btn btn-primary">Submit Decision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>