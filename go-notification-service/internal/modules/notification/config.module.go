package notification

import (
	"context"
	"database/sql"

	"go-notification-service/internal/modules/notification/controllers"
	"go-notification-service/internal/modules/notification/repositories"
	"go-notification-service/internal/modules/notification/services"
	pb "go-notification-service/pb/notification/v1"

	"google.golang.org/grpc"
)

func InitModule(grpcServer *grpc.Server, db *sql.DB) context.CancelFunc {
	notificationRepo := repositories.NewNotificationRepository(db)
	emailService := services.NewNotificationService(notificationRepo)
	notificationController := controllers.NewNotificationController(emailService)

	pb.RegisterNotificationServiceServer(grpcServer, notificationController)

	workerContext, cancelWorker := context.WithCancel(context.Background())
	go emailService.RunTaskReminderWorker(workerContext)

	return cancelWorker
}
