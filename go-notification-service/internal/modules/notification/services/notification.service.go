package services

import (
	"crypto/tls"
	"fmt"
	"log"
	"net/smtp"
	"os"

	"go-notification-service/internal/modules/notification/contract"
)

type notificationServiceImpl struct {
	repo contract.Repository
}

func NewNotificationService(repo contract.Repository) contract.Service {
	return &notificationServiceImpl{repo: repo}
}

func (s *notificationServiceImpl) SendEmail(to, subject, body string) error {
	host := os.Getenv("SMTP_HOST")
	if host == "" {
		log.Printf("[SMTP fallback/log] To: %s | Subject: %s | Body: %s", to, subject, body)
		// Fallback log untuk mencatat email yang gagal dikirim
		_ = s.repo.SaveNotificationLog(to, "SUCCESS_FALLBACK")
		return nil
	}

	port := os.Getenv("SMTP_PORT")
	user := os.Getenv("SMTP_USERNAME")
	pass := os.Getenv("SMTP_PASSWORD")
	from := os.Getenv("SMTP_FROM")

	addr := fmt.Sprintf("%s:%s", host, port)
	auth := smtp.PlainAuth("", user, pass, host)

	msg := []byte(fmt.Sprintf("From: %s\r\nTo: %s\r\nSubject: %s\r\n\r\n%s", from, to, subject, body))
	tlsConfig := &tls.Config{ServerName: host}
	
	// Proses pengiriman
	err := sendSmtpMail(addr, host, auth, from, to, msg, tlsConfig)
	if err != nil {
		// Jika gagal kirim email, simpan status FAILED ke database
		_ = s.repo.SaveNotificationLog(to, "FAILED")
		return err
	}

	// Jika berhasil kirim email, simpan status SUCCESS ke database
	_ = s.repo.SaveNotificationLog(to, "SUCCESS")
	return nil
}

func sendSmtpMail(addr, host string, auth smtp.Auth, from, to string, msg []byte, tlsConfig *tls.Config) error {
	conn, err := tls.Dial("tcp", addr, tlsConfig)
	if err != nil {
		return err
	}
	defer conn.Close()

	client, err := smtp.NewClient(conn, host)
	if err != nil {
		return err
	}
	defer client.Close()

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
