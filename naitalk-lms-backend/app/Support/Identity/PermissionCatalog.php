<?php

namespace App\Support\Identity;

/**
 * Single source of truth for every permission key in the system, and which
 * default roles get which permissions. PermissionSeeder reads this to
 * populate the `permissions` and `roles` tables.
 */
class PermissionCatalog
{
    /** @var array<string, string> key => label */
    public const PERMISSIONS = [
        'courses.create' => 'Create courses',
        'courses.update' => 'Edit courses',
        'courses.publish' => 'Publish courses',
        'courses.delete' => 'Delete courses',
        'students.manage' => 'Manage students',
        'instructors.manage' => 'Manage instructors and coaches',
        'payments.view' => 'View payments',
        'payments.refund' => 'Issue refunds',
        'payment_gateway.manage' => 'Configure the payment gateway',
        'memberships.manage' => 'Manage membership plans',
        'coaching.manage' => 'Manage coaching services and bookings',
        'certificates.issue' => 'Issue certificates',
        'certificates.revoke' => 'Revoke certificates',
        'reports.view' => 'View reports',
        'users.manage' => 'Manage staff and roles',
        'settings.manage' => 'Manage settings and homepage content',
        'audit.view' => 'View audit logs',
        'members.approve' => 'Approve pending member applications',
    ];

    /** @var array<string, list<string>> slug => [permission keys] */
    public const ROLE_DEFAULTS = [
        'owner' => ['*'],
        'administrator' => [
            'courses.create', 'courses.update', 'courses.publish', 'courses.delete',
            'students.manage', 'instructors.manage', 'payments.view', 'payments.refund',
            'memberships.manage', 'coaching.manage', 'certificates.issue', 'certificates.revoke',
            'reports.view', 'users.manage', 'settings.manage', 'payment_gateway.manage',
            'audit.view', 'members.approve',
        ],
        'finance-manager' => ['payments.view', 'payments.refund', 'reports.view', 'payment_gateway.manage'],
        'content-manager' => ['courses.create', 'courses.update', 'courses.publish', 'courses.delete', 'reports.view'],
        'instructor' => ['courses.update', 'students.manage', 'reports.view'],
        'coach' => ['coaching.manage', 'reports.view'],
        'support-agent' => ['students.manage'],
        'student' => [],
    ];

    public const ROLE_LABELS = [
        'owner' => 'Owner',
        'administrator' => 'Administrator',
        'finance-manager' => 'Finance Manager',
        'content-manager' => 'Content Manager',
        'instructor' => 'Instructor',
        'coach' => 'Coach',
        'support-agent' => 'Support Agent',
        'student' => 'Student',
    ];
}
