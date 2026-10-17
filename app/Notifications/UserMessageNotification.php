<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class UserMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly string $subject,
        public readonly string $message,
        public readonly string $reference,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line(new HtmlString((string) str($this->message)->sanitizeHtml()))
            ->line(__('admin.mail_test.reference', ['reference' => $this->reference]));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(e($this->subject))
            ->body((string) str($this->message)->sanitizeHtml())
            ->getDatabaseMessage();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30];
    }
}
