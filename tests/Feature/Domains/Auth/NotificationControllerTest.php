<?php

use App\Domains\Auth\Http\Resources\NotificationResource;
use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\ManagerAssignedNotification;
use App\Domains\Core\Models\Center;
use Illuminate\Support\Facades\Notification;

test('guests are redirected to login', function () {
    $this->getJson(route('admin.notifications.index'))->assertUnauthorized();
});

test('a real manager-assigned notification lands in the database and can be listed/read', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Manager Notifié',
        'email' => 'notified@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'direct',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $manager = User::query()->where('email', 'notified@example.com')->firstOrFail();
    expect($manager->notifications()->count())->toBe(1);
    expect($manager->unreadNotifications()->count())->toBe(1);

    $index = $this->actingAs($manager)->getJson(route('admin.notifications.index'));
    $index->assertOk();
    expect($index->json('unread_count'))->toBe(1);
    expect($index->json('notifications'))->toHaveCount(1);

    $notificationId = $index->json('notifications.0.id');

    $read = $this->actingAs($manager)->postJson(route('admin.notifications.read', $notificationId));
    $read->assertOk();
    expect($read->json('unread_count'))->toBe(0);
    expect($manager->fresh()->unreadNotifications()->count())->toBe(0);
});

test('mark all as read clears every unread notification for the user', function () {
    $manager = User::factory()->create();
    $manager->notify(new ManagerAssignedNotification(Center::factory()->create()));
    $manager->notify(new ManagerAssignedNotification(Center::factory()->create()));

    expect($manager->unreadNotifications()->count())->toBe(2);

    $response = $this->actingAs($manager)->postJson(route('admin.notifications.mark-all-read'));

    $response->assertOk();
    expect($response->json('unread_count'))->toBe(0);
    expect($manager->fresh()->unreadNotifications()->count())->toBe(0);
});

test('a notification is shown in the reader\'s language, not the sender\'s', function () {
    app()->setLocale('fr'); // whoever triggers it works in French
    $manager = User::factory()->create(['locale' => 'ar']);
    $manager->notify(new ManagerAssignedNotification(Center::factory()->create(['name' => 'Centre Nord'])));

    $stored = $manager->notifications()->first()->data;
    expect($stored['title'])->toBe('notifications.manager_assigned.title')
        ->and($stored['params'])->toBe(['center' => 'Centre Nord']);

    $arabic = $this->actingAs($manager)->getJson(route('admin.notifications.index'));
    expect($arabic->json('notifications.0.data.title'))->toBe(__('notifications.manager_assigned.title', [], 'ar'))
        ->and($arabic->json('notifications.0.data.message'))->toContain('Centre Nord')
        ->and($arabic->json('notifications.0.data'))->not->toHaveKey('params');

    $manager->update(['locale' => 'en']);
    $english = $this->actingAs($manager->fresh())->getJson(route('admin.notifications.index'));
    expect($english->json('notifications.0.data.title'))->toBe('New managed center');
});

test('appointment notification dates are formatted for the reader, in the center time zone', function () {
    $data = NotificationResource::translate([
        'title' => 'notifications.appointment_assigned.title',
        'message' => 'notifications.appointment.message',
        'params' => ['date' => '2026-12-03T14:30:00+01:00', 'patient' => 'Awa Diallo'],
        'date_params' => ['date'],
    ]);

    app()->setLocale('en');
    $english = NotificationResource::translate([
        'title' => 'notifications.appointment_assigned.title',
        'message' => 'notifications.appointment.message',
        'params' => ['date' => '2026-12-03T14:30:00+01:00', 'patient' => 'Awa Diallo'],
        'date_params' => ['date'],
    ]);

    expect($data['message'])->toBe('Rendez-vous le 03 décembre 2026 à 14:30 avec Awa Diallo.')
        ->and($english['message'])->toContain('December 3, 2026 at 14:30')
        ->and($english['title'])->toBe('New appointment');
});

test('notifications are sent in the recipient\'s preferred locale', function () {
    Notification::fake();
    $manager = User::factory()->create(['locale' => 'ar']);

    $manager->notify(new ManagerAssignedNotification(Center::factory()->create()));

    Notification::assertSentTo(
        $manager,
        ManagerAssignedNotification::class,
        fn ($notification, $channels, $notifiable, $locale) => $locale === 'ar',
    );
});
