-- The workflow creates one settlement per claim. Enforce that invariant in SQL too.
CREATE UNIQUE INDEX u_payouts_claim ON payouts (claim_id);
