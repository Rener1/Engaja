<?php

namespace App\Models\Concerns;

use LogicException;

trait RegistroImutavel
{
    protected static function bootRegistroImutavel(): void
    {
        static::updating(fn () => throw new LogicException('Este registro é imutável.'));
        static::deleting(fn () => throw new LogicException('Este registro é imutável.'));
    }
}
