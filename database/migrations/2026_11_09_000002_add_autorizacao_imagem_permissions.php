<?php

use Database\Seeders\AutorizacaoImagemPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        (new AutorizacaoImagemPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', AutorizacaoImagemPermissionsSeeder::PERMISSOES)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
