<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Presenca;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use SimpleSoftwareIO\QrCode\Generator;
use Tests\Concerns\InteractsWithPdf;
use Tests\TestCase;

class ListaPresencaPdfTest extends TestCase
{
    use InteractsWithPdf, RefreshDatabase;

    private Atividade $atividade;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
        $this->admin = User::factory()->create(['force_password_change' => false]);
        $this->admin->assignRole('administrador');
        $evento = Evento::factory()->create(['user_id' => $this->admin->id]);
        $this->atividade = Atividade::factory()->create([
            'evento_id' => $evento->id, 'descricao' => 'Escuta Territorial',
            'dia' => '2026-11-08', 'hora_inicio' => '08:30:00', 'hora_fim' => '12:00:00',
        ]);
    }

    #[DataProvider('modelos')]
    public function test_download_utiliza_mesma_rota_e_preserva_modelos_anteriores(?string $tipo): void
    {
        $query = $tipo === null ? [] : ['tipo' => $tipo];
        $response = $this->actingAs($this->admin)
            ->get(route('atividades.lista-presenca.pdf', [$this->atividade, ...$query]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Lista_Presenca_escuta-territorial.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(1, $this->lerPdf($response->getContent())->getPageCount());
    }

    public static function modelos(): array
    {
        return [[null], ['assessoria'], ['oficina'], ['escuta_territorial'], ['feira_pedagogica']];
    }

    #[DataProvider('perfisPermitidos')]
    public function test_mesmos_perfis_autorizados_podem_baixar_modelos(string $perfil, string $tipo): void
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole($perfil);
        $this->actingAs($user)->get($this->url($tipo))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public static function perfisPermitidos(): array
    {
        $casos = [];
        foreach (['escuta_territorial', 'feira_pedagogica'] as $tipo) {
            foreach (['administrador', 'gerente', 'eq_pedagogica', 'articulador'] as $perfil) {
                $casos[] = [$perfil, $tipo];
            }
        }

        return $casos;
    }

    #[DataProvider('escutaEFeira')]
    public function test_visitante_nao_recebe_pdf(string $tipo): void
    {
        $this->get($this->url($tipo))->assertRedirect(route('login'));
    }

    #[DataProvider('escutaEFeira')]
    public function test_perfil_sem_permissao_de_presenca_e_bloqueado(string $tipo): void
    {
        $user = User::factory()->create();
        $user->assignRole('gerente');
        $user->roles->first()->revokePermissionTo('presenca.abrir');
        $this->actingAs($user)->get($this->url($tipo))->assertForbidden();
    }

    #[DataProvider('escutaEFeira')]
    public function test_permissao_isolada_nao_contorna_restricao_de_perfil_da_rota(string $tipo): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('presenca.abrir');
        $this->actingAs($user)->get($this->url($tipo))->assertForbidden();
    }

    #[DataProvider('escutaEFeira')]
    public function test_usuario_do_cartas_nao_acessa_lista_do_engaja(string $tipo): void
    {
        $user = User::factory()->create(['sistema_origem' => User::SISTEMA_CARTAS]);
        $user->assignRole('administrador');
        $this->actingAs($user)->get($this->url($tipo))->assertForbidden();
    }

    #[DataProvider('tiposInvalidos')]
    public function test_selecao_invalida_nao_permite_escolher_arquivo_arbitrario(mixed $tipo): void
    {
        $this->actingAs($this->admin)->getJson(route('atividades.lista-presenca.pdf', [$this->atividade, 'tipo' => $tipo]))
            ->assertUnprocessable()->assertJsonValidationErrors('tipo');
    }

    public static function escutaEFeira(): array
    {
        return [['escuta_territorial'], ['feira_pedagogica']];
    }

    public static function tiposInvalidos(): array
    {
        return [['../../.env'], ['/etc/passwd'], ['<script>alert(1)</script>'], [''], [['escuta_territorial']], [['feira_pedagogica']], ['desconhecido']];
    }

    #[DataProvider('escutaEFeira')]
    public function test_momento_inexistente_ou_excluido_nao_gera_pdf(string $tipo): void
    {
        $this->actingAs($this->admin)->get(route('atividades.lista-presenca.pdf', ['atividade' => 99999999, 'tipo' => $tipo]))
            ->assertNotFound();
        $this->atividade->delete();
        $this->get($this->url($tipo))->assertNotFound();
    }

    #[DataProvider('escutaEFeira')]
    public function test_unifica_inscricoes_e_presencas_sem_duplicar_e_ordena_por_nome(string $tipo): void
    {
        $alvaro = User::factory()->create(['name' => 'Álvaro da Escuta', 'email' => 'alvaro@example.test']);
        $bruna = User::factory()->create(['name' => 'Bruna da Escuta', 'email' => 'bruna@example.test']);
        $zelia = User::factory()->create(['name' => 'Zélia da Escuta', 'email' => 'zelia@example.test']);
        $fora = User::factory()->create(['name' => 'Pessoa de outro momento']);

        foreach ([$zelia, $alvaro] as $user) {
            $inscricao = Inscricao::create([
                'evento_id' => $this->atividade->evento_id, 'atividade_id' => $this->atividade->id,
                'participante_id' => $user->participante->id,
            ]);
            Presenca::create(['inscricao_id' => $inscricao->id, 'atividade_id' => $this->atividade->id, 'status' => 'presente']);
        }
        $legada = Inscricao::create([
            'evento_id' => $this->atividade->evento_id, 'atividade_id' => null, 'participante_id' => $bruna->participante->id,
        ]);
        Presenca::create(['inscricao_id' => $legada->id, 'atividade_id' => $this->atividade->id, 'status' => 'presente']);
        $outroMomento = Atividade::factory()->create(['evento_id' => $this->atividade->evento_id]);
        Inscricao::create([
            'evento_id' => $this->atividade->evento_id, 'atividade_id' => $outroMomento->id, 'participante_id' => $fora->participante->id,
        ]);

        $response = $this->actingAs($this->admin)->get($this->url($tipo))->assertOk();
        $texto = $this->textoPdf($this->lerPdf($response->getContent()));
        $nomes = [$alvaro->name, $bruna->name, $zelia->name];
        foreach ($nomes as $nome) {
            $this->assertSame(1, substr_count($texto, $nome));
        }
        $this->assertLessThan(strpos($texto, $bruna->name), strpos($texto, $alvaro->name));
        $this->assertLessThan(strpos($texto, $zelia->name), strpos($texto, $bruna->name));
        $this->assertStringNotContainsString($fora->name, $texto);
        $this->assertDatabaseCount('inscricaos', 4);
        $this->assertDatabaseCount('presencas', 3);
    }

    #[DataProvider('escutaEFeira')]
    public function test_download_nao_injeta_cabecalhos_por_meio_do_nome_do_momento(string $tipo): void
    {
        $this->atividade->update(['descricao' => "Escuta \"Teste\"\r\nX-Injetado: sim"]);
        $response = $this->actingAs($this->admin)->get($this->url($tipo))->assertOk();
        $disposicao = $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString("\r", $disposicao);
        $this->assertStringNotContainsString("\n", $disposicao);
        $this->assertNull($response->headers->get('X-Injetado'));
    }

    public function test_seletor_oferece_os_quatro_modelos(): void
    {
        $qrCode = Mockery::mock(Generator::class);
        $qrCode->shouldReceive('format', 'style', 'color', 'eye', 'eyeColor', 'size', 'margin', 'merge', 'errorCorrection')->andReturnSelf();
        $qrCode->shouldReceive('generate')->andReturn('');
        $this->app->instance(Generator::class, $qrCode);
        $this->actingAs($this->admin)->get(route('atividades.show', $this->atividade))->assertOk()
            ->assertSee('value="assessoria"', false)->assertSee('value="oficina"', false)
            ->assertSee('value="escuta_territorial"', false)->assertSee('Escuta Territorial')
            ->assertSee('value="feira_pedagogica"', false)->assertSee('Feira Pedagógica');
    }

    private function url(string $tipo): string
    {
        return route('atividades.lista-presenca.pdf', [$this->atividade, 'tipo' => $tipo]);
    }
}
