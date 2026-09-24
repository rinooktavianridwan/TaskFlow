CREATE TABLE notification_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    notification_type VARCHAR(50) NOT NULL COMMENT 'Contoh: VERIFICATION, INVITATION, REMINDER',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'Contoh: PENDING, SUCCESS, FAILED',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Menambahkan index untuk mempercepat pencarian log berdasarkan email atau status
    INDEX idx_recipient_email (recipient_email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
