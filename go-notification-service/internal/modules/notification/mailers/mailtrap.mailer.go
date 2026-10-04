package mailers

import (
	"context"
	"fmt"
	"time"

	"github.com/mailtrap/mailtrap-go"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

type MailtrapMailer struct {
	client  *mailtrap.Client
	from    mailtrap.Address
	timeout time.Duration
}

var _ contract.Mailer = (*MailtrapMailer)(nil)

func NewMailtrapMailer(cfg MailtrapConfig, timeout time.Duration) (*MailtrapMailer, error) {
	if cfg.APIToken == "" || cfg.FromEmail == "" {
		return nil, fmt.Errorf("MAILTRAP_API_TOKEN dan MAILTRAP_FROM_EMAIL wajib diisi")
	}

	client, err := mailtrap.NewClient(cfg.APIToken)
	if err != nil {
		return nil, fmt.Errorf("create mailtrap client: %w", err)
	}

	return &MailtrapMailer{
		client:  client,
		from:    mailtrap.Address{Email: cfg.FromEmail, Name: cfg.FromName},
		timeout: timeout,
	}, nil
}

func (m *MailtrapMailer) Send(ctx context.Context, email contract.Email) (values.DeliveryStatus, error) {
	ctx, cancel := context.WithTimeout(ctx, m.timeout)
	defer cancel()

	_, _, err := m.client.Send(ctx, &mailtrap.SendRequest{
		From:     m.from,
		To:       []mailtrap.Address{{Email: email.To}},
		Subject:  email.Subject,
		Text:     email.Body,
		Category: "TaskFlow Notification",
	})
	if err != nil {
		return "", fmt.Errorf("mailtrap send: %w", err)
	}

	return values.DeliveryStatusSuccess, nil
}
