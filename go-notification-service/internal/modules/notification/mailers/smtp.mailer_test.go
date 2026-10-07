package mailers

import (
	"bytes"
	"context"
	"io"
	"mime"
	"net/mail"
	"strings"
	"testing"
	"time"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

func validSMTPConfig() SMTPConfig {
	return SMTPConfig{
		Host:     "smtp.example.com",
		Port:     "2525",
		Username: "user",
		Password: "secret",
		From:     "TaskFlow <no-reply@taskflow.test>",
	}
}

func TestBuildMessage_HeadersAndBody(t *testing.T) {
	email := contract.Email{
		To:      "budi@example.com",
		Type:    values.NotificationTypeVerification,
		Subject: "Kode Verifikasi TaskFlow Kamu",
		Body:    "Halo Budi,\n\nKode OTP kamu: 123456",
	}

	raw, err := buildMessage("TaskFlow <no-reply@taskflow.test>", email)
	if err != nil {
		t.Fatalf("buildMessage() error = %v", err)
	}

	msg, err := mail.ReadMessage(bytes.NewReader(raw))
	if err != nil {
		t.Fatalf("pesan harus bisa diparse sebagai email: %v", err)
	}

	checks := map[string]string{
		"From":         "TaskFlow <no-reply@taskflow.test>",
		"To":           "budi@example.com",
		"Subject":      "Kode Verifikasi TaskFlow Kamu",
		"Mime-Version": "1.0",
		"Content-Type": "text/plain; charset=UTF-8",
	}

	for header, want := range checks {
		if got := msg.Header.Get(header); got != want {
			t.Errorf("header %s = %q, want %q", header, got, want)
		}
	}

	body, err := io.ReadAll(msg.Body)
	if err != nil {
		t.Fatalf("baca body: %v", err)
	}

	if string(body) != email.Body {
		t.Fatalf("body = %q, want %q", body, email.Body)
	}
}

func TestBuildMessage_EncodesNonASCIISubject(t *testing.T) {
	subject := "Pengingat: task “Rapat” segera jatuh tempo"

	raw, err := buildMessage("no-reply@taskflow.test", contract.Email{
		To:      "budi@example.com",
		Subject: subject,
		Body:    "Isi",
	})
	if err != nil {
		t.Fatalf("buildMessage() error = %v", err)
	}

	if !strings.Contains(string(raw), "=?utf-8?q?") {
		t.Fatalf("subjek non-ASCII harus di-encode, got:\n%s", raw)
	}

	msg, err := mail.ReadMessage(bytes.NewReader(raw))
	if err != nil {
		t.Fatalf("ReadMessage: %v", err)
	}

	decoded, err := new(mime.WordDecoder).DecodeHeader(msg.Header.Get("Subject"))
	if err != nil {
		t.Fatalf("DecodeHeader: %v", err)
	}

	if decoded != subject {
		t.Fatalf("subjek setelah decode = %q, want %q", decoded, subject)
	}
}

func TestBuildMessage_RejectsHeaderInjection(t *testing.T) {
	tests := []struct {
		name  string
		from  string
		email contract.Email
	}{
		{"CR LF di From", "a@b.c\r\nBcc: x@y.z", contract.Email{To: "t@example.com", Subject: "s"}},
		{"LF di To", "a@b.c", contract.Email{To: "t@example.com\nBcc: x@y.z", Subject: "s"}},
		{"LF di Subject", "a@b.c", contract.Email{To: "t@example.com", Subject: "s\nBcc: x@y.z"}},
		{"CR di Subject", "a@b.c", contract.Email{To: "t@example.com", Subject: "s\rBcc: x@y.z"}},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			raw, err := buildMessage(tt.from, tt.email)
			if err == nil {
				t.Fatalf("harus ditolak, got pesan:\n%s", raw)
			}

			if !strings.Contains(err.Error(), "line breaks") {
				t.Fatalf("error = %v", err)
			}
		})
	}
}

func TestBuildMessage_BodyMayContainLineBreaks(t *testing.T) {
	_, err := buildMessage("a@b.c", contract.Email{
		To:      "t@example.com",
		Subject: "s",
		Body:    "baris 1\r\nbaris 2\nbaris 3",
	})
	if err != nil {
		t.Fatalf("pemeriksaan header tidak boleh menolak isi body: %v", err)
	}
}

func TestNewSMTPMailer_RequiresAllFields(t *testing.T) {
	clear := map[string]func(*SMTPConfig){
		"host":     func(c *SMTPConfig) { c.Host = "" },
		"port":     func(c *SMTPConfig) { c.Port = "" },
		"username": func(c *SMTPConfig) { c.Username = "" },
		"password": func(c *SMTPConfig) { c.Password = "" },
		"from":     func(c *SMTPConfig) { c.From = "" },
	}

	for name, mutate := range clear {
		t.Run(name, func(t *testing.T) {
			cfg := validSMTPConfig()
			mutate(&cfg)

			if _, err := NewSMTPMailer(cfg, time.Second); err == nil {
				t.Fatalf("konfigurasi tanpa %s harus ditolak", name)
			}
		})
	}
}

func TestNewSMTPMailer_BuildsAddressAndKeepsTimeout(t *testing.T) {
	mailer, err := NewSMTPMailer(validSMTPConfig(), 3*time.Second)
	if err != nil {
		t.Fatalf("NewSMTPMailer() error = %v", err)
	}

	if mailer.addr != "smtp.example.com:2525" {
		t.Fatalf("addr = %q", mailer.addr)
	}

	if mailer.timeout != 3*time.Second {
		t.Fatalf("timeout = %v", mailer.timeout)
	}
}

func TestSMTPMailerSend_InvalidHeaderFailsBeforeAnyNetworkCall(t *testing.T) {
	mailer, err := NewSMTPMailer(validSMTPConfig(), time.Second)
	if err != nil {
		t.Fatalf("NewSMTPMailer() error = %v", err)
	}

	status, err := mailer.Send(context.Background(), contract.Email{
		To:      "t@example.com\nBcc: x@y.z",
		Subject: "s",
		Body:    "b",
	})
	if err == nil {
		t.Fatal("header dengan line break harus ditolak")
	}

	if status != "" {
		t.Fatalf("status saat error harus kosong, got %q", status)
	}
}
