<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaEvento;
use App\Models\Cartas\CartaMensagem;
use App\Models\User;
use App\Services\Cartas\CartaTimbradoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CartaEdicaoGestorTest extends CartasBaseTest
{
    /**
     * Carta de voluntário enviada como anexo, já com o timbrado aplicado.
     */
    private function mensagemDeVoluntarioComTimbrado(): CartaMensagem
    {
        [, $mensagem] = $this->criarCartaComRespostaAguardandoVerificacao();

        $mensagem->update([
            'canal_entrada' => CartaMensagem::CANAL_ANEXO_MANUSCRITO,
            'texto' => null,
            'anexo_original_path' => $this->pdfFalsoValido('antiga.pdf')->store("cartas/{$mensagem->carta_id}/originais", 'local'),
            'anexo_original_nome' => 'antiga.pdf',
            'anexo_original_mime' => 'application/pdf',
        ]);

        app(CartaTimbradoService::class)->aplicarAnexo($mensagem);

        return $mensagem->refresh();
    }

    private function mensagemDeEducando(): CartaMensagem
    {
        $carta = $this->criarCartaParaVoluntario();
        $mensagem = $carta->mensagens()->firstOrFail();

        $mensagem->update([
            'anexo_original_path' => $this->pdfFalsoValido('educando.pdf')->store("cartas/{$carta->id}/originais", 'local'),
            'anexo_original_nome' => 'educando.pdf',
            'anexo_original_mime' => 'application/pdf',
        ]);

        return $mensagem->refresh();
    }

    private function cartasAdmin(): User
    {
        $admin = User::factory()->create([
            'sistema_origem' => User::SISTEMA_CARTAS,
            'email_verified_at' => now(),
            'cartas_terms_accepted_at' => now(),
        ]);
        $admin->assignRole('cartas_admin');

        return $admin;
    }

    public function test_gestor_substitui_pdf_e_reaplica_timbrado(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();
        $originalAntigo = $mensagem->anexo_original_path;
        $this->assertNotNull($mensagem->timbrado_aplicado_em);

        $response = $this->actingAs($this->gestor)->put(route('cartas.mensagens.update', $mensagem), [
            'arquivo' => $this->pdfFalsoValido('nova.pdf'),
            'aplicar_timbrado' => '1',
        ]);

        $response->assertRedirect(route('cartas.cartas.show', $mensagem->carta_id));
        $response->assertSessionHas('status', 'Carta atualizada.');

        $mensagem->refresh();

        $this->assertSame('nova.pdf', $mensagem->anexo_original_nome);
        $this->assertNotSame($originalAntigo, $mensagem->anexo_original_path);
        Storage::disk('local')->assertExists($mensagem->anexo_original_path);
        Storage::disk('local')->assertMissing($originalAntigo);

        $this->assertNotNull($mensagem->timbrado_aplicado_em);
        $this->assertFalse($mensagem->timbrado_desativado);
        Storage::disk('local')->assertExists($mensagem->arquivo_final_path);

        $evento = CartaEvento::where('carta_mensagem_id', $mensagem->id)
            ->where('tipo', CartaEvento::TIPO_EDITADA_ADMIN)
            ->first();
        $this->assertNotNull($evento);
        $this->assertSame('antiga.pdf', $evento->dados_antes['anexo_original_nome']);
        $this->assertSame('nova.pdf', $evento->dados_depois['anexo_original_nome']);
    }

    public function test_admin_de_cartas_tambem_pode_editar(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();

        $this->actingAs($this->cartasAdmin())->put(route('cartas.mensagens.update', $mensagem), [
            'arquivo' => $this->pdfFalsoValido('nova.pdf'),
            'aplicar_timbrado' => '1',
        ])->assertRedirect(route('cartas.cartas.show', $mensagem->carta_id));

        $this->assertSame('nova.pdf', $mensagem->refresh()->anexo_original_nome);
    }

    public function test_desmarcar_timbrado_remove_pdf_final_e_download_serve_original(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();
        $finalAntigo = $mensagem->arquivo_final_path;

        $this->actingAs($this->gestor)->put(route('cartas.mensagens.update', $mensagem), [
            'aplicar_timbrado' => '0',
        ])->assertSessionHasNoErrors();

        $mensagem->refresh();

        $this->assertNull($mensagem->arquivo_final_path);
        $this->assertNull($mensagem->arquivo_final_nome);
        $this->assertNull($mensagem->timbrado_aplicado_em);
        $this->assertTrue($mensagem->timbrado_desativado);
        Storage::disk('local')->assertMissing($finalAntigo);

        // O original é mantido quando nenhum arquivo novo é enviado.
        $this->assertSame('antiga.pdf', $mensagem->anexo_original_nome);
        Storage::disk('local')->assertExists($mensagem->anexo_original_path);

        $download = $this->actingAs($this->gestor)->get(route('cartas.mensagens.download', $mensagem));
        $download->assertOk();
        $this->assertSame(
            Storage::disk('local')->get($mensagem->anexo_original_path),
            $download->streamedContent()
        );
    }

    public function test_timbrado_desativado_e_respeitado_pela_aplicacao_automatica(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();

        $this->actingAs($this->gestor)->put(route('cartas.mensagens.update', $mensagem), [
            'aplicar_timbrado' => '0',
        ]);

        app(CartaTimbradoService::class)->aplicarAnexo($mensagem->refresh());

        $this->assertNull($mensagem->refresh()->timbrado_aplicado_em);
    }

    public function test_checkbox_marcada_forca_timbrado_em_carta_de_educando(): void
    {
        $mensagem = $this->mensagemDeEducando();
        $this->assertNull($mensagem->timbrado_aplicado_em);

        $this->actingAs($this->gestor)->put(route('cartas.mensagens.update', $mensagem), [
            'aplicar_timbrado' => '1',
        ])->assertSessionHas('status', 'Carta atualizada.');

        $mensagem->refresh();

        $this->assertNotNull($mensagem->timbrado_aplicado_em);
        Storage::disk('local')->assertExists($mensagem->arquivo_final_path);
        $this->assertSame('educando.pdf', $mensagem->anexo_original_nome);
    }

    public function test_carta_digitada_sem_anexo_reaplica_timbrado_pelo_texto(): void
    {
        [, $mensagem] = $this->criarCartaComRespostaAguardandoVerificacao();

        $this->actingAs($this->gestor)->put(route('cartas.mensagens.update', $mensagem), [
            'aplicar_timbrado' => '1',
        ])->assertSessionHasNoErrors();

        $mensagem->refresh();

        $this->assertNull($mensagem->anexo_original_path);
        $this->assertNotNull($mensagem->timbrado_aplicado_em);
        Storage::disk('local')->assertExists($mensagem->arquivo_final_path);
    }

    public function test_arquivo_que_nao_e_pdf_e_rejeitado(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();

        $this->actingAs($this->gestor)->put(route('cartas.mensagens.update', $mensagem), [
            'arquivo' => UploadedFile::fake()->create('carta.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'aplicar_timbrado' => '1',
        ])->assertSessionHasErrors(['arquivo' => 'Envie um arquivo em PDF.']);

        $this->assertSame('antiga.pdf', $mensagem->refresh()->anexo_original_nome);
    }

    public function test_voluntario_nao_pode_editar_carta_enviada(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();

        $this->actingAs($this->voluntario)->put(route('cartas.mensagens.update', $mensagem), [
            'arquivo' => $this->pdfFalsoValido('nova.pdf'),
            'aplicar_timbrado' => '0',
        ])->assertForbidden();

        $mensagem->refresh();
        $this->assertSame('antiga.pdf', $mensagem->anexo_original_nome);
        $this->assertNotNull($mensagem->timbrado_aplicado_em);
    }

    public function test_download_original_retorna_pdf_sem_timbrado(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();
        $this->assertNotNull($mensagem->arquivo_final_path);

        $response = $this->actingAs($this->gestor)->get(route('cartas.mensagens.download-original', $mensagem));

        $response->assertOk();
        $this->assertStringContainsString('_SEM_TIMBRADO.pdf', $response->headers->get('content-disposition'));
        $this->assertSame(
            Storage::disk('local')->get($mensagem->anexo_original_path),
            $response->streamedContent()
        );
    }

    public function test_download_original_retorna_404_sem_anexo(): void
    {
        [, $mensagem] = $this->criarCartaComRespostaAguardandoVerificacao();

        $this->actingAs($this->gestor)
            ->get(route('cartas.mensagens.download-original', $mensagem))
            ->assertNotFound();
    }

    public function test_voluntario_nao_pode_baixar_original(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();

        $this->actingAs($this->voluntario)
            ->get(route('cartas.mensagens.download-original', $mensagem))
            ->assertForbidden();
    }

    public function test_ajuste_do_voluntario_reativa_timbrado_padrao(): void
    {
        [, $mensagem] = $this->criarCartaComRespostaComAjusteSolicitado();
        $mensagem->forceFill(['timbrado_desativado' => true])->save();

        $this->actingAs($this->voluntario)->put(route('cartas.mensagens.update-adjustment', $mensagem), [
            'modo_resposta' => 'anexo_manuscrito',
            'arquivo' => $this->pdfFalsoValido('ajuste.pdf'),
        ])->assertSessionHasNoErrors();

        $mensagem->refresh();

        $this->assertFalse($mensagem->timbrado_desativado);
        $this->assertNotNull($mensagem->timbrado_aplicado_em);
    }

    public function test_tela_da_carta_exibe_acoes_de_edicao_e_download_para_gestor(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();
        $carta = Carta::findOrFail($mensagem->carta_id);

        $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta))
            ->assertOk()
            ->assertSee('editMensagemModal-'.$mensagem->id, false)
            ->assertSee(route('cartas.mensagens.update', $mensagem), false)
            ->assertSee('name="aplicar_timbrado"', false)
            // Com as duas versões disponíveis: um único botão com dropdown.
            ->assertSee('class="cpe-download-menu"', false)
            ->assertSeeInOrder(['Baixar carta', 'Com timbrado', 'Original (sem timbrado)'])
            ->assertSee(route('cartas.mensagens.download', $mensagem), false)
            ->assertSee(route('cartas.mensagens.download-original', $mensagem), false)
            ->assertDontSee('Baixar com timbrado')
            ->assertDontSee('Baixar original (sem timbrado)')
            ->assertDontSee('cpe-msg-item__download', false);
    }

    public function test_sem_timbrado_exibe_botao_unico_de_original_sem_dropdown(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();
        app(CartaTimbradoService::class)->removerTimbrado($mensagem);

        $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $mensagem->carta_id))
            ->assertOk()
            ->assertDontSee('class="cpe-download-menu"', false)
            ->assertSee('Baixar original (sem timbrado)')
            ->assertSee(route('cartas.mensagens.download-original', $mensagem), false)
            ->assertDontSee(route('cartas.mensagens.download', $mensagem).'"', false);
    }

    public function test_carta_digitada_exibe_botao_unico_com_timbrado_sem_dropdown(): void
    {
        [$carta, $mensagem] = $this->criarCartaComRespostaAguardandoVerificacao();
        app(CartaTimbradoService::class)->aplicar($mensagem);

        $this->actingAs($this->gestor)->get(route('cartas.cartas.show', $carta))
            ->assertOk()
            ->assertDontSee('class="cpe-download-menu"', false)
            ->assertSee('Baixar com timbrado')
            ->assertDontSee(route('cartas.mensagens.download-original', $mensagem), false);
    }

    public function test_tela_da_carta_nao_exibe_acoes_de_gestor_para_voluntario(): void
    {
        $mensagem = $this->mensagemDeVoluntarioComTimbrado();
        $carta = Carta::findOrFail($mensagem->carta_id);

        $this->actingAs($this->voluntario)->get(route('cartas.cartas.show', $carta))
            ->assertOk()
            ->assertDontSee('editMensagemModal-'.$mensagem->id, false)
            ->assertDontSee(route('cartas.mensagens.download-original', $mensagem), false);
    }
}
