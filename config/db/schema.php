<?php
declare(strict_types=1);

/**
 * Idempotent schema bootstrap. Run from the CLI:
 *   php config/db/schema.php
 *
 * Every CREATE TABLE is guarded by IF NOT EXISTS, so this is safe to run
 * against an existing database — it only adds anything that is missing.
 * The rest of the schema lives in the DB; this file lets a fresh install
 * (or this repo's own test database) reach the same shape.
 */

require_once __DIR__ . '/db_connection.php';

$tables = [];

$tables['users'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id int unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  full_name varchar(100) NOT NULL,
  email varchar(150) NOT NULL,
  phone varchar(20) DEFAULT NULL,
  garage_address varchar(255) DEFAULT NULL,
  trading_licence varchar(100) DEFAULT NULL,
  password_hash varchar(255) NOT NULL,
  role enum('claimant','adjuster','garage') NOT NULL DEFAULT 'claimant',
  status enum('active','suspended','pending','rejected') NOT NULL DEFAULT 'pending',
  avatar varchar(255) DEFAULT NULL,
  email_verified_at timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY u_users_uuid (uuid),
  UNIQUE KEY u_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['policies'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS policies (
  id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  policy_number varchar(50) NOT NULL,
  vehicle_make varchar(60) DEFAULT NULL,
  vehicle_model varchar(60) DEFAULT NULL,
  vehicle_year year DEFAULT NULL,
  vehicle_vin varchar(17) DEFAULT NULL,
  vehicle_plate varchar(20) DEFAULT NULL,
  coverage_type enum('basic','comprehensive','third_party') NOT NULL,
  coverage_limit decimal(12,2) NOT NULL,
  premium decimal(10,2) DEFAULT NULL,
  start_date date NOT NULL,
  end_date date NOT NULL,
  status enum('active','expired','cancelled') DEFAULT 'active',
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY u_policies_number (policy_number),
  UNIQUE KEY u_policies_vin (vehicle_vin),
  KEY idx_policies_user (user_id),
  KEY idx_policies_status (status),
  CONSTRAINT fk_policies_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['claims'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS claims (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_number varchar(25) NOT NULL,
  user_id int unsigned NOT NULL,
  policy_id int unsigned NOT NULL,
  adjuster_id int unsigned DEFAULT NULL,
  incident_date date NOT NULL,
  incident_location varchar(255) DEFAULT NULL,
  incident_lat decimal(10,7) DEFAULT NULL,
  incident_lng decimal(10,7) DEFAULT NULL,
  incident_description text NOT NULL,
  claim_type enum('collision','theft','vandalism','fire','natural_disaster','other') NOT NULL,
  estimated_damage decimal(12,2) DEFAULT NULL,
  approved_amount decimal(12,2) DEFAULT NULL,
  police_report_ref varchar(60) DEFAULT NULL,
  status enum('draft','submitted','under_review','pending_docs','approved','rejected','paid','closed') DEFAULT 'draft',
  rejection_reason text DEFAULT NULL,
  submitted_at timestamp NULL DEFAULT NULL,
  reviewed_at timestamp NULL DEFAULT NULL,
  resolved_at timestamp NULL DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY u_claims_number (claim_number),
  KEY idx_claims_policy (policy_id),
  KEY idx_claims_status (status),
  KEY idx_claims_user (user_id),
  KEY idx_claims_adjuster (adjuster_id),
  CONSTRAINT fk_claims_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_claims_policy FOREIGN KEY (policy_id) REFERENCES policies (id),
  CONSTRAINT fk_claims_adjuster FOREIGN KEY (adjuster_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['claim_documents'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS claim_documents (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_id int unsigned NOT NULL,
  uploaded_by int unsigned NOT NULL,
  doc_type enum('accident_photo','police_report','repair_estimate','medical_report','vehicle_photo','other') NOT NULL,
  original_name varchar(255) DEFAULT NULL,
  stored_name varchar(255) NOT NULL,
  file_path varchar(500) NOT NULL,
  file_size int unsigned DEFAULT NULL,
  mime_type varchar(80) DEFAULT NULL,
  is_verified tinyint(1) NOT NULL DEFAULT 0,
  verified_by int unsigned DEFAULT NULL,
  verified_at timestamp NULL DEFAULT NULL,
  notes text DEFAULT NULL,
  uploaded_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_docs_uploader (uploaded_by),
  KEY idx_docs_claim (claim_id),
  KEY idx_docs_type (doc_type),
  CONSTRAINT fk_docs_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE,
  CONSTRAINT fk_docs_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['garage_estimates'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS garage_estimates (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_id int unsigned NOT NULL,
  garage_user_id int unsigned DEFAULT NULL,
  garage_name varchar(120) NOT NULL,
  garage_address varchar(255) DEFAULT NULL,
  garage_phone varchar(25) DEFAULT NULL,
  parts_cost decimal(12,2) NOT NULL DEFAULT 0.00,
  labor_cost decimal(12,2) NOT NULL DEFAULT 0.00,
  other_cost decimal(12,2) NOT NULL DEFAULT 0.00,
  total_estimate decimal(12,2) NOT NULL,
  repair_days int unsigned DEFAULT NULL,
  estimate_doc_id int unsigned DEFAULT NULL,
  status enum('pending','approved','rejected') DEFAULT 'pending',
  adjuster_notes text DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimates_claim (claim_id),
  KEY idx_estimates_garage (garage_user_id),
  CONSTRAINT fk_estimates_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['work_orders'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS work_orders (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_id int unsigned NOT NULL,
  garage_user_id int unsigned NOT NULL,
  assigned_by int unsigned NOT NULL,
  assigned_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due date DEFAULT NULL,
  status enum('open','quoted','revision_requested','closed') NOT NULL DEFAULT 'open',
  PRIMARY KEY (id),
  KEY idx_wo_claim (claim_id),
  KEY idx_wo_garage (garage_user_id),
  CONSTRAINT fk_wo_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE,
  CONSTRAINT fk_wo_garage FOREIGN KEY (garage_user_id) REFERENCES users (id),
  CONSTRAINT fk_wo_assigner FOREIGN KEY (assigned_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['notifications'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS notifications (
  id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  claim_id int unsigned DEFAULT NULL,
  type varchar(60) NOT NULL,
  title varchar(150) NOT NULL,
  message text NOT NULL,
  is_read tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_claim (claim_id),
  KEY idx_notif_user_read (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['payouts'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS payouts (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_id int unsigned NOT NULL,
  user_id int unsigned NOT NULL,
  approved_by int unsigned NOT NULL,
  amount decimal(12,2) NOT NULL,
  payment_method enum('bank_transfer','cheque','mobile_money') NOT NULL,
  bank_name varchar(100) DEFAULT NULL,
  account_number varchar(60) DEFAULT NULL,
  account_name varchar(100) DEFAULT NULL,
  reference_number varchar(120) DEFAULT NULL,
  status enum('pending','processing','completed','failed') DEFAULT 'pending',
  processed_at timestamp NULL DEFAULT NULL,
  failure_reason varchar(255) DEFAULT NULL,
  notes text DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY u_payouts_ref (reference_number),
  KEY idx_payouts_claim (claim_id),
  KEY idx_payouts_user (user_id),
  KEY idx_payouts_approved (approved_by),
  CONSTRAINT fk_payouts_claim FOREIGN KEY (claim_id) REFERENCES claims (id),
  CONSTRAINT fk_payouts_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_payouts_approved FOREIGN KEY (approved_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['claim_status_history'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS claim_status_history (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_id int unsigned NOT NULL,
  changed_by int unsigned DEFAULT NULL,
  from_status varchar(30) DEFAULT NULL,
  to_status varchar(30) NOT NULL,
  notes text DEFAULT NULL,
  changed_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_history_claim (claim_id),
  CONSTRAINT fk_history_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['adjuster_reviews'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS adjuster_reviews (
  id int unsigned NOT NULL AUTO_INCREMENT,
  claim_id int unsigned NOT NULL,
  adjuster_id int unsigned NOT NULL,
  review_notes text DEFAULT NULL,
  recommended_amount decimal(12,2) DEFAULT NULL,
  decision enum('approve','reject','request_docs','escalate') NOT NULL,
  decision_reason text DEFAULT NULL,
  reviewed_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reviews_adjuster (adjuster_id),
  KEY idx_reviews_claim (claim_id),
  CONSTRAINT fk_reviews_claim FOREIGN KEY (claim_id) REFERENCES claims (id),
  CONSTRAINT fk_reviews_adjuster FOREIGN KEY (adjuster_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['audit_log'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS audit_log (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned DEFAULT NULL,
  action varchar(100) NOT NULL,
  entity_type varchar(50) DEFAULT NULL,
  entity_id int unsigned DEFAULT NULL,
  old_value longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  new_value longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  ip_address varchar(45) DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['login_attempts'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS login_attempts (
  id int unsigned NOT NULL AUTO_INCREMENT,
  ip varchar(45) NOT NULL,
  email varchar(150) NOT NULL,
  ok tinyint(1) DEFAULT 0,
  at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_ip_at (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['password_resets'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS password_resets (
  email varchar(150) NOT NULL,
  token char(64) NOT NULL,
  expires_at timestamp NOT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_resets_email (email),
  KEY idx_resets_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$tables['settings'] = <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
  key_name varchar(80) NOT NULL,
  value text DEFAULT NULL,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
SQL;

$created = [];
foreach ($tables as $name => $ddl) {
    $conn->exec($ddl);
    $created[] = $name;
}

if (PHP_SAPI === 'cli') {
    echo "Schema ready: " . implode(', ', $created) . "\n";
}