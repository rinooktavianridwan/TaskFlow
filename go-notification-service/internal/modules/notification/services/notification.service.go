package services

import (
	"context"
	"fmt"
	"log"
	"strings"
	"time"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

const (
	reminderPollInterval = 15 * time.Second
	reminderBatchSize    = 25
	reminderMaxAttempts  = 5
	reminderRetryDelay   = 5 * time.Minute
	reminderStaleAfter   = 5 * time.Minute
	reminderLeadTime     = 24 * time.Hour
)

type notificationServiceImpl struct {
	repo   contract.Repository
	mailer contract.Mailer
}

func NewNotificationService(repo contract.Repository, mailer contract.Mailer) contract.Service {
	return &notificationServiceImpl{repo: repo, mailer: mailer}
}

func (s *notificationServiceImpl) SendEmail(ctx context.Context, email contract.Email) error {
	email.To = strings.TrimSpace(email.To)

	if email.To == "" {
		return fmt.Errorf("recipient email is required")
	}

	status, err := s.mailer.Send(ctx, email)

	entry := contract.NotificationLog{
		RecipientEmail:   email.To,
		NotificationType: email.Type,
		Status:           status,
	}

	if err != nil {
		entry.Status = values.DeliveryStatusFailed
		entry.ErrorMessage = err.Error()
	}

	s.saveNotificationLog(entry)

	return err
}

func (s *notificationServiceImpl) ScheduleTaskReminder(ctx context.Context, input contract.ScheduleTaskReminderInput) error {
	taskID := input.TaskID
	taskTitle := strings.TrimSpace(input.TaskTitle)
	projectName := strings.TrimSpace(input.ProjectName)
	assigneeEmail := strings.TrimSpace(input.AssigneeEmail)
	dueDate := input.DueDate

	if taskID < 1 {
		return fmt.Errorf("task_id must be greater than zero")
	}

	if taskTitle == "" {
		return fmt.Errorf("task_title is required")
	}

	if projectName == "" {
		return fmt.Errorf("project_name is required")
	}

	if assigneeEmail == "" {
		return fmt.Errorf("assignee_email is required")
	}

	dueAt, err := time.Parse(time.RFC3339, strings.TrimSpace(dueDate))
	if err != nil {
		return fmt.Errorf("due_date must use RFC3339 format: %w", err)
	}

	_, offset := dueAt.Zone()
	if offset != 0 {
		return fmt.Errorf("due_date must be in UTC")
	}

	now := time.Now().UTC()
	dueAt = dueAt.UTC()

	if !dueAt.After(now) {
		return s.CancelTaskReminder(ctx, taskID)
	}

	remindAt := dueAt.Add(-reminderLeadTime)

	// Jika waktu H-24 sudah lewat tetapi due date masih di masa depan,
	// jadwalkan untuk segera diproses worker.
	if remindAt.Before(now) {
		remindAt = now
	}

	reminder := contract.TaskReminder{
		TaskID:         taskID,
		TaskTitle:      taskTitle,
		ProjectName:    projectName,
		RecipientEmail: assigneeEmail,
		DueAt:          dueAt,
		RemindAt:       remindAt,
		NextAttemptAt:  remindAt,
	}

	if err := s.repo.UpsertTaskReminder(ctx, reminder); err != nil {
		return fmt.Errorf("schedule task reminder: %w", err)
	}

	return nil
}

func (s *notificationServiceImpl) CancelTaskReminder(ctx context.Context, taskID int64) error {
	if taskID < 1 {
		return fmt.Errorf("task_id must be greater than zero")
	}

	if err := s.repo.CancelTaskReminder(ctx, taskID); err != nil {
		return fmt.Errorf("cancel task reminder: %w", err)
	}

	return nil
}

func (s *notificationServiceImpl) RunTaskReminderWorker(ctx context.Context) {
	ticker := time.NewTicker(reminderPollInterval)
	defer ticker.Stop()

	log.Printf(
		"[Reminder worker] Started; polling every %s",
		reminderPollInterval,
	)

	for {
		if err := s.processDueTaskReminders(ctx); err != nil {
			log.Printf("[Reminder worker] Processing error: %v", err)
		}

		select {
		case <-ctx.Done():
			log.Println("[Reminder worker] Stopped.")
			return
		case <-ticker.C:
		}
	}
}

func (s *notificationServiceImpl) processDueTaskReminders(ctx context.Context) error {
	now := time.Now().UTC()

	reminders, err := s.repo.ClaimDueTaskReminders(ctx, contract.ClaimDueTaskRemindersInput{
		Now:         now,
		StaleBefore: now.Add(-reminderStaleAfter),
		Limit:       reminderBatchSize,
	})
	if err != nil {
		return fmt.Errorf("claim due task reminders: %w", err)
	}

	for _, reminder := range reminders {
		if err := ctx.Err(); err != nil {
			return err
		}

		subject := fmt.Sprintf(
			"Pengingat: task %q segera jatuh tempo",
			reminder.TaskTitle,
		)

		body := fmt.Sprintf(
			"Halo,\n\n"+
				"Ini pengingat bahwa task berikut akan jatuh tempo dalam waktu dekat.\n\n"+
				"Project: %s\n"+
				"Task: %s\n"+
				"Jatuh tempo: %s UTC\n\n"+
				"Silakan buka TaskFlow untuk melihat detail task.",
			reminder.ProjectName,
			reminder.TaskTitle,
			reminder.DueAt.UTC().Format("02 Jan 2006 15:04"),
		)

		if err := s.SendEmail(ctx, contract.Email{
			To:      reminder.RecipientEmail,
			Type:    values.NotificationTypeTaskReminder,
			Subject: subject,
			Body:    body,
		}); err != nil {
			retryAt := time.Now().UTC().Add(
				time.Duration(reminder.AttemptCount) * reminderRetryDelay,
			)

			if retryErr := s.repo.RetryTaskReminder(ctx, contract.RetryTaskReminderInput{
				TaskID:        reminder.TaskID,
				LastError:     err.Error(),
				NextAttemptAt: retryAt,
				MaxAttempts:   reminderMaxAttempts,
			}); retryErr != nil {
				log.Printf(
					"[Reminder worker] Could not record failed attempt for task %d: %v",
					reminder.TaskID,
					retryErr,
				)
			}

			continue
		}

		if err := s.repo.MarkTaskReminderSent(
			ctx,
			reminder.TaskID,
			time.Now().UTC(),
		); err != nil {
			log.Printf(
				"[Reminder worker] Email sent but could not mark task %d as sent: %v",
				reminder.TaskID,
				err,
			)
		}
	}

	return nil
}

func (s *notificationServiceImpl) saveNotificationLog(entry contract.NotificationLog) {
	if err := s.repo.SaveNotificationLog(entry); err != nil {
		log.Printf(
			"[Notification log] Could not save log for %s (%s): %v",
			entry.RecipientEmail,
			entry.NotificationType,
			err,
		)
	}
}
