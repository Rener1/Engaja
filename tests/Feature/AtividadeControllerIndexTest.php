<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtividadeControllerIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_index_renderiza_grid_com_dados_para_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        $evento = Evento::factory()->create();
        Atividade::factory()->create([
            'evento_id' => $evento->id,
            'descricao' => 'Encontro de abertura',
        ]);

        $this->actingAs($user)
            ->get(route('eventos.atividades.index', $evento))
            ->assertOk()
            ->assertSee('grid-atividades', false)
            ->assertSee('Encontro de abertura')
            ->assertSee('Gerenciar');
    }

    public function test_index_filtra_por_periodo_e_descricao_no_card_de_filtros(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        $evento = Evento::factory()->create();
        Atividade::factory()->create(['evento_id' => $evento->id, 'dia' => '2026-03-10', 'descricao' => 'Roda de conversa']);
        Atividade::factory()->create(['evento_id' => $evento->id, 'dia' => '2026-03-20', 'descricao' => 'Roda de leitura']);
        Atividade::factory()->create(['evento_id' => $evento->id, 'dia' => '2026-05-01', 'descricao' => 'Roda de encerramento']);

        $this->actingAs($user)
            ->get(route('eventos.atividades.index', [$evento, 'de' => '2026-03-15', 'ate' => '2026-04-30', 'descricao' => 'Roda']))
            ->assertOk()
            ->assertSee('eg-filter-card', false)
            ->assertSee('name="de"', false)
            ->assertSee('name="ate"', false)
            ->assertSee('name="descricao"', false)
            ->assertSee('Limpar filtros')
            ->assertSee('Roda de leitura')
            ->assertDontSee('Roda de conversa')
            ->assertDontSee('Roda de encerramento');
    }

    public function test_index_sem_filtros_nao_mostra_limpar_filtros(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $evento = Evento::factory()->create();

        $this->actingAs($user)
            ->get(route('eventos.atividades.index', $evento))
            ->assertOk()
            ->assertSee('eg-filter-card', false)
            ->assertDontSee('Limpar filtros');
    }

    public function test_index_oculta_acoes_para_visitante_nao_autenticado(): void
    {
        $evento = Evento::factory()->create();
        Atividade::factory()->create([
            'evento_id' => $evento->id,
            'descricao' => 'Encontro público',
        ]);

        $this->get(route('eventos.atividades.index', $evento))
            ->assertOk()
            ->assertSee('Encontro público')
            ->assertDontSee('Gerenciar');
    }

    public function test_momento_pode_ter_abrangencia_nacional_sem_municipio_ficticio(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $evento = Evento::factory()->create();

        $response = $this->actingAs($user)->post(route('eventos.atividades.store', $evento), [
            'descricao' => 'Curso nacional CACPF',
            'dia' => '2026-08-10',
            'hora_inicio' => '09:00',
            'hora_fim' => '11:00',
            'abrangencia_nacional' => '1',
        ]);

        $response->assertRedirect(route('eventos.show', $evento));

        $atividade = Atividade::where('evento_id', $evento->id)->firstOrFail();
        $this->assertTrue($atividade->abrangencia_nacional);
        $this->assertNull($atividade->municipio_id);
        $this->assertCount(0, $atividade->municipios);

        $this->get(route('eventos.atividades.index', $evento))
            ->assertOk()
            ->assertSee('Brasil');
    }
}
