package controllers

import (
	"context"
	"fmt"
	"log"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
	pb "go-notification-service/pb/notification/v1"
)

type NotificationController struct {
	pb.UnimplementedNotificationServiceServer
	emailService contract.Service
}

func NewNotificationController(emailService contract.Service) *NotificationController {
	return &NotificationController{
		emailService: emailService,
	}
}

func (c *NotificationController) SendVerificationEmail(ctx context.Context, req *pb.SendVerificationEmailRequest) (*pb.SendVerificationEmailResponse, error) {
	subject := "Kode Verifikasi TaskFlow Kamu"
	body := fmt.Sprintf(
		"Halo %s,\n\nKode OTP kamu: %s\nBerlaku 10 menit.",
		req.GetName(),
		req.GetToken(),
	)

	if err := c.emailService.SendEmail(ctx, contract.Email{
		To:      req.GetEmail(),
		Type:    values.NotificationTypeVerification,
		Subject: subject,
		Body:    body,
	}); err != nil {
		log.Printf("gagal kirim email verifikasi ke %s: %v", req.GetEmail(), err)

		return &pb.SendVerificationEmailResponse{
			Success: false,
			Message: err.Error(),
		}, nil
	}

	return &pb.SendVerificationEmailResponse{
		Success: true,
		Message: "OTP terkirim",
	}, nil
}

func (c *NotificationController) SendInvitationEmail(ctx context.Context, req *pb.SendInvitationEmailRequest) (*pb.SendInvitationEmailResponse, error) {
	if req.GetAcceptUrl() == "" {
		return &pb.SendInvitationEmailResponse{
			Success: false,
			Message: "accept_url is required",
		}, nil
	}

	subject := fmt.Sprintf(
		"Undangan bergabung ke project %s di TaskFlow",
		req.GetProjectName(),
	)
	body := fmt.Sprintf(
		"Halo,\n\nKamu diundang bergabung ke project %s di TaskFlow.\n\n"+
			"Buka tautan berikut untuk melihat dan menerima undangan:\n%s\n\n"+
			"Abaikan email ini jika kamu tidak merasa diundang.",
		req.GetProjectName(),
		req.GetAcceptUrl(),
	)

	if err := c.emailService.SendEmail(ctx, contract.Email{
		To:      req.GetTargetEmail(),
		Type:    values.NotificationTypeInvitation,
		Subject: subject,
		Body:    body,
	}); err != nil {
		log.Printf("gagal kirim undangan ke %s: %v", req.GetTargetEmail(), err)

		return &pb.SendInvitationEmailResponse{
			Success: false,
			Message: err.Error(),
		}, nil
	}

	return &pb.SendInvitationEmailResponse{
		Success: true,
		Message: "Undangan terkirim",
	}, nil
}

func (c *NotificationController) SendPasswordResetEmail(ctx context.Context, req *pb.SendPasswordResetEmailRequest) (*pb.SendPasswordResetEmailResponse, error) {
	if req.GetResetUrl() == "" {
		return &pb.SendPasswordResetEmailResponse{
			Success: false,
			Message: "reset_url is required",
		}, nil
	}

	subject := "Atur ulang password TaskFlow kamu"
	body := fmt.Sprintf(
		"Halo %s,\n\nKami menerima permintaan untuk mengatur ulang password akun TaskFlow kamu.\n\n"+
			"Buka tautan berikut untuk membuat password baru:\n%s\n\n"+
			"Jika kamu tidak merasa memintanya, abaikan email ini. Password kamu tidak akan berubah.",
		req.GetName(),
		req.GetResetUrl(),
	)

	if err := c.emailService.SendEmail(ctx, contract.Email{
		To:      req.GetEmail(),
		Type:    values.NotificationTypePasswordReset,
		Subject: subject,
		Body:    body,
	}); err != nil {
		log.Printf("gagal kirim email reset password ke %s: %v", req.GetEmail(), err)

		return &pb.SendPasswordResetEmailResponse{
			Success: false,
			Message: err.Error(),
		}, nil
	}

	return &pb.SendPasswordResetEmailResponse{
		Success: true,
		Message: "Email reset password terkirim",
	}, nil
}

func (c *NotificationController) ScheduleTaskReminder(ctx context.Context, req *pb.ScheduleTaskReminderRequest) (*pb.ScheduleTaskReminderResponse, error) {
	if err := c.emailService.ScheduleTaskReminder(ctx, contract.ScheduleTaskReminderInput{
		TaskID:        req.GetTaskId(),
		TaskTitle:     req.GetTaskTitle(),
		ProjectName:   req.GetProjectName(),
		AssigneeEmail: req.GetAssigneeEmail(),
		DueDate:       req.GetDueDate(),
	}); err != nil {
		log.Printf(
			"gagal menjadwalkan reminder task %d: %v",
			req.GetTaskId(),
			err,
		)

		return &pb.ScheduleTaskReminderResponse{
			Success: false,
			Message: err.Error(),
		}, nil
	}

	return &pb.ScheduleTaskReminderResponse{
		Success: true,
		Message: "Reminder task berhasil dijadwalkan",
	}, nil
}

func (c *NotificationController) CancelTaskReminder(ctx context.Context, req *pb.CancelTaskReminderRequest) (*pb.CancelTaskReminderResponse, error) {
	if err := c.emailService.CancelTaskReminder(ctx, req.GetTaskId()); err != nil {
		log.Printf(
			"gagal membatalkan reminder task %d: %v",
			req.GetTaskId(),
			err,
		)

		return &pb.CancelTaskReminderResponse{
			Success: false,
			Message: err.Error(),
		}, nil
	}

	return &pb.CancelTaskReminderResponse{
		Success: true,
		Message: "Reminder task berhasil dibatalkan",
	}, nil
}
