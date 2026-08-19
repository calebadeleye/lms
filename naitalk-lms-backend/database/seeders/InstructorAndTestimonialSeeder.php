<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Site\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the one instructor account courses are authored under (used by
 * CareerFitCourseSeeder) and a launch testimonial. Previously named
 * HrGemsCourseSeeder and also seeded a placeholder HR course catalogue
 * (6 paid courses + a free preview) matching an early mockup — that
 * catalogue was removed once "Find Your Career Fit" became the one real
 * course, so only the instructor/testimonial half of the seeder remains.
 */
class InstructorAndTestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $instructorRole = Role::where('slug', 'instructor')->firstOrFail();

        // email_verified_at isn't in User::$fillable (deliberately — nothing
        // should mass-assign it from request input), so passing it into
        // firstOrCreate()'s attributes silently drops it. forceFill after.
        $instructor = User::firstOrCreate(
            ['email' => env('OWNER_SEED_EMAIL', 'lara.yeku@hrgems.test')],
            [
                'name' => env('OWNER_SEED_NAME', 'Lara Yeku'),
                'password' => env('OWNER_SEED_PASSWORD', 'password'),
                'role_id' => $instructorRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );
        if (! $instructor->email_verified_at) {
            $instructor->forceFill(['email_verified_at' => now()])->save();
        }

        Testimonial::firstOrCreate(
            ['author' => 'Funke A., HR Manager'],
            ['quote' => 'The coaching I received from HR Gems transformed the way I lead my team. Highly recommended!']
        );

        $this->command?->line('Instructor account and testimonial seeded.');
    }
}
