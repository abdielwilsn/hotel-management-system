<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Every notification in this app is delivered the same two ways: a row in
 * the in-app bell/history, and a real OS push if the recipient has enabled
 * it on this browser. Concrete notifications only need to describe their
 * payload via toArray() — team_id (for scoping the bell to a team), a
 * human-readable message, and an optional url to send the user to.
 *
 * @property array{team_id: int, message: string, url?: string|null} $data
 */
abstract class TeamNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $data = $this->toArray($notifiable);

        return (new WebPushMessage)
            ->title(config('app.name'))
            ->body($data['message'])
            ->icon('/favicon.ico')
            ->data(['url' => $data['url'] ?? null]);
    }

    /**
     * @return array{team_id: int, message: string, url?: string|null}
     */
    abstract public function toArray(object $notifiable): array;
}
