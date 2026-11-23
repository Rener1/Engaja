<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use App\Models\Estado;
use App\Models\Evento;
use App\Models\Municipio;
use App\Models\Regiao;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use ZipArchive;

class CartaDownloadBatchTest extends CartasBaseTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Evento::where('id', '!=', $this->eventoCartas->id)->update(['is_cartas' => false]);
    }

    public function test_gestor_dashboard_without_pending_responses_disables_approval_selection(): void
    {
        $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.dashboard'));

        $response->assertOk();
        $response->assertSee('0 respostas selecionadas nesta página');
        $response->assertSee('Esta troca não possui resposta aguardando aprovação');
    }

    public function test_gestor_dashboard_with_municipio_filter_starts_without_approval_selection(): void
    {
        $regiao = Regiao::firstOrCreate(['nome' => 'Norte']);
        $estado = Estado::factory()->create(['regiao_id' => $regiao->id]);
        $municipioTarget = Municipio::factory()->create(['nome' => 'Santarém', 'estado_id' => $estado->id]);

        $this->educando->update(['municipio_id' => $municipioTarget->id]);
        $this->educando->refresh();

        $this->criarCartaParaVoluntario();

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.dashboard', ['municipio_id' => $municipioTarget->id]));

        $response->assertOk();
        $response->assertSee('0 respostas selecionadas nesta página');
        $response->assertSee('Baixar cartas PDF');
        $response->assertSee('(todas as páginas)');
    }

    public function test_gestor_dashboard_with_status_filter_filters_correctly(): void
    {
        $cartaEnviada = $this->criarCartaParaVoluntario();
        $cartaEnviada->update(['codigo' => 'CARTA_ENV_111']);

        $cartaRespondida = $this->criarCartaParaVoluntario();
        $cartaRespondida->update(['status' => Carta::STATUS_RESPONDIDA, 'codigo' => 'CARTA_RESP_999']);

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.dashboard', ['status' => 'respondida']));

        $response->assertOk();
        $response->assertSee('CARTA_RESP_999');
        $response->assertDontSee('CARTA_ENV_111');
        $response->assertSee('0 respostas selecionadas nesta página');
    }

    public function test_gestor_dashboard_combined_filters(): void
    {
        $regiao = Regiao::firstOrCreate(['nome' => 'Nordeste I']);
        $estado = Estado::factory()->create(['regiao_id' => $regiao->id]);
        $municipioTarget = Municipio::factory()->create(['nome' => 'Fortaleza', 'estado_id' => $estado->id]);

        $this->educando->update(['municipio_id' => $municipioTarget->id]);
        $this->educando->refresh();

        $cartaTarget = $this->criarCartaParaVoluntario();
        $cartaTarget->update(['status' => Carta::STATUS_RESPONDIDA, 'codigo' => 'CARTA_MATCH_777']);

        $cartaOutra = $this->criarCartaParaVoluntario();
        $cartaOutra->update(['codigo' => 'CARTA_OTHER_333']);

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.dashboard', [
                'municipio_id' => $municipioTarget->id,
                'status' => 'respondida',
                'q' => 'CARTA_MATCH_777',
            ]));

        $response->assertOk();
        $response->assertSee('CARTA_MATCH_777');
        $response->assertDontSee('CARTA_OTHER_333');
        $response->assertSee('0 respostas selecionadas nesta página');
    }

    public function test_download_batch_downloads_only_latest_sent_volunteer_message_per_carta(): void
    {
        $carta = $this->criarCartaParaVoluntario();
        $this->criarRespostaComArquivo($carta, 2, ['status' => CartaMensagem::STATUS_APROVADA]);
        $this->criarRespostaComArquivo($carta, 4, ['arquivo_final_path' => 'cartas/resposta-final.pdf']);
        Storage::disk('local')->put('cartas/resposta-final.pdf', 'Ultima resposta timbrada');

        Storage::disk('local')->put('cartas/educando.pdf', 'Nova mensagem do educando');
        CartaMensagem::factory()->create([
            'carta_id' => $carta->id,
            'rodada' => 5,
            'remetente_participante_id' => $this->educando->id,
            'destinatario_user_id' => $this->voluntario->id,
            'anexo_original_path' => 'cartas/educando.pdf',
        ]);
        $this->criarRespostaComArquivo($carta, 6, [
            'status' => CartaMensagem::STATUS_RASCUNHO,
            'enviada_em' => null,
        ]);
        $this->criarRespostaComArquivo($carta, 7)->delete();

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.download-batch'));

        $this->assertSame(['Ultima resposta timbrada'], $this->conteudosDoZip($response));
    }

    #[DataProvider('managementRoles')]
    public function test_download_batch_includes_all_filtered_pages_and_ignores_selected_ids(string $role): void
    {
        $this->gestor->syncRoles([$role]);
        $this->remetente->update(['name' => 'Ana Remetente']);
        $this->voluntario->update(['name' => 'Carlos Voluntario']);

        $cartas = [];
        $conteudosEsperados = [];
        for ($i = 0; $i < 17; $i++) {
            $carta = $this->criarCartaParaVoluntario();
            $mensagem = $this->criarRespostaComArquivo($carta);
            $cartas[] = $carta;
            $conteudosEsperados[] = Storage::disk('local')->get($mensagem->anexo_original_path);
        }

        $outroRemetente = User::factory()->create(['name' => 'Bruno Remetente', 'sistema_origem' => User::SISTEMA_ENGAJA]);
        $outroRemetente->participante->update(['municipio_id' => $this->educando->municipio_id]);
        $foraDoNome = $this->criarCartaParaVoluntario();
        $foraDoNome->update(['educando_participante_id' => $outroRemetente->participante->id]);
        $this->criarRespostaComArquivo($foraDoNome);

        $outroMunicipio = Municipio::factory()->create();
        $remetenteOutraCidade = User::factory()->create(['name' => 'Ana Outra Cidade', 'sistema_origem' => User::SISTEMA_ENGAJA]);
        $remetenteOutraCidade->participante->update(['municipio_id' => $outroMunicipio->id]);
        $foraDoMunicipio = $this->criarCartaParaVoluntario();
        $foraDoMunicipio->update(['educando_participante_id' => $remetenteOutraCidade->participante->id]);
        $this->criarRespostaComArquivo($foraDoMunicipio);

        $foraDoStatus = $this->criarCartaParaVoluntario();
        $this->criarRespostaComArquivo($foraDoStatus, 2, ['status' => CartaMensagem::STATUS_APROVADA]);

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.download-batch', [
                'q' => 'Ana',
                'municipio_id' => $this->educando->municipio_id,
                'status' => 'pendente',
                'page' => 2,
                'carta_ids' => [$cartas[0]->id, $foraDoNome->id],
            ]));

        $this->assertEqualsCanonicalizing($conteudosEsperados, $this->conteudosDoZip($response));
    }

    public static function managementRoles(): array
    {
        return [['cartas_gestao'], ['cartas_admin']];
    }

    public function test_download_batch_without_filters_includes_all_volunteer_responses(): void
    {
        $conteudosEsperados = [];
        foreach ([CartaMensagem::STATUS_APROVADA, CartaMensagem::STATUS_AJUSTE_SOLICITADO] as $status) {
            $carta = $this->criarCartaParaVoluntario();
            $mensagem = $this->criarRespostaComArquivo($carta, 2, ['status' => $status]);
            $conteudosEsperados[] = Storage::disk('local')->get($mensagem->anexo_original_path);
        }

        $semResposta = $this->criarCartaParaVoluntario();
        $semResposta->mensagens()->first()->update(['anexo_original_path' => 'cartas/educando.pdf']);
        Storage::disk('local')->put('cartas/educando.pdf', 'Carta sem resposta do voluntario');

        $response = $this->actingAs($this->gestor)
            ->get(route('cartas.download-batch'));

        $this->assertEqualsCanonicalizing($conteudosEsperados, $this->conteudosDoZip($response));
    }

    public function test_download_batch_does_not_replace_latest_response_without_file_with_older_response(): void
    {
        $carta = $this->criarCartaParaVoluntario();
        $this->criarRespostaComArquivo($carta);
        $ultima = $this->criarRespostaComArquivo($carta, 4);
        Storage::disk('local')->delete($ultima->anexo_original_path);

        $response = $this->actingAs($this->gestor)
            ->from(route('cartas.dashboard'))
            ->get(route('cartas.download-batch'));

        $response->assertRedirect(route('cartas.dashboard'));
        $response->assertSessionHas('status', 'Nenhuma carta com anexo/PDF foi encontrada para gerar o lote.');
    }

    private function criarRespostaComArquivo(Carta $carta, int $rodada = 2, array $attributes = []): CartaMensagem
    {
        $path = "cartas/{$carta->id}/resposta-{$rodada}.pdf";
        Storage::disk('local')->put($path, "Resposta {$carta->id} rodada {$rodada}");

        return CartaMensagem::factory()->doVoluntario()->aguardandoVerificacao()->create(array_merge([
            'carta_id' => $carta->id,
            'rodada' => $rodada,
            'remetente_user_id' => $this->voluntario->id,
            'destinatario_participante_id' => $carta->educando_participante_id,
            'anexo_original_path' => $path,
            'anexo_original_nome' => "resposta-{$rodada}.pdf",
            'anexo_original_mime' => 'application/pdf',
        ], $attributes));
    }

    private function conteudosDoZip(TestResponse $response): array
    {
        $response->assertOk();
        $response->assertDownload('Cartas-Lote.zip');
        $this->assertSame('application/zip', $response->headers->get('content-type'));

        $zipPath = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));

        try {
            $conteudos = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $conteudos[] = $zip->getFromIndex($i);
            }

            return $conteudos;
        } finally {
            $zip->close();
            unlink($zipPath);
        }
    }
}
