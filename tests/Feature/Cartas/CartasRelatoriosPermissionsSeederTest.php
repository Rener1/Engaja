<?php

namespace Tests\Feature\Cartas;

use App\Models\User;
use Database\Seeders\CartasRelatoriosPermissionsSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CartasRelatoriosPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_a_permissao_e_concede_apenas_aos_perfis_de_gestao_do_cartas(): void
    {
        $voluntario = Role::findOrCreate('cartas_voluntario', 'web');

        $this->seed(CartasRelatoriosPermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'cartas.relatorio', 'guard_name' => 'web']);

        foreach (['cartas_admin', 'cartas_gestao'] as $roleName) {
            $this->assertTrue(Role::findByName($roleName, 'web')->hasPermissionTo('cartas.relatorio', 'web'));
        }

        $this->assertFalse($voluntario->fresh()->hasPermissionTo('cartas.relatorio', 'web'));
    }

    public function test_pode_reexecutar_preservando_permissoes_e_cadastros_existentes(): void
    {
        $outraPermissao = Permission::findOrCreate('cartas.ver', 'web');
        $adminRole = Role::findOrCreate('cartas_admin', 'web');
        $adminRole->givePermissionTo($outraPermissao);

        $admin = User::factory()->create(['sistema_origem' => User::SISTEMA_CARTAS]);
        $admin->assignRole($adminRole);
        $cadastroOriginal = $admin->fresh()->getRawOriginal();

        $this->seed(CartasRelatoriosPermissionsSeeder::class);
        $this->seed(CartasRelatoriosPermissionsSeeder::class);

        $permission = Permission::findByName('cartas.relatorio', 'web');
        $this->assertSame(1, Permission::where('name', 'cartas.relatorio')->where('guard_name', 'web')->count());
        $this->assertDatabaseCount('roles', 2);
        $this->assertSame(2, $permission->roles()->count());
        $this->assertTrue($adminRole->fresh()->hasPermissionTo('cartas.ver', 'web'));
        $this->assertSame($cadastroOriginal, $admin->fresh()->getRawOriginal());
    }

    public function test_fluxo_geral_inclui_a_permissao_e_repara_associacao_ausente(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $admin = Role::findByName('cartas_admin', 'web');
        $gestao = Role::findByName('cartas_gestao', 'web');
        $this->assertTrue($admin->hasPermissionTo('cartas.relatorio', 'web'));
        $this->assertTrue($gestao->hasPermissionTo('cartas.relatorio', 'web'));

        $admin->revokePermissionTo('cartas.relatorio');
        $this->seed(RolesPermissionsSeeder::class);

        $this->assertTrue($admin->fresh()->hasPermissionTo('cartas.relatorio', 'web'));
        $this->assertTrue($admin->fresh()->hasPermissionTo('cartas.ver', 'web'));
    }

    public function test_invalida_o_cache_e_libera_o_gate_apos_o_seed(): void
    {
        $admin = User::factory()->create(['sistema_origem' => User::SISTEMA_CARTAS]);
        $admin->assignRole(Role::findOrCreate('cartas_admin', 'web'));

        $this->assertFalse(Gate::forUser($admin)->allows('cartas.ver-relatorios'));

        $this->seed(CartasRelatoriosPermissionsSeeder::class);

        $this->assertTrue(Gate::forUser($admin->fresh())->allows('cartas.ver-relatorios'));
    }

    public function test_preserva_perfis_e_permissoes_de_outros_guards(): void
    {
        $apiRole = Role::findOrCreate('cartas_admin', 'api');
        Permission::findOrCreate('cartas.relatorio', 'api');

        $this->seed(CartasRelatoriosPermissionsSeeder::class);

        $this->assertSame(0, $apiRole->fresh()->permissions()->count());
        $this->assertTrue(Role::findByName('cartas_admin', 'web')->hasPermissionTo('cartas.relatorio', 'web'));
    }
}
