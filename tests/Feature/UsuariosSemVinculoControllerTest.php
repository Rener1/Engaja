<?php

namespace Tests\Feature;

use App\Exports\UsuariosSemVinculoExport;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuariosSemVinculoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_index_renderiza_grid(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        User::factory()->create();

        $this->actingAs($user)
            ->get(route('usuarios.sem-vinculo.index'))
            ->assertOk()
            ->assertSee('data-ag-grid', false);
    }

    public function test_index_exibe_sexo_e_idade_calculada_na_tabela(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $semVinculo = User::factory()->create(['name' => 'Aluno Sem Vinculo']);
        $semVinculo->participante->update([
            'cpf' => '39053344705',
            'sexo' => 'Feminino',
            'data_nascimento' => now()->subYears(28)->startOfYear()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('usuarios.sem-vinculo.index'))
            ->assertOk();

        $response->assertSee('Feminino');
        $response->assertSee('28 anos');
    }

    public function test_exportar_contem_cabecalhos_sexo_e_idade(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $export = new UsuariosSemVinculoExport($admin);
        $headings = $export->headings();

        $this->assertContains('Sexo', $headings);
        $this->assertContains('Idade', $headings);
    }
}
