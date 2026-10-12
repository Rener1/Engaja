<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Eixo;
use App\Models\Estado;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Municipio;
use App\Models\Regiao;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InscricaoImportTodosMomentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_confirmar_importacao_com_sessao_todos_os_momentos_inscreve_em_cada_atividade(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $eixo = Eixo::create(['nome' => 'Eixo import']);
        $evento = Evento::factory()->create([
            'user_id' => $admin->id,
            'eixo_id' => $eixo->id,
        ]);

        Atividade::factory()->create([
            'evento_id' => $evento->id,
            'dia' => '2026-02-10',
            'hora_inicio' => '09:00:00',
            'hora_fim' => '10:00:00',
        ]);
        Atividade::factory()->create([
            'evento_id' => $evento->id,
            'dia' => '2026-02-11',
            'hora_inicio' => '09:00:00',
            'hora_fim' => '10:00:00',
        ]);

        $pUser = User::factory()->create(['email' => 'import_todos@test.local']);
        $participante = $pUser->participante;

        DB::table('origem_usuario')->insert([
            'evento_id' => $evento->id,
            'user_id' => $pUser->id,
            'origem' => 'Origem antiga',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionKey = "import_preview_evento_{$evento->id}_todos";
        session([$sessionKey => [
            'evento_id' => $evento->id,
            'user_id' => $admin->id,
            'modo_todos_momentos' => true,
            'atividade_id' => null,
            'origem' => 'LP',
            'rows' => [
                [
                    'nome' => 'Participante Planilha',
                    'email' => 'import_todos@test.local',
                    'cpf' => null,
                    'telefone' => null,
                    'data_nascimento' => '1990-03-25',
                    'municipio_id' => null,
                ],
            ],
        ]]);

        $response = $this->actingAs($admin)->post(route('inscricoes.confirmar', $evento), [
            'session_key' => $sessionKey,
        ]);

        $response->assertRedirect(route('eventos.show', $evento));

        $this->assertSame(2, Inscricao::query()
            ->where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->whereNull('deleted_at')
            ->count());

        $this->assertDatabaseHas('origem_usuario', [
            'evento_id' => $evento->id,
            'user_id' => $pUser->id,
            'origem' => 'LP',
        ]);

        $this->assertDatabaseMissing('origem_usuario', [
            'evento_id' => $evento->id,
            'user_id' => $pUser->id,
            'origem' => 'Origem antiga',
        ]);

        $this->assertSame(1, DB::table('origem_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $pUser->id)
            ->count());
    }

    public function test_confirmar_importacao_sem_origem_mantem_registro_de_origem_existente(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $eixo = Eixo::create(['nome' => 'Eixo import sem origem']);
        $evento = Evento::factory()->create([
            'user_id' => $admin->id,
            'eixo_id' => $eixo->id,
        ]);

        Atividade::factory()->create([
            'evento_id' => $evento->id,
            'dia' => '2026-02-10',
            'hora_inicio' => '09:00:00',
            'hora_fim' => '10:00:00',
        ]);

        $pUser = User::factory()->create(['email' => 'import_sem_origem@test.local']);
        $participante = $pUser->participante;

        DB::table('origem_usuario')->insert([
            'evento_id' => $evento->id,
            'user_id' => $pUser->id,
            'origem' => 'Origem existente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionKey = "import_preview_evento_{$evento->id}_todos";
        session([$sessionKey => [
            'evento_id' => $evento->id,
            'user_id' => $admin->id,
            'modo_todos_momentos' => true,
            'atividade_id' => null,
            'origem' => null,
            'rows' => [
                [
                    'nome' => 'Participante Sem Origem',
                    'email' => 'import_sem_origem@test.local',
                    'cpf' => null,
                    'telefone' => null,
                    'data_nascimento' => '1990-03-25',
                    'municipio_id' => null,
                ],
            ],
        ]]);

        $response = $this->actingAs($admin)->post(route('inscricoes.confirmar', $evento), [
            'session_key' => $sessionKey,
        ]);

        $response->assertRedirect(route('eventos.show', $evento));

        $this->assertSame(1, Inscricao::query()
            ->where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->whereNull('deleted_at')
            ->count());

        $this->assertDatabaseHas('origem_usuario', [
            'evento_id' => $evento->id,
            'user_id' => $pUser->id,
            'origem' => 'Origem existente',
        ]);

        $this->assertSame(1, DB::table('origem_usuario')
            ->where('evento_id', $evento->id)
            ->where('user_id', $pUser->id)
            ->count());
    }

    public function test_confirmar_importacao_associa_localidade_existente_ao_participante_sem_municipio(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $regiao = Regiao::factory()->create(['nome' => 'Outras']);
        $estado = Estado::create(['regiao_id' => $regiao->id, 'nome' => 'Ceará', 'sigla' => 'CE']);
        $municipio = Municipio::create(['estado_id' => $estado->id, 'nome' => 'Sobral']);

        $eixo = Eixo::create(['nome' => 'Eixo importa localidade']);
        $evento = Evento::factory()->create([
            'user_id' => $admin->id,
            'eixo_id' => $eixo->id,
        ]);

        Atividade::factory()->create([
            'evento_id' => $evento->id,
            'dia' => '2026-02-10',
            'hora_inicio' => '09:00:00',
            'hora_fim' => '10:00:00',
        ]);

        $participanteUser = User::factory()->create([
            'email' => 'participante.sobral@test.local',
        ]);
        $participante = $participanteUser->participante;

        $sessionKey = "import_preview_evento_{$evento->id}_todos";
        session([$sessionKey => [
            'evento_id' => $evento->id,
            'user_id' => $admin->id,
            'modo_todos_momentos' => true,
            'atividade_id' => null,
            'origem' => null,
            'rows' => [
                [
                    'nome' => 'Participante Sobral',
                    'email' => 'participante.sobral@test.local',
                    'cpf' => null,
                    'telefone' => null,
                    'data_nascimento' => '1990-03-25',
                    'municipio' => 'Sobral',
                    'municipio_id' => null,
                    'estado' => 'CE',
                ],
            ],
        ]]);

        $this->actingAs($admin)
            ->post(route('inscricoes.confirmar', $evento), ['session_key' => $sessionKey])
            ->assertRedirect(route('eventos.show', $evento));

        $participante->refresh();

        $this->assertSame($municipio->id, $participante->municipio_id);
        $this->assertSame(1, Inscricao::where('evento_id', $evento->id)
            ->where('participante_id', $participante->id)
            ->count());
    }
}
