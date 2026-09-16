<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

final class CartasUrl
{
    /**
     * Gera links comuns do Cartas sem alterar a origem global das URLs.
     * Links assinados devem continuar usando o gerador de assinaturas do Laravel.
     */
    public static function route(string $name, mixed $parameters = []): string
    {
        if (! str_starts_with($name, 'cartas.')) {
            throw new InvalidArgumentException('CartasUrl aceita apenas rotas com o prefixo cartas.');
        }

        $origin = config('cartas.url');
        $parts = is_string($origin) ? parse_url($origin) : false;

        if (! is_string($origin)
            || filter_var($origin, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new RuntimeException(
                'Configure CARTAS_URL com uma origem HTTP(S) valida, sem caminho, credenciais, query ou fragmento.'
            );
        }

        if (app()->isProduction() && $parts['scheme'] !== 'https') {
            throw new RuntimeException('CARTAS_URL deve usar HTTPS em producao.');
        }

        return rtrim($origin, '/').route($name, $parameters, absolute: false);
    }
}
