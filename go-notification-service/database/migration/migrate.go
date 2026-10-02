package migration

import (
	"database/sql"
	"log"

	"github.com/golang-migrate/migrate/v4"
	"github.com/golang-migrate/migrate/v4/database/mysql"
	_ "github.com/golang-migrate/migrate/v4/source/file"
)

// RunMigration mengeksekusi file .up.sql yang ada di folder database/migration/sql
func RunMigration(db *sql.DB) {
	driver, err := mysql.WithInstance(db, &mysql.Config{})
	if err != nil {
		log.Fatalf("Gagal inisialisasi driver migrasi MySQL: %v", err)
	}

	// Mengarah ke folder tempat file .sql disimpan
	// Gunakan prefix "file://"
	m, err := migrate.NewWithDatabaseInstance(
		"file://database/migration/sql",
		"mysql",
		driver,
	)
	if err != nil {
		log.Fatalf("Gagal memuat file migrasi: %v", err)
	}

	// Eksekusi migrasi up
	err = m.Up()
	if err != nil && err != migrate.ErrNoChange {
		log.Fatalf("Gagal menjalankan migrasi database: %v", err)
	}

	if err == migrate.ErrNoChange {
		log.Println("[Migration] Database sudah up-to-date.")
	} else {
		log.Println("[Migration] Tabel berhasil dibuat/diperbarui.")
	}
}
