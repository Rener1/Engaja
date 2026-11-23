<?php

namespace Tests\Concerns;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;

trait InteractsWithPdf
{
    protected function lerPdf(string $conteudo): PdfReader
    {
        return new PdfReader(new PdfParser(StreamReader::createByString($conteudo)));
    }

    protected function textoPdf(PdfReader $pdf): string
    {
        $texto = '';
        for ($pagina = 1; $pagina <= $pdf->getPageCount(); $pagina++) {
            $texto .= $pdf->getPage($pagina)->getContentStream();
        }

        return mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }
}
