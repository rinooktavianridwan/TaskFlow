package contract

import (
	"context"
	pb "go-notification-service/pb/notification/v1"
)

type Repository interface {
	SaveNotificationLog(email, status string) error
}

type Service interface {
	SendEmail(to, subject, body string) error
}

type Controller interface {
	SendVerificationEmail(ctx context.Context, req *pb.SendVerificationEmailRequest) (*pb.SendVerificationEmailResponse, error)
	SendInvitationEmail(ctx context.Context, req *pb.SendInvitationEmailRequest) (*pb.SendInvitationEmailResponse, error)
	ScheduleTaskReminder(ctx context.Context, req *pb.ScheduleTaskReminderRequest) (*pb.ScheduleTaskReminderResponse, error)
}