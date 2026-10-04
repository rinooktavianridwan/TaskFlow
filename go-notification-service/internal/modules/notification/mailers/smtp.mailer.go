package mailers

import (
	"context"
	"crypto/tls"
	"fmt"
	"mime"
	"net"
	"net/smtp"
	"strings"
	"time"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

type SMTPMailer struct {
	cfg     SMTPConfig
	addr    string
	timeout time.Duration
}

var _ contract.Mailer = (*SMTPMailer)(nil)

func NewSMTPMailer(cfg SMTPConfig, timeout time.Duration) (*SMTPMailer, error) {
	if cfg.Host == "" || cfg.Port == "" || cfg.Username == "" || cfg.Password == "" || cfg.From == "" {
		return nil, fmt.Errorf("SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, dan SMTP_FROM wajib diisi")
	}

	return &SMTPMailer{
		cfg:     cfg,
		addr:    net.JoinHostPort(cfg.Host, cfg.Port),
		timeout: timeout,
	}, nil
}

func (m *SMTPMailer) Send(ctx context.Context, email contract.Email) (values.DeliveryStatus, error) {
	msg, err := buildMessage(m.cfg.From, email)
	if err != nil {
		return "", err
	}

	ctx, cancel := context.WithTimeout(ctx, m.timeout)
	defer cancel()

	if err := m.deliver(ctx, email.To, msg); err != nil {
		return "", err
	}

	return values.DeliveryStatusSuccess, nil
}

func (m *SMTPMailer) deliver(ctx context.Context, to string, msg []byte) error {
	var dialer net.Dialer

	conn, err := dialer.DialContext(ctx, "tcp", m.addr)
	if err != nil {
		return fmt.Errorf("dial smtp %s: %w", m.addr, err)
	}

	// Deadline di koneksi membatasi seluruh percakapan SMTP, bukan hanya proses
	// dial. Tanpa ini, server yang berhenti merespons membuat kita menggantung.
	if deadline, ok := ctx.Deadline(); ok {
		if err := conn.SetDeadline(deadline); err != nil {
			_ = conn.Close()
			return fmt.Errorf("set smtp deadline: %w", err)
		}
	}

	client, err := smtp.NewClient(conn, m.cfg.Host)
	if err != nil {
		_ = conn.Close()
		return fmt.Errorf("start smtp session: %w", err)
	}
	defer client.Close()

	tlsConfig := &tls.Config{ServerName: m.cfg.Host, MinVersion: tls.VersionTLS12}
	if err := client.StartTLS(tlsConfig); err != nil {
		return fmt.Errorf("smtp starttls: %w", err)
	}

	auth := smtp.PlainAuth("", m.cfg.Username, m.cfg.Password, m.cfg.Host)
	if err := client.Auth(auth); err != nil {
		return fmt.Errorf("smtp auth: %w", err)
	}

	if err := client.Mail(m.cfg.From); err != nil {
		return fmt.Errorf("smtp mail from: %w", err)
	}

	if err := client.Rcpt(to); err != nil {
		return fmt.Errorf("smtp rcpt to: %w", err)
	}

	writer, err := client.Data()
	if err != nil {
		return fmt.Errorf("smtp data: %w", err)
	}

	if _, err := writer.Write(msg); err != nil {
		_ = writer.Close()
		return fmt.Errorf("smtp write message: %w", err)
	}

	if err := writer.Close(); err != nil {
		return fmt.Errorf("smtp finish message: %w", err)
	}

	// Email sudah diterima server begitu DATA ditutup. Kegagalan QUIT tidak boleh
	// dianggap gagal kirim, kalau tidak job akan retry dan penerima dapat email ganda.
	_ = client.Quit()

	return nil
}

func buildMessage(from string, email contract.Email) ([]byte, error) {
	if strings.ContainsAny(from+email.To+email.Subject, "\r\n") {
		return nil, fmt.Errorf("email header must not contain line breaks")
	}

	return fmt.Appendf(
		nil,
		"From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n%s",
		from,
		email.To,
		mime.QEncoding.Encode("utf-8", email.Subject),
		email.Body,
	), nil
}
