CREATE TABLE IF NOT EXISTS claim_document_requests (
 id int unsigned AUTO_INCREMENT PRIMARY KEY,
 claim_id int unsigned NOT NULL,
 requested_by int unsigned NOT NULL,
 details text NOT NULL,
 status enum('open','answered') NOT NULL DEFAULT 'open',
 created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 answered_at timestamp NULL,
 FOREIGN KEY (claim_id) REFERENCES claims(id),
 FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS claim_settlements (
 claim_id int unsigned PRIMARY KEY,
 status enum('pending','accepted','disputed') NOT NULL DEFAULT 'pending',
 accepted_by int unsigned NULL,
 accepted_at timestamp NULL,
 payment_method enum('bank_transfer','cheque','mobile_money') NULL,
 account_name varchar(100) NULL,
 account_number varchar(60) NULL,
 bank_name varchar(100) NULL,
 FOREIGN KEY (claim_id) REFERENCES claims(id),
 FOREIGN KEY (accepted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payout_evidence (
 payout_id int unsigned PRIMARY KEY,
 external_reference varchar(120) NOT NULL,
 verification_note text NOT NULL,
 confirmed_by int unsigned NOT NULL,
 confirmed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY u_external_payment_reference (external_reference),
 FOREIGN KEY (payout_id) REFERENCES payouts(id),
 FOREIGN KEY (confirmed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS claim_complaints (
 id int unsigned AUTO_INCREMENT PRIMARY KEY,
 claim_id int unsigned NOT NULL,
 opened_by int unsigned NOT NULL,
 reviewer_id int unsigned NOT NULL,
 details text NOT NULL,
 status enum('open','under_review','resolved') NOT NULL DEFAULT 'open',
 response text NULL,
 created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at timestamp NULL,
 resolved_at timestamp NULL,
 FOREIGN KEY (claim_id) REFERENCES claims(id),
 FOREIGN KEY (opened_by) REFERENCES users(id),
 FOREIGN KEY (reviewer_id) REFERENCES users(id),
 KEY idx_complaint_reviewer (reviewer_id,status),
 KEY idx_complaint_claim (claim_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO claim_settlements (claim_id) SELECT claim_id FROM payouts WHERE status IN ('pending','processing');
