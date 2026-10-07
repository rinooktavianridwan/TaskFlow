package controllers

import (
	"context"
	"errors"
	"io"
	"log"
	"os"
	"strings"
	"testing"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
	pb "go-notification-service/pb/notification/v1"
)

func TestMain(m *testing.M) {
	log.SetOutput(io.Discard)
	os.Exit(m.Run())
}

type fakeService struct {
	emails         []contract.Email
	sendErr        error
	scheduleInputs []contract.ScheduleTaskReminderInput
	scheduleErr    error
	cancelIDs      []int64
	cancelErr      error
}

var _ contract.Service = (*fakeService)(nil)

func (f *fakeService) SendEmail(_ context.Context, email contract.Email) error {
	f.emails = append(f.emails, email)
	return f.sendErr
}

func (f *fakeService) ScheduleTaskReminder(_ context.Context, input contract.ScheduleTaskReminderInput) error {
	f.scheduleInputs = append(f.scheduleInputs, input)
	return f.scheduleErr
}

func (f *fakeService) CancelTaskReminder(_ context.Context, taskID int64) error {
	f.cancelIDs = append(f.cancelIDs, taskID)
	return f.cancelErr
}

func (f *fakeService) RunTaskReminderWorker(_ context.Context) {}

// ---------------------------------------------------------------- SendVerificationEmail

func TestSendVerificationEmail_Success(t *testing.T) {
	svc := &fakeService{}
	ctrl := NewNotificationController(svc)

	resp, err := ctrl.SendVerificationEmail(context.Background(), &pb.SendVerificationEmailRequest{
		UserId: 1,
		Name:   "Budi",
		Email:  "budi@example.com",
		Token:  "123456",
	})
	if err != nil {
		t.Fatalf("error = %v, want nil", err)
	}

	if !resp.GetSuccess() {
		t.Fatalf("response = %+v", resp)
	}

	email := svc.emails[0]

	if email.To != "budi@example.com" || email.Type != values.NotificationTypeVerification {
		t.Fatalf("email = %+v", email)
	}

	if !strings.Contains(email.Body, "Budi") || !strings.Contains(email.Body, "123456") {
		t.Fatalf("body harus memuat nama dan OTP, got %q", email.Body)
	}
}

func TestSendVerificationEmail_ServiceErrorBecomesFailedResponseWithNilError(t *testing.T) {
	ctrl := NewNotificationController(&fakeService{sendErr: errors.New("smtp down")})

	resp, err := ctrl.SendVerificationEmail(context.Background(), &pb.SendVerificationEmailRequest{
		Email: "budi@example.com",
	})
	if err != nil {
		t.Fatalf("kegagalan operasional harus lewat Success:false, bukan error gRPC: %v", err)
	}

	if resp.GetSuccess() || resp.GetMessage() != "smtp down" {
		t.Fatalf("response = %+v", resp)
	}
}

// ---------------------------------------------------------------- SendInvitationEmail

func TestSendInvitationEmail_RequiresAcceptURL(t *testing.T) {
	svc := &fakeService{}
	ctrl := NewNotificationController(svc)

	resp, err := ctrl.SendInvitationEmail(context.Background(), &pb.SendInvitationEmailRequest{
		ProjectName: "Proyek A",
		TargetEmail: "budi@example.com",
	})
	if err != nil {
		t.Fatalf("error = %v, want nil", err)
	}

	if resp.GetSuccess() || resp.GetMessage() != "accept_url is required" {
		t.Fatalf("response = %+v", resp)
	}

	if len(svc.emails) != 0 {
		t.Fatal("tanpa accept_url tidak boleh ada email terkirim")
	}
}

func TestSendInvitationEmail_Success(t *testing.T) {
	svc := &fakeService{}
	ctrl := NewNotificationController(svc)

	resp, err := ctrl.SendInvitationEmail(context.Background(), &pb.SendInvitationEmailRequest{
		ProjectId:   5,
		ProjectName: "Proyek A",
		TargetEmail: "budi@example.com",
		AcceptUrl:   "http://localhost:3000/invitations/abc123",
	})
	if err != nil || !resp.GetSuccess() {
		t.Fatalf("resp = %+v, err = %v", resp, err)
	}

	email := svc.emails[0]

	if email.To != "budi@example.com" || email.Type != values.NotificationTypeInvitation {
		t.Fatalf("email = %+v", email)
	}

	if !strings.Contains(email.Subject, "Proyek A") {
		t.Fatalf("subjek harus memuat nama project, got %q", email.Subject)
	}

	if !strings.Contains(email.Body, "http://localhost:3000/invitations/abc123") {
		t.Fatalf("body harus memuat tautan undangan, got %q", email.Body)
	}
}

func TestSendInvitationEmail_ServiceErrorBecomesFailedResponse(t *testing.T) {
	ctrl := NewNotificationController(&fakeService{sendErr: errors.New("smtp down")})

	resp, err := ctrl.SendInvitationEmail(context.Background(), &pb.SendInvitationEmailRequest{
		TargetEmail: "budi@example.com",
		AcceptUrl:   "http://localhost:3000/invitations/abc123",
	})
	if err != nil || resp.GetSuccess() || resp.GetMessage() != "smtp down" {
		t.Fatalf("resp = %+v, err = %v", resp, err)
	}
}

// ---------------------------------------------------------------- SendPasswordResetEmail

func TestSendPasswordResetEmail_RequiresResetURL(t *testing.T) {
	svc := &fakeService{}
	ctrl := NewNotificationController(svc)

	resp, err := ctrl.SendPasswordResetEmail(context.Background(), &pb.SendPasswordResetEmailRequest{
		Email: "budi@example.com",
		Name:  "Budi",
	})
	if err != nil {
		t.Fatalf("error = %v, want nil", err)
	}

	if resp.GetSuccess() || resp.GetMessage() != "reset_url is required" {
		t.Fatalf("response = %+v", resp)
	}

	if len(svc.emails) != 0 {
		t.Fatal("tanpa reset_url tidak boleh ada email terkirim")
	}
}

func TestSendPasswordResetEmail_Success(t *testing.T) {
	svc := &fakeService{}
	ctrl := NewNotificationController(svc)

	resetURL := "http://localhost:3000/password-reset/token-uji?email=budi%40example.com"

	resp, err := ctrl.SendPasswordResetEmail(context.Background(), &pb.SendPasswordResetEmailRequest{
		Email:    "budi@example.com",
		Name:     "Budi",
		ResetUrl: resetURL,
	})
	if err != nil || !resp.GetSuccess() {
		t.Fatalf("resp = %+v, err = %v", resp, err)
	}

	email := svc.emails[0]

	if email.To != "budi@example.com" || email.Type != values.NotificationTypePasswordReset {
		t.Fatalf("email = %+v", email)
	}

	if !strings.Contains(email.Body, resetURL) || !strings.Contains(email.Body, "Budi") {
		t.Fatalf("body = %q", email.Body)
	}
}

func TestSendPasswordResetEmail_ServiceErrorBecomesFailedResponse(t *testing.T) {
	ctrl := NewNotificationController(&fakeService{sendErr: errors.New("smtp down")})

	resp, err := ctrl.SendPasswordResetEmail(context.Background(), &pb.SendPasswordResetEmailRequest{
		Email:    "budi@example.com",
		ResetUrl: "http://localhost:3000/password-reset/x",
	})
	if err != nil || resp.GetSuccess() || resp.GetMessage() != "smtp down" {
		t.Fatalf("resp = %+v, err = %v", resp, err)
	}
}

// ---------------------------------------------------------------- reminder

func TestScheduleTaskReminder_MapsRequestToServiceInput(t *testing.T) {
	svc := &fakeService{}
	ctrl := NewNotificationController(svc)

	resp, err := ctrl.ScheduleTaskReminder(context.Background(), &pb.ScheduleTaskReminderRequest{
		TaskId:        7,
		TaskTitle:     "Kirim laporan",
		ProjectName:   "Proyek A",
		AssigneeEmail: "budi@example.com",
		DueDate:       "2030-01-01T10:00:00Z",
	})
	if err != nil || !resp.GetSuccess() {
		t.Fatalf("resp = %+v, err = %v", resp, err)
	}

	want := contract.ScheduleTaskReminderInput{
		TaskID:        7,
		TaskTitle:     "Kirim laporan",
		ProjectName:   "Proyek A",
		AssigneeEmail: "budi@example.com",
		DueDate:       "2030-01-01T10:00:00Z",
	}

	if len(svc.scheduleInputs) != 1 || svc.scheduleInputs[0] != want {
		t.Fatalf("input = %+v, want %+v", svc.scheduleInputs, want)
	}
}

func TestScheduleTaskReminder_ServiceErrorBecomesFailedResponseWithNilError(t *testing.T) {
	ctrl := NewNotificationController(&fakeService{scheduleErr: errors.New("due_date must be in UTC")})

	resp, err := ctrl.ScheduleTaskReminder(context.Background(), &pb.ScheduleTaskReminderRequest{TaskId: 7})
	if err != nil {
		t.Fatalf("error = %v, want nil", err)
	}

	if resp.GetSuccess() || resp.GetMessage() != "due_date must be in UTC" {
		t.Fatalf("response = %+v", resp)
	}
}

func TestCancelTaskReminder(t *testing.T) {
	t.Run("sukses", func(t *testing.T) {
		svc := &fakeService{}
		ctrl := NewNotificationController(svc)

		resp, err := ctrl.CancelTaskReminder(context.Background(), &pb.CancelTaskReminderRequest{TaskId: 9})
		if err != nil || !resp.GetSuccess() {
			t.Fatalf("resp = %+v, err = %v", resp, err)
		}

		if len(svc.cancelIDs) != 1 || svc.cancelIDs[0] != 9 {
			t.Fatalf("cancelIDs = %v, want [9]", svc.cancelIDs)
		}
	})

	t.Run("error service menjadi respons gagal", func(t *testing.T) {
		ctrl := NewNotificationController(&fakeService{cancelErr: errors.New("db down")})

		resp, err := ctrl.CancelTaskReminder(context.Background(), &pb.CancelTaskReminderRequest{TaskId: 9})
		if err != nil {
			t.Fatalf("error = %v, want nil", err)
		}

		if resp.GetSuccess() || resp.GetMessage() != "db down" {
			t.Fatalf("response = %+v", resp)
		}
	})
}
