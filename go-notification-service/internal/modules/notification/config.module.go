package notification

import (
	"database/sql"
	"google.golang.org/grpc"
	
	"go-notification-service/internal/modules/notification/controllers"
	"go-notification-service/internal/modules/notification/repositories"
	"go-notification-service/internal/modules/notification/services"
	pb "go-notification-service/pb/notification/v1"
)

// InitModule menerima grpcServer dan koneksi database
func InitModule(grpcServer *grpc.Server, db *sql.DB) {
	// 1. Repository
	notificationRepo := repositories.NewNotificationRepository(db)

	// 2. Service
	emailService := services.NewNotificationService(notificationRepo)

	// 3. Controller
	notificationController := controllers.NewNotificationController(emailService)

	// 4. Register
	pb.RegisterNotificationServiceServer(grpcServer, notificationController)
}
