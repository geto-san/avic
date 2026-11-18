INSERT INTO payouts (claim_id, user_id, approved_by, amount, payment_method, reference_number, status)
 SELECT c.id, c.user_id, c.adjuster_id, c.approved_amount, 'mobile_money', CONCAT('PAY-RECOVERED-', c.id), 'pending'
 FROM claims c LEFT JOIN payouts p ON p.claim_id = c.id
 WHERE c.status = 'approved' AND c.approved_amount > 0 AND c.adjuster_id IS NOT NULL AND p.id IS NULL;

INSERT IGNORE INTO claim_settlements (claim_id)
 SELECT claim_id FROM payouts WHERE status IN ('pending','processing');
