<?php

namespace App\Services\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaEvento;
use App\Models\Cartas\CartaMensagem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartaAprovacaoService
{
    public function aprovar(CartaMensagem $mensagem, User $verificador): bool
    {
        return DB::transaction(function () use ($mensagem, $verificador) {
            $carta = Carta::query()->lockForUpdate()->find($mensagem->carta_id);

            if (! $carta) {
                return false;
            }

            $atual = CartaMensagem::query()
                ->where('carta_id', $carta->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $carta->setRelation('ultimaMensagem', $atual);

            if ($atual?->id !== $mensagem->id || ! $carta->podeAprovarResposta()) {
                return false;
            }

            $atual->setRelation('carta', $carta);
            $before = [
                'mensagem_status' => $atual->status,
                'carta_status' => $carta->status,
            ];

            $atual->update([
                'status' => CartaMensagem::STATUS_APROVADA,
                'verificada_por' => $verificador->id,
                'verificada_em' => now(),
                'parecer_verificacao' => null,
                'atualizada_por' => $verificador->id,
            ]);

            $carta->update([
                'status' => Carta::STATUS_RESPONDIDA,
                'atualizada_por' => $verificador->id,
            ]);

            CartaEvento::create([
                'carta_id' => $carta->id,
                'carta_mensagem_id' => $atual->id,
                'user_id' => $verificador->id,
                'tipo' => CartaEvento::TIPO_MENSAGEM_VERIFICADA,
                'dados_antes' => $before,
                'dados_depois' => [
                    'mensagem_status' => CartaMensagem::STATUS_APROVADA,
                    'carta_status' => Carta::STATUS_RESPONDIDA,
                ],
            ]);

            return true;
        }, 3);
    }
}
