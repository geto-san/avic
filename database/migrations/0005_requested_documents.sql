CREATE TABLE IF NOT EXISTS requested_documents (
 id int unsigned AUTO_INCREMENT PRIMARY KEY,
 request_id int unsigned NOT NULL,
 document_type varchar(32) NOT NULL,
 document_name varchar(150) NOT NULL,
 document_id int unsigned NULL,
 uploaded_at timestamp NULL,
 FOREIGN KEY (request_id) REFERENCES claim_document_requests(id),
 FOREIGN KEY (document_id) REFERENCES claim_documents(id),
 KEY idx_requested_document_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO requested_documents (request_id, document_type, document_name)
 SELECT r.id, 'other', 'Requested documents' FROM claim_document_requests r
 WHERE r.status = 'open' AND NOT EXISTS (SELECT 1 FROM requested_documents d WHERE d.request_id = r.id);
