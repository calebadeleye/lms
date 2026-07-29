<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\PlatformPlan;
use Illuminate\Database\Seeder;

/**
 * NAI TALK's own SaaS plans (what tenants pay NAI TALK — platform billing,
 * not tenant commerce). Purely data: the frontend renders whatever plans
 * and features exist here, nothing about "Free/Solo/Professional" is
 * hard-coded in application logic.
 *
 * Source of truth: NAI_TALK_LMS_Plans_and_Features.xlsx ("Plan Summary" and
 * "Feature Matrix" sheets). Institution is custom/contract pricing starting
 * at the listed monthly price — sales-assisted, not self-serve.
 */
class PlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'New tutors testing their first course.',
                'billing_period' => 'free',
                'price_cents' => 0,
                'is_public' => true,
                'trial_days' => 0,
                'sort_order' => 1,
                'features' => [
                    'max_administrators' => '1', 'max_instructors' => '1', 'max_coaches' => '1',
                    'max_active_students' => '25', 'max_published_courses' => '2', 'storage_gb' => '1',
                    'certificates_enabled' => 'false', 'certificate_template_limit' => '0',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'false',
                    'custom_domain_enabled' => 'false', 'client_owned_gateway_enabled' => 'false',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'false',
                    'api_access_enabled' => 'false', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'false',
                ],
            ],
            [
                'code' => 'solo-monthly',
                'name' => 'Solo (Monthly)',
                'description' => 'Independent tutors, coaches and consultants.',
                'billing_period' => 'monthly',
                'price_cents' => 490000, // NGN 4,900.00
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 2,
                'features' => [
                    'max_administrators' => '1', 'max_instructors' => '2', 'max_coaches' => '2',
                    'max_active_students' => '100', 'max_published_courses' => '5', 'storage_gb' => '5',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '1',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'false',
                    'custom_domain_enabled' => 'false', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'false', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'false',
                ],
            ],
            [
                'code' => 'solo-annual',
                'name' => 'Solo (Annual)',
                'description' => 'Independent tutors, coaches and consultants.',
                'billing_period' => 'annual',
                'price_cents' => 4900000, // NGN 49,000.00 (2 months free)
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 3,
                'features' => [
                    'max_administrators' => '1', 'max_instructors' => '2', 'max_coaches' => '2',
                    'max_active_students' => '100', 'max_published_courses' => '5', 'storage_gb' => '5',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '1',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'false',
                    'custom_domain_enabled' => 'false', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'false', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'false',
                ],
            ],
            [
                'code' => 'professional-monthly',
                'name' => 'Professional (Monthly)',
                'description' => 'Growing course and coaching businesses.',
                'billing_period' => 'monthly',
                'price_cents' => 1250000, // NGN 12,500.00
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 4,
                'features' => [
                    'max_administrators' => '2', 'max_instructors' => '5', 'max_coaches' => '3',
                    'max_active_students' => '500', 'max_published_courses' => '25', 'storage_gb' => '25',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '5',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'true',
                    'custom_domain_enabled' => 'true', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'false', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'true',
                ],
            ],
            [
                'code' => 'professional-annual',
                'name' => 'Professional (Annual)',
                'description' => 'Growing course and coaching businesses.',
                'billing_period' => 'annual',
                'price_cents' => 12500000, // NGN 125,000.00 (2 months free)
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 5,
                'features' => [
                    'max_administrators' => '2', 'max_instructors' => '5', 'max_coaches' => '3',
                    'max_active_students' => '500', 'max_published_courses' => '25', 'storage_gb' => '25',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '5',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'true',
                    'custom_domain_enabled' => 'true', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'false', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'true',
                ],
            ],
            [
                'code' => 'academy-monthly',
                'name' => 'Academy (Monthly)',
                'description' => 'Training centres and established academies.',
                'billing_period' => 'monthly',
                'price_cents' => 2950000, // NGN 29,500.00
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 6,
                'features' => [
                    'max_administrators' => '5', 'max_instructors' => '15', 'max_coaches' => '10',
                    'max_active_students' => '2000', 'max_published_courses' => '100', 'storage_gb' => '100',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '20',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'true',
                    'custom_domain_enabled' => 'true', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'true', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'true',
                ],
            ],
            [
                'code' => 'academy-annual',
                'name' => 'Academy (Annual)',
                'description' => 'Training centres and established academies.',
                'billing_period' => 'annual',
                'price_cents' => 29500000, // NGN 295,000.00 (2 months free)
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 7,
                'features' => [
                    'max_administrators' => '5', 'max_instructors' => '15', 'max_coaches' => '10',
                    'max_active_students' => '2000', 'max_published_courses' => '100', 'storage_gb' => '100',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '20',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'true',
                    'custom_domain_enabled' => 'true', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'true', 'native_mobile_app_enabled' => 'false',
                    'advanced_reporting_enabled' => 'true',
                ],
            ],
            [
                'code' => 'institution-custom',
                'name' => 'Institution',
                'description' => 'Universities, corporations, NGOs and large schools — custom contract pricing, starting from the listed monthly price.',
                'billing_period' => 'custom',
                'price_cents' => 7500000, // NGN 75,000.00 starting price
                'is_public' => false,
                'trial_days' => 0,
                'sort_order' => 8,
                'features' => [
                    'max_administrators' => '10', 'max_instructors' => '25', 'max_coaches' => '25',
                    'max_active_students' => '5000', 'max_published_courses' => '300', 'storage_gb' => '250',
                    'certificates_enabled' => 'true', 'certificate_template_limit' => '999',
                    'coaching_enabled' => 'true', 'memberships_enabled' => 'true',
                    'custom_domain_enabled' => 'true', 'client_owned_gateway_enabled' => 'true',
                    'managed_gateway_enabled' => 'true', 'full_data_export_enabled' => 'true',
                    'api_access_enabled' => 'true', 'native_mobile_app_enabled' => 'true',
                    'advanced_reporting_enabled' => 'true',
                ],
            ],
        ];

        $keepCodes = array_column($plans, 'code');
        PlatformPlan::whereNotIn('code', $keepCodes)->get()->each(function (PlatformPlan $stale) {
            if ($stale->subscriptions()->exists()) {
                return;
            }
            $stale->features()->delete();
            $stale->delete();
        });

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = PlatformPlan::updateOrCreate(['code' => $planData['code']], $planData);

            foreach ($features as $key => $value) {
                $plan->features()->updateOrCreate(['feature_key' => $key], ['value' => $value]);
            }

            $plan->features()->whereNotIn('feature_key', array_keys($features))->delete();
        }
    }
}
