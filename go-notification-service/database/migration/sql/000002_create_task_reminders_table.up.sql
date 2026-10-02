CREATE TABLE task_reminders (
    task_id BIGINT NOT NULL PRIMARY KEY,
    task_title VARCHAR(255) NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    due_at DATETIME(6) NOT NULL,
    remind_at DATETIME(6) NOT NULL,
    next_attempt_at DATETIME(6) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_at DATETIME(6) NULL,
    last_error TEXT NULL,
    sent_at DATETIME(6) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_task_reminders_due (status, next_attempt_at),
    INDEX idx_task_reminders_locked (status, locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;