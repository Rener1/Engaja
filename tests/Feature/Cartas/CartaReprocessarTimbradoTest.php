<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\CartaMensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartaReprocessarTimbradoTest extends TestCase
{
    use RefreshDatabase;

    private function mensagemComAnexoPendente(): CartaMensagem
    {
        return CartaMensagem::factory()->doVoluntario()->create([
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
            'anexo_original_path' => Storage::disk('local')->putFile(
                'cartas/anexos-teste',
                new File(base_path('tests/Fixtures/cartas/exemplo-anexo.pdf'))
            ),
            'arquivo_final_path' => null,
            'timbrado_aplicado_em' => null,
        ]);
    }

    public function test_reprocessa_mensagens_pendentes_e_gera_arquivo_final(): void
    {
        Storage::fake('local');

        $mensagem = $this->mensagemComAnexoPendente();
        $anexoOriginal = $mensagem->anexo_original_path;

        $this->artisan('cartas:reprocessar-timbrado', ['--force' => true])
            ->assertExitCode(0);

        $mensagem->refresh();

        $this->assertNotNull($mensagem->arquivo_final_path);
        $this->assertNotNull($mensagem->timbrado_aplicado_em);
        Storage::disk('local')->assertExists($mensagem->arquivo_final_path);

        // O anexo original nunca pode ser alterado/removido pelo reprocessamento.
        $this->assertSame($anexoOriginal, $mensagem->anexo_original_path);
        Storage::disk('local')->assertExists($anexoOriginal);
    }

    public function test_dry_run_nao_altera_nenhuma_mensagem(): void
    {
        Storage::fake('local');

        $mensagem = $this->mensagemComAnexoPendente();

        $this->artisan('cartas:reprocessar-timbrado', ['--dry-run' => true])
            ->expectsOutputToContain("mensagem #{$mensagem->id}")
            ->assertExitCode(0);

        $mensagem->refresh();

        $this->assertNull($mensagem->arquivo_final_path);
        $this->assertNull($mensagem->timbrado_aplicado_em);
    }

    public function test_nao_reprocessa_mensagem_que_ja_tem_timbrado_aplicado(): void
    {
        Storage::fake('local');

        $jaProcessada = $this->mensagemComAnexoPendente();
        $jaProcessada->forceFill([
            'arquivo_final_path' => 'cartas/ja-processada.pdf',
            'timbrado_aplicado_em' => now(),
        ])->save();
        Storage::disk('local')->put('cartas/ja-processada.pdf', 'conteudo-original-ja-timbrado');

        $this->artisan('cartas:reprocessar-timbrado', ['--force' => true])
            ->assertExitCode(0);

        $jaProcessada->refresh();

        // Continua exatamente como estava — não foi re-renderizada.
        $this->assertSame('cartas/ja-processada.pdf', $jaProcessada->arquivo_final_path);
        $this->assertSame('conteudo-original-ja-timbrado', Storage::disk('local')->get('cartas/ja-processada.pdf'));
    }

    public function test_filtro_por_id_restringe_o_reprocessamento(): void
    {
        Storage::fake('local');

        $alvo = $this->mensagemComAnexoPendente();
        $fora = $this->mensagemComAnexoPendente();

        $this->artisan('cartas:reprocessar-timbrado', ['--id' => [$alvo->id], '--force' => true])
            ->assertExitCode(0);

        $alvo->refresh();
        $fora->refresh();

        $this->assertNotNull($alvo->timbrado_aplicado_em);
        $this->assertNull($fora->timbrado_aplicado_em);
    }

    public function test_nao_reprocessa_cartas_de_educando(): void
    {
        Storage::fake('local');

        $educando = $this->mensagemComAnexoPendente();
        $educando->forceFill(['tipo_remetente' => CartaMensagem::TIPO_REMETENTE_EDUCANDO])->save();

        $voluntario = $this->mensagemComAnexoPendente();

        $this->artisan('cartas:reprocessar-timbrado', ['--force' => true])
            ->assertExitCode(0);

        $educando->refresh();
        $voluntario->refresh();

        $this->assertNull($educando->arquivo_final_path);
        $this->assertNull($educando->timbrado_aplicado_em);
        $this->assertNotNull($voluntario->timbrado_aplicado_em);
    }

    public function test_nao_reprocessa_mensagem_com_timbrado_desativado_pelo_gestor(): void
    {
        Storage::fake('local');

        $desativada = $this->mensagemComAnexoPendente();
        $desativada->forceFill(['timbrado_desativado' => true])->save();

        $pendente = $this->mensagemComAnexoPendente();

        $this->artisan('cartas:reprocessar-timbrado', ['--force' => true])
            ->assertExitCode(0);

        $desativada->refresh();
        $pendente->refresh();

        $this->assertNull($desativada->arquivo_final_path);
        $this->assertNull($desativada->timbrado_aplicado_em);
        $this->assertNotNull($pendente->timbrado_aplicado_em);
    }

    public function test_anexo_incompativel_continua_sem_timbrado_e_e_reportado(): void
    {
        Storage::fake('local');

        $path = 'cartas/anexos-teste/invalido.pdf';
        Storage::disk('local')->put($path, random_bytes(200));

        $mensagem = CartaMensagem::factory()->doVoluntario()->create([
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
            'anexo_original_path' => $path,
            'arquivo_final_path' => null,
            'timbrado_aplicado_em' => null,
        ]);

        $this->artisan('cartas:reprocessar-timbrado', ['--force' => true])
            ->assertExitCode(1);

        $mensagem->refresh();

        $this->assertNull($mensagem->arquivo_final_path);
        $this->assertNull($mensagem->timbrado_aplicado_em);
        $this->assertNotNull($mensagem->anexo_original_path);
    }

    public function test_sem_confirmacao_nenhuma_mensagem_e_alterada(): void
    {
        Storage::fake('local');

        $mensagem = $this->mensagemComAnexoPendente();

        $this->artisan('cartas:reprocessar-timbrado')
            ->expectsConfirmation(
                'Aplicar o timbrado em 1 carta(s) agora? O anexo original (anexo_original_path) nunca é alterado ou removido; apenas o PDF final timbrado é (re)gerado em arquivo separado.',
                'no'
            )
            ->assertExitCode(0);

        $mensagem->refresh();

        $this->assertNull($mensagem->arquivo_final_path);
        $this->assertNull($mensagem->timbrado_aplicado_em);
    }
}
