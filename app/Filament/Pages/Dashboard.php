<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\UserMessageNotification;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
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
            Action::make('sendNotification')
                ->label(__('admin.user_notification.send_to_user'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->authorize('manage-catalog')
                ->modalHeading(__('admin.user_notification.send_to_user'))
                ->modalDescription(__('admin.user_notification.help'))
                ->modalSubmitActionLabel(__('admin.user_notification.send'))
                ->modalWidth('2xl')
                ->schema([
                    Select::make('recipient_id')
                        ->label(__('admin.user_notification.recipient'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => User::query()
                            ->where(fn (Builder $query): Builder => $query->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"))
                            ->orderBy('name')->limit(50)->get()
                            ->mapWithKeys(fn (User $user): array => [$user->getKey() => "{$user->name} ({$user->email})"])->all())
                        ->getOptionLabelUsing(function (mixed $value): ?string {
                            $user = User::find($value);

                            return $user ? "{$user->name} ({$user->email})" : null;
                        })
                        ->required()
                        ->exists(User::class, 'id'),
                    TextInput::make('subject')
                        ->label(__('admin.user_notification.subject'))
                        ->required()->maxLength(255)->rules(['not_regex:/[\r\n]/']),
                    RichEditor::make('message')
                        ->label(__('admin.user_notification.message'))
                        ->fileAttachments(false)
                        ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                        ->required()->maxLength(20000)
                        ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $text = html_entity_decode(strip_tags((string) RichContentRenderer::make($value)->toHtml()), ENT_QUOTES | ENT_HTML5);
                            if (blank(preg_replace('/[\s\p{Z}\x{200B}]/u', '', $text))) {
                                $fail(__('admin.user_notification.message_required'));
                            }
                        }]),
                ])
                ->action(function (array $data, Action $action): void {
                    Gate::authorize('manage-catalog');

                    if (config('mail.default') !== 'resend' || blank(config('services.resend.key'))) {
                        Notification::make()->title(__('admin.mail_test.not_configured'))->body(__('admin.mail_test.setup_required'))->danger()->send();
                        $action->halt();
                    }

                    $recipient = User::findOrFail($data['recipient_id']);
                    if (! Cache::add('user-notification:'.Filament::auth()->id(), true, 60)) {
                        Notification::make()->title(__('admin.user_notification.rate_limited'))->warning()->send();
                        $action->halt();
                    }

                    $reference = (string) Str::uuid();
                    $recipient->notify(new UserMessageNotification($data['subject'], $data['message'], $reference));

                    Notification::make()->title(__('admin.user_notification.queued'))->body(__('admin.user_notification.queued_body', ['email' => $recipient->email, 'reference' => $reference]))->success()->send();
                }),
        ];
    }
}
