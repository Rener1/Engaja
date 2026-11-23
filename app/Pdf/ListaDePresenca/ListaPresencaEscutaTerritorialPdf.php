<?php

namespace App\Pdf\ListaDePresenca;

use setasign\Fpdi\Fpdi;

class ListaPresencaEscutaTerritorialPdf extends Fpdi
{
    protected $templatePage1;

    protected $templatePage2;

    public $municipioLabel;

    public $dataLabel;

    public $atividadeLabel;

    public $periodoLabel;

    public function setBaseTemplate($caminhoPdf)
    {
        $this->setSourceFile($caminhoPdf);
        $this->templatePage1 = $this->importPage(1);
        $this->templatePage2 = $this->importPage(2);
    }

    /** @return list<int> */
    public function largurasColunas(): array
    {
        return [6, 51, 24, 34, 48, 27];
    }

    public function Header()
    {
        $this->SetLineWidth(0.4);

        if ($this->PageNo() === 1) {
            $this->useTemplate($this->templatePage1, 0, 0, null, null, true);
            $this->SetFont('Helvetica', 'B', 11);

            $this->Text(30, 47.2, $this->ajustarTexto($this->municipioLabel, 90));
            $this->Text(137, 47.2, $this->ajustarTexto($this->dataLabel, 59));
            $this->Text(29, 55.1, $this->ajustarTexto($this->atividadeLabel, 90));
            $this->Text(143, 55.1, $this->ajustarTexto($this->periodoLabel, 54));

            $this->SetY(65);
        } else {
            $this->useTemplate($this->templatePage2, 0, 0, null, null, true);
            $this->SetY(32);
        }

        $this->desenharCabecalhoTabela();
    }

    protected function desenharCabecalhoTabela(): void
    {
        $this->SetFont('Helvetica', 'B', 9);
        $larguras = $this->largurasColunas();

        $this->Cell($larguras[0], 8, utf8_decode('Nº'), 1, 0, 'C');
        $this->Cell($larguras[1], 8, utf8_decode('Nome do Participante'), 1, 0, 'C');
        $this->Cell($larguras[2], 8, utf8_decode('CPF'), 1, 0, 'C');
        $this->Cell($larguras[3], 8, utf8_decode('Município'), 1, 0, 'C');
        $this->Cell($larguras[4], 8, utf8_decode('E-mail'), 1, 0, 'C');
        $this->Cell($larguras[5], 8, utf8_decode('Assinatura'), 1, 1, 'C');
    }

    private function ajustarTexto(?string $texto, float $largura): string
    {
        $texto = mb_convert_encoding($texto ?? '', 'ISO-8859-1', 'UTF-8');
        if ($this->GetStringWidth($texto) <= $largura) {
            return $texto;
        }

        while ($this->GetStringWidth($texto.'...') > $largura) {
            $texto = substr($texto, 0, -1);
        }

        return $texto.'...';
    }
}
