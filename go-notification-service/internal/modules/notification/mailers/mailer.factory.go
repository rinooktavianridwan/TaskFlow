package mailers

import (
	"fmt"
	"log"

	"go-notification-service/internal/modules/notification/contract"
)

const (
	providerSMTP     = "smtp"
	providerMailtrap = "mailtrap"
)

// New memilih implementasi Mailer berdasarkan konfigurasi.
// Konfigurasi yang tidak valid menghasilkan error agar service gagal start,
// bukan gagal diam-diam saat email pertama dikirim.
func New(cfg Config) (contract.Mailer, error) {
	switch cfg.Provider {
	case providerSMTP:
		mailer, err := NewSMTPMailer(cfg.SMTP, cfg.Timeout)
		if err != nil {
			return nil, err
		}

		log.Printf("[Mailer] Provider aktif: smtp (%s)", mailer.addr)

		return mailer, nil

	case providerMailtrap:
		mailer, err := NewMailtrapMailer(cfg.Mailtrap, cfg.Timeout)
		if err != nil {
			return nil, err
		}

		log.Println("[Mailer] Provider aktif: mailtrap (API)")

		return mailer, nil

	case "":
		log.Println("[Mailer] MAIL_PROVIDER kosong: email hanya ditulis ke log (SUCCESS_FALLBACK).")

		return LogMailer{}, nil

	default:
		return nil, fmt.Errorf(
			"MAIL_PROVIDER %q tidak dikenal (gunakan %q, %q, atau kosongkan)",
			cfg.Provider,
			providerSMTP,
			providerMailtrap,
		)
	}
}
