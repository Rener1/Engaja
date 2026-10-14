<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use App\Models\Participante;
use App\Models\User;
use App\Services\Cartas\CartaTimbradoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartaTimbradoTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplicar_gera_pdf_final_e_salva_metadados(): void
    {
        Storage::fake('local');

        $mensagem = CartaMensagem::factory()->create([
            'texto' => "Olá, querido educando!\n\nRecebi sua carta com esperança. Um abraço — çãõ.",
            'canal_entrada' => CartaMensagem::CANAL_DIGITADA,
            'status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
        ]);

        (new CartaTimbradoService)->aplicar($mensagem);

        $mensagem->refresh();

        $this->assertNotNull($mensagem->arquivo_final_path);
        $this->assertSame('application/pdf', $mensagem->arquivo_final_mime);
        $this->assertGreaterThan(0, $mensagem->arquivo_final_tamanho);
        $this->assertNotNull($mensagem->timbrado_aplicado_em);
        Storage::disk('local')->assertExists($mensagem->arquivo_final_path);
    }

    public function test_aplicar_anexo_gera_pdf_final_multipagina(): void
    {
        Storage::fake('local');

        $mensagem = CartaMensagem::factory()->doVoluntario()->create([
            'texto' => null,
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
            'status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
            'anexo_original_path' => Storage::disk('local')->putFile(
                'cartas/anexos-teste',
                new File(base_path('tests/Fixtures/cartas/exemplo-anexo.pdf'))
            ),
        ]);

        (new CartaTimbradoService)->aplicarAnexo($mensagem);

        $mensagem->refresh();

        $this->assertNotNull($mensagem->arquivo_final_path);
        $this->assertSame('application/pdf', $mensagem->arquivo_final_mime);
        $this->assertGreaterThan(0, $mensagem->arquivo_final_tamanho);
        $this->assertNotNull($mensagem->timbrado_aplicado_em);
        Storage::disk('local')->assertExists($mensagem->arquivo_final_path);
    }

    public function test_aplicar_anexo_nao_aplica_timbrado_em_carta_de_educando(): void
    {
        Storage::fake('local');

        $mensagem = CartaMensagem::factory()->doEducando()->create([
            'texto' => null,
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_DIGITALIZADO,
            'anexo_original_path' => Storage::disk('local')->putFile(
                'cartas/anexos-teste',
                new File(base_path('tests/Fixtures/cartas/exemplo-anexo.pdf'))
            ),
        ]);

        (new CartaTimbradoService)->aplicarAnexo($mensagem);

        $mensagem->refresh();

        $this->assertNull($mensagem->arquivo_final_path);
        $this->assertNull($mensagem->timbrado_aplicado_em);
        $this->assertNotNull($mensagem->anexo_original_path);
    }

    public function test_aplicar_anexo_com_pdf_nao_suportado_nao_derruba_o_envio(): void
    {
        Storage::fake('local');

        $path = 'cartas/anexos-teste/invalido.pdf';
        Storage::disk('local')->put($path, random_bytes(200));

        $mensagem = CartaMensagem::factory()->doVoluntario()->create([
            'texto' => null,
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
            'status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
            'anexo_original_path' => $path,
        ]);

        (new CartaTimbradoService)->aplicarAnexo($mensagem);

        $mensagem->refresh();

        $this->assertNull($mensagem->arquivo_final_path);
        $this->assertNotNull($mensagem->anexo_original_path);
    }

    public function test_resposta_digitada_do_voluntario_aplica_timbrado(): void
    {
        Storage::fake('local');

        $voluntario = User::factory()->create([
            'sistema_origem' => User::SISTEMA_CARTAS,
            'email_verified_at' => now(),
        ]);

        $educando = Participante::factory()->create([
            'user_id' => User::factory()->create(['sistema_origem' => User::SISTEMA_ENGAJA]),
        ]);

        $carta = Carta::factory()->create([
            'educando_participante_id' => $educando->id,
            'voluntario_user_id' => $voluntario->id,
            'status' => Carta::STATUS_AGUARDANDO_VOLUNTARIO,
        ]);

        CartaMensagem::factory()->create([
            'carta_id' => $carta->id,
            'rodada' => 1,
            'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_EDUCANDO,
            'status' => CartaMensagem::STATUS_APROVADA,
        ]);

        $response = $this->actingAs($voluntario)->post(route('cartas.cartas.respond', $carta), [
            'modo_resposta' => 'digitada',
            'texto' => 'Minha resposta digitada com carinho.',
        ]);

        $response->assertRedirect();

        $resposta = $carta->mensagens()->where('rodada', 2)->first();

        $this->assertNotNull($resposta);
        $this->assertNotNull($resposta->arquivo_final_path);
        Storage::disk('local')->assertExists($resposta->arquivo_final_path);
    }

    public function test_render_com_espacos_de_largura_zero(): void
    {
        $service = new CartaTimbradoService;
        $texto = "Olá querido educando!\u{200B} Esta é uma frase com ZWSP,\u{200C} ZWNJ,\u{200D} ZWJ e\u{FEFF} BOM.";

        // Garante que os caracteres invisíveis foram devidamente limpos
        $normalizado = $service->normalizarTexto($texto);
        $this->assertStringNotContainsString("\u{200B}", $normalizado);
        $this->assertStringNotContainsString("\u{200C}", $normalizado);
        $this->assertStringNotContainsString("\u{200D}", $normalizado);
        $this->assertStringNotContainsString("\u{FEFF}", $normalizado);

        $pdf = $service->render($texto);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_render_com_espacos_especiais_e_quebras_de_linha_unicode(): void
    {
        $service = new CartaTimbradoService;
        $texto = "Linha um com espaço não quebrável NBSP\u{00A0}aqui e NNBSP\u{202F}aqui.\u{2028}".
                 "Linha dois após line separator Unicode.\u{2029}".
                 "Linha três com Em-space\u{2003}e Ideographic\u{3000}espaço.\r\n".
                 'Linha quatro após retorno de carro clássico.';

        $pdf = $service->render($texto);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_render_com_travessoes_e_aspas_tipograficas(): void
    {
        $service = new CartaTimbradoService;
        $texto = '“Escrevo esta carta com esperança” — disse o voluntário – com carinho, ‘sempre’ acreditando… «avante».';

        // Verifica que o método encode mapeia para os bytes corretos do Windows-1252
        $encoded = $service->encode($texto);
        $this->assertStringContainsString(chr(147), $encoded); // left double quote “
        $this->assertStringContainsString(chr(148), $encoded); // right double quote ”
        $this->assertStringContainsString(chr(151), $encoded); // em-dash —
        $this->assertStringContainsString(chr(150), $encoded); // en-dash –
        $this->assertStringContainsString(chr(133), $encoded); // ellipsis …

        $pdf = $service->render($texto);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_render_com_variacoes_de_codificacao_e_bytes_malformados(): void
    {
        $service = new CartaTimbradoService;
        // Bytes inválidos e sequências UTF-8 quebradas/incompletas
        $textoComBytesCorrompidos = "Carta com bytes legados e corrompidos: \xA0 e \x96 e \x80 e \xFF, e órfãos \xC3 e \xE2\x82 e \xF0\x9F\x98 final.";

        // Não deve disparar iconv(): Detected an illegal character in input string
        $pdf = $service->render($textoComBytesCorrompidos);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_resposta_digitada_no_endpoint_com_emojis_aspas_travessoes_e_bytes_malformados(): void
    {
        Storage::fake('local');

        $voluntario = User::factory()->create([
            'sistema_origem' => User::SISTEMA_CARTAS,
            'email_verified_at' => now(),
        ]);

        $educando = Participante::factory()->create([
            'user_id' => User::factory()->create(['sistema_origem' => User::SISTEMA_ENGAJA]),
        ]);

        $carta = Carta::factory()->create([
            'educando_participante_id' => $educando->id,
            'voluntario_user_id' => $voluntario->id,
            'status' => Carta::STATUS_AGUARDANDO_VOLUNTARIO,
        ]);

        CartaMensagem::factory()->create([
            'carta_id' => $carta->id,
            'rodada' => 1,
            'tipo_remetente' => CartaMensagem::TIPO_REMETENTE_EDUCANDO,
            'status' => CartaMensagem::STATUS_APROVADA,
        ]);

        $textoDesafiador = "Olá educando! ❤️ 😊 ✨ 🌟\n\n".
            "“Que a esperança renove seus passos” — com todo o meu afeto – e carinho.\u{200B}\n".
            "Espaços especiais: NBSP\u{00A0}aqui e quebra Unicode\u{2028}aqui.\n".
            "Bytes de colagem legada: \xA0 e \x96 e \x80 com família 👨‍👩‍👧‍👦 e bandeira 🇧🇷.";

        $response = $this->actingAs($voluntario)->post(route('cartas.cartas.respond', $carta), [
            'modo_resposta' => 'digitada',
            'texto' => $textoDesafiador,
        ]);

        $response->assertRedirect();

        $resposta = $carta->mensagens()->where('rodada', 2)->first();

        $this->assertNotNull($resposta);
        $this->assertNotNull($resposta->arquivo_final_path);
        Storage::disk('local')->assertExists($resposta->arquivo_final_path);

        $conteudoPdf = Storage::disk('local')->get($resposta->arquivo_final_path);
        $this->assertStringStartsWith('%PDF-', $conteudoPdf);
    }
}
