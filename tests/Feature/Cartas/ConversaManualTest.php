<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ConversaManualTest extends CartasBaseTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->actingAs($this->gestor);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'envio_token' => (string) Str::uuid(),
            'fluxo' => 'manual',
            'remetente_user_id' => $this->remetente->id,
            'voluntario_user_id' => $this->voluntario2->id,
            'arquivo' => $this->pdfFalsoValido(),
        ], $extra);
    }

    public function test_gestor_registra_conversa_com_resposta_no_historico_normal(): void
    {
        $dados = $this->dados(['arquivo_resposta' => $this->pdfFalsoValido('resposta.pdf')]);
        $this->post(route('cartas.cartas.store'), $dados)->assertSessionHasNoErrors()->assertRedirect();
        $carta = Carta::firstOrFail();
        $this->assertSame($this->voluntario2->id, $carta->voluntario_user_id);
        $this->assertSame(Carta::STATUS_RESPONDIDA, $carta->status);
        $this->assertCount(2, $carta->mensagens);
        $resposta = $carta->mensagens->last();
        $this->assertSame($this->voluntario2->id, $resposta->remetente_user_id);
        $this->assertSame($this->gestor->id, $resposta->criada_por);
        $this->assertSame(CartaMensagem::STATUS_APROVADA, $resposta->status);
        $this->assertNotNull($resposta->arquivo_final_path);
        $this->get(route('cartas.dashboard'))->assertOk()->assertSee($carta->codigo);
        $this->get(route('cartas.cartas.show', $carta))->assertOk();
        $this->post(route('cartas.cartas.store'), $dados)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cartas', 1);
        $this->assertDatabaseCount('carta_mensagens', 2);
    }

    public function test_resposta_pode_ser_anexada_depois_pelo_gestor(): void
    {
        $this->post(route('cartas.cartas.store'), $this->dados())->assertSessionHasNoErrors();
        $carta = Carta::firstOrFail();
        $this->get(route('cartas.cartas.show', $carta))->assertOk()->assertSee('Adicionar resposta do voluntário');
        $this->post(route('cartas.cartas.mensagens.store', $carta), ['arquivo' => $this->pdfFalsoValido(), 'tipo_remetente' => 'voluntario'])->assertSessionHasNoErrors();
        $this->assertCount(2, $carta->fresh()->mensagens);
        $this->assertTrue($carta->fresh()->podeEducandoEnviar());
        $this->post(route('cartas.cartas.mensagens.store', $carta), ['arquivo' => $this->pdfFalsoValido(), 'tipo_remetente' => 'voluntario'])->assertSessionHasErrors('arquivo');
        $this->assertCount(2, $carta->fresh()->mensagens);
    }

    public function test_admin_pode_cadastrar_e_pdf_invalido_e_rejeitado(): void
    {
        $this->gestor->syncRoles(['cartas_admin']);
        $this->post(route('cartas.cartas.store'), $this->dados([
            'arquivo_resposta' => \Illuminate\Http\UploadedFile::fake()->create('resposta.txt', 10, 'text/plain'),
        ]))->assertSessionHasErrors('arquivo_resposta');
        $this->assertDatabaseCount('cartas', 0);
        $this->post(route('cartas.cartas.store'), $this->dados())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cartas', 1);
    }

    public function test_nao_aceita_usuario_sem_papel_voluntario(): void
    {
        $this->post(route('cartas.cartas.store'), $this->dados(['voluntario_user_id' => $this->gestor->id]))->assertSessionHasErrors('voluntario_user_id');
        $this->assertDatabaseCount('cartas', 0);
    }

    public function test_permite_voluntario_no_limite_no_fluxo_manual(): void
    {
        $this->voluntario2->update(['cartas_limite_respostas' => 0]);
        $this->post(route('cartas.cartas.store'), $this->dados())->assertSessionHasNoErrors();
        $this->assertSame($this->voluntario2->id, Carta::firstOrFail()->voluntario_user_id);
    }

    public function test_nao_permite_escolher_o_proprio_remetente_pelo_cpf(): void
    {
        $this->voluntario2->participante->update(['cpf' => $this->educando->cpf]);
        $this->post(route('cartas.cartas.store'), $this->dados())->assertSessionHasErrors('voluntario_user_id');
        $this->assertDatabaseCount('cartas', 0);
    }

    public function test_voluntario_nao_pode_registrar_conversa_pelo_gestor(): void
    {
        $this->actingAs($this->voluntario)->post(route('cartas.cartas.store'), $this->dados())->assertForbidden();
    }

    public function test_resposta_pendente_bloqueia_novo_anexo_do_gestor(): void
    {
        [$carta] = $this->criarCartaComRespostaAguardandoVerificacao();
        $this->post(route('cartas.cartas.mensagens.store', $carta), ['arquivo' => $this->pdfFalsoValido()])->assertSessionHasErrors('arquivo');
        $this->assertDatabaseCount('carta_mensagens', 1);
    }
}
