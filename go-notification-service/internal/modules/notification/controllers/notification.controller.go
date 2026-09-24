package controllers

import (
	"context"
	"fmt"
	"log"

	"go-notification-service/internal/modules/notification/contract"
	pb "go-notification-service/pb/notification/v1"
)

type NotificationController struct {
	pb.UnimplementedNotificationServiceServer
	emailService contract.Service
}

func NewNotificationController(emailService contract.Service) *NotificationController {
	return &NotificationController{emailService: emailService}
}

func (c *NotificationController) SendVerificationEmail(ctx context.Context, req *pb.SendVerificationEmailRequest) (*pb.SendVerificationEmailResponse, error) {
	subject := "Kode Verifikasi TaskFlow Kamu"
	body := fmt.Sprintf("Halo %s,\n\nKode OTP kamu: %s\nBerlaku 10 menit.", req.GetName(), req.GetToken())

	if err := c.emailService.SendEmail(req.GetEmail(), subject, body); err != nil {
		log.Printf("gagal kirim email ke %s: %v", req.GetEmail(), err)
		return &pb.SendVerificationEmailResponse{Success: false, Message: err.Error()}, nil
	}

	return &pb.SendVerificationEmailResponse{Success: true, Message: "OTP terkirim"}, nil
}

func (c *NotificationController) SendInvitationEmail(ctx context.Context, req *pb.SendInvitationEmailRequest) (*pb.SendInvitationEmailResponse, error) {
	return &pb.SendInvitationEmailResponse{Success: false, Message: "belum diimplementasi"}, nil
}

func (c *NotificationController) ScheduleTaskReminder(ctx context.Context, req *pb.ScheduleTaskReminderRequest) (*pb.ScheduleTaskReminderResponse, error) {
	return &pb.ScheduleTaskReminderResponse{Success: false, Message: "belum diimplementasi"}, nil
}