package services

import (
	"context"
	"crypto/tls"
	"fmt"
	"log"
	"net/smtp"
	"os"
	"strings"
	"time"

	"github.com/mailtrap/mailtrap-go"

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
	repo contract.Repository
}

func NewNotificationService(repo contract.Repository) contract.Service {
	return &notificationServiceImpl{repo: repo}
}

func (s *notificationServiceImpl) SendEmail(
	to string,
	notificationType values.NotificationType,
	subject string,
	body string,
) error {
	to = strings.TrimSpace(to)

	if to == "" {
		return fmt.Errorf("recipient email is required")
	}

	provider := os.Getenv("MAIL_PROVIDER")

	var err error

	switch provider {
	case "smtp":
		err = sendSmtpMail(to, subject, body)
	case "mailtrap":
		err = sendMailtrapMail(to, subject, body)
	default:
		log.Printf(
			"[Mail fallback/log] To: %s | Type: %s | Subject: %s | Body: %s",
			to,
			notificationType,
			subject,
			body,
		)

		s.saveNotificationLog(
			to,
			notificationType,
			values.DeliveryStatusSuccessFallback,
			"",
		)

		return nil
	}

	if err != nil {
		s.saveNotificationLog(
			to,
			notificationType,
			values.DeliveryStatusFailed,
			err.Error(),
		)

		return err
	}

	s.saveNotificationLog(
		to,
		notificationType,
		values.DeliveryStatusSuccess,
		"",
	)

	return nil
}

func (s *notificationServiceImpl) ScheduleTaskReminder(
	ctx context.Context,
	taskID int64,
	taskTitle string,
	projectName string,
	assigneeEmail string,
	dueDate string,
) error {
	if taskID < 1 {
		return fmt.Errorf("task_id must be greater than zero")
	}

	taskTitle = strings.TrimSpace(taskTitle)
	projectName = strings.TrimSpace(projectName)
	assigneeEmail = strings.TrimSpace(assigneeEmail)

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

func (s *notificationServiceImpl) CancelTaskReminder(
	ctx context.Context,
	taskID int64,
) error {
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

	reminders, err := s.repo.ClaimDueTaskReminders(
		ctx,
		now,
		now.Add(-reminderStaleAfter),
		reminderBatchSize,
	)
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

		if err := s.SendEmail(
			reminder.RecipientEmail,
			values.NotificationTypeTaskReminder,
			subject,
			body,
		); err != nil {
			retryAt := time.Now().UTC().Add(
				time.Duration(reminder.AttemptCount) * reminderRetryDelay,
			)

			if retryErr := s.repo.RetryTaskReminder(
				ctx,
				reminder.TaskID,
				err.Error(),
				retryAt,
				reminderMaxAttempts,
			); retryErr != nil {
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

func (s *notificationServiceImpl) saveNotificationLog(
	email string,
	notificationType values.NotificationType,
	status values.DeliveryStatus,
	errorMessage string,
) {
	if err := s.repo.SaveNotificationLog(
		email,
		notificationType,
		status,
		errorMessage,
	); err != nil {
		log.Printf(
			"[Notification log] Could not save log for %s (%s): %v",
			email,
			notificationType,
			err,
		)
	}
}

// ---------- Mailtrap (HTTP API) ----------

func sendMailtrapMail(to, subject, body string) error {
	client, err := mailtrap.NewClient(os.Getenv("MAILTRAP_API_TOKEN"))
	if err != nil {
		return err
	}

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	_, _, err = client.Send(ctx, &mailtrap.SendRequest{
		From:     mailtrap.Address{Email: os.Getenv("MAILTRAP_FROM_EMAIL"), Name: os.Getenv("MAILTRAP_FROM_NAME")},
		To:       []mailtrap.Address{{Email: to}},
		Subject:  subject,
		Text:     body,
		Category: "TaskFlow Notification",
	})

	return err
}

// ---------- SMTP dengan STARTTLS ----------

func sendSmtpMail(to, subject, body string) error {
	host := os.Getenv("SMTP_HOST")
	port := os.Getenv("SMTP_PORT")
	user := os.Getenv("SMTP_USERNAME")
	pass := os.Getenv("SMTP_PASSWORD")
	from := os.Getenv("SMTP_FROM")

	addr := fmt.Sprintf("%s:%s", host, port)
	auth := smtp.PlainAuth("", user, pass, host)
		msg := fmt.Appendf(
		nil,
		"From: %s\r\nTo: %s\r\nSubject: %s\r\n\r\n%s",
		from,
		to,
		subject,
		body,
	)

	client, err := smtp.Dial(addr)
	if err != nil {
		return err
	}
	defer client.Close()

	if err := client.StartTLS(&tls.Config{ServerName: host}); err != nil {
		return err
	}

	if err := client.Auth(auth); err != nil {
		return err
	}

	if err := client.Mail(from); err != nil {
		return err
	}

	if err := client.Rcpt(to); err != nil {
		return err
	}

	writer, err := client.Data()
	if err != nil {
		return err
	}

	if _, err := writer.Write(msg); err != nil {
		_ = writer.Close()
		return err
	}

	if err := writer.Close(); err != nil {
		return err
	}

	return client.Quit()
}
