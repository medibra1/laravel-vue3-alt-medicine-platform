<?php

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\ManagerAssignedNotification;
use App\Domains\Core\Models\Center;

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
