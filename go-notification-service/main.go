package main

import (
	"database/sql"
	"fmt"
	"log"
	"net"
	"os"

	_ "github.com/go-sql-driver/mysql"
	"github.com/joho/godotenv"
	"google.golang.org/grpc"
	"google.golang.org/grpc/health"
	"google.golang.org/grpc/health/grpc_health_v1"
	"google.golang.org/grpc/reflection"

	"go-notification-service/database/migration"
	"go-notification-service/internal/modules/notification"
)

func main() {
	// =========================================================================
	// LOAD ENVIRONMENT VARIABLES
	// =========================================================================

	if err := godotenv.Load(); err != nil {
		log.Println("[Warning] File .env tidak ditemukan, menggunakan environment variable sistem.")
	}

	// =========================================================================
	// DATABASE INITIALIZATION & MIGRATION
	// =========================================================================

	dbUser := os.Getenv("DB_USERNAME")
	dbPass := os.Getenv("DB_PASSWORD")
	dbHost := os.Getenv("DB_HOST")
	dbPort := os.Getenv("DB_PORT")
	dbName := os.Getenv("DB_DATABASE")

	dsn := fmt.Sprintf(
		"%s:%s@tcp(%s:%s)/%s?parseTime=true&loc=UTC",
		dbUser,
		dbPass,
		dbHost,
		dbPort,
		dbName,
	)

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		log.Fatalf("[Database] Gagal membuka koneksi awal: %v", err)
	}
	defer db.Close()

	if err := db.Ping(); err != nil {
		log.Fatalf("[Database] Server tidak merespons: %v", err)
	}
	log.Println("[Database] Berhasil terhubung ke MySQL.")

	migration.RunMigration(db)

	// =========================================================================
	// GRPC SERVER SETUP
	// =========================================================================

	lis, err := net.Listen("tcp", ":50051")
	if err != nil {
		log.Fatalf("[gRPC] Gagal mendengarkan pada port 50051: %v", err)
	}

	grpcServer := grpc.NewServer()

	stopReminderWorker := notification.InitModule(grpcServer, db)
	defer stopReminderWorker()

	// Health Check microservice
	healthServer := health.NewServer()
	grpc_health_v1.RegisterHealthServer(grpcServer, healthServer)
	healthServer.SetServingStatus("", grpc_health_v1.HealthCheckResponse_SERVING)

	// Setup Reflection
	reflection.Register(grpcServer)

	// =========================================================================
	// START SERVER
	// =========================================================================

	log.Println("[gRPC] go-notification-service berjalan di port :50051")
	if err := grpcServer.Serve(lis); err != nil {
		log.Fatalf("[gRPC] Server berhenti secara tiba-tiba: %v", err)
	}
}
