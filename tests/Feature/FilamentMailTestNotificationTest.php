<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Notifications\SystemTestNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['app.name' => 'Mail Test App', 'mail.default' => 'resend', 'services.resend.key' => 'test-resend-key']);
});

test('the admin dashboard offers a test notification to the signed in account only', function () {
    $admin = User::factory()->create(['email' => 'ycm@data4.work']);
    $other = User::factory()->create();
    $this->actingAs($admin);
    Notification::fake();

    $this->get(route('filament.admin.pages.dashboard'))->assertOk()->assertSee('Send test notification to me');

    Livewire::test(Dashboard::class)
        ->callAction(TestAction::make('sendTestNotification')->arguments(['email' => $other->email]))
        ->assertNotified('Test notification queued');

    Notification::assertSentTo($admin, SystemTestNotification::class, fn ($notification, array $channels): bool => $notification instanceof ShouldQueue && $channels === ['database', 'mail'] && Str::isUuid($notification->reference));
    Notification::assertNotSentTo($other, SystemTestNotification::class);
});

test('test notification sends are limited per account across dashboard instances for one minute', function () {
    $this->freezeTime();
    $first = User::factory()->create(['email' => 'ycm@data4.work']);
    $second = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    Notification::fake();
    $this->actingAs($first);

    Livewire::test(Dashboard::class)->callAction('sendTestNotification');
    Livewire::test(Dashboard::class)->callAction('sendTestNotification')->assertNotified('Please wait before sending another test');
    Notification::assertSentToTimes($first, SystemTestNotification::class, 1);

    $this->actingAs($second);
    Livewire::test(Dashboard::class)->callAction('sendTestNotification');
    Notification::assertSentToTimes($second, SystemTestNotification::class, 1);

    $this->travel(61)->seconds();
    $this->actingAs($first);
    Livewire::test(Dashboard::class)->callAction('sendTestNotification');
    Notification::assertSentToTimes($first, SystemTestNotification::class, 2);
});

test('an account losing admin access cannot submit a mounted test notification action', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $page = Livewire::test(Dashboard::class)->mountAction('sendTestNotification');
    $this->actingAs(User::factory()->create());
    Notification::fake();

    $page->call('callMountedAction')->assertForbidden();

    Notification::assertNothingSent();
});

test('overlapping sends for the same account queue only one notification pair', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    config(['queue.default' => 'database']);
    $overlappingRequestStarted = false;

    Queue::createPayloadUsing(function () use (&$overlappingRequestStarted): array {
        if (! $overlappingRequestStarted) {
            $overlappingRequestStarted = true;
            Livewire::test(Dashboard::class)->callAction('sendTestNotification');
        }

        return [];
    });

    try {
        Livewire::test(Dashboard::class)->callAction('sendTestNotification');

        $this->assertDatabaseCount('jobs', 2);
    } finally {
        Queue::createPayloadUsing(null);
    }
});

test('a log mailer or missing Resend key cannot report a queued test', function (array $configuration) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    config($configuration);
    Notification::fake();

    Livewire::test(Dashboard::class)->callAction('sendTestNotification')->assertNotified('Email is not configured');

    Notification::assertNothingSent();
})->with([
    'log mailer' => [['mail.default' => 'log']],
    'missing API key' => [['services.resend.key' => null]],
]);

test('a test notification queues database and email delivery separately with the same reference', function () {
    $admin = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($admin);
    Queue::fake();

    Livewire::test(Dashboard::class)->callAction('sendTestNotification');

    $jobs = Queue::pushed(SendQueuedNotifications::class);
    expect($jobs)->toHaveCount(2);
    expect($jobs->pluck('channels')->all())->toBe([['database'], ['mail']]);
    expect($jobs[0]->notification->reference)->toBe($jobs[1]->notification->reference);
});

test('test notification content reaches the Filament inbox and renders a matching email reference', function () {
    $admin = User::factory()->create(['email' => 'ycm@data4.work']);
    $reference = '5eb62a8b-9920-4afd-91fc-3dc06daf0220';
    config(['mail.default' => 'array']);

    $notification = new SystemTestNotification($reference);
    $admin->notifyNow($notification);

    $stored = $admin->notifications()->sole();
    expect($stored->data['format'])->toBe('filament');
    expect($stored->data['body'])->toContain($reference);
    expect($stored->data['actions'][0]['url'])->toBe(route('filament.admin.pages.dashboard'));
    $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($email->getTo()[0]->getAddress())->toBe($admin->email);
    expect($email->getSubject())->toBe('Mail Test App test notification');
    expect($email->getHtmlBody())
        ->toContain($reference, route('filament.admin.pages.dashboard'), 'Mail Test App');
});
