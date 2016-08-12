<?php

namespace NotificationChannels\WebPush;

use Minishlink\WebPush\WebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\Events\MessageWasSent;
use NotificationChannels\WebPush\Events\SendingMessage;

class WebPushChannel
{
    /** @var \Minishlink\WebPush\WebPush */
    protected $webPush;

    /**
     * @param  \Minishlink\WebPush\WebPush $webPush
     * @return void
     */
    public function __construct(WebPush $webPush)
    {
        $this->webPush = $webPush;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed $notifiable
     * @param  \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $shouldSendMessage = event(new SendingMessage($notifiable, $notification), [], true) !== false;

        if (! $shouldSendMessage) {
            return;
        }

        $subscriptions = $notifiable->routeNotificationFor('WebPush');

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable, $notification)->toArray());

        $subscriptions->each(function ($sub) use ($payload) {
            $this->webPush->sendNotification(
                $sub->endpoint,
                $payload,
                $sub->public_key,
                $sub->auth_token
            );
        });

        $response = $this->webPush->flush();

        $this->deleteInvalidSubscriptions($response, $subscriptions);

        event(new MessageWasSent($notifiable, $notification));
    }

    /**
     * @param $response
     * @param $subscriptions
     */
    protected function deleteInvalidSubscriptions($response, $subscriptions)
    {
        if (is_array($response)) {
            foreach ($response as $index => $value) {
                if (!$value['success'] && isset($subscriptions[$index])) {
                    $subscriptions[$index]->delete();
                }
            }
        }
    }
}