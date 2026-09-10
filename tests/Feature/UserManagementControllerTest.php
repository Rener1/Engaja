<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use App\Models\Certificado;
use App\Models\Eixo;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Presenca;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_index_renderiza_grid_com_usuarios_selecionaveis(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $comParticipante = User::factory()->create(['name' => 'Usuário Com Participante']);

        $semParticipante = User::factory()->create(['name' => 'Usuário Sem Participante']);
        $semParticipante->participante()->delete();

        $this->actingAs($admin)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('grid-usuarios', false)
            ->assertSee('data-row-selection="multiple"', false)
            ->assertSee('data-row-selectable-field="_selectable"', false)
            ->assertSee('Usuário Com Participante')
            ->assertSee('&quot;_selectable&quot;:true', false)
            ->assertSee('&quot;_selectable&quot;:false', false);
    }

    public function test_administrador_pode_excluir_usuario_e_dados_do_participante_em_cascata(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $target = User::factory()->create(['name' => 'Usuário a excluir']);
        $participante = $target->participante;
        $eixo = Eixo::create(['nome' => 'Eixo de teste']);
        $evento = Evento::factory()->for($admin)->for($eixo)->create();
        $atividade = Atividade::factory()->for($evento)->create();
        $inscricao = Inscricao::create([
            'evento_id' => $evento->id,
            'atividade_id' => $atividade->id,
            'participante_id' => $participante->id,
        ]);
        $presenca = Presenca::create([
            'inscricao_id' => $inscricao->id,
            'atividade_id' => $atividade->id,
            'status' => 'presente',
        ]);
        $certificado = Certificado::factory()->create([
            'participante_id' => $participante->id,
        ]);
        $carta = Carta::factory()->create([
            'educando_participante_id' => $participante->id,
        ]);
        $cartaMensagem = CartaMensagem::factory()->for($carta)->create();

        $this->actingAs($admin)
            ->delete(route('usuarios.destroy', $target))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($target);
        $this->assertSoftDeleted($participante);
        $this->assertSoftDeleted($inscricao);
        $this->assertSoftDeleted($presenca);
        $this->assertSoftDeleted($certificado);
        $this->assertSoftDeleted($carta);
        $this->assertSoftDeleted($cartaMensagem);
        $this->assertNotSoftDeleted($evento);
        $this->assertNotSoftDeleted($atividade);
    }

    public function test_apenas_administrador_e_gerente_visualizam_e_acessam_exclusao(): void
    {
        foreach (['administrador', 'gerente'] as $role) {
            $authorizedUser = User::factory()->create();
            $authorizedUser->assignRole($role);
            $target = User::factory()->create(['name' => "Usuário excluído por {$role}"]);

            $this->actingAs($authorizedUser)
                ->get(route('usuarios.index'))
                ->assertOk()
                ->assertSee('Deletar usuário')
                ->assertSee('Você tem ciência de que está deletando este usuário e todos os dados vinculados a ele?')
                ->assertSee('Cancelar')
                ->assertSee('Confirmar exclusão');

            $this->actingAs($authorizedUser)
                ->delete(route('usuarios.destroy', $target))
                ->assertRedirect(route('usuarios.index'));

            $this->assertSoftDeleted($target);
        }

        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole('eq_pedagogica');
        $target = User::factory()->create(['name' => 'Usuário protegido por acesso']);

        $this->actingAs($unauthorizedUser)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertDontSee('Deletar usuário');

        $this->actingAs($unauthorizedUser)
            ->delete(route('usuarios.destroy', $target))
            ->assertForbidden();

        $this->assertNotSoftDeleted($target);
    }

    public function test_gerente_nao_pode_excluir_administrador_por_url_direta(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('gerente');

        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($manager)
            ->delete(route('usuarios.destroy', $admin))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_usuario_nao_pode_excluir_a_si_mesmo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($admin)
            ->delete(route('usuarios.destroy', $admin))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($admin);
    }
}
