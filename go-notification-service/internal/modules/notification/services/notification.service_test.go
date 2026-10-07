package services

import (
	"context"
	"errors"
	"fmt"
	"io"
	"log"
	"os"
	"slices"
	"strings"
	"testing"
	"time"

	"go-notification-service/internal/modules/notification/contract"
	"go-notification-service/internal/modules/notification/values"
)

func TestMain(m *testing.M) {
	log.SetOutput(io.Discard)
	os.Exit(m.Run())
}

// ---------------------------------------------------------------- fake

type fakeRepo struct {
	logs        []contract.NotificationLog
	upserted    []contract.TaskReminder
	cancelled   []int64
	claimResult []contract.TaskReminder
	claimInputs []contract.ClaimDueTaskRemindersInput
	sentIDs     []int64
	retries     []contract.RetryTaskReminderInput

	saveLogErr  error
	upsertErr   error
	cancelErr   error
	claimErr    error
	markSentErr error
	retryErr    error
}

func (r *fakeRepo) SaveNotificationLog(entry contract.NotificationLog) error {
	r.logs = append(r.logs, entry)
	return r.saveLogErr
}

func (r *fakeRepo) UpsertTaskReminder(_ context.Context, reminder contract.TaskReminder) error {
	r.upserted = append(r.upserted, reminder)
	return r.upsertErr
}

func (r *fakeRepo) CancelTaskReminder(_ context.Context, taskID int64) error {
	r.cancelled = append(r.cancelled, taskID)
	return r.cancelErr
}

func (r *fakeRepo) ClaimDueTaskReminders(
	_ context.Context,
	input contract.ClaimDueTaskRemindersInput,
) ([]contract.TaskReminder, error) {
	r.claimInputs = append(r.claimInputs, input)
	return r.claimResult, r.claimErr
}

func (r *fakeRepo) MarkTaskReminderSent(_ context.Context, taskID int64, _ time.Time) error {
	r.sentIDs = append(r.sentIDs, taskID)
	return r.markSentErr
}

func (r *fakeRepo) RetryTaskReminder(_ context.Context, input contract.RetryTaskReminderInput) error {
	r.retries = append(r.retries, input)
	return r.retryErr
}

type fakeMailer struct {
	sent   []contract.Email
	status values.DeliveryStatus
	err    error
	errFor map[string]error
}

func newFakeMailer() *fakeMailer {
	return &fakeMailer{status: values.DeliveryStatusSuccess, errFor: map[string]error{}}
}

func (m *fakeMailer) Send(_ context.Context, email contract.Email) (values.DeliveryStatus, error) {
	m.sent = append(m.sent, email)

	if err := m.errFor[email.To]; err != nil {
		return "", err
	}

	if m.err != nil {
		return "", m.err
	}

	return m.status, nil
}

func newTestService(repo *fakeRepo, mailer *fakeMailer) *notificationServiceImpl {
	return &notificationServiceImpl{repo: repo, mailer: mailer}
}

func validScheduleInput(due time.Time) contract.ScheduleTaskReminderInput {
	return contract.ScheduleTaskReminderInput{
		TaskID:        7,
		TaskTitle:     "  Kirim laporan  ",
		ProjectName:   " Proyek A ",
		AssigneeEmail: " budi@example.com ",
		DueDate:       due.Format(time.RFC3339),
	}
}

func dueReminder(id int64, email string, attempt int) contract.TaskReminder {
	return contract.TaskReminder{
		TaskID:         id,
		TaskTitle:      fmt.Sprintf("Laporan %d", id),
		ProjectName:    "Proyek A",
		RecipientEmail: email,
		DueAt:          time.Date(2030, time.January, 2, 15, 4, 0, 0, time.UTC),
		AttemptCount:   attempt,
	}
}

// ---------------------------------------------------------------- SendEmail

func TestSendEmail_TrimsRecipientAndLogsSuccess(t *testing.T) {
	repo := &fakeRepo{}
	mailer := newFakeMailer()
	svc := newTestService(repo, mailer)

	err := svc.SendEmail(context.Background(), contract.Email{
		To:      "  budi@example.com ",
		Type:    values.NotificationTypeInvitation,
		Subject: "Subjek",
		Body:    "Isi",
	})
	if err != nil {
		t.Fatalf("SendEmail() error = %v", err)
	}

	if len(mailer.sent) != 1 || mailer.sent[0].To != "budi@example.com" {
		t.Fatalf("penerima harus di-trim sebelum dikirim, got %+v", mailer.sent)
	}

	want := contract.NotificationLog{
		RecipientEmail:   "budi@example.com",
		NotificationType: values.NotificationTypeInvitation,
		Status:           values.DeliveryStatusSuccess,
	}
	if len(repo.logs) != 1 || repo.logs[0] != want {
		t.Fatalf("log = %+v, want %+v", repo.logs, want)
	}
}

func TestSendEmail_RecordsFallbackStatusFromMailer(t *testing.T) {
	repo := &fakeRepo{}
	mailer := newFakeMailer()
	mailer.status = values.DeliveryStatusSuccessFallback
	svc := newTestService(repo, mailer)

	if err := svc.SendEmail(context.Background(), contract.Email{To: "a@example.com"}); err != nil {
		t.Fatalf("SendEmail() error = %v", err)
	}

	if repo.logs[0].Status != values.DeliveryStatusSuccessFallback {
		t.Fatalf("status = %q, want %q", repo.logs[0].Status, values.DeliveryStatusSuccessFallback)
	}
}

func TestSendEmail_MailerErrorIsReturnedAndLoggedAsFailed(t *testing.T) {
	boom := errors.New("smtp down")
	repo := &fakeRepo{}
	mailer := newFakeMailer()
	mailer.err = boom
	svc := newTestService(repo, mailer)

	err := svc.SendEmail(context.Background(), contract.Email{
		To:   "a@example.com",
		Type: values.NotificationTypeVerification,
	})
	if !errors.Is(err, boom) {
		t.Fatalf("error = %v, want %v", err, boom)
	}

	if len(repo.logs) != 1 {
		t.Fatalf("harus ada 1 log, got %d", len(repo.logs))
	}

	if repo.logs[0].Status != values.DeliveryStatusFailed || repo.logs[0].ErrorMessage != "smtp down" {
		t.Fatalf("log = %+v", repo.logs[0])
	}
}

func TestSendEmail_EmptyRecipientIsRejectedWithoutSideEffects(t *testing.T) {
	repo := &fakeRepo{}
	mailer := newFakeMailer()
	svc := newTestService(repo, mailer)

	err := svc.SendEmail(context.Background(), contract.Email{To: "   "})
	if err == nil {
		t.Fatal("penerima kosong harus ditolak")
	}

	if len(mailer.sent) != 0 || len(repo.logs) != 0 {
		t.Fatalf("tidak boleh mengirim atau mencatat log, sent=%d logs=%d", len(mailer.sent), len(repo.logs))
	}
}

func TestSendEmail_LogSaveFailureDoesNotFailTheSend(t *testing.T) {
	repo := &fakeRepo{saveLogErr: errors.New("db down")}
	svc := newTestService(repo, newFakeMailer())

	if err := svc.SendEmail(context.Background(), contract.Email{To: "a@example.com"}); err != nil {
		t.Fatalf("email sudah terkirim, kegagalan log tidak boleh jadi error: %v", err)
	}
}

// ---------------------------------------------------------------- ScheduleTaskReminder

func TestScheduleTaskReminder_RejectsInvalidInput(t *testing.T) {
	far := time.Now().UTC().Add(72 * time.Hour)

	tests := []struct {
		name    string
		mutate  func(*contract.ScheduleTaskReminderInput)
		wantErr string
	}{
		{"task id nol", func(in *contract.ScheduleTaskReminderInput) { in.TaskID = 0 }, "task_id"},
		{"task id negatif", func(in *contract.ScheduleTaskReminderInput) { in.TaskID = -3 }, "task_id"},
		{"judul kosong", func(in *contract.ScheduleTaskReminderInput) { in.TaskTitle = "   " }, "task_title"},
		{"project kosong", func(in *contract.ScheduleTaskReminderInput) { in.ProjectName = "" }, "project_name"},
		{"email kosong", func(in *contract.ScheduleTaskReminderInput) { in.AssigneeEmail = " " }, "assignee_email"},
		{"due date bukan RFC3339", func(in *contract.ScheduleTaskReminderInput) { in.DueDate = "2030-01-01 10:00" }, "RFC3339"},
		{"due date bukan UTC", func(in *contract.ScheduleTaskReminderInput) { in.DueDate = "2030-01-01T10:00:00+07:00" }, "UTC"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			repo := &fakeRepo{}
			svc := newTestService(repo, newFakeMailer())

			input := validScheduleInput(far)
			tt.mutate(&input)

			err := svc.ScheduleTaskReminder(context.Background(), input)
			if err == nil || !strings.Contains(err.Error(), tt.wantErr) {
				t.Fatalf("error = %v, want mengandung %q", err, tt.wantErr)
			}

			if len(repo.upserted) != 0 || len(repo.cancelled) != 0 {
				t.Fatal("input tidak valid tidak boleh menyentuh repository")
			}
		})
	}
}

func TestScheduleTaskReminder_FarDueDateRemindsExactly24HoursBefore(t *testing.T) {
	repo := &fakeRepo{}
	svc := newTestService(repo, newFakeMailer())

	due := time.Now().UTC().Add(72 * time.Hour).Truncate(time.Second)

	if err := svc.ScheduleTaskReminder(context.Background(), validScheduleInput(due)); err != nil {
		t.Fatalf("ScheduleTaskReminder() error = %v", err)
	}

	if len(repo.upserted) != 1 {
		t.Fatalf("upsert = %d, want 1", len(repo.upserted))
	}

	got := repo.upserted[0]

	if got.TaskID != 7 || got.TaskTitle != "Kirim laporan" || got.ProjectName != "Proyek A" ||
		got.RecipientEmail != "budi@example.com" {
		t.Fatalf("field harus di-trim, got %+v", got)
	}

	if !got.DueAt.Equal(due) {
		t.Fatalf("DueAt = %v, want %v", got.DueAt, due)
	}

	if want := due.Add(-reminderLeadTime); !got.RemindAt.Equal(want) {
		t.Fatalf("RemindAt = %v, want %v", got.RemindAt, want)
	}

	if !got.NextAttemptAt.Equal(got.RemindAt) {
		t.Fatalf("NextAttemptAt = %v, want sama dengan RemindAt %v", got.NextAttemptAt, got.RemindAt)
	}
}

func TestScheduleTaskReminder_DueWithin24HoursRemindsImmediately(t *testing.T) {
	repo := &fakeRepo{}
	svc := newTestService(repo, newFakeMailer())

	due := time.Now().UTC().Add(2 * time.Hour).Truncate(time.Second)

	before := time.Now().UTC()
	if err := svc.ScheduleTaskReminder(context.Background(), validScheduleInput(due)); err != nil {
		t.Fatalf("ScheduleTaskReminder() error = %v", err)
	}
	after := time.Now().UTC()

	got := repo.upserted[0]

	if got.RemindAt.Before(before) || got.RemindAt.After(after) {
		t.Fatalf("RemindAt = %v, harus antara %v dan %v (secepatnya)", got.RemindAt, before, after)
	}

	if !got.DueAt.Equal(due) {
		t.Fatalf("DueAt tidak boleh berubah, got %v want %v", got.DueAt, due)
	}
}

func TestScheduleTaskReminder_PastDueDateCancelsInsteadOfScheduling(t *testing.T) {
	repo := &fakeRepo{}
	svc := newTestService(repo, newFakeMailer())

	past := time.Now().UTC().Add(-time.Hour)

	if err := svc.ScheduleTaskReminder(context.Background(), validScheduleInput(past)); err != nil {
		t.Fatalf("ScheduleTaskReminder() error = %v", err)
	}

	if len(repo.upserted) != 0 {
		t.Fatal("due date yang sudah lewat tidak boleh dijadwalkan")
	}

	if !slices.Equal(repo.cancelled, []int64{7}) {
		t.Fatalf("cancelled = %v, want [7]", repo.cancelled)
	}
}

func TestScheduleTaskReminder_AcceptsZeroOffsetWrittenAsPlusZero(t *testing.T) {
	repo := &fakeRepo{}
	svc := newTestService(repo, newFakeMailer())

	input := validScheduleInput(time.Now().UTC().Add(72 * time.Hour))
	input.DueDate = time.Now().UTC().Add(72*time.Hour).Format("2006-01-02T15:04:05") + "+00:00"

	if err := svc.ScheduleTaskReminder(context.Background(), input); err != nil {
		t.Fatalf("offset +00:00 adalah UTC dan harus diterima: %v", err)
	}

	if len(repo.upserted) != 1 {
		t.Fatalf("upsert = %d, want 1", len(repo.upserted))
	}
}

func TestScheduleTaskReminder_WrapsRepositoryError(t *testing.T) {
	boom := errors.New("db down")
	repo := &fakeRepo{upsertErr: boom}
	svc := newTestService(repo, newFakeMailer())

	err := svc.ScheduleTaskReminder(context.Background(), validScheduleInput(time.Now().UTC().Add(72*time.Hour)))
	if !errors.Is(err, boom) || !strings.Contains(err.Error(), "schedule task reminder") {
		t.Fatalf("error = %v", err)
	}
}

// ---------------------------------------------------------------- CancelTaskReminder

func TestCancelTaskReminder(t *testing.T) {
	t.Run("task id harus positif", func(t *testing.T) {
		repo := &fakeRepo{}
		svc := newTestService(repo, newFakeMailer())

		if err := svc.CancelTaskReminder(context.Background(), 0); err == nil {
			t.Fatal("task_id 0 harus ditolak")
		}

		if len(repo.cancelled) != 0 {
			t.Fatal("repository tidak boleh dipanggil")
		}
	})

	t.Run("meneruskan ke repository", func(t *testing.T) {
		repo := &fakeRepo{}
		svc := newTestService(repo, newFakeMailer())

		if err := svc.CancelTaskReminder(context.Background(), 9); err != nil {
			t.Fatalf("error = %v", err)
		}

		if !slices.Equal(repo.cancelled, []int64{9}) {
			t.Fatalf("cancelled = %v, want [9]", repo.cancelled)
		}
	})

	t.Run("error repository dibungkus", func(t *testing.T) {
		boom := errors.New("db down")
		svc := newTestService(&fakeRepo{cancelErr: boom}, newFakeMailer())

		err := svc.CancelTaskReminder(context.Background(), 9)
		if !errors.Is(err, boom) || !strings.Contains(err.Error(), "cancel task reminder") {
			t.Fatalf("error = %v", err)
		}
	})
}

// ---------------------------------------------------------------- processDueTaskReminders

func TestProcessDueTaskReminders_ClaimsWithBatchAndStaleWindow(t *testing.T) {
	repo := &fakeRepo{}
	svc := newTestService(repo, newFakeMailer())

	before := time.Now().UTC()
	if err := svc.processDueTaskReminders(context.Background()); err != nil {
		t.Fatalf("error = %v", err)
	}
	after := time.Now().UTC()

	if len(repo.claimInputs) != 1 {
		t.Fatalf("claim dipanggil %d kali, want 1", len(repo.claimInputs))
	}

	in := repo.claimInputs[0]

	if in.Now.Before(before) || in.Now.After(after) {
		t.Fatalf("Now = %v, harus antara %v dan %v", in.Now, before, after)
	}

	if in.Now.Sub(in.StaleBefore) != reminderStaleAfter {
		t.Fatalf("StaleBefore = Now - %v, got selisih %v", reminderStaleAfter, in.Now.Sub(in.StaleBefore))
	}

	if in.Limit != reminderBatchSize {
		t.Fatalf("Limit = %d, want %d", in.Limit, reminderBatchSize)
	}
}

func TestProcessDueTaskReminders_SendsEmailAndMarksSent(t *testing.T) {
	repo := &fakeRepo{claimResult: []contract.TaskReminder{dueReminder(11, "budi@example.com", 0)}}
	mailer := newFakeMailer()
	svc := newTestService(repo, mailer)

	if err := svc.processDueTaskReminders(context.Background()); err != nil {
		t.Fatalf("error = %v", err)
	}

	if len(mailer.sent) != 1 {
		t.Fatalf("email terkirim = %d, want 1", len(mailer.sent))
	}

	email := mailer.sent[0]

	if email.To != "budi@example.com" || email.Type != values.NotificationTypeTaskReminder {
		t.Fatalf("email = %+v", email)
	}

	if !strings.Contains(email.Subject, `"Laporan 11"`) {
		t.Fatalf("subjek harus memuat judul task, got %q", email.Subject)
	}

	for _, want := range []string{"Project: Proyek A", "Task: Laporan 11", "Jatuh tempo: 02 Jan 2030 15:04 UTC"} {
		if !strings.Contains(email.Body, want) {
			t.Fatalf("body tidak memuat %q:\n%s", want, email.Body)
		}
	}

	if !slices.Equal(repo.sentIDs, []int64{11}) {
		t.Fatalf("sentIDs = %v, want [11]", repo.sentIDs)
	}

	if len(repo.retries) != 0 {
		t.Fatal("tidak boleh ada retry pada pengiriman sukses")
	}
}

func TestProcessDueTaskReminders_FailureSchedulesRetryAndContinuesWithNext(t *testing.T) {
	boom := errors.New("smtp down")
	repo := &fakeRepo{claimResult: []contract.TaskReminder{
		dueReminder(1, "gagal@example.com", 2),
		dueReminder(2, "ok@example.com", 1),
	}}
	mailer := newFakeMailer()
	mailer.errFor["gagal@example.com"] = boom
	svc := newTestService(repo, mailer)

	before := time.Now().UTC()
	if err := svc.processDueTaskReminders(context.Background()); err != nil {
		t.Fatalf("satu kegagalan tidak boleh menghentikan batch: %v", err)
	}
	after := time.Now().UTC()

	if len(mailer.sent) != 2 {
		t.Fatalf("kedua reminder harus dicoba, sent = %d", len(mailer.sent))
	}

	if len(repo.retries) != 1 {
		t.Fatalf("retries = %d, want 1", len(repo.retries))
	}

	retry := repo.retries[0]

	if retry.TaskID != 1 || retry.LastError != "smtp down" || retry.MaxAttempts != reminderMaxAttempts {
		t.Fatalf("retry = %+v", retry)
	}

	// Backoff linear: attempt ke-2 menunggu 2 x reminderRetryDelay.
	delay := 2 * reminderRetryDelay
	if retry.NextAttemptAt.Before(before.Add(delay)) || retry.NextAttemptAt.After(after.Add(delay)) {
		t.Fatalf("NextAttemptAt = %v, harus sekitar sekarang + %v", retry.NextAttemptAt, delay)
	}

	if !slices.Equal(repo.sentIDs, []int64{2}) {
		t.Fatalf("hanya reminder 2 yang boleh ditandai terkirim, got %v", repo.sentIDs)
	}

	if len(repo.logs) != 2 || repo.logs[0].Status != values.DeliveryStatusFailed ||
		repo.logs[1].Status != values.DeliveryStatusSuccess {
		t.Fatalf("logs = %+v", repo.logs)
	}
}

func TestProcessDueTaskReminders_ClaimErrorIsWrappedAndSendsNothing(t *testing.T) {
	boom := errors.New("db down")
	repo := &fakeRepo{claimErr: boom}
	mailer := newFakeMailer()
	svc := newTestService(repo, mailer)

	err := svc.processDueTaskReminders(context.Background())
	if !errors.Is(err, boom) || !strings.Contains(err.Error(), "claim due task reminders") {
		t.Fatalf("error = %v", err)
	}

	if len(mailer.sent) != 0 {
		t.Fatal("tidak boleh mengirim apa pun")
	}
}

func TestProcessDueTaskReminders_StopsWhenContextIsCancelled(t *testing.T) {
	repo := &fakeRepo{claimResult: []contract.TaskReminder{dueReminder(1, "a@example.com", 0)}}
	mailer := newFakeMailer()
	svc := newTestService(repo, mailer)

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	err := svc.processDueTaskReminders(ctx)
	if !errors.Is(err, context.Canceled) {
		t.Fatalf("error = %v, want context.Canceled", err)
	}

	if len(mailer.sent) != 0 {
		t.Fatal("konteks yang dibatalkan tidak boleh mengirim email")
	}
}

func TestProcessDueTaskReminders_MarkSentFailureIsOnlyLogged(t *testing.T) {
	repo := &fakeRepo{
		claimResult: []contract.TaskReminder{dueReminder(1, "a@example.com", 0)},
		markSentErr: errors.New("db down"),
	}
	svc := newTestService(repo, newFakeMailer())

	if err := svc.processDueTaskReminders(context.Background()); err != nil {
		t.Fatalf("email sudah terkirim, kegagalan menandai tidak boleh jadi error: %v", err)
	}

	if len(repo.retries) != 0 {
		t.Fatal("email sukses tidak boleh dijadwalkan ulang")
	}
}

func TestProcessDueTaskReminders_RetryRecordFailureDoesNotStopBatch(t *testing.T) {
	repo := &fakeRepo{
		claimResult: []contract.TaskReminder{
			dueReminder(1, "gagal@example.com", 1),
			dueReminder(2, "ok@example.com", 1),
		},
		retryErr: errors.New("db down"),
	}
	mailer := newFakeMailer()
	mailer.errFor["gagal@example.com"] = errors.New("smtp down")
	svc := newTestService(repo, mailer)

	if err := svc.processDueTaskReminders(context.Background()); err != nil {
		t.Fatalf("error = %v", err)
	}

	if !slices.Equal(repo.sentIDs, []int64{2}) {
		t.Fatalf("sentIDs = %v, want [2]", repo.sentIDs)
	}
}

// ---------------------------------------------------------------- worker

func TestRunTaskReminderWorker_ProcessesOnceThenStopsOnCancel(t *testing.T) {
	repo := &fakeRepo{}
	svc := newTestService(repo, newFakeMailer())

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	done := make(chan struct{})
	go func() {
		svc.RunTaskReminderWorker(ctx)
		close(done)
	}()

	select {
	case <-done:
	case <-time.After(2 * time.Second):
		t.Fatal("worker harus berhenti setelah konteks dibatalkan")
	}

	if len(repo.claimInputs) != 1 {
		t.Fatalf("worker memproses sekali di awal, claim = %d", len(repo.claimInputs))
	}
}

func TestNewNotificationService_ReturnsService(t *testing.T) {
	if NewNotificationService(&fakeRepo{}, newFakeMailer()) == nil {
		t.Fatal("service tidak boleh nil")
	}
}
