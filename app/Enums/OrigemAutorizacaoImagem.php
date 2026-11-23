<?php

namespace App\Enums;

enum OrigemAutorizacaoImagem: string
{
    case PresencaPublica = 'presenca_publica';
    case PresencaAutenticada = 'presenca_autenticada';
    case Perfil = 'perfil';
    case Gestao = 'gestao';

    public function rotulo(): string
    {
        return match ($this) {
            self::PresencaPublica => 'Identificação por dado cadastral',
            self::PresencaAutenticada => 'Confirmação autenticada',
            self::Perfil => 'Perfil do participante',
            self::Gestao => 'Registro pela gestão',
        };
    }
}
