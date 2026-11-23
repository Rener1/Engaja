<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CartasRelatoriosPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate('cartas.relatorio', 'web');

        foreach (['cartas_admin', 'cartas_gestao'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permission);
        }

        $registrar->forgetCachedPermissions();
    }
}
