<?php

namespace App\Support\Identity;

/**
 * Single source of truth for every permission key in the system, and which
 * default tenant roles get which permissions. PermissionSeeder reads this to
 * populate the `permissions` table; TenantProvisioningService reads the
 * TENANT_ROLE_DEFAULTS map to build each new tenant's role set.
 */
class PermissionCatalog
{
    /** @var array<string, string> key => label */
    public const TENANT_PERMISSIONS = [
        'courses.create' => 'Create courses',
        'courses.update' => 'Edit courses',
        'courses.publish' => 'Publish courses',
        'courses.delete' => 'Delete courses',
        'students.manage' => 'Manage students',
        'instructors.manage' => 'Manage instructors and coaches',
        'payments.view' => 'View payments',
        'payments.refund' => 'Issue refunds',
        // Deliberately not "payments.manage" — that key already exists in
        // PLATFORM_PERMISSIONS for NAI TALK's managed-payments oversight,
        // and permission keys are globally unique (see the standing note in
        // ARCHITECTURE.md about the domains.manage/domains.oversight
        // collision this same class of bug caused in Phase 1).
        'payment_gateway.manage' => "Configure the tenant's payment gateway",
        'memberships.manage' => 'Manage membership plans',
        'coaching.manage' => 'Manage coaching services and bookings',
        'certificates.issue' => 'Issue certificates',
        'certificates.revoke' => 'Revoke certificates',
        'branding.manage' => 'Manage branding and homepage',
        'domains.manage' => 'Manage custom domains',
        'exports.request' => 'Request full data export',
        'reports.view' => 'View reports',
        'users.manage' => 'Manage tenant staff and roles',
        'settings.manage' => 'Manage tenant settings',
        'subscription.manage' => 'Manage tenant subscription and billing',
    ];

    /** @var array<string, string> key => label */
    public const PLATFORM_PERMISSIONS = [
        'tenants.manage' => 'Create, approve, suspend tenants',
        'plans.manage' => 'Manage platform plans and features',
        'subscriptions.manage' => 'Manage tenant subscriptions',
        'complimentary.grant' => 'Grant complimentary access',
        'domains.oversight' => 'Manage platform-level domain oversight',
        'payments.manage' => 'Manage NAI TALK-managed payments and commission',
        'refunds.manage' => 'Approve refunds and chargebacks',
        'support.impersonate' => 'Impersonate tenant users for support',
        'audit.view' => 'View audit logs',
        'exports.manage' => 'Manage export/import jobs',
        'deletions.manage' => 'Manage tenant deletion requests',
        'announcements.manage' => 'Manage system announcements',
        'platform_admins.manage' => 'Manage platform administrators',
        'webhooks.view' => 'View webhook and queue health',
    ];

    /** @var array<string, list<string>> slug => [permission keys] */
    public const TENANT_ROLE_DEFAULTS = [
        'tenant-owner' => ['*'],
        'tenant-administrator' => [
            'courses.create', 'courses.update', 'courses.publish', 'courses.delete',
            'students.manage', 'instructors.manage', 'payments.view', 'payments.refund',
            'memberships.manage', 'coaching.manage', 'certificates.issue', 'certificates.revoke',
            'branding.manage', 'domains.manage', 'exports.request', 'reports.view',
            'users.manage', 'settings.manage', 'subscription.manage',
            'payment_gateway.manage',
        ],
        'finance-manager' => ['payments.view', 'payments.refund', 'reports.view', 'subscription.manage', 'payment_gateway.manage'],
        'content-manager' => ['courses.create', 'courses.update', 'courses.publish', 'courses.delete', 'reports.view'],
        'instructor' => ['courses.update', 'students.manage', 'reports.view'],
        'coach' => ['coaching.manage', 'reports.view'],
        'support-agent' => ['students.manage'],
        'student' => [],
    ];

    /** @var array<string, list<string>> slug => [permission keys] */
    public const PLATFORM_ROLE_DEFAULTS = [
        'platform-super-administrator' => ['*'],
        'platform-operations' => ['tenants.manage', 'domains.oversight', 'subscriptions.manage', 'exports.manage', 'deletions.manage', 'webhooks.view'],
        'platform-support' => ['support.impersonate', 'audit.view', 'tenants.manage'],
        'platform-finance' => ['payments.manage', 'refunds.manage', 'subscriptions.manage', 'complimentary.grant'],
    ];

    public const TENANT_ROLE_LABELS = [
        'tenant-owner' => 'Tenant Owner',
        'tenant-administrator' => 'Tenant Administrator',
        'finance-manager' => 'Finance Manager',
        'content-manager' => 'Content Manager',
        'instructor' => 'Instructor',
        'coach' => 'Coach',
        'support-agent' => 'Support Agent',
        'student' => 'Student',
    ];

    public const PLATFORM_ROLE_LABELS = [
        'platform-super-administrator' => 'Platform Super Administrator',
        'platform-operations' => 'Platform Operations',
        'platform-support' => 'Platform Support',
        'platform-finance' => 'Platform Finance',
    ];
}
