<?php

namespace App\Policies;

use App\Models\User;

class AutorizacaoImagemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEngajaUser() && $user->can('autorizacao-imagem.ver');
    }

    public function create(User $user): bool
    {
        return $user->isEngajaUser() && $user->can('autorizacao-imagem.registrar');
    }

    public function export(User $user): bool
    {
        return $user->isEngajaUser() && $user->can('autorizacao-imagem.exportar');
    }
}
