<?php

namespace App\Pdf\ListaDePresenca\Strategies;

use App\Models\Atividade;
use App\Pdf\ListaDePresenca\ListaPresencaFeiraPedagogicaPdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;

class ListaPresencaFeiraPedagogicaStrategy implements ListaPresencaStrategyInterface
{
    protected function caminhoTemplate(): string
    {
        return storage_path('app/templates/base_lista_feira_pedagogica.pdf');
    }

    protected function criarPdf(): ListaPresencaFeiraPedagogicaPdf
    {
        return new ListaPresencaFeiraPedagogicaPdf('L');
    }

    public function gerarPdf(Atividade $atividade, Collection $participantes): string
    {
        $templatePath = $this->caminhoTemplate();

        if (! file_exists($templatePath)) {
            throw new Exception('O template base em PDF não foi encontrado.');
        }

        $pdf = $this->criarPdf();
        $pdf->setBaseTemplate($templatePath);
        $larguras = $pdf->largurasColunas();

        // preenche o cabecalho do template
        $municipioAtividade = $atividade->municipio;
        $pdf->municipioLabel = $atividade->abrangencia_nacional
            ? 'Brasil'
            : ($municipioAtividade ? ($municipioAtividade->nome.' / '.($municipioAtividade->estado->sigla ?? '')) : '');
        $ini = Carbon::parse($atividade->hora_inicio)->format('H:i');
        $fim = Carbon::parse($atividade->hora_fim)->format('H:i');
        $pdf->periodoLabel = "{$ini} às {$fim}";
        $pdf->dataLabel = Carbon::parse($atividade->dia)->format('d/m/Y');
        $pdf->atividadeLabel = $atividade->descricao;

        // margens
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 30);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 7.5);

        $pdf->SetLineWidth(0.4);

        $contador = 1;

        if ($participantes->isEmpty()) {
            $pdf->Cell(array_sum($larguras), 8, utf8_decode('Nenhum participante inscrito neste momento.'), 1, 1, 'C');
        } else {
            foreach ($participantes as $participante) {
                $user = $participante->user;

                // nome
                $nome = utf8_decode($user->name ?? '');
                while ($pdf->GetStringWidth($nome) > $larguras[1] - 1) {
                    $nome = substr($nome, 0, -1);
                }

                // CPF
                $cpfSujo = $participante->cpf ?? '';
                $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfSujo);
                if (strlen($cpfLimpo) === 11) {
                    $cpfFormatado = preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", '$1.$2.$3-$4', $cpfLimpo);
                } else {
                    $cpfFormatado = $cpfSujo ?: '';
                }
                $cpf = utf8_decode($cpfFormatado);

                // municipio do participante
                $nomeMun = $participante->municipio?->nome;
                $siglaUf = $participante->municipio?->estado?->sigla;
                $textoLocal = $nomeMun ? ($nomeMun.' - '.$siglaUf) : '';
                $municipio = utf8_decode($textoLocal);
                while ($pdf->GetStringWidth($municipio) > $larguras[3] - 1) {
                    $municipio = substr($municipio, 0, -1);
                }

                $email = utf8_decode($user->email ?? '');
                while ($pdf->GetStringWidth($email) > $larguras[4] - 1) {
                    $email = substr($email, 0, -1);
                }

                $pdf->Cell($larguras[0], 8, $contador++, 1, 0, 'C');
                $pdf->Cell($larguras[1], 8, $nome, 1, 0, 'L');
                $pdf->Cell($larguras[2], 8, $cpf, 1, 0, 'C');
                $pdf->Cell($larguras[3], 8, $municipio, 1, 0, 'C');
                $pdf->Cell($larguras[4], 8, $email, 1, 0, 'C');
                $pdf->Cell($larguras[5], 8, '', 1, 1, 'C');
            }
        }

        return $pdf->Output('S');
    }
}
