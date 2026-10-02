package values

type NotificationType string

const (
	NotificationTypeVerification NotificationType = "VERIFICATION"
	NotificationTypeInvitation   NotificationType = "INVITATION"
	NotificationTypeTaskReminder NotificationType = "TASK_REMINDER"
)

type DeliveryStatus string

const (
	DeliveryStatusSuccess         DeliveryStatus = "SUCCESS"
	DeliveryStatusFailed          DeliveryStatus = "FAILED"
	DeliveryStatusSuccessFallback DeliveryStatus = "SUCCESS_FALLBACK"
)

type TaskReminderStatus string

const (
	TaskReminderStatusScheduled  TaskReminderStatus = "SCHEDULED"
	TaskReminderStatusProcessing TaskReminderStatus = "PROCESSING"
	TaskReminderStatusSent       TaskReminderStatus = "SENT"
	TaskReminderStatusCancelled  TaskReminderStatus = "CANCELLED"
	TaskReminderStatusFailed     TaskReminderStatus = "FAILED"
)
