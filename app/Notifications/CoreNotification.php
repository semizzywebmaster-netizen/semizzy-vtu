<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $url = null,
        private readonly array $channels = ['web_push'],
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $via = [];

        if (in_array('web_push', $this->channels, true)) {
            $via[] = 'database';
        }

        if (in_array('email', $this->channels, true) && filled($notifiable->email)) {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.((string) ($notifiable->name ?? 'there')).',')
            ->line($this->message);

        if ($this->url !== null && str_starts_with($this->url, '/')) {
            $mail->action('Open SEMIZZY ONE', url($this->url));
        }

        return $mail->salutation('Regards, '.config('app.name', 'SEMIZZY ONE'));
    }
}
