<?php

$qrCodeFormat = strtolower(trim((string) env('QR_CODE_FORMAT', 'png')));

return [
    /*
    |--------------------------------------------------------------------------
    | Formato do QR Code
    |--------------------------------------------------------------------------
    |
    | Define o formato de saída do QR Code na confirmação de presença.
    | Formatos admitidos para esta tela: "png" (padrão; exige Imagick e GD para
    | compor o logotipo) e "svg" (no pacote instalado, a fusão do logotipo via
    | merge() ocorre apenas para PNG). Valores inesperados são normalizados
    | para "png".
    |
    */

    'format' => in_array($qrCodeFormat, ['png', 'svg'], true) ? $qrCodeFormat : 'png',
];
