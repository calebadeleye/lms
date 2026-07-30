<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Support\Identity\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the global permission catalog and the fixed set of roles.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::PERMISSIONS as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label]);
        }

        $allPermissionIds = Permission::pluck('id');

        foreach (PermissionCatalog::ROLE_DEFAULTS as $slug => $keys) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => PermissionCatalog::ROLE_LABELS[$slug], 'is_system' => true]
            );

            $permissionIds = in_array('*', $keys, true)
                ? $allPermissionIds
                : Permission::whereIn('key', $keys)->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
