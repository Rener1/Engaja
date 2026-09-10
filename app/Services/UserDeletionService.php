<?php

namespace App\Services;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use App\Models\Certificado;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\Presenca;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserDeletionService
{
    /**
     * Soft delete the user and their participant-owned records atomically.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $participante = $user->participante()->withTrashed()->first();

            $this->deleteLetterData($user, $participante);

            if ($participante) {
                $this->deleteParticipantData($participante);
            }

            $user->delete();
        });
    }

    private function deleteParticipantData(Participante $participante): void
    {
        $inscricaoIds = $participante->inscricoes()
            ->pluck('id');

        if ($inscricaoIds->isNotEmpty()) {
            Presenca::query()
                ->whereIn('inscricao_id', $inscricaoIds)
                ->delete();

            Inscricao::query()
                ->whereIn('id', $inscricaoIds)
                ->delete();
        }

        Certificado::query()
            ->where('participante_id', $participante->getKey())
            ->delete();

        $participante->delete();
    }

    private function deleteLetterData(User $user, ?Participante $participante): void
    {
        $cartaIds = Carta::query()
            ->where(function ($query) use ($user, $participante): void {
                $query->where('voluntario_user_id', $user->getKey());

                if ($participante) {
                    $query->orWhere('educando_participante_id', $participante->getKey());
                }
            })
            ->pluck('id');

        if ($cartaIds->isEmpty()) {
            return;
        }

        CartaMensagem::query()
            ->whereIn('carta_id', $cartaIds)
            ->delete();

        Carta::query()
            ->whereIn('id', $cartaIds)
            ->delete();
    }
}
