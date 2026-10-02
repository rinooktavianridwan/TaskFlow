package contract

import (
	"context"
	"go-notification-service/internal/modules/notification/values"
	"time"

	pb "go-notification-service/pb/notification/v1"
)

type TaskReminder struct {
	TaskID         int64
	TaskTitle      string
	ProjectName    string
	RecipientEmail string
	DueAt          time.Time
	RemindAt       time.Time
	NextAttemptAt  time.Time
	AttemptCount   int
}

type Repository interface {
	SaveNotificationLog(
		email string,
		notificationType values.NotificationType,
		status values.DeliveryStatus,
		errorMessage string,
	) error

	UpsertTaskReminder(ctx context.Context, reminder TaskReminder) error
	CancelTaskReminder(ctx context.Context, taskID int64) error
	ClaimDueTaskReminders(
		ctx context.Context,
		now time.Time,
		staleBefore time.Time,
		limit int,
	) ([]TaskReminder, error)
	MarkTaskReminderSent(ctx context.Context, taskID int64, sentAt time.Time) error
	RetryTaskReminder(
		ctx context.Context,
		taskID int64,
		lastError string,
		nextAttemptAt time.Time,
		maxAttempts int,
	) error
}

type Service interface {
	SendEmail(
		to string,
		notificationType values.NotificationType,
		subject string,
		body string,
	) error

	ScheduleTaskReminder(
		ctx context.Context,
		taskID int64,
		taskTitle string,
		projectName string,
		assigneeEmail string,
		dueDate string,
	) error

	CancelTaskReminder(ctx context.Context, taskID int64) error
	RunTaskReminderWorker(ctx context.Context)
}

type Controller interface {
	SendVerificationEmail(
		ctx context.Context,
		req *pb.SendVerificationEmailRequest,
	) (*pb.SendVerificationEmailResponse, error)

	SendInvitationEmail(
		ctx context.Context,
		req *pb.SendInvitationEmailRequest,
	) (*pb.SendInvitationEmailResponse, error)

	ScheduleTaskReminder(
		ctx context.Context,
		req *pb.ScheduleTaskReminderRequest,
	) (*pb.ScheduleTaskReminderResponse, error)

	CancelTaskReminder(
		ctx context.Context,
		req *pb.CancelTaskReminderRequest,
	) (*pb.CancelTaskReminderResponse, error)
}
