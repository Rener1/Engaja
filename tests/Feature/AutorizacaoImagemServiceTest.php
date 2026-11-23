<?php

namespace Tests\Feature;

use App\Enums\OrigemAutorizacaoImagem;
use App\Exceptions\AutorizacaoImagemConflitoException;
use App\Models\Atividade;
use App\Models\AutorizacaoImagem;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Presenca;
use App\Models\User;
use App\Services\AutorizacaoImagemContexto;
use App\Services\AutorizacaoImagemService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AutorizacaoImagemServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Evento $evento;

    private Presenca $presenca;

    private AutorizacaoImagemService $service;

    private AutorizacaoImagemContexto $contextos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-09 12:00:00');
        $this->seed(RolesPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->evento = Evento::factory()->create();
        $this->presenca = $this->vincular($this->user, $this->evento);
        $this->service = app(AutorizacaoImagemService::class);
        $this->contextos = app(AutorizacaoImagemContexto::class);
    }

    #[DataProvider('decisoes')]
    public function test_registra_decisao_e_historico_sem_alterar_presenca(bool $decisao): void
    {
        $registro = $this->responder($decisao);
        $this->assertSame($decisao, $registro->autorizado);
        $this->assertSame(1, $registro->versao);
        $this->assertNull($registro->registrado_por_user_id);
        $this->assertSame('presenca_publica', $registro->origem);
        $this->assertSame($this->evento->nome, $registro->acao_nome);
        $historico = $registro->historico->sole();
        $this->assertNull($historico->autorizado_anterior);
        $this->assertSame($decisao, $historico->autorizado);
        $this->assertDatabaseHas('presencas', ['id' => $this->presenca->id, 'status' => 'presente']);
        $this->assertDatabaseCount('presencas', 1);
    }

    public static function decisoes(): array
    {
        return [[true], [false]];
    }

    public function test_recusa_tambem_impede_nova_pergunta_na_mesma_acao(): void
    {
        $this->responder(false);
        $outraPresenca = $this->vincular($this->user, $this->evento);
        $this->assertNull($this->contextos->paraPresenca($outraPresenca, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica));
        $outraAcao = Evento::factory()->create();
        $novaPresenca = $this->vincular($this->user, $outraAcao);
        $this->assertNotNull($this->contextos->paraPresenca($novaPresenca, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica));
    }

    public function test_fechar_sem_responder_mantem_presenca_e_permite_nova_coleta(): void
    {
        $primeiro = $this->contexto();
        $segundo = $this->contexto();
        $this->assertNotSame($primeiro['token'], $segundo['token']);
        $this->assertDatabaseCount('autorizacoes_imagem', 0);
        $this->assertDatabaseCount('presencas', 1);
    }

    public function test_campo_global_legado_nao_define_autorizacao_por_acao(): void
    {
        $this->user->participante->update(['autorizacao_imagem' => true]);
        $this->assertNotNull($this->contexto());
        $this->responder(false);
        $this->assertTrue((bool) $this->user->participante->fresh()->autorizacao_imagem);
        $this->assertFalse($this->service->resposta($this->user->participante, $this->evento)->autorizado);
    }

    public function test_repeticao_do_mesmo_envio_e_idempotente(): void
    {
        $contexto = $this->contexto();
        for ($i = 0; $i < 2; $i++) {
            $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica, atividade: $this->presenca->atividade);
        }
        $this->assertDatabaseCount('autorizacoes_imagem', 1);
        $this->assertDatabaseCount('historicos_autorizacao_imagem', 1);
    }

    public function test_contexto_utilizado_nao_pode_inverter_decisao(): void
    {
        $contexto = $this->contexto();
        $this->contextos->responder($contexto['token'], false, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
        $this->expectException(AutorizacaoImagemConflitoException::class);
        $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
    }

    public function test_dois_popups_nao_sobrescrevem_primeira_resposta(): void
    {
        $primeiro = $this->contexto();
        $segundo = $this->contexto();
        $this->contextos->responder($primeiro['token'], false, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
        try {
            $this->contextos->responder($segundo['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
            $this->fail('Uma decisão concorrente não deve sobrescrever a primeira.');
        } catch (AutorizacaoImagemConflitoException) {
            $this->assertFalse($this->service->resposta($this->user->participante, $this->evento)->autorizado);
            $this->assertDatabaseCount('historicos_autorizacao_imagem', 1);
        }
    }

    public function test_token_de_outra_sessao_e_rejeitado(): void
    {
        $contexto = $this->contexto();
        $outraSessao = new Store('outra', new ArraySessionHandler(120));
        $this->expectException(ValidationException::class);
        $this->contextos->responder($contexto['token'], true, $outraSessao, OrigemAutorizacaoImagem::PresencaPublica);
    }

    public function test_token_expirado_nao_registra_autorizacao(): void
    {
        $contexto = $this->contexto();
        $this->travel(16)->minutes();
        try {
            $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
            $this->fail('O token deveria ter expirado.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('autorizacoes_imagem', 0);
            $this->assertDatabaseCount('presencas', 1);
        }
    }

    public function test_trocar_momento_do_contexto_e_bloqueado(): void
    {
        $contexto = $this->contexto();
        $outra = Atividade::factory()->create(['evento_id' => $this->evento->id]);
        $this->expectException(HttpException::class);
        $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica, atividade: $outra);
    }

    public function test_fechamento_da_presenca_nao_invalida_resposta_ja_em_andamento(): void
    {
        $contexto = $this->contexto();
        $this->presenca->atividade->update(['presenca_ativa' => false]);
        $registro = $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
        $this->assertTrue($registro->autorizado);
    }

    public function test_texto_do_termo_pode_mudar_sem_versionamento(): void
    {
        $contexto = $this->contexto();
        config(['autorizacao_imagem.texto' => 'Novo texto para leitura e autorização.']);
        $novoContexto = $this->contexto();
        $this->assertSame('Novo texto para leitura e autorização.', $novoContexto['termo_texto']);
        $registro = $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
        $this->assertTrue($registro->autorizado);
    }

    public function test_estrutura_inicial_guarda_decisao_sem_metadados_do_termo(): void
    {
        $registro = $this->responder(false);
        $this->assertFalse(Schema::hasTable('termos_autorizacao_imagem'));
        foreach (['autorizacoes_imagem', 'historicos_autorizacao_imagem'] as $tabela) {
            $this->assertFalse(Schema::hasColumn($tabela, 'termo_versao_id'));
            $this->assertFalse(Schema::hasColumn($tabela, 'referencia_documental'));
        }
        $this->assertDatabaseCount('autorizacoes_imagem', 1);
        $this->assertFalse($registro->fresh()->autorizado);
        $this->assertSame($this->evento->id, $registro->fresh()->evento_id);
        $this->assertSame($this->presenca->id, $registro->fresh()->presenca_origem_id);
        $this->assertDatabaseCount('historicos_autorizacao_imagem', 1);
        $this->assertFalse($registro->historico()->first()->autorizado);
        $this->assertDatabaseCount('presencas', 1);
    }

    public function test_alteracao_explicita_preserva_historico(): void
    {
        $original = $this->responder(false);
        $contexto = $this->contextos->paraFormulario($this->user->participante, $this->evento, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->user);
        $atual = $this->contextos->responder($contexto['token'], true, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->user, evento: $this->evento, participante: $this->user->participante);
        $this->assertSame($original->id, $atual->id);
        $this->assertSame(2, $atual->versao);
        $this->assertSame(2, $atual->historico()->count());
        $this->assertFalse($atual->historico()->first()->autorizado_anterior);
        $this->assertSame($this->user->id, $atual->registrado_por_user_id);
    }

    public function test_edicao_antiga_nao_substitui_edicao_mais_recente(): void
    {
        $this->responder(false);
        $primeiro = $this->contextos->paraFormulario($this->user->participante, $this->evento, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->user);
        $segundo = $this->contextos->paraFormulario($this->user->participante, $this->evento, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->user);
        $this->contextos->responder($primeiro['token'], true, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->user);
        $this->expectException(AutorizacaoImagemConflitoException::class);
        $this->contextos->responder($segundo['token'], false, session()->driver(), OrigemAutorizacaoImagem::Perfil, $this->user);
    }

    public function test_historico_e_imutavel(): void
    {
        $registro = $this->responder(true);
        $this->expectException(LogicException::class);
        $registro->historico()->first()->update(['autorizado' => false]);
    }

    public function test_banco_impede_duplicidade_do_participante_na_acao(): void
    {
        $registro = $this->responder(true);
        $this->expectException(QueryException::class);
        $registro->replicate()->save();
    }

    private function contexto(): array
    {
        return $this->contextos->paraPresenca($this->presenca, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica);
    }

    private function responder(bool $decisao): AutorizacaoImagem
    {
        $contexto = $this->contexto();

        return $this->contextos->responder($contexto['token'], $decisao, session()->driver(), OrigemAutorizacaoImagem::PresencaPublica, atividade: $this->presenca->atividade);
    }

    private function vincular(User $user, Evento $evento): Presenca
    {
        $atividade = Atividade::factory()->create(['evento_id' => $evento->id, 'presenca_ativa' => true]);
        $inscricao = Inscricao::create(['evento_id' => $evento->id, 'atividade_id' => $atividade->id, 'participante_id' => $user->participante->id]);

        return Presenca::create(['inscricao_id' => $inscricao->id, 'atividade_id' => $atividade->id, 'status' => 'presente']);
    }
}
