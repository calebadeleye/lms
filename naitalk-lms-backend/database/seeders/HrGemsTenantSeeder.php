<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\PlatformPlan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantTestimonial;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Illuminate\Database\Seeder;

/**
 * HR GEMS is a seeded demonstration tenant, not hard-coded branding — every
 * value here (colors, copy, plan) lives in normal tenant rows and could
 * belong to any client. Matches the attached HR GEMS Coach Network mockup.
 */
class HrGemsTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (Tenant::where('slug', 'hrgems')->exists()) {
            $this->command?->line('HR GEMS tenant already exists, skipping.');

            return;
        }

        $plan = PlatformPlan::where('code', 'growth-annual')->firstOrFail();

        $tenant = app(TenantProvisioningService::class)->provision(
            name: 'HR GEMS',
            ownerEmail: 'admin@hrgems.test',
            ownerName: 'Titi Adeola',
            ownerPassword: 'password',
            plan: $plan,
            subscriptionStatus: 'active',
        );

        // Slug is derived from the name ("hr-gems"); pin it to "hrgems" to
        // match the mockup's intended default URL (hrgems.<neutral-domain>).
        $tenant->update(['slug' => 'hrgems', 'legal_name' => 'HR Gems Coach Network Ltd', 'support_email' => 'support@hrgems.test']);
        $tenant->domains()->update(['hostname' => 'hrgems.'.config('services.frontend.neutral_platform_domain')]);

        app(TenantContext::class)->set($tenant);

        $tenant->branding()->update([
            'primary_color' => '#3B0F32',
            'secondary_color' => '#F5B84B',
            'accent_color' => '#E8A33D',
            'font_family' => 'Inter, system-ui, sans-serif',
            'email_sender_name' => 'HR GEMS',
            'pwa_name' => 'HR GEMS Coach Network',
            'pwa_theme_color' => '#3B0F32',
            'contact_json' => [
                'support_email' => 'support@hrgems.test',
                'phone' => '+234 800 000 0000',
            ],
            'social_json' => [
                'linkedin' => 'https://linkedin.com/company/hrgems',
                'instagram' => 'https://instagram.com/hrgems',
            ],
            'homepage_json' => [
                'hero_title' => 'Grow Your HR Career.',
                'hero_highlight' => 'Lead with Impact.',
                'hero_subtitle' => 'Practical training, expert coaching, and valuable resources to help HR professionals and business leaders excel.',
                'hero_cta_primary' => 'Explore Courses',
                'hero_cta_secondary' => 'Book a Free Consultation',
                'stats' => [
                    ['label' => 'Active Learners', 'value' => '1,500+'],
                    ['label' => 'Courses', 'value' => '40+'],
                    ['label' => 'Coaching Sessions', 'value' => '200+'],
                    ['label' => 'Satisfaction Rate', 'value' => '98%'],
                ],
                'how_we_help' => [
                    ['title' => 'Learn', 'description' => 'Access practical HR courses on-demand.'],
                    ['title' => 'Connect', 'description' => 'Join a community of HR professionals.'],
                    ['title' => 'Get Coaching', 'description' => 'Book 1-on-1 sessions with industry experts.'],
                    ['title' => 'Grow', 'description' => 'Advance your career and increase your impact.'],
                ],
            ],
        ]);

        TenantTestimonial::create([
            'quote' => 'The coaching I received from HR Gems transformed the way I lead my team. Highly recommended!',
            'author' => 'Funke A., HR Manager',
        ]);

        app(TenantContext::class)->clear();

        $this->command?->line("HR GEMS tenant provisioned: {$tenant->fresh()->slug}.".config('services.frontend.neutral_platform_domain'));
    }
}
