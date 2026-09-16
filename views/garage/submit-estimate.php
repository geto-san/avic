<div class="page-head">
    <div>
        <h1 class="page-title">Submit Repair Estimate</h1>
        <p class="page-subtitle">Provide a cost breakdown for the assigned claim</p>
    </div>
</div>

<div class="card">
    <div class="callout-info">
        <i class="fas fa-info-circle"></i>
        <div><strong>Work Order:</strong> You have been assigned to inspect claim <strong>CLM-2026-00012</strong> (Toyota Land Cruiser Prado - UAY 452J). Vehicle presented at your garage on Sep 06, 2026.</div>
    </div>
    <form id="estimate-form" class="form-grid2" novalidate>
        <div class="form-group">
            <label for="garage_name">Garage Name</label>
            <div class="input-wrap"><i class="fas fa-tools"></i><input type="text" id="garage_name" value="AutoHub Workshops" disabled></div>
        </div>
        <div class="form-group">
            <label for="claim_no">Claim Number</label>
            <div class="input-wrap"><i class="fas fa-file-alt"></i><input type="text" id="claim_no" value="CLM-2026-00012" disabled></div>
        </div>
        <div class="form-group">
            <label for="parts_cost">Parts Cost (UGX) <span class="req">*</span></label>
            <div class="input-wrap"><i class="fas fa-cog"></i><input type="number" id="parts_cost" name="parts_cost" min="0" step="1000" placeholder="0" required></div>
            <div class="field-error" hidden>Enter parts cost.</div>
        </div>
        <div class="form-group">
            <label for="labor_cost">Labor Cost (UGX) <span class="req">*</span></label>
            <div class="input-wrap"><i class="fas fa-wrench"></i><input type="number" id="labor_cost" name="labor_cost" min="0" step="1000" placeholder="0" required></div>
            <div class="field-error" hidden>Enter labor cost.</div>
        </div>
        <div class="form-group">
            <label for="other_cost">Other Costs (UGX)</label>
            <div class="input-wrap"><i class="fas fa-plus-circle"></i><input type="number" id="other_cost" name="other_cost" min="0" step="1000" placeholder="0"></div>
        </div>
        <div class="form-group">
            <label for="repair_days">Estimated Repair Days</label>
            <div class="input-wrap"><i class="fas fa-calendar-day"></i><input type="number" id="repair_days" name="repair_days" min="1" max="60" placeholder="e.g. 5"></div>
        </div>
        <div class="form-group full">
            <label>Itemized Breakdown</label>
            <div class="itemized-rows" id="itemized-rows">
                <div class="itemized-row">
                    <input type="text" placeholder="Item (e.g. front bumper)">
                    <input type="text" placeholder="Qty">
                    <input type="number" placeholder="Unit price (UGX)">
                    <button type="button" class="icon-btn remove-line"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" id="add-line"><i class="fas fa-plus"></i> Add Item</button>
        </div>
        <div class="form-group full">
            <label for="estimate_notes">Notes</label>
            <textarea id="estimate_notes" rows="4" placeholder="Any observations, parts availability notes, etc."></textarea>
        </div>
        <div class="form-group full totalizer">
            <span>Total Estimate</span>
            <strong id="estimate-total">UGX 0</strong>
        </div>
        <div class="form-group full">
            <div class="upload-zone compact" id="estimate-upload">
                <input type="file" id="estimate-file" accept=".pdf,image/*" hidden>
                <div class="upload-icon"><i class="fas fa-file-upload"></i></div>
                <p><strong>Attach the itemized estimate document</strong> or <span class="link">browse</span></p>
                <small>PDF or image · up to 10MB</small>
            </div>
        </div>
        <div class="form-group full form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Estimate</button>
        </div>
    </form>
</div>