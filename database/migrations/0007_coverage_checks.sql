CREATE TABLE IF NOT EXISTS staff_permissions (
 user_id INT UNSIGNED NOT NULL,
 permission VARCHAR(80) NOT NULL,
 PRIMARY KEY (user_id, permission),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coverage_checks (
 claim_id INT UNSIGNED PRIMARY KEY,
 due_at DATETIME NOT NULL,
 completed_by INT UNSIGNED NULL,
 completed_at DATETIME NULL,
 evidence VARCHAR(500) NULL,
 INDEX idx_coverage_due (completed_at, due_at),
 FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
 FOREIGN KEY (completed_by) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO coverage_checks (claim_id, due_at)
SELECT c.id, DATE_ADD(COALESCE(c.submitted_at, c.created_at), INTERVAL GREATEST(1, COALESCE((SELECT CAST(value AS SIGNED) FROM settings WHERE key_name = 'claims_sla_days'), 14)) DAY)
FROM claims c LEFT JOIN coverage_checks t ON t.claim_id = c.id
WHERE c.status IN ('submitted','pending_docs','under_review') AND t.claim_id IS NULL;
