<?php

namespace Tests\Feature;

use App\Enums\OrigemAutorizacaoImagem;
use App\Models\Atividade;
use App\Models\AutorizacaoImagem;
use App\Models\Evento;
use App\Models\User;
use App\Services\AutorizacaoImagemContexto;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAutorizacaoImagem;
use Tests\TestCase;

class AutorizacaoImagemFluxoTest extends TestCase
{
    use InteractsWithAutorizacaoImagem, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAutorizacoes();
    }

    #[DataProvider('decisoes')]
    public function test_fluxo_publico_registra_presenca_e_resposta_no_mesmo_envio(bool $decisao): void
    {
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => $decisao])
            ->assertRedirect()->assertSessionHas('success-presenca')->assertSessionMissing(AutorizacaoImagemContexto::FLASH_KEY);
        $this->assertDatabaseHas('autorizacoes_imagem', ['participante_id' => $this->pessoa->participante->id, 'evento_id' => $this->acao->id, 'autorizado' => $decisao]);
        $this->assertDatabaseHas('presencas', ['atividade_id' => $this->momento->id, 'status' => 'presente']);
        $this->assertDatabaseCount('historicos_autorizacao_imagem', 1);
        $this->get(route('presenca.confirmar', $this->momento))->assertOk()
            ->assertDontSee('id="autorizacaoImagemPresencaModal"', false);
    }

    public static function decisoes(): array
    {
        return [[true], [false]];
    }

    public function test_identificacao_apenas_consulta_pendencia_sem_gravar_dados(): void
    {
        $this->postJson(route('presenca.identificar', $this->momento), ['campo' => $this->pessoa->email])
            ->assertOk()->assertExactJson(['autorizacao_pendente' => true]);
        $this->assertDatabaseCount('presencas', 0);
        $this->assertDatabaseCount('autorizacoes_imagem', 0);
        $this->assertDatabaseCount('inscricaos', 0);
    }

    public function test_formulario_exibe_opcoes_e_termo_atual_em_modal_de_leitura(): void
    {
        config(['autorizacao_imagem.texto' => 'Texto atual do termo para leitura.']);
        $this->get(route('presenca.confirmar', $this->momento))->assertOk()
            ->assertSee('id="autorizacao-presenca"', false)
            ->assertSee('id="confirmar-presenca" disabled', false)
            ->assertSee('id="termoAutorizacaoImagemModal"', false)
            ->assertSee('Texto atual do termo para leitura.');
    }

    public function test_envio_sem_escolha_nao_inventa_autorizacao(): void
    {
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email])
            ->assertRedirect()->assertSessionHas('success-presenca')->assertSessionMissing(AutorizacaoImagemContexto::FLASH_KEY);
        $this->assertDatabaseCount('presencas', 1);
        $this->assertDatabaseCount('autorizacoes_imagem', 0);
    }

    public function test_valor_invalido_nao_e_convertido_em_recusa(): void
    {
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => 'valor-invalido'])
            ->assertSessionHas('success-presenca');
        $this->assertDatabaseCount('autorizacoes_imagem', 0);
    }

    #[DataProvider('decisoes')]
    public function test_resposta_anterior_dispensa_pergunta_e_nao_e_sobrescrita(bool $decisao): void
    {
        $this->vinculo($this->pessoa, presente: false);
        $this->respostaDaGestao($this->pessoa, $decisao);
        $this->postJson(route('presenca.identificar', $this->momento), ['campo' => $this->pessoa->email])
            ->assertOk()->assertExactJson(['autorizacao_pendente' => false]);
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => ! $decisao])
            ->assertSessionHas('success-presenca');
        $this->assertSame($decisao, AutorizacaoImagem::sole()->autorizado);
        $this->assertDatabaseCount('historicos_autorizacao_imagem', 1);
    }

    public function test_recusa_dispensa_nova_pergunta_na_acao_e_outra_acao_continua_pendente(): void
    {
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => false])->assertSessionHas('success-presenca');
        $outro = Atividade::factory()->create(['evento_id' => $this->acao->id, 'presenca_ativa' => true]);
        $this->postJson(route('presenca.identificar', $outro), ['campo' => $this->pessoa->email])
            ->assertExactJson(['autorizacao_pendente' => false]);
        $this->post(route('presenca.store', $outro), ['campo' => $this->pessoa->email])->assertSessionHas('success-presenca');
        $novaAcao = Atividade::factory()->create(['evento_id' => Evento::factory()->create()->id, 'presenca_ativa' => true]);
        $this->postJson(route('presenca.identificar', $novaAcao), ['campo' => $this->pessoa->email])
            ->assertExactJson(['autorizacao_pendente' => true]);
        $this->assertDatabaseCount('presencas', 2);
        $this->assertDatabaseCount('autorizacoes_imagem', 1);
        $this->assertFalse(AutorizacaoImagem::sole()->autorizado);
    }

    public function test_ids_enviados_pelo_cliente_nao_trocam_participante_ou_acao(): void
    {
        $outra = $this->pessoaComDados();
        $this->post(route('presenca.store', $this->momento), [
            'campo' => $this->pessoa->email, 'autorizado' => false,
            'participante_id' => $outra->participante->id, 'evento_id' => 99999, 'origem' => 'gestao',
        ])->assertSessionHas('success-presenca');
        $registro = AutorizacaoImagem::sole();
        $this->assertSame($this->pessoa->participante->id, $registro->participante_id);
        $this->assertSame($this->acao->id, $registro->evento_id);
        $this->assertSame('presenca_publica', $registro->origem);
    }

    public function test_reenvio_da_presenca_nao_duplica_nem_altera_a_primeira_resposta(): void
    {
        foreach ([true, true, false] as $decisao) {
            $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => $decisao])->assertSessionHas('success-presenca');
        }
        $this->assertDatabaseCount('presencas', 1);
        $this->assertDatabaseCount('historicos_autorizacao_imagem', 1);
        $this->assertTrue(AutorizacaoImagem::sole()->autorizado);
    }

    public function test_escolha_e_preservada_no_passo_dos_dados_demograficos(): void
    {
        $campos = ['identidade_genero', 'raca_cor', 'comunidade_tradicional', 'faixa_etaria', 'pcd', 'orientacao_sexual'];
        $demograficos = $this->pessoa->only($campos);
        $this->pessoa->update(['raca_cor' => null]);
        $this->from(route('presenca.confirmar', $this->momento))
            ->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => false])
            ->assertSessionHas('demograficos_pendentes', true)->assertSessionHas('demograficos_autorizado', false);
        $token = session('demograficos_user_token');
        $this->assertDatabaseCount('presencas', 0);
        $this->assertDatabaseCount('autorizacoes_imagem', 0);
        $html = $this->get(route('presenca.confirmar', $this->momento))->assertOk()->getContent();
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($documento);
        $this->assertCount(1, $xpath->query('//form[@id="form-demograficos-presenca"]/input[@type="hidden" and @name="autorizado" and @value="0"]'));
        $this->post(route('presenca.demograficos', $this->momento), [
            ...$demograficos, 'user_token' => $token, 'autorizado' => '0',
        ])->assertSessionHasNoErrors()->assertSessionHas('success-presenca')
            ->assertSessionMissing(AutorizacaoImagemContexto::FLASH_KEY);
        $this->assertFalse(AutorizacaoImagem::sole()->autorizado);
        $this->assertDatabaseCount('presencas', 1);
    }

    public function test_consulta_respeita_fechamento_da_presenca(): void
    {
        $this->momento->update(['presenca_ativa' => false]);
        $this->postJson(route('presenca.identificar', $this->momento), ['campo' => $this->pessoa->email])
            ->assertUnprocessable()->assertJsonValidationErrors('campo');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_checkin_autenticado_registra_o_proprio_usuario(): void
    {
        $this->actingAs($this->pessoa)->post(route('atividades.presenca.checkin', $this->momento))->assertRedirect()->assertSessionHas(AutorizacaoImagemContexto::FLASH_KEY);
        $contexto = session(AutorizacaoImagemContexto::FLASH_KEY);
        $this->postJson(route('presenca.autorizacao-imagem', $this->momento), ['contexto' => $contexto['token'], 'autorizado' => false])->assertOk();
        $resposta = AutorizacaoImagem::sole();
        $this->assertSame($this->pessoa->id, $resposta->registrado_por_user_id);
        $this->assertSame('presenca_autenticada', $resposta->origem);
    }

    public function test_cpf_mascarado_identifica_sem_exigir_login(): void
    {
        $this->post(route('presenca.store', $this->momento), ['campo' => '012.345.678-90', 'autorizado' => true])->assertRedirect()->assertSessionHas('success-presenca');
        $this->assertDatabaseCount('presencas', 1);
    }

    public function test_cpf_ambiguo_nao_seleciona_cadastro_arbitrario(): void
    {
        $outra = $this->pessoaComDados();
        $outra->participante->update(['cpf' => '012.345.678-90']);
        $this->postJson(route('presenca.identificar', $this->momento), ['campo' => '01234567890'])->assertUnprocessable();
        $this->post(route('presenca.store', $this->momento), ['campo' => '01234567890'])->assertSessionHas('error');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_cadastro_cartas_nao_e_usado_no_checkin_engaja(): void
    {
        $this->pessoa->update(['sistema_origem' => User::SISTEMA_CARTAS]);
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email])->assertSessionHas('error');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_perfil_altera_somente_sua_decisao_e_mantem_historico(): void
    {
        $this->vinculo($this->pessoa);
        $this->actingAs($this->pessoa)->get(route('profile.autorizacoes-imagem'))->assertOk()->assertSee($this->acao->nome);
        $contexto = app(AutorizacaoImagemContexto::class)->paraFormulario($this->pessoa->participante, $this->acao, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->pessoa);
        $this->post(route('profile.autorizacoes-imagem.salvar', $this->acao), ['contexto' => $contexto['token'], 'autorizado' => true])->assertSessionHasNoErrors();
        $contexto = app(AutorizacaoImagemContexto::class)->paraFormulario($this->pessoa->participante, $this->acao, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->pessoa);
        $this->post(route('profile.autorizacoes-imagem.salvar', $this->acao), ['contexto' => $contexto['token'], 'autorizado' => false])->assertSessionHasNoErrors();
        $this->assertFalse(AutorizacaoImagem::sole()->autorizado);
        $this->assertDatabaseCount('historicos_autorizacao_imagem', 2);
    }

    public function test_outro_usuario_nao_pode_usar_contexto_do_perfil(): void
    {
        $this->vinculo($this->pessoa);
        $contexto = app(AutorizacaoImagemContexto::class)->paraFormulario($this->pessoa->participante, $this->acao, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->pessoa);
        $this->actingAs($this->gestor)->post(route('profile.autorizacoes-imagem.salvar', $this->acao), ['contexto' => $contexto['token'], 'autorizado' => true])->assertForbidden();
        $this->assertDatabaseCount('autorizacoes_imagem', 0);
    }

    public function test_contexto_do_perfil_nao_pode_ser_usado_na_rota_publica(): void
    {
        $this->vinculo($this->pessoa);
        $contexto = app(AutorizacaoImagemContexto::class)->paraFormulario($this->pessoa->participante, $this->acao, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->pessoa);
        $this->postJson(route('presenca.autorizacao-imagem', $this->momento), ['contexto' => $contexto['token'], 'autorizado' => true])->assertForbidden();
    }

    #[DataProvider('decisoes')]
    public function test_gestao_registra_apenas_com_escolha_sem_documento(bool $decisao): void
    {
        $this->vinculo($this->pessoa, presente: false);
        $contexto = app(AutorizacaoImagemContexto::class)->paraFormulario($this->pessoa->participante, $this->acao, session()->driver(), OrigemAutorizacaoImagem::Gestao, $this->gestor);
        $url = route('autorizacoes-imagem.registrar', [$this->acao, $this->pessoa->participante]);
        $this->actingAs($this->gestor)->post($url, ['contexto' => $contexto['token']])->assertSessionHasErrors('autorizado');
        $this->post($url, ['contexto' => $contexto['token'], 'autorizado' => $decisao])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('autorizacoes_imagem', ['origem' => 'gestao', 'autorizado' => $decisao]);
    }

    public function test_gestao_lista_somente_a_acao_selecionada_e_distingue_pendente_de_recusa(): void
    {
        $this->vinculo($this->pessoa);
        $this->respostaDaGestao($this->pessoa, false);
        $pendente = $this->pessoaComDados(['name' => 'Pessoa pendente exclusiva']);
        $this->vinculo($pendente);
        $outra = $this->pessoaComDados(['name' => 'Pessoa de outra ação']);
        $this->vinculo($outra, Atividade::factory()->create(['evento_id' => Evento::factory()->create()->id]));
        $this->actingAs($this->gestor)->get(route('autorizacoes-imagem.index', ['evento_id' => $this->acao->id, 'situacao' => 'pendente']))
            ->assertOk()->assertSee($pendente->name)->assertDontSee($this->pessoa->name)->assertDontSee($outra->name);
        $this->get(route('autorizacoes-imagem.index', ['evento_id' => $this->acao->id, 'situacao' => 'negada']))
            ->assertOk()->assertSee($this->pessoa->name)->assertDontSee($pendente->name);
    }

    #[DataProvider('rotasPrivadas')]
    public function test_gestao_rejeita_usuario_sem_permissao(string $rota, array $dados): void
    {
        $this->actingAs($this->pessoa)->get(route($rota, $dados))->assertForbidden();
    }

    public static function rotasPrivadas(): array
    {
        return [
            ['autorizacoes-imagem.index', []],
            ['autorizacoes-imagem.export', ['evento_id' => 1]],
        ];
    }

    #[DataProvider('acessos')]
    public function test_acesso_da_acao_fica_apenas_no_dropdown_gerenciar(string $perfil): void
    {
        $usuario = $this->pessoa;
        $usuario->assignRole($perfil);
        $html = $this->actingAs($usuario)->get(route('eventos.show', $this->acao))->assertOk()->getContent();
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($documento);
        $links = $xpath->query('//a[@href="'.route('autorizacoes-imagem.index', ['evento_id' => $this->acao->id]).'"]');
        $this->assertCount(1, $links);
        $menu = $xpath->query('ancestor::ul[@aria-labelledby="dropdownGerenciarEvento"]', $links->item(0));
        $this->assertCount(1, $menu);
    }

    public static function acessos(): array
    {
        return [['administrador'], ['articulador']];
    }

    public function test_formularios_mostram_texto_atual_sem_campos_documentais(): void
    {
        config(['autorizacao_imagem.texto' => 'Texto atualizado sem cadastro de versão.']);
        $this->vinculo($this->pessoa);
        $this->actingAs($this->gestor)->get(route('autorizacoes-imagem.index', ['evento_id' => $this->acao->id]))
            ->assertOk()->assertSee('Texto atualizado sem cadastro de versão.')
            ->assertDontSee('name="referencia_documental"', false)->assertDontSee('name="confirmar_documento"', false);
        $this->actingAs($this->pessoa)->get(route('profile.autorizacoes-imagem'))->assertOk()
            ->assertSee('Texto atualizado sem cadastro de versão.');
    }

    public function test_perfil_nao_exibe_acoes_de_outra_pessoa(): void
    {
        $this->vinculo($this->gestor);
        $this->actingAs($this->pessoa)->get(route('profile.autorizacoes-imagem'))->assertOk()->assertDontSee($this->acao->nome);
    }
}
