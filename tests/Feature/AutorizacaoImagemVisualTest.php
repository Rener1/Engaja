<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithAutorizacaoImagem;
use Tests\TestCase;

#[Group('browser')]
class AutorizacaoImagemVisualTest extends TestCase
{
    use InteractsWithAutorizacaoImagem, RefreshDatabase;

    public function test_formulario_e_termo_desktop_e_mobile(): void
    {
        if (! getenv('AUTORIZACAO_IMAGEM_QA_DIR')) {
            $this->markTestSkipped('Defina AUTORIZACAO_IMAGEM_QA_DIR para executar o Chrome e salvar os artefatos visuais.');
        }
        $this->prepararAutorizacoes();
        Avaliacao::factory()->create(['atividade_id' => $this->momento->id]);
        $response = $this->get(route('presenca.confirmar', $this->momento))->assertOk();
        $diretorio = getenv('AUTORIZACAO_IMAGEM_QA_DIR');
        if (! is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }
        $html = $diretorio.'/presenca.html';
        file_put_contents($html, $response->getContent());
        $this->post(route('presenca.store', $this->momento), ['campo' => $this->pessoa->email, 'autorizado' => false])->assertSessionHasNoErrors();
        $confirmada = $this->get(route('presenca.confirmar', $this->momento))->assertOk();
        file_put_contents($diretorio.'/presenca-confirmada.html', $confirmada->getContent());
        $processo = new Process(['node', base_path('tests/Browser/autorizacao-imagem.mjs'), $html, $diretorio], base_path());
        $processo->setTimeout(60);
        $processo->run();
        $this->assertTrue($processo->isSuccessful(), $processo->getOutput().$processo->getErrorOutput());
        $resultado = json_decode(trim($processo->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($resultado['ok']);
        $this->assertCount(2, $resultado['results']);
    }
}
