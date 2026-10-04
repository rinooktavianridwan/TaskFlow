package contract

import (
	"context"
	"time"

	"go-notification-service/internal/modules/notification/values"
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

type Email struct {
	To      string
	Type    values.NotificationType
	Subject string
	Body    string
}

type NotificationLog struct {
	RecipientEmail   string
	NotificationType values.NotificationType
	Status           values.DeliveryStatus
	ErrorMessage     string
}

type ScheduleTaskReminderInput struct {
	TaskID        int64
	TaskTitle     string
	ProjectName   string
	AssigneeEmail string
	DueDate       string // RFC3339, harus UTC
}

type ClaimDueTaskRemindersInput struct {
	Now         time.Time
	StaleBefore time.Time
	Limit       int
}

type RetryTaskReminderInput struct {
	TaskID        int64
	LastError     string
	NextAttemptAt time.Time
	MaxAttempts   int
}

type Mailer interface {
	// Send mengirim email dan melaporkan status pengirimannya.
	// Saat error, status yang dikembalikan boleh kosong.
	Send(ctx context.Context, email Email) (values.DeliveryStatus, error)
}

type Repository interface {
	SaveNotificationLog(entry NotificationLog) error
	UpsertTaskReminder(ctx context.Context, reminder TaskReminder) error
	CancelTaskReminder(ctx context.Context, taskID int64) error
	ClaimDueTaskReminders(ctx context.Context, input ClaimDueTaskRemindersInput) ([]TaskReminder, error)
	MarkTaskReminderSent(ctx context.Context, taskID int64, sentAt time.Time) error
	RetryTaskReminder(ctx context.Context, input RetryTaskReminderInput) error
}

type Service interface {
	SendEmail(ctx context.Context, email Email) error
	ScheduleTaskReminder(ctx context.Context, input ScheduleTaskReminderInput) error
	CancelTaskReminder(ctx context.Context, taskID int64) error
	RunTaskReminderWorker(ctx context.Context)
}

type Controller interface {
	SendVerificationEmail(ctx context.Context, req *pb.SendVerificationEmailRequest) (*pb.SendVerificationEmailResponse, error)
	SendInvitationEmail(ctx context.Context, req *pb.SendInvitationEmailRequest) (*pb.SendInvitationEmailResponse, error)
	ScheduleTaskReminder(ctx context.Context, req *pb.ScheduleTaskReminderRequest) (*pb.ScheduleTaskReminderResponse, error)
	CancelTaskReminder(ctx context.Context, req *pb.CancelTaskReminderRequest) (*pb.CancelTaskReminderResponse, error)
}
