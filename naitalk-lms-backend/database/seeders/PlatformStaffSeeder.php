<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\PlatformStaff;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformStaffSeeder extends Seeder
{
    public function run(): void
    {
        // email_verified_at isn't in User::$fillable (deliberately — nothing
        // should mass-assign it from request input), so passing it into
        // firstOrCreate()'s attributes silently drops it. forceFill after.
        $user = User::firstOrCreate(
            ['email' => 'platform-admin@naitalk-lms.test'],
            ['name' => 'NAI TALK Platform Admin', 'password' => 'password']
        );
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $role = Role::platform()->where('slug', 'platform-super-administrator')->firstOrFail();

        PlatformStaff::firstOrCreate(
            ['user_id' => $user->id],
            ['role_id' => $role->id, 'status' => 'active']
        );
    }
}
