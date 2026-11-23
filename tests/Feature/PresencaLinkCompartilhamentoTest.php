<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Eixo;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SimpleSoftwareIO\QrCode\Generator;
use Tests\TestCase;

class PresencaLinkCompartilhamentoTest extends TestCase
{
    use RefreshDatabase;

    private function criarUsuarioEngaja(array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'identidade_genero' => 'Mulher Cisgênero',
            'raca_cor' => 'Parda',
            'comunidade_tradicional' => 'Não',
            'faixa_etaria' => 'Adulto (18 a 59 anos)',
            'pcd' => 'Não',
            'orientacao_sexual' => 'Heterossexual',
            'force_password_change' => false,
        ], $extra));
    }

    private function criarAtividade(array $atributos = []): Atividade
    {
        $eixo = Eixo::firstOrCreate(['nome' => 'Eixo Teste']);
        $evento = Evento::factory()->create(['eixo_id' => $eixo->id]);

        return Atividade::factory()->create(array_merge([
            'evento_id' => $evento->id,
            'presenca_ativa' => true,
            'presenca_abre_em' => null,
            'presenca_fecha_em' => null,
        ], $atributos));
    }

    private function mockQrCodeGenerator(?string $formatoEsperado = null, ?string &$urlRecebida = null, string $retorno = 'qr-code-binario'): void
    {
        $qrCodeMock = Mockery::mock(Generator::class);

        if ($formatoEsperado !== null) {
            $qrCodeMock->shouldReceive('format')
                ->once()
                ->with($formatoEsperado)
                ->andReturnSelf();
        } else {
            $qrCodeMock->shouldReceive('format')->andReturnSelf();
        }

        $qrCodeMock->shouldReceive('style', 'color', 'eye', 'eyeColor', 'size', 'margin', 'merge', 'errorCorrection')
            ->andReturnSelf();
        $qrCodeMock->shouldReceive('generate')
            ->once()
            ->with(Mockery::capture($urlRecebida))
            ->andReturn($retorno);

        $this->app->instance(Generator::class, $qrCodeMock);
    }

    private function extrairValorDoCampoUrl(string $html): ?string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $input = $dom->getElementById('presenca-confirmacao-url');

        return $input?->getAttribute('value');
    }

    public function test_usuario_autenticado_visualiza_campo_somente_leitura_label_e_botao_abaixo_do_qr(): void
    {
        config(['qrcode.format' => 'png']);
        $urlQr = null;
        $this->mockQrCodeGenerator('png', $urlQr, 'qr-png-bytes');

        $user = $this->criarUsuarioEngaja();
        $atividade = $this->criarAtividade();
        $urlEsperada = route('presenca.confirmar', $atividade);

        $response = $this->actingAs($user)->get(route('atividades.show', $atividade));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Confirmação de presença (QR)',
            'data:image/png;base64,'.base64_encode('qr-png-bytes'),
            'for="presenca-confirmacao-url"',
            'Link de confirmação de presença',
            'id="presenca-confirmacao-url"',
            'value="'.e($urlEsperada).'"',
            'readonly',
            'id="copiar-link-presenca"',
            'Copiar link',
            'Use este link para compartilhar a confirmação de presença.',
            'id="presenca-confirmacao-feedback"',
            'role="status"',
            'aria-live="polite"',
        ], false);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $input = $dom->getElementById('presenca-confirmacao-url');
        $button = $dom->getElementById('copiar-link-presenca');

        $this->assertNotNull($input);
        $this->assertSame('url', $input->getAttribute('type'));
        $this->assertTrue($input->hasAttribute('readonly'));
        $this->assertFalse($input->hasAttribute('disabled'));

        $this->assertNotNull($button);
        $this->assertSame('button', $button->getAttribute('type'));
    }

    public function test_url_codificada_no_qr_e_identica_a_url_absoluta_do_campo(): void
    {
        $urlRecebidaNoQr = null;
        $this->mockQrCodeGenerator(null, $urlRecebidaNoQr, 'qr-png-bytes');

        $user = $this->criarUsuarioEngaja();
        $atividade = $this->criarAtividade();
        $urlEsperada = route('presenca.confirmar', $atividade);

        $response = $this->actingAs($user)->get(route('atividades.show', $atividade));

        $response->assertOk();
        $valorDoCampo = $this->extrairValorDoCampoUrl($response->getContent());

        $this->assertSame($urlEsperada, $urlRecebidaNoQr);
        $this->assertSame($urlEsperada, $valorDoCampo);
    }

    public function test_atividades_diferentes_exibem_urls_distintas_correspondentes_a_cada_momento(): void
    {
        $user = $this->criarUsuarioEngaja();
        $atividadeA = $this->criarAtividade(['descricao' => 'Momento A']);
        $atividadeB = $this->criarAtividade(['descricao' => 'Momento B']);

        $urlA = route('presenca.confirmar', $atividadeA);
        $urlB = route('presenca.confirmar', $atividadeB);

        $urlQrA = null;
        $this->mockQrCodeGenerator(null, $urlQrA);
        $responseA = $this->actingAs($user)->get(route('atividades.show', $atividadeA));
        $responseA->assertOk();
        $this->assertSame($urlA, $urlQrA);
        $this->assertSame($urlA, $this->extrairValorDoCampoUrl($responseA->getContent()));
        $responseA->assertDontSee($urlB, false);

        $urlQrB = null;
        $this->mockQrCodeGenerator(null, $urlQrB);
        $responseB = $this->actingAs($user)->get(route('atividades.show', $atividadeB));
        $responseB->assertOk();
        $this->assertSame($urlB, $urlQrB);
        $this->assertSame($urlB, $this->extrairValorDoCampoUrl($responseB->getContent()));
        $responseB->assertDontSee($urlA, false);
    }

    public function test_visitante_nao_visualiza_bloco_qr_nem_campo_ou_botao_de_copia(): void
    {
        $atividade = $this->criarAtividade(['presenca_ativa' => true]);

        $response = $this->get(route('atividades.show', $atividade));

        $response->assertOk();
        $response->assertDontSee('Confirmação de presença (QR)');
        $response->assertDontSee('Link de confirmação de presença');
        $response->assertDontSee('presenca-confirmacao-url', false);
        $response->assertDontSee('copiar-link-presenca', false);
        $response->assertDontSee('Copiar link');
    }

    public function test_presenca_fechada_mantem_link_visivel_para_autenticado_e_respeita_bloqueio_no_formulario_publico(): void
    {
        $urlQr = null;
        $this->mockQrCodeGenerator(null, $urlQr);

        $gestor = $this->criarUsuarioEngaja();
        $participanteUser = $this->criarUsuarioEngaja(['email' => 'participante@engaja.test']);
        Participante::firstOrCreate(['user_id' => $participanteUser->id]);

        $atividade = $this->criarAtividade(['presenca_ativa' => false]);
        $urlEsperada = route('presenca.confirmar', $atividade);

        $showResponse = $this->actingAs($gestor)->get(route('atividades.show', $atividade));
        $showResponse->assertOk();
        $this->assertSame($urlEsperada, $urlQr);
        $this->assertSame($urlEsperada, $this->extrairValorDoCampoUrl($showResponse->getContent()));
        $showResponse->assertSee('Copiar link');

        $this->app['auth']->forgetGuards();

        $publicFormResponse = $this->get($urlEsperada);
        $publicFormResponse->assertOk();
        $publicFormResponse->assertSee('Confirmação de presença encerrada');
        $publicFormResponse->assertDontSee('id="form-busca-presenca"', false);

        $postResponse = $this->post(route('presenca.store', $atividade), [
            'campo' => 'participante@engaja.test',
        ]);
        $postResponse->assertRedirect($urlEsperada);
        $postResponse->assertSessionHas('error', 'A confirmação de presença deste momento está encerrada.');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_presenca_aberta_link_abre_mesmo_formulario_publico_do_qr(): void
    {
        $urlQr = null;
        $this->mockQrCodeGenerator(null, $urlQr);

        $user = $this->criarUsuarioEngaja();
        $atividade = $this->criarAtividade(['presenca_ativa' => true]);

        $showResponse = $this->actingAs($user)->get(route('atividades.show', $atividade));
        $showResponse->assertOk();

        $urlDoCampo = $this->extrairValorDoCampoUrl($showResponse->getContent());
        $this->assertNotNull($urlDoCampo);
        $this->assertSame($urlQr, $urlDoCampo);

        $this->app['auth']->forgetGuards();

        $publicFormResponse = $this->get($urlDoCampo);
        $publicFormResponse->assertOk();
        $publicFormResponse->assertSee('E-mail, CPF ou telefone');
        $publicFormResponse->assertSee('id="form-busca-presenca"', false);
    }

    public function test_view_respeita_config_qrcode_format_e_nao_consulta_env_diretamente(): void
    {
        $user = $this->criarUsuarioEngaja();
        $atividade = $this->criarAtividade();

        config(['qrcode.format' => 'svg']);
        $urlQrSvg = null;
        $this->mockQrCodeGenerator('svg', $urlQrSvg, '<svg></svg>');

        $svgResponse = $this->actingAs($user)->get(route('atividades.show', $atividade));
        $svgResponse->assertOk();
        $svgResponse->assertSee('data:image/svg+xml;base64,'.base64_encode('<svg></svg>'), false);

        config(['qrcode.format' => 'formato-invalido']);
        $urlQrFallback = null;
        $this->mockQrCodeGenerator('png', $urlQrFallback, 'png-fallback');

        $fallbackResponse = $this->actingAs($user)->get(route('atividades.show', $atividade));
        $fallbackResponse->assertOk();
        $fallbackResponse->assertSee('data:image/png;base64,'.base64_encode('png-fallback'), false);

        $viewContents = file_get_contents(resource_path('views/atividades/show.blade.php'));
        $this->assertNotFalse($viewContents);
        $this->assertStringNotContainsString('env(', $viewContents);
    }
}
