<?php

namespace Database\Seeders;

use App\Domain\Coaching\Models\Coach;
use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Launch-day membership plans and coaching catalogue so those public pages
 * aren't empty. Not meant to be the final word — an admin edits/removes
 * these through the app once real pricing/offerings are decided.
 */
class MembershipAndCoachingSeeder extends Seeder
{
    public function run(): void
    {
        LearnerMembershipPlan::firstOrCreate(
            ['slug' => 'monthly-membership'],
            [
                'name' => 'Monthly Membership',
                'billing_period' => 'monthly',
                'price_cents' => 500000,
                'currency' => 'NGN',
                'benefits' => [
                    'Access to the HR GEMs community',
                    'Monthly group coaching calls',
                    'Exclusive resource library',
                    'Priority event registration',
                ],
                'is_active' => true,
            ]
        );

        LearnerMembershipPlan::firstOrCreate(
            ['slug' => 'annual-membership'],
            [
                'name' => 'Annual Membership',
                'billing_period' => 'annual',
                'price_cents' => 5000000,
                'currency' => 'NGN',
                'benefits' => [
                    'Access to the HR GEMs community',
                    'Monthly group coaching calls',
                    'Exclusive resource library',
                    'Priority event registration',
                    '2 months free compared to paying monthly',
                ],
                'is_active' => true,
            ]
        );

        $ownerEmail = env('OWNER_SEED_EMAIL', 'admin@hrgems.test');
        $coachUser = User::where('email', $ownerEmail)->first();

        if ($coachUser) {
            $coach = Coach::firstOrCreate(
                ['user_id' => $coachUser->id],
                [
                    'title' => 'Career & Leadership Coach',
                    'bio' => 'Founder of HR GEMs Coach Network, helping HR professionals and career-changers find clarity and build transformational leadership skills.',
                    'years_experience' => 8,
                    'timezone' => 'Africa/Lagos',
                    'is_active' => true,
                ]
            );

            CoachingService::firstOrCreate(
                ['coach_id' => $coach->id, 'title' => 'Free Discovery Call'],
                [
                    'description' => 'A short introductory call to discuss your goals and see if coaching is the right fit.',
                    'session_type' => 'one_to_one',
                    'duration_minutes' => 20,
                    'is_free' => true,
                    'price_cents' => 0,
                    'currency' => 'NGN',
                    'max_participants' => 1,
                    'is_active' => true,
                ]
            );

            CoachingService::firstOrCreate(
                ['coach_id' => $coach->id, 'title' => 'Career Clarity Session'],
                [
                    'description' => 'A focused 1:1 session to identify your strengths, values and the career path that fits you.',
                    'session_type' => 'one_to_one',
                    'duration_minutes' => 45,
                    'is_free' => false,
                    'price_cents' => 2000000,
                    'currency' => 'NGN',
                    'max_participants' => 1,
                    'is_active' => true,
                ]
            );

            CoachingService::firstOrCreate(
                ['coach_id' => $coach->id, 'title' => 'Leadership Coaching Session'],
                [
                    'description' => 'A 1:1 session for HR professionals and managers building leadership and people-management skills.',
                    'session_type' => 'one_to_one',
                    'duration_minutes' => 60,
                    'is_free' => false,
                    'price_cents' => 2500000,
                    'currency' => 'NGN',
                    'max_participants' => 1,
                    'is_active' => true,
                ]
            );

            CoachingService::firstOrCreate(
                ['coach_id' => $coach->id, 'title' => 'Group Coaching Circle'],
                [
                    'description' => 'A small-group coaching session for peer learning, accountability and shared growth.',
                    'session_type' => 'group',
                    'duration_minutes' => 60,
                    'is_free' => false,
                    'price_cents' => 800000,
                    'currency' => 'NGN',
                    'max_participants' => 10,
                    'is_active' => true,
                ]
            );

            $this->command?->line('Membership plans and coaching catalogue seeded.');
        } else {
            $this->command?->warn("Skipped coach/coaching-service seeding: no user found for email {$ownerEmail}.");
        }
    }
}
