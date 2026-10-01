package services

import (
	"context"
	"crypto/tls"
	"fmt"
	"log"
	"net/smtp"
	"os"

	"github.com/mailtrap/mailtrap-go"

	"go-notification-service/internal/modules/notification/contract"
)

type notificationServiceImpl struct {
	repo contract.Repository
}

func NewNotificationService(repo contract.Repository) contract.Service {
	return &notificationServiceImpl{repo: repo}
}

func (s *notificationServiceImpl) SendEmail(to, subject, body string) error {
	provider := os.Getenv("MAIL_PROVIDER") // "mailtrap" atau "smtp"

	var err error
	switch provider {
	case "smtp":
		err = sendSmtpMail(to, subject, body)
	case "mailtrap":
		err = sendMailtrapMail(to, subject, body)
	default:
		log.Printf("[Mail fallback/log] To: %s | Subject: %s | Body: %s", to, subject, body)
		_ = s.repo.SaveNotificationLog(to, "SUCCESS_FALLBACK")
		return nil
	}

	if err != nil {
		_ = s.repo.SaveNotificationLog(to, "FAILED")
		return err
	}

	_ = s.repo.SaveNotificationLog(to, "SUCCESS")
	return nil
}

// ---------- Mailtrap (HTTP API) ----------

func sendMailtrapMail(to, subject, body string) error {
	client, err := mailtrap.NewClient(os.Getenv("MAILTRAP_API_TOKEN"))
	if err != nil {
		return err
	}

	_, _, err = client.Send(context.Background(), &mailtrap.SendRequest{
		From:     mailtrap.Address{Email: os.Getenv("MAILTRAP_FROM_EMAIL"), Name: os.Getenv("MAILTRAP_FROM_NAME")},
		To:       []mailtrap.Address{{Email: to}},
		Subject:  subject,
		Text:     body,
		Category: "TaskFlow Notification",
	})

	return err
}

// ---------- Gmail / SMTP umum (STARTTLS) ----------

func sendSmtpMail(to, subject, body string) error {
	host := os.Getenv("SMTP_HOST")
	port := os.Getenv("SMTP_PORT")
	user := os.Getenv("SMTP_USERNAME")
	pass := os.Getenv("SMTP_PASSWORD")
	from := os.Getenv("SMTP_FROM")

	addr := fmt.Sprintf("%s:%s", host, port)
	auth := smtp.PlainAuth("", user, pass, host)
	msg := []byte(fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\n\r\n%s", from, to, subject, body))

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
	w, err := client.Data()
	if err != nil {
		return err
	}
	defer w.Close()

	_, err = w.Write(msg)
	return err
}
