<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AutorizacaoImagemPermissionsSeeder extends Seeder
{
    public const PERMISSOES = [
        'autorizacao-imagem.ver',
        'autorizacao-imagem.registrar',
        'autorizacao-imagem.exportar',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERMISSOES as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        foreach (Role::where('guard_name', 'web')->whereIn('name', ['administrador', 'gerente', 'eq_pedagogica', 'articulador'])->get() as $role) {
            $role->givePermissionTo(self::PERMISSOES);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
