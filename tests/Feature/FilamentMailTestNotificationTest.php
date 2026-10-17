<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Notifications\SystemTestNotification;
use App\Notifications\UserMessageNotification;
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
    $this->recipient = User::factory()->create();
    $this->messageData = ['recipient_id' => $this->recipient->id, 'subject' => 'Catalog update', 'message' => '<p>The <strong>catalog</strong> is ready.</p>'];
});

test('the dashboard replaces the self test with a notification addressed to the selected user', function () {
    $admin = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($admin);
    Notification::fake();

    $this->get(route('filament.admin.pages.dashboard'))->assertOk()->assertSee('Send notification to user')->assertDontSee('Send test notification to me');

    Livewire::test(Dashboard::class)
        ->assertActionDoesNotExist('sendTestNotification')
        ->callAction('sendNotification', $this->messageData)
        ->assertHasNoFormErrors()
        ->assertNotified('Notification queued');

    Notification::assertSentTo($this->recipient, UserMessageNotification::class, fn ($notification, array $channels): bool => $notification instanceof ShouldQueue && $channels === ['database', 'mail'] && $notification->subject === 'Catalog update' && $notification->message === $this->messageData['message'] && Str::isUuid($notification->reference));
    Notification::assertNotSentTo($admin, UserMessageNotification::class);
});

test('notification sends are limited per account across dashboard instances for one minute', function () {
    $this->freezeTime();
    $first = User::factory()->create(['email' => 'ycm@data4.work']);
    $second = User::factory()->create(['email' => 'beny.cr@gmail.com']);
    Notification::fake();
    $this->actingAs($first);

    Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData);
    Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData)->assertNotified('Please wait before sending another notification');
    Notification::assertSentToTimes($this->recipient, UserMessageNotification::class, 1);

    $this->actingAs($second);
    Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData);
    Notification::assertSentToTimes($this->recipient, UserMessageNotification::class, 2);

    $this->travel(61)->seconds();
    $this->actingAs($first);
    Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData);
    Notification::assertSentToTimes($this->recipient, UserMessageNotification::class, 3);
});

test('an account losing admin access cannot submit a mounted test notification action', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $page = Livewire::test(Dashboard::class)->mountAction('sendNotification');
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
            Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData);
        }

        return [];
    });

    try {
        Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData);

        $this->assertDatabaseCount('jobs', 2);
    } finally {
        Queue::createPayloadUsing(null);
    }
});

test('a log mailer or missing Resend key cannot report a queued notification', function (array $configuration) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    config($configuration);
    Notification::fake();

    Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData)->assertNotified('Email is not configured');

    Notification::assertNothingSent();
})->with([
    'log mailer' => [['mail.default' => 'log']],
    'missing API key' => [['services.resend.key' => null]],
]);

test('a test notification queues database and email delivery separately with the same reference', function () {
    $admin = User::factory()->create(['email' => 'ycm@data4.work']);
    $this->actingAs($admin);
    Queue::fake();

    Livewire::test(Dashboard::class)->callAction('sendNotification', $this->messageData);

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

test('a notification requires a valid recipient subject and nonempty message', function (array $invalid, array $errors) {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    Notification::fake();

    Livewire::test(Dashboard::class)
        ->callAction('sendNotification', array_replace($this->messageData, $invalid))
        ->assertHasFormErrors($errors);

    Notification::assertNothingSent();
})->with([
    'missing recipient' => [['recipient_id' => null], ['recipient_id' => 'required']],
    'unknown recipient' => [['recipient_id' => 999999], ['recipient_id']],
    'missing subject' => [['subject' => ''], ['subject' => 'required']],
    'long subject' => [['subject' => str_repeat('x', 256)], ['subject' => 'max']],
    'header injection' => [['subject' => "Update\r\nBcc: other@example.com"], ['subject']],
    'missing message' => [['message' => ''], ['message']],
    'empty editor' => [['message' => '<p>&nbsp;</p>'], ['message']],
    'script only' => [['message' => '<p></p><script>alert(1)</script>'], ['message']],
    'long message' => [['message' => str_repeat('x', 20001)], ['message']],
]);

test('user messages render the same sanitized content and subject in the inbox and email', function () {
    config(['mail.default' => 'array']);
    $reference = (string) Str::uuid();
    $message = '<p>Hello <strong>team</strong>.</p><script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">Unsafe link</a>';
    $this->recipient->notifyNow(new UserMessageNotification('Catalog update', $message, $reference));

    $stored = $this->recipient->notifications()->sole();
    expect($stored->data['format'])->toBe('filament');
    expect($stored->data['title'])->toBe('Catalog update');
    expect($stored->data['body'])->toContain('<strong>team</strong>')->not->toContain('<script', 'javascript:', 'onclick=');
    $email = Mail::mailer()->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($email->getTo()[0]->getAddress())->toBe($this->recipient->email);
    expect($email->getSubject())->toBe('Catalog update');
    expect($email->getHtmlBody())->toContain('<strong', 'team</strong>', $reference)->not->toContain('<script', 'javascript:', 'onclick=');
    expect($email->getTextBody())->toContain('Hello team.', $reference);
});

test('literal angle bracket text is accepted as notification content', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    Notification::fake();

    Livewire::test(Dashboard::class)
        ->callAction('sendNotification', array_replace($this->messageData, ['message' => '<p>&lt;catalog&gt;</p>']))
        ->assertHasNoFormErrors();

    Notification::assertSentTo($this->recipient, UserMessageNotification::class);
});
