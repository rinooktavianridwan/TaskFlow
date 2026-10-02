package repositories

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"time"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

type notificationRepositoryImpl struct {
	db *sql.DB
}

func NewNotificationRepository(db *sql.DB) contract.Repository {
	return &notificationRepositoryImpl{db: db}
}

func (r *notificationRepositoryImpl) SaveNotificationLog(
	email string,
	notificationType values.NotificationType,
	status values.DeliveryStatus,
	errorMessage string,
) error {
	const query = `
		INSERT INTO notification_logs (
			recipient_email,
			notification_type,
			status,
			error_message
		)
		VALUES (?, ?, ?, NULLIF(?, ''))
	`

	_, err := r.db.Exec(
		query,
		email,
		string(notificationType),
		string(status),
		errorMessage,
	)
	if err != nil {
		return fmt.Errorf("save notification log: %w", err)
	}

	return nil
}

func (r *notificationRepositoryImpl) UpsertTaskReminder(
	ctx context.Context,
	reminder contract.TaskReminder,
) error {
	tx, err := r.db.BeginTx(ctx, nil)
	if err != nil {
		return fmt.Errorf("begin upsert task reminder %d: %w", reminder.TaskID, err)
	}
	defer tx.Rollback()

	var (
		currentStatus string
		currentDueAt  time.Time
		currentEmail  string
	)

	err = tx.QueryRowContext(
		ctx,
		`SELECT status, due_at, recipient_email
		 FROM task_reminders
		 WHERE task_id = ?
		 FOR UPDATE`,
		reminder.TaskID,
	).Scan(&currentStatus, &currentDueAt, &currentEmail)

	switch {
	case errors.Is(err, sql.ErrNoRows):
		_, err = tx.ExecContext(
			ctx,
			`INSERT INTO task_reminders (
				task_id, task_title, project_name, recipient_email,
				due_at, remind_at, next_attempt_at, status
			)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
			reminder.TaskID,
			reminder.TaskTitle,
			reminder.ProjectName,
			reminder.RecipientEmail,
			reminder.DueAt.UTC(),
			reminder.RemindAt.UTC(),
			reminder.NextAttemptAt.UTC(),
			string(values.TaskReminderStatusScheduled),
		)

	case err != nil:
		return fmt.Errorf("lock task reminder %d: %w", reminder.TaskID, err)

	// Jadwal dan penerima sama dengan yang sudah terkirim (atau sedang dikirim):
	// jangan dikirim ulang, cukup perbarui teksnya.
	case (currentStatus == string(values.TaskReminderStatusSent) ||
		currentStatus == string(values.TaskReminderStatusProcessing)) &&
		currentDueAt.Equal(reminder.DueAt) &&
		currentEmail == reminder.RecipientEmail:
		_, err = tx.ExecContext(
			ctx,
			`UPDATE task_reminders SET task_title = ?, project_name = ? WHERE task_id = ?`,
			reminder.TaskTitle,
			reminder.ProjectName,
			reminder.TaskID,
		)

	default:
		_, err = tx.ExecContext(
			ctx,
			`UPDATE task_reminders
			 SET task_title = ?,
				 project_name = ?,
				 recipient_email = ?,
				 due_at = ?,
				 remind_at = ?,
				 next_attempt_at = ?,
				 status = ?,
				 attempt_count = 0,
				 locked_at = NULL,
				 last_error = NULL,
				 sent_at = NULL
			 WHERE task_id = ?`,
			reminder.TaskTitle,
			reminder.ProjectName,
			reminder.RecipientEmail,
			reminder.DueAt.UTC(),
			reminder.RemindAt.UTC(),
			reminder.NextAttemptAt.UTC(),
			string(values.TaskReminderStatusScheduled),
			reminder.TaskID,
		)
	}

	if err != nil {
		return fmt.Errorf("upsert task reminder %d: %w", reminder.TaskID, err)
	}

	if err := tx.Commit(); err != nil {
		return fmt.Errorf("commit upsert task reminder %d: %w", reminder.TaskID, err)
	}

	return nil
}

func (r *notificationRepositoryImpl) CancelTaskReminder(
	ctx context.Context,
	taskID int64,
) error {
	const query = `
		UPDATE task_reminders
		SET status = ?,
			locked_at = NULL
		WHERE task_id = ?
			AND status IN (?, ?)
	`

	_, err := r.db.ExecContext(
		ctx,
		query,
		string(values.TaskReminderStatusCancelled),
		taskID,
		string(values.TaskReminderStatusScheduled),
		string(values.TaskReminderStatusProcessing),
	)
	if err != nil {
		return fmt.Errorf("cancel task reminder %d: %w", taskID, err)
	}

	return nil
}

func (r *notificationRepositoryImpl) ClaimDueTaskReminders(
	ctx context.Context,
	now time.Time,
	staleBefore time.Time,
	limit int,
) ([]contract.TaskReminder, error) {
	if limit < 1 {
		return nil, fmt.Errorf("claim limit must be positive")
	}

	tx, err := r.db.BeginTx(ctx, nil)
	if err != nil {
		return nil, fmt.Errorf("begin claim transaction: %w", err)
	}
	defer tx.Rollback()

	const selectQuery = `
		SELECT
			task_id,
			task_title,
			project_name,
			recipient_email,
			due_at,
			remind_at,
			next_attempt_at,
			attempt_count
		FROM task_reminders
		WHERE
			(status = ? AND next_attempt_at <= ?)
			OR
			(status = ? AND locked_at IS NOT NULL AND locked_at <= ?)
		ORDER BY next_attempt_at ASC
		LIMIT ?
		FOR UPDATE SKIP LOCKED
	`

	rows, err := tx.QueryContext(
		ctx,
		selectQuery,
		string(values.TaskReminderStatusScheduled),
		now.UTC(),
		string(values.TaskReminderStatusProcessing),
		staleBefore.UTC(),
		limit,
	)
	if err != nil {
		return nil, fmt.Errorf("select due task reminders: %w", err)
	}

	reminders := make([]contract.TaskReminder, 0, limit)

	for rows.Next() {
		var reminder contract.TaskReminder

		if err := rows.Scan(
			&reminder.TaskID,
			&reminder.TaskTitle,
			&reminder.ProjectName,
			&reminder.RecipientEmail,
			&reminder.DueAt,
			&reminder.RemindAt,
			&reminder.NextAttemptAt,
			&reminder.AttemptCount,
		); err != nil {
			rows.Close()
			return nil, fmt.Errorf("scan due task reminder: %w", err)
		}

		reminders = append(reminders, reminder)
	}

	if err := rows.Err(); err != nil {
		rows.Close()
		return nil, fmt.Errorf("read due task reminders: %w", err)
	}

	if err := rows.Close(); err != nil {
		return nil, fmt.Errorf("close due task reminder rows: %w", err)
	}

	const claimQuery = `
		UPDATE task_reminders
		SET status = ?,
			locked_at = ?,
			attempt_count = attempt_count + 1
		WHERE task_id = ?
	`

	for i := range reminders {
		if _, err := tx.ExecContext(
			ctx,
			claimQuery,
			string(values.TaskReminderStatusProcessing),
			now.UTC(),
			reminders[i].TaskID,
		); err != nil {
			return nil, fmt.Errorf(
				"claim task reminder %d: %w",
				reminders[i].TaskID,
				err,
			)
		}

		reminders[i].AttemptCount++
	}

	if err := tx.Commit(); err != nil {
		return nil, fmt.Errorf("commit task reminder claims: %w", err)
	}

	return reminders, nil
}

func (r *notificationRepositoryImpl) MarkTaskReminderSent(
	ctx context.Context,
	taskID int64,
	sentAt time.Time,
) error {
	const query = `
		UPDATE task_reminders
		SET status = ?,
			sent_at = ?,
			locked_at = NULL,
			last_error = NULL
		WHERE task_id = ?
			AND status = ?
	`

	result, err := r.db.ExecContext(
		ctx,
		query,
		string(values.TaskReminderStatusSent),
		sentAt.UTC(),
		taskID,
		string(values.TaskReminderStatusProcessing),
	)
	if err != nil {
		return fmt.Errorf("mark task reminder %d sent: %w", taskID, err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("read sent reminder update result: %w", err)
	}
	if rowsAffected == 0 {
		return fmt.Errorf("task reminder %d was not in processing state", taskID)
	}

	return nil
}

func (r *notificationRepositoryImpl) RetryTaskReminder(
	ctx context.Context,
	taskID int64,
	lastError string,
	nextAttemptAt time.Time,
	maxAttempts int,
) error {
	const query = `
		UPDATE task_reminders
		SET status = CASE
				WHEN attempt_count >= ? THEN ?
				ELSE ?
			END,
			next_attempt_at = ?,
			last_error = ?,
			locked_at = NULL
		WHERE task_id = ?
			AND status = ?
	`

	_, err := r.db.ExecContext(
		ctx,
		query,
		maxAttempts,
		string(values.TaskReminderStatusFailed),
		string(values.TaskReminderStatusScheduled),
		nextAttemptAt.UTC(),
		lastError,
		taskID,
		string(values.TaskReminderStatusProcessing),
	)
	if err != nil {
		return fmt.Errorf("retry task reminder %d: %w", taskID, err)
	}

	return nil
}
