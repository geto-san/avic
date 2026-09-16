<div class="page-head">
    <div>
        <h1 class="page-title">Payments</h1>
        <p class="page-subtitle">Process approved claims and generate receipts</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-content"><span class="stat-value">4</span><span class="stat-label">Pending Payouts</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-spinner"></i></div>
        <div class="stat-content"><span class="stat-value">2</span><span class="stat-label">Processing</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content"><span class="stat-value">UGX 86.4M</span><span class="stat-label">Completed</span></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Pending Payouts</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Claim No.</th><th>Claimant</th><th>Approved</th><th>Amount</th><th></th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>CLM-2026-00014</strong></td>
                    <td>David Ochieng</td>
                    <td>Sep 10, 2026</td>
                    <td><strong>UGX 12,500,000</strong></td>
                    <td><button class="btn btn-sm btn-primary" data-process-payout="CLM-2026-00014"><i class="fas fa-hand-holding-usd"></i> Process Payout</button></td>
                </tr>
                <tr>
                    <td><strong>CLM-2026-00001</strong></td>
                    <td>John Doe</td>
                    <td>Sep 02, 2026</td>
                    <td><strong>UGX 5,660,000</strong></td>
                    <td><button class="btn btn-sm btn-primary" data-process-payout="CLM-2026-00001"><i class="fas fa-hand-holding-usd"></i> Process Payout</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop hidden" id="payout-modal">
    <div class="modal">
        <h3>Process Payout</h3>
        <p class="muted-text">Claim <strong id="payout-modal-claim">CLM-2026-00014</strong> · Approved amount: <strong>UGX 12,500,000</strong></p>
        <form id="payout-form" class="form-stack" novalidate>
            <div class="form-group">
                <label for="payment_method">Payment Method <span class="req">*</span></label>
                <div class="input-wrap"><i class="fas fa-credit-card"></i>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select method...</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="account_name">Account / Wallet Name <span class="req">*</span></label>
                <div class="input-wrap"><i class="fas fa-user"></i><input type="text" id="account_name" name="account_name" placeholder="e.g. David Ochieng" required></div>
            </div>
            <div class="form-group">
                <label for="bank_name">Bank or Provider</label>
                <div class="input-wrap"><i class="fas fa-university"></i><input type="text" id="bank_name" name="bank_name" placeholder="e.g. Stanbic Bank / MTN MoMo"></div>
            </div>
            <div class="form-group">
                <label for="account_number">Account / Phone Number</label>
                <div class="input-wrap"><i class="fas fa-hashtag"></i><input type="text" id="account_number" name="account_number" placeholder="e.g. 10123xxxx / 256700000000"></div>
            </div>
            <div class="form-group">
                <label for="payout_notes">Notes</label>
                <textarea id="payout_notes" rows="3" placeholder="Anything the finance team should know"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" id="cancel-payout">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Initiate Payout</button>
            </div>
        </form>
    </div>
</div>