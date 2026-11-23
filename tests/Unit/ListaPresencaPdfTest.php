<?php

namespace Tests\Unit;

use App\Models\Atividade;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use App\Pdf\ListaDePresenca\ListaPresencaEscutaTerritorialPdf;
use App\Pdf\ListaDePresenca\ListaPresencaFactory;
use App\Pdf\ListaDePresenca\Strategies\ListaPresencaAssessoriaStrategy;
use App\Pdf\ListaDePresenca\Strategies\ListaPresencaEscutaTerritorialStrategy;
use App\Pdf\ListaDePresenca\Strategies\ListaPresencaFeiraPedagogicaStrategy;
use App\Pdf\ListaDePresenca\Strategies\ListaPresencaOficinaStrategy;
use App\Pdf\ListaDePresenca\Strategies\ListaPresencaStrategyInterface;
use Exception;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use setasign\Fpdi\Fpdi;
use Tests\Concerns\InteractsWithPdf;
use Tests\TestCase;

class ListaPresencaPdfTest extends TestCase
{
    use InteractsWithPdf;

    #[DataProvider('modelos')]
    public function test_factory_seleciona_strategy_e_modelos_existentes_continuam_gerando_pdf(string $tipo, string $classe): void
    {
        $strategy = ListaPresencaFactory::criar($tipo);
        $this->assertInstanceOf($classe, $strategy);
        $conteudo = $strategy->gerarPdf($this->atividade(), $this->participantes(2));
        $this->assertStringStartsWith('%PDF-', $conteudo);
        $this->assertSame(1, $this->lerPdf($conteudo)->getPageCount());
    }

    public static function modelos(): array
    {
        return [
            ['assessoria', ListaPresencaAssessoriaStrategy::class],
            ['oficina', ListaPresencaOficinaStrategy::class],
            ['escuta_territorial', ListaPresencaEscutaTerritorialStrategy::class],
            ['feira_pedagogica', ListaPresencaFeiraPedagogicaStrategy::class],
        ];
    }

    public function test_strategy_de_escuta_implementa_diretamente_a_interface_comum(): void
    {
        $reflexao = new \ReflectionClass(ListaPresencaEscutaTerritorialStrategy::class);
        $this->assertTrue($reflexao->implementsInterface(ListaPresencaStrategyInterface::class));
        $this->assertFalse($reflexao->getParentClass());
    }

    public function test_pdf_de_escuta_herda_diretamente_do_fpdi(): void
    {
        $reflexao = new \ReflectionClass(ListaPresencaEscutaTerritorialPdf::class);
        $this->assertSame(Fpdi::class, $reflexao->getParentClass()->getName());
    }

    public function test_factory_rejeita_caminhos_e_modelos_desconhecidos(): void
    {
        $this->expectException(Exception::class);
        ListaPresencaFactory::criar('../../.env');
    }

    public function test_escuta_preenche_cabecalho_e_mesmas_colunas_da_assessoria(): void
    {
        $pdf = $this->lerPdf((new ListaPresencaEscutaTerritorialStrategy)->gerarPdf($this->atividade(), $this->participantes(1)));
        $texto = $this->textoPdf($pdf);

        foreach (['Nome do Participante', 'CPF', 'Município', 'E-mail', 'Assinatura',
            'Participante 01', '012.345.678-90', 'Belém - PA', 'p01@example.test',
            'Belém / PA', '08/11/2026', '08:30 às 12:00', 'Escuta Territorial'] as $valor) {
            $this->assertStringContainsString($valor, $texto);
        }
        $this->assertStringNotContainsString('EJA REDE MUNICIPAL', $texto);
        $this->assertStringNotContainsString('EJA MOVIMENTO SOCIAL', $texto);
    }

    #[DataProvider('quantidades')]
    public function test_escuta_pagina_sem_perder_ou_duplicar_participantes(int $quantidade, int $paginas): void
    {
        $pdf = $this->lerPdf((new ListaPresencaEscutaTerritorialStrategy)->gerarPdf($this->atividade(), $this->participantes($quantidade)));
        $this->assertSame($paginas, $pdf->getPageCount());
        $texto = $this->textoPdf($pdf);
        $this->assertSame($paginas, substr_count($texto, 'Nome do Participante'));
        for ($numero = 1; $numero <= $quantidade; $numero++) {
            $this->assertSame(1, substr_count($texto, sprintf('Participante %02d', $numero)));
        }
        for ($pagina = 1; $pagina <= $paginas; $pagina++) {
            [$largura, $altura] = $pdf->getPage($pagina)->getWidthAndHeight();
            $this->assertLessThan($altura, $largura);
            $this->assertEqualsWithDelta(596, $largura, 0.1);
            $this->assertEqualsWithDelta(842, $altura, 0.1);
        }
    }

    public static function quantidades(): array
    {
        return [[24, 1], [25, 2], [52, 2], [53, 3]];
    }

    public function test_lista_vazia_preserva_template_e_informa_ausencia_de_inscritos(): void
    {
        $pdf = $this->lerPdf((new ListaPresencaEscutaTerritorialStrategy)->gerarPdf($this->atividade(), collect()));
        $this->assertSame(1, $pdf->getPageCount());
        $this->assertStringContainsString('Nenhum participante inscrito neste momento.', $this->textoPdf($pdf));
    }

    public function test_abrangencia_nacional_exibe_brasil(): void
    {
        $atividade = $this->atividade();
        $atividade->abrangencia_nacional = true;
        $texto = $this->textoPdf($this->lerPdf((new ListaPresencaEscutaTerritorialStrategy)->gerarPdf($atividade, collect())));
        $this->assertStringContainsString('Brasil', $texto);
        $this->assertStringNotContainsString('Belém / PA', $texto);
    }

    public function test_dados_opcionais_ausentes_nao_impedem_geracao(): void
    {
        $atividade = $this->atividade();
        $atividade->setRelation('municipio', null);
        $participante = new Participante;
        $participante->setRelation('user', null);
        $participante->setRelation('municipio', null);

        $conteudo = (new ListaPresencaEscutaTerritorialStrategy)->gerarPdf($atividade, collect([$participante]));
        $this->assertStringStartsWith('%PDF-', $conteudo);
        $this->assertSame(1, $this->lerPdf($conteudo)->getPageCount());
    }

    public function test_template_ausente_falha_sem_expor_caminho_do_servidor(): void
    {
        $strategy = new class extends ListaPresencaEscutaTerritorialStrategy
        {
            protected function caminhoTemplate(): string
            {
                return storage_path('app/templates/template-que-nao-existe.pdf');
            }
        };
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('O template base em PDF não foi encontrado.');
        $strategy->gerarPdf($this->atividade(), collect());
    }

    private function atividade(): Atividade
    {
        $municipio = new Municipio(['nome' => 'Belém']);
        $municipio->setRelation('estado', new Estado(['sigla' => 'PA']));
        $atividade = new Atividade([
            'descricao' => 'Escuta Territorial', 'dia' => '2026-11-08',
            'hora_inicio' => '08:30:00', 'hora_fim' => '12:00:00',
        ]);
        $atividade->setRelation('municipio', $municipio);

        return $atividade;
    }

    private function participantes(int $quantidade): Collection
    {
        return collect(range(1, $quantidade))->map(function (int $numero): Participante {
            $participante = new Participante(['cpf' => '01234567890', 'tag' => 'Rede de ensino']);
            $participante->setRelation('user', new User([
                'name' => sprintf('Participante %02d', $numero), 'email' => sprintf('p%02d@example.test', $numero),
            ]));
            $participante->setRelation('municipio', $this->atividade()->municipio);

            return $participante;
        });
    }
}
