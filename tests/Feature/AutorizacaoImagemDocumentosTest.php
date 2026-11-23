<?php

namespace Tests\Feature;

use App\Exports\AutorizacoesImagemPorAcaoExport;
use App\Services\AutorizacaoImagemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\InteractsWithAutorizacaoImagem;
use Tests\TestCase;

class AutorizacaoImagemDocumentosTest extends TestCase
{
    use InteractsWithAutorizacaoImagem, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAutorizacoes();
        $this->vinculo($this->pessoa, presente: false);
    }

    public function test_planilha_distingue_pendente_de_recusa_sem_consultar_legado(): void
    {
        $this->pessoa->participante->update(['autorizacao_imagem' => true]);
        $negada = $this->pessoaComDados(['name' => '=HYPERLINK("https://example.invalid","nome")']);
        $this->vinculo($negada, presente: false);
        $this->respostaDaGestao($negada, false);
        $query = app(AutorizacaoImagemService::class)->participantesDaAcao($this->acao);
        $export = new AutorizacoesImagemPorAcaoExport($this->acao, $query);
        $this->assertNotContains('Termo', $export->headings());
        $this->assertNotContains('Referência documental', $export->headings());
        $arquivo = tempnam(sys_get_temp_dir(), 'auth-xlsx-');
        try {
            file_put_contents($arquivo, app(Excel::class)->raw($export, Excel::XLSX));
            $planilha = IOFactory::load($arquivo)->getActiveSheet();
            $this->assertSame('01234567890', $planilha->getCell('F2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $planilha->getCell('F2')->getDataType());
            $this->assertSame('Não respondida', $planilha->getCell('G2')->getValue());
            $this->assertSame('Não autorizada', $planilha->getCell('G3')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $planilha->getCell('D3')->getDataType());
            $this->assertSame($negada->name, $planilha->getCell('D3')->getValue());
        } finally {
            unlink($arquivo);
        }
    }

    public function test_exportacao_http_respeita_acao_e_permissao(): void
    {
        $this->actingAs($this->gestor)->get(route('autorizacoes-imagem.export', ['evento_id' => $this->acao->id]))->assertOk()->assertDownload('autorizacoes-acao-'.$this->acao->id.'.xlsx');
        $this->actingAs($this->pessoa)->get(route('autorizacoes-imagem.export', ['evento_id' => $this->acao->id]))->assertForbidden();
        $this->actingAs($this->gestor)->get(route('autorizacoes-imagem.export'))->assertSessionHasErrors('evento_id');
    }
}
