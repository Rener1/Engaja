<?php

return [
    /*
    | Origem dos links enviados pelo Cartas (sem o prefixo /cartas).
    | Obrigatoria para notificacoes com links; HTTPS em producao.
    */
    'url' => env('CARTAS_URL'),

    /*
    |--------------------------------------------------------------------------
    | Papel timbrado (Cartas para Esperançar)
    |--------------------------------------------------------------------------
    |
    | Modelo PDF (A5, 148x210mm) sobre o qual o texto digitado pelo voluntário
    | é sobreposto para gerar o documento final. As medidas (em mm) posicionam
    | o texto sobre as linhas pontilhadas do modelo — ajuste caso o modelo mude.
    |
    */

    'timbrado' => [
        'path' => public_path('images/cartas/PAEB_CartasparaEsperançar_PapeldeCarta.pdf'),

        // Área de escrita, em milímetros. Também é a caixa útil usada para
        // centralizar o conteúdo de cartas enviadas como anexo em PDF (ver
        // CartaTimbradoService::renderUpload()) — não só para texto digitado.
        'margin_left' => 13.0,
        'margin_right' => 13.0,
        // topo do texto da primeira linha — logo do cabeçalho termina em
        // ~33,2mm (medido), por isso a folga extra até aqui.
        'start_top' => 36.0,
        'line_height' => 5.0,  // passo exato das linhas pontilhadas do modelo (medido)
        // reserva o rodapé; a faixa roxa começa em ~259,8mm (medida), então
        // a última linha de largura cheia termina em 297-40=257mm, com folga.
        'bottom_margin' => 40.0,

        // Fonte manuscrita (Kalam) embutida via FPDF makefont (cp1252).
        'font_family' => 'Kalam',
        'font_dir' => resource_path('fonts/cartas'),
        'font_file' => 'Kalam-Regular.php',
        'font_size' => 13.0,

        // Ilustração (bonequinho com estrela) no canto inferior direito do
        // modelo: nessa faixa vertical o texto para antes de alcançá-la em
        // vez de usar a largura cheia. Coordenadas medidas sobre o PDF do
        // modelo — ajuste caso a arte mude de posição/tamanho.
        'obstaculo' => [
            'top' => 220.0,
            'bottom' => 253.0,
            'right_edge' => 164.0,
        ],
    ],
];
