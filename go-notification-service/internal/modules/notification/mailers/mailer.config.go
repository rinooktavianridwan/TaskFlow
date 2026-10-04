package mailers

import (
	"os"
	"strings"
	"time"
)

const defaultSendTimeout = 10 * time.Second

type SMTPConfig struct {
	Host     string
	Port     string
	Username string
	Password string
	From     string
}

type MailtrapConfig struct {
	APIToken  string
	FromEmail string
	FromName  string
}

type Config struct {
	// Provider: "smtp", "mailtrap", atau kosong (hanya tulis ke log).
	Provider string
	Timeout  time.Duration
	SMTP     SMTPConfig
	Mailtrap MailtrapConfig
}

// LoadConfigFromEnv dipanggil sekali saat startup, bukan di setiap pengiriman.
func LoadConfigFromEnv() Config {
	return Config{
		Provider: strings.ToLower(env("MAIL_PROVIDER")),
		Timeout:  defaultSendTimeout,
		SMTP: SMTPConfig{
			Host:     env("SMTP_HOST"),
			Port:     env("SMTP_PORT"),
			Username: env("SMTP_USERNAME"),
			Password: env("SMTP_PASSWORD"),
			From:     env("SMTP_FROM"),
		},
		Mailtrap: MailtrapConfig{
			APIToken:  env("MAILTRAP_API_TOKEN"),
			FromEmail: env("MAILTRAP_FROM_EMAIL"),
			FromName:  env("MAILTRAP_FROM_NAME"),
		},
	}
}

func env(key string) string {
	return strings.TrimSpace(os.Getenv(key))
}
