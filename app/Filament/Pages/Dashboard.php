<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\SystemTestNotification;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return Gate::allows('manage-catalog');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestNotification')
                ->label(__('admin.mail_test.send_to_me'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->authorize('manage-catalog')
                ->requiresConfirmation()
                ->modalHeading(__('admin.mail_test.send_to_me'))
                ->modalDescription(fn (): string => __('admin.mail_test.confirm', ['email' => Filament::auth()->user()?->email]))
                ->modalSubmitActionLabel(__('admin.mail_test.send'))
                ->action(function (): void {
                    Gate::authorize('manage-catalog');

                    if (config('mail.default') !== 'resend' || blank(config('services.resend.key'))) {
                        Notification::make()->title(__('admin.mail_test.not_configured'))->body(__('admin.mail_test.setup_required'))->danger()->send();

                        return;
                    }

                    /** @var User $user */
                    $user = Filament::auth()->user();
                    if (! Cache::add('system-test-notification:'.$user->getKey(), true, 60)) {
                        Notification::make()->title(__('admin.mail_test.rate_limited'))->warning()->send();

                        return;
                    }

                    $reference = (string) Str::uuid();
                    $user->notify(new SystemTestNotification($reference));

                    Notification::make()->title(__('admin.mail_test.queued'))->body(__('admin.mail_test.queued_body', ['reference' => $reference]))->success()->send();
                }),
        ];
    }
}
