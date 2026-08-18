<?php

namespace Database\Seeders;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Bootstraps the first super_admin account from SUPER_ADMIN_EMAIL /
 * SUPER_ADMIN_PASSWORD (.env) — there's no admin UI yet to create one.
 * Requires RolesAndPermissionsSeeder to have run first.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.super_admin.email');
        $password = config('app.super_admin.password');

        if (! $email || ! $password) {
            $this->command->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set — skipping super admin bootstrap.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        setPermissionsTeamId(0);
        if (! $user->isSuperAdmin()) {
            $user->assignRole('super_admin');
        }
        setPermissionsTeamId(null);
    }
}
