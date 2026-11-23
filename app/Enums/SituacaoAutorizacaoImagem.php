<?php

namespace App\Enums;

use App\Models\AutorizacaoImagem;

enum SituacaoAutorizacaoImagem: string
{
    case Pendente = 'pendente';
    case Autorizada = 'autorizada';
    case Negada = 'negada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Não respondida',
            self::Autorizada => 'Autorizada',
            self::Negada => 'Não autorizada',
        };
    }

    public static function daResposta(?AutorizacaoImagem $resposta): self
    {
        return $resposta === null ? self::Pendente : ($resposta->autorizado ? self::Autorizada : self::Negada);
    }
}
