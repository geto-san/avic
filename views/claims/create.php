<div class="wizard">
    <div class="wizard-head">
        <div class="wizard-title">
            <h1>File a New Claim</h1>
            <p>Complete the steps below to submit your claim</p>
        </div>
        <div class="autosave-status" id="autosave-status">
            <i class="fas fa-check-circle"></i> <span>Autosaved just now</span>
            <small class="hidden">— </small>
        </div>
    </div>

    <ol class="step-indicator">
        <li class="done"><span class="step-num">1</span><span class="step-label">Policy</span></li>
        <li class="current"><span class="step-num">2</span><span class="step-label">Incident</span></li>
        <li><span class="step-num">3</span><span class="step-label">Damage</span></li>
        <li><span class="step-num">4</span><span class="step-label">Documents</span></li>
        <li><span class="step-num">5</span><span class="step-label">Review</span></li>
    </ol>

    <form id="claim-form" class="wizard-body" novalidate>
        <!-- STEP 1: POLICY -->
        <section class="wizard-step active" data-step="1">
            <h3 class="step-title">Select Your Policy</h3>
            <p class="step-desc">Choose the policy this claim applies to. We'll verify coverage automatically.</p>
            <div class="policy-lookup">
                <div class="input-wrap large">
                    <i class="fas fa-search"></i>
                    <input type="text" id="policy-search" placeholder="Enter policy number or vehicle plate..." autocomplete="off" required>
                    <button type="button" class="btn btn-primary lookup-btn">Look Up</button>
                </div>
                <div class="field-error" hidden>Please enter a policy number.</div>
            </div>
            <div id="policy-result" class="policy-card hidden">
                <div class="policy-vehicle">
                    <div class="vehicle-icon"><i class="fas fa-car"></i></div>
                    <div>
                        <strong>Toyota Land Cruiser Prado</strong>
                        <span>2019 · UAY 452J · VIN JTEBU4BF9K5XXXXXX</span>
                    </div>
                </div>
                <div class="policy-meta">
                    <div><span>Policy</span><strong>POL-10293</strong></div>
                    <div><span>Coverage</span><strong>Comprehensive</strong></div>
                    <div><span>Limit</span><strong>UGX 40,000,000</strong></div>
                    <div><span>Expires</span><strong>Dec 31, 2026</strong></div>
                </div>
                <div class="policy-status"><span class="pill pill-green">Active</span> Eligible for claims</div>
            </div>
        </section>

        <!-- STEP 2: INCIDENT -->
        <section class="wizard-step" data-step="2">
            <h3 class="step-title">Incident Details</h3>
            <p class="step-desc">Tell us what happened. Photos will come in the next steps.</p>
            <div class="form-grid2">
                <div class="form-group">
                    <label for="incident_date">Date of Incident <span class="req">*</span></label>
                    <div class="input-wrap"><i class="fas fa-calendar"></i><input type="date" id="incident_date" name="incident_date" required></div>
                    <div class="field-error" hidden>Date of incident is required.</div>
                </div>
                <div class="form-group">
                    <label for="claim_type">Claim Type <span class="req">*</span></label>
                    <div class="input-wrap"><i class="fas fa-tag"></i>
                        <select id="claim_type" name="claim_type" required>
                            <option value="">Select claim type...</option>
                            <option>Collision</option><option>Theft</option><option>Vandalism</option>
                            <option>Fire</option><option>Natural Disaster</option><option>Other</option>
                        </select>
                    </div>
                    <div class="field-error" hidden>Please select a claim type.</div>
                </div>
                <div class="form-group full">
                    <label for="incident_location">Incident Location <span class="req">*</span></label>
                    <div class="input-wrap"><i class="fas fa-map-marker-alt"></i><input type="text" id="incident_location" name="incident_location" placeholder="e.g. Nakawa Roundabout, Kampala" required></div>
                    <div class="field-error" hidden>Location is required.</div>
                </div>
                <div class="form-group full">
                    <label for="police_report_ref">Police Report Reference <span class="opt">optional</span></label>
                    <div class="input-wrap"><i class="fas fa-file-invoice"></i><input type="text" id="police_report_ref" name="police_report_ref" placeholder="e.g. KLA/CRB/2026/12345"></div>
                </div>
                <div class="form-group full">
                    <label for="incident_description">Describe the Incident <span class="req">*</span></label>
                    <textarea id="incident_description" name="incident_description" rows="5" placeholder="Provide a detailed account of what happened, weather/visibility, witnesses, etc." required></textarea>
                    <div class="field-error" hidden>Please describe the incident.</div>
                    <span class="char-count"><span id="char-count">0</span>/1600</span>
                </div>
            </div>
        </section>

        <!-- STEP 3: DAMAGE -->
        <section class="wizard-step" data-step="3">
            <h3 class="step-title">Damage Assessment</h3>
            <p class="step-desc">Estimate the extent of damage to your vehicle.</p>
            <div class="form-grid2">
                <div class="form-group">
                    <label for="estimated_damage">Estimated Damage (UGX) <span class="req">*</span></label>
                    <div class="input-wrap"><i class="fas fa-money-bill"></i><input type="number" id="estimated_damage" name="estimated_damage" min="0" step="10000" placeholder="e.g. 2500000" required></div>
                    <div class="field-error" hidden>Please enter an amount.</div>
                </div>
                <div class="form-group">
                    <label>Vehicle Driveable? <span class="req">*</span></label>
                    <div class="segmented" id="driveable">
                        <button type="button" class="active" data-value="yes">Yes</button>
                        <button type="button" data-value="no">No</button>
                    </div>
                </div>
                <div class="form-group full">
                    <label>Towing / Recovery Needed?</label>
                    <label class="checkbox-label"><input type="checkbox" name="needs_towing"> Vehicle requires towing to a garage</label>
                </div>
            </div>
            <div class="callout-info">
                <i class="fas fa-info-circle"></i>
                <div>A licensed garage will provide a formal repair estimate during review. Your rough estimate here helps us prioritise.</div>
            </div>
        </section>

        <!-- STEP 4: DOCUMENTS -->
        <section class="wizard-step" data-step="4">
            <h3 class="step-title">Supporting Documents</h3>
            <p class="step-desc">Upload photos of the damage and any related documents.</p>
            <div class="upload-zone" id="upload-zone">
                <input type="file" id="file-input" multiple accept="image/*,.pdf" hidden>
                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <p><strong>Drag &amp; drop files here</strong> or <span class="link">browse</span></p>
                <small>JPG, PNG or PDF · up to 10MB each · max 8 files</small>
            </div>
            <div class="upload-type-tabs" id="upload-type-tabs">
                <button type="button" class="active" data-type="accident_photo">Accident Photo</button>
                <button type="button" data-type="police_report">Police Report</button>
                <button type="button" data-type="repair_estimate">Repair Estimate</button>
                <button type="button" data-type="other">Other</button>
            </div>
            <div class="upload-preview" id="upload-preview"></div>
        </section>

        <!-- STEP 5: REVIEW -->
        <section class="wizard-step" data-step="5">
            <h3 class="step-title">Review &amp; Submit</h3>
            <p class="step-desc">Confirm the details below before submitting.</p>
            <div class="review-summary" id="review-summary"></div>
            <label class="checkbox-label agree">
                <input type="checkbox" name="agree" id="agree" required>
                <span>I confirm that the information provided is accurate and that I consent to AVIC processing my claim.</span>
            </label>
            <div class="field-error" id="agree-error" hidden>You must agree before submitting.</div>
        </section>

        <!-- NAV -->
        <div class="wizard-nav">
            <button type="button" class="btn btn-outline" id="prev-step" disabled><i class="fas fa-arrow-left"></i> Back</button>
            <div class="wizard-nav-right">
                <span class="step-progress-label" id="step-progress-label">Step 1 of 5</span>
                <button type="button" class="btn btn-primary" id="next-step">Continue <i class="fas fa-arrow-right"></i></button>
                <button type="submit" class="btn btn-success" id="submit-step" hidden><i class="fas fa-paper-plane"></i> Submit Claim</button>
            </div>
        </div>
    </form>
</div>