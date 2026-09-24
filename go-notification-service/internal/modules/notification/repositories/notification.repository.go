package repositories

import (
	"database/sql"
	"log"

	"go-notification-service/internal/modules/notification/contract"
)

type notificationRepositoryImpl struct {
	db *sql.DB
}

func NewNotificationRepository(db *sql.DB) contract.Repository {
	return &notificationRepositoryImpl{db: db}
}

func (r *notificationRepositoryImpl) SaveNotificationLog(email, status string) error {
	// notification_type di-hardcode sementara ke 'EMAIL' untuk memenuhi skema tabel
	query := `INSERT INTO notification_logs (recipient_email, notification_type, status) VALUES (?, 'EMAIL', ?)`
	
	_, err := r.db.Exec(query, email, status)
	if err != nil {
		log.Printf("[Database Error] Gagal menyimpan log notifikasi untuk %s: %v", email, err)
		return err
	}

	log.Printf("[Database] Log notifikasi berhasil disimpan: %s -> %s", email, status)
	return nil
}
