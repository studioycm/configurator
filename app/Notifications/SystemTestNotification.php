<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemTestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly string $reference) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('admin.mail_test.subject', ['app' => config('app.name')]))
            ->line(__('admin.mail_test.email_body', ['app' => config('app.name')]))
            ->line(__('admin.mail_test.origin', ['environment' => app()->environment(), 'url' => config('app.url')]))
            ->line(__('admin.mail_test.reference', ['reference' => $this->reference]))
            ->action(__('admin.mail_test.open_dashboard'), route('filament.admin.pages.dashboard'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('admin.mail_test.received'))
            ->body(__('admin.mail_test.database_body', ['reference' => $this->reference]))
            ->success()
            ->actions([
                Action::make('openDashboard')
                    ->label(__('admin.mail_test.open_dashboard'))
                    ->url(route('filament.admin.pages.dashboard')),
            ])
            ->getDatabaseMessage();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30];
    }
}
