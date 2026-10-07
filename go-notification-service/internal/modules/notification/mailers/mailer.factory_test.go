package mailers

import (
	"context"
	"io"
	"log"
	"os"
	"strings"
	"testing"
	"time"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

func TestMain(m *testing.M) {
	log.SetOutput(io.Discard)
	os.Exit(m.Run())
}

func TestNew_EmptyProviderUsesLogMailer(t *testing.T) {
	mailer, err := New(Config{})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}

	if _, ok := mailer.(LogMailer); !ok {
		t.Fatalf("mailer = %T, want LogMailer", mailer)
	}
}

func TestNew_SMTPProvider(t *testing.T) {
	mailer, err := New(Config{Provider: "smtp", Timeout: time.Second, SMTP: validSMTPConfig()})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}

	if _, ok := mailer.(*SMTPMailer); !ok {
		t.Fatalf("mailer = %T, want *SMTPMailer", mailer)
	}
}

func TestNew_SMTPProviderWithMissingConfigFailsAtStartup(t *testing.T) {
	if _, err := New(Config{Provider: "smtp"}); err == nil {
		t.Fatal("konfigurasi SMTP yang kosong harus membuat service gagal start")
	}
}

func TestNew_UnknownProviderFailsAtStartup(t *testing.T) {
	_, err := New(Config{Provider: "ses"})
	if err == nil {
		t.Fatal("provider yang tidak dikenal harus ditolak")
	}

	if !strings.Contains(err.Error(), "tidak dikenal") || !strings.Contains(err.Error(), "ses") {
		t.Fatalf("error = %v", err)
	}
}

func TestLogMailer_ReportsFallbackStatus(t *testing.T) {
	status, err := LogMailer{}.Send(context.Background(), contract.Email{
		To:      "a@example.com",
		Type:    values.NotificationTypeInvitation,
		Subject: "s",
		Body:    "b",
	})
	if err != nil {
		t.Fatalf("Send() error = %v", err)
	}

	if status != values.DeliveryStatusSuccessFallback {
		t.Fatalf("status = %q, want %q", status, values.DeliveryStatusSuccessFallback)
	}
}

func TestLoadConfigFromEnv_TrimsAndNormalizesValues(t *testing.T) {
	t.Setenv("MAIL_PROVIDER", "  SMTP ")
	t.Setenv("SMTP_HOST", " smtp.example.com ")
	t.Setenv("SMTP_PORT", " 2525 ")
	t.Setenv("SMTP_USERNAME", " user ")
	t.Setenv("SMTP_PASSWORD", " secret ")
	t.Setenv("SMTP_FROM", " no-reply@taskflow.test ")
	t.Setenv("MAILTRAP_API_TOKEN", " token ")
	t.Setenv("MAILTRAP_FROM_EMAIL", " from@taskflow.test ")
	t.Setenv("MAILTRAP_FROM_NAME", " TaskFlow ")

	cfg := LoadConfigFromEnv()

	if cfg.Provider != "smtp" {
		t.Fatalf("Provider = %q, want %q", cfg.Provider, "smtp")
	}

	if cfg.Timeout != defaultSendTimeout {
		t.Fatalf("Timeout = %v, want %v", cfg.Timeout, defaultSendTimeout)
	}

	want := SMTPConfig{
		Host:     "smtp.example.com",
		Port:     "2525",
		Username: "user",
		Password: "secret",
		From:     "no-reply@taskflow.test",
	}
	if cfg.SMTP != want {
		t.Fatalf("SMTP = %+v, want %+v", cfg.SMTP, want)
	}

	wantMailtrap := MailtrapConfig{APIToken: "token", FromEmail: "from@taskflow.test", FromName: "TaskFlow"}
	if cfg.Mailtrap != wantMailtrap {
		t.Fatalf("Mailtrap = %+v, want %+v", cfg.Mailtrap, wantMailtrap)
	}
}

func TestLoadConfigFromEnv_EmptyProviderMeansLogOnly(t *testing.T) {
	t.Setenv("MAIL_PROVIDER", "")

	if cfg := LoadConfigFromEnv(); cfg.Provider != "" {
		t.Fatalf("Provider = %q, want kosong", cfg.Provider)
	}
}
