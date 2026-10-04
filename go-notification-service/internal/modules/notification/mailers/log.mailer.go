package mailers

import (
	"context"
	"log"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

// LogMailer tidak mengirim apa pun; hanya mencatat email ke log (untuk development).
type LogMailer struct{}

var _ contract.Mailer = LogMailer{}

func (LogMailer) Send(_ context.Context, email contract.Email) (values.DeliveryStatus, error) {
	log.Printf(
		"[Mail fallback/log] To: %s | Type: %s | Subject: %s | Body: %s",
		email.To,
		email.Type,
		email.Subject,
		email.Body,
	)

	return values.DeliveryStatusSuccessFallback, nil
}
