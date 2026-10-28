-- AVIC schema. Money is stored as whole Uganda shillings (BIGINT): UGX has no minor unit,
-- and integers keep every ledger sum exact.

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  phone         VARCHAR(25) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('claimant','adjuster') NOT NULL DEFAULT 'claimant',
  region        ENUM('central','eastern','northern','western') DEFAULT NULL,
  specialty     ENUM('collision','theft','fire','vandalism','natural_disaster','other') DEFAULT NULL,
  status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY u_users_email (email),
  KEY idx_users_role (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policies (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  policy_number  VARCHAR(30) NOT NULL,
  user_id        INT UNSIGNED DEFAULT NULL,
  insured_name   VARCHAR(100) NOT NULL,
  vehicle_make   VARCHAR(60) NOT NULL,
  vehicle_model  VARCHAR(60) NOT NULL,
  vehicle_year   SMALLINT UNSIGNED DEFAULT NULL,
  vehicle_plate  VARCHAR(20) NOT NULL,
  coverage_type  ENUM('third_party','comprehensive') NOT NULL,
  coverage_limit BIGINT UNSIGNED NOT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  status         ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY u_policies_number (policy_number),
  UNIQUE KEY u_policies_plate (vehicle_plate),
  KEY idx_policies_user (user_id),
  CONSTRAINT fk_policies_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE claims (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_number  VARCHAR(30) NOT NULL,
  policy_id     INT UNSIGNED NOT NULL,
  reported_by   INT UNSIGNED NOT NULL,
  assigned_to   INT UNSIGNED DEFAULT NULL,
  loss_date     DATE NOT NULL,
  loss_type     ENUM('collision','theft','fire','vandalism','natural_disaster','other') NOT NULL,
  loss_region   ENUM('central','eastern','northern','western') NOT NULL,
  loss_location VARCHAR(255) NOT NULL,
  description   TEXT NOT NULL,
  police_ref    VARCHAR(60) DEFAULT NULL,
  status        ENUM('open','investigating','settled','closed') NOT NULL DEFAULT 'open',
  close_outcome ENUM('paid','denied','withdrawn') DEFAULT NULL,
  flags         TEXT DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY u_claims_number (claim_number),
  KEY idx_claims_policy (policy_id),
  KEY idx_claims_assigned (assigned_to, status),
  KEY idx_claims_status (status),
  CONSTRAINT fk_claims_policy   FOREIGN KEY (policy_id)   REFERENCES policies (id),
  CONSTRAINT fk_claims_reporter FOREIGN KEY (reported_by) REFERENCES users (id),
  CONSTRAINT fk_claims_assigned FOREIGN KEY (assigned_to) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE exposures (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id      INT UNSIGNED NOT NULL,
  exposure_type ENUM('own_vehicle','third_party_vehicle','third_party_property','third_party_injury') NOT NULL,
  party_name    VARCHAR(100) NOT NULL,
  description   VARCHAR(500) DEFAULT NULL,
  status        ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exposures_claim (claim_id),
  CONSTRAINT fk_exposures_claim FOREIGN KEY (claim_id) REFERENCES claims (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id      INT UNSIGNED NOT NULL,
  exposure_id   INT UNSIGNED DEFAULT NULL,
  template_key  VARCHAR(60) DEFAULT NULL,
  subject       VARCHAR(150) NOT NULL,
  description   VARCHAR(500) DEFAULT NULL,
  priority      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  mandatory     TINYINT(1) NOT NULL DEFAULT 0,
  status        ENUM('open','completed','skipped') NOT NULL DEFAULT 'open',
  assigned_to   INT UNSIGNED DEFAULT NULL,
  due_date      DATE NOT NULL,
  created_by    INT UNSIGNED DEFAULT NULL,
  completed_by  INT UNSIGNED DEFAULT NULL,
  completed_at  TIMESTAMP NULL DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activities_claim (claim_id, status),
  KEY idx_activities_user (assigned_to, status, due_date),
  CONSTRAINT fk_activities_claim    FOREIGN KEY (claim_id)    REFERENCES claims (id) ON DELETE CASCADE,
  CONSTRAINT fk_activities_exposure FOREIGN KEY (exposure_id) REFERENCES exposures (id) ON DELETE SET NULL,
  CONSTRAINT fk_activities_assigned FOREIGN KEY (assigned_to) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id       INT UNSIGNED NOT NULL,
  exposure_id    INT UNSIGNED NOT NULL,
  type           ENUM('reserve','payment','recovery') NOT NULL,
  amount         BIGINT NOT NULL,
  payee          VARCHAR(120) DEFAULT NULL,
  payment_method ENUM('bank_transfer','cheque','mobile_money') DEFAULT NULL,
  reference      VARCHAR(80) DEFAULT NULL,
  memo           VARCHAR(255) DEFAULT NULL,
  created_by     INT UNSIGNED DEFAULT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tx_claim (claim_id),
  KEY idx_tx_exposure (exposure_id, type),
  CONSTRAINT chk_tx_amount CHECK (amount <> 0 AND (type = 'reserve' OR amount > 0)),
  CONSTRAINT fk_tx_claim    FOREIGN KEY (claim_id)    REFERENCES claims (id),
  CONSTRAINT fk_tx_exposure FOREIGN KEY (exposure_id) REFERENCES exposures (id),
  CONSTRAINT fk_tx_user     FOREIGN KEY (created_by)  REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id      INT UNSIGNED NOT NULL,
  uploaded_by   INT UNSIGNED NOT NULL,
  doc_type      ENUM('accident_photo','police_report','repair_estimate','medical_report','id_document','other') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(80) NOT NULL,
  mime_type     VARCHAR(80) NOT NULL,
  file_size     INT UNSIGNED NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_documents_claim (claim_id),
  CONSTRAINT fk_documents_claim FOREIGN KEY (claim_id)    REFERENCES claims (id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_user  FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id    INT UNSIGNED DEFAULT NULL,
  user_id     INT UNSIGNED DEFAULT NULL,
  action      VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) DEFAULT NULL,
  entity_id   INT UNSIGNED DEFAULT NULL,
  detail      TEXT DEFAULT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_claim (claim_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip    VARCHAR(45) NOT NULL,
  email VARCHAR(150) NOT NULL,
  ok    TINYINT(1) NOT NULL DEFAULT 0,
  at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_lookup (ip, email, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
