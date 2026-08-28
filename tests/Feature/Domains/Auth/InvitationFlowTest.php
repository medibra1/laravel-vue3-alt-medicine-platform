<?php

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Core\Models\Center;
use Illuminate\Support\Facades\Notification;

test('invited user can follow the emailed link, set a password, log in, email gets verified', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Invité Bout En Bout',
        'email' => 'e2e@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
    ])->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'e2e@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();

    // The invited user isn't the super_admin who created them — drop
    // that session before simulating their own click-through, so the
    // password.store/login assertions below reflect the invited user's
    // own (until-now unauthenticated) session.
    auth()->logout();
    $this->app['session']->flush();

    Notification::assertSentTo($user, WelcomeSetPasswordNotification::class, function ($notification) use ($user) {
        $reset = $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'BrandNewPassword1!',
            'password_confirmation' => 'BrandNewPassword1!',
        ]);

        $reset->assertRedirect(route('login'));

        return true;
    });

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();

    $login = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'BrandNewPassword1!',
    ]);

    $login->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});
