CREATE TABLE IF NOT EXISTS submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_title VARCHAR(120) NOT NULL,
  job_small_script TEXT NULL,
  country VARCHAR(64) NOT NULL,
  state_province VARCHAR(128) NOT NULL,
  budget VARCHAR(20) NOT NULL,
  attachment_original_name VARCHAR(255) NULL,
  attachment_stored_name VARCHAR(255) NULL,
  attachment_extension VARCHAR(16) NULL,
  attachment_mime VARCHAR(127) NULL,
  attachment_size BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
