<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The first owner account for this single-organization app — replaces what
 * used to be the HR GEMS demo tenant's provisioned owner.
 */
class OwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        $ownerRole = Role::where('slug', 'owner')->firstOrFail();

        $owner = User::firstOrCreate(
            ['email' => env('OWNER_SEED_EMAIL', 'admin@hrgems.test')],
            [
                'name' => env('OWNER_SEED_NAME', 'Titi Adeola'),
                'password' => env('OWNER_SEED_PASSWORD', 'password'),
                'role_id' => $ownerRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        if (! $owner->email_verified_at) {
            $owner->forceFill(['email_verified_at' => now()])->save();
        }

        $this->command?->line("Owner account seeded: {$owner->email}.");
    }
}
