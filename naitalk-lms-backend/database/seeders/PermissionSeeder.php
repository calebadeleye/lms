<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the global permission catalog and the four platform-scoped roles.
 * Tenant-scoped roles are NOT seeded here — each tenant gets its own copies,
 * created by TenantProvisioningService when the tenant is created.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::TENANT_PERMISSIONS as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label, 'scope' => 'tenant']);
        }

        foreach (PermissionCatalog::PLATFORM_PERMISSIONS as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label, 'scope' => 'platform']);
        }

        $allPlatformPermissionIds = Permission::where('scope', 'platform')->pluck('id');

        foreach (PermissionCatalog::PLATFORM_ROLE_DEFAULTS as $slug => $keys) {
            $role = Role::updateOrCreate(
                ['tenant_id' => null, 'slug' => $slug],
                ['name' => PermissionCatalog::PLATFORM_ROLE_LABELS[$slug], 'is_system' => true]
            );

            $permissionIds = in_array('*', $keys, true)
                ? $allPlatformPermissionIds
                : Permission::whereIn('key', $keys)->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
