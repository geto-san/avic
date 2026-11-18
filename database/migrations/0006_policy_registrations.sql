CREATE TABLE IF NOT EXISTS policy_registrations (
 id int unsigned AUTO_INCREMENT PRIMARY KEY,
 user_id int unsigned NOT NULL,
 policy_number varchar(50) NOT NULL,
 insurer_name varchar(150) NOT NULL,
 details longtext NOT NULL,
 status enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
 review_notes text NULL,
 reviewed_by int unsigned NULL,
 reviewed_at timestamp NULL,
 policy_id int unsigned NULL,
 created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY u_registered_policy_number (policy_number),
 UNIQUE KEY u_registered_policy_id (policy_id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id),
 FOREIGN KEY (policy_id) REFERENCES policies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
