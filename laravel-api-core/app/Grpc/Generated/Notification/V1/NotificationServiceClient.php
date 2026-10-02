<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Notification\V1;

/**
 */
class NotificationServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Notification\V1\SendVerificationEmailRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Notification\V1\SendVerificationEmailResponse>
     */
    public function SendVerificationEmail(\Notification\V1\SendVerificationEmailRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/notification.v1.NotificationService/SendVerificationEmail',
        $argument,
        ['\Notification\V1\SendVerificationEmailResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Notification\V1\SendInvitationEmailRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Notification\V1\SendInvitationEmailResponse>
     */
    public function SendInvitationEmail(\Notification\V1\SendInvitationEmailRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/notification.v1.NotificationService/SendInvitationEmail',
        $argument,
        ['\Notification\V1\SendInvitationEmailResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Notification\V1\ScheduleTaskReminderRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Notification\V1\ScheduleTaskReminderResponse>
     */
    public function ScheduleTaskReminder(\Notification\V1\ScheduleTaskReminderRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/notification.v1.NotificationService/ScheduleTaskReminder',
        $argument,
        ['\Notification\V1\ScheduleTaskReminderResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Notification\V1\CancelTaskReminderRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Notification\V1\CancelTaskReminderResponse>
     */
    public function CancelTaskReminder(\Notification\V1\CancelTaskReminderRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/notification.v1.NotificationService/CancelTaskReminder',
        $argument,
        ['\Notification\V1\CancelTaskReminderResponse', 'decode'],
        $metadata, $options);
    }

}
