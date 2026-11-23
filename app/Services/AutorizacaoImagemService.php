<?php

namespace App\Services;

use App\Enums\OrigemAutorizacaoImagem;
use App\Enums\SituacaoAutorizacaoImagem;
use App\Exceptions\AutorizacaoImagemConflitoException;
use App\Models\AutorizacaoImagem;
use App\Models\AutorizacaoImagemHistorico;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Presenca;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AutorizacaoImagemService
{
    public function participantesDaAcao(Evento $evento): Builder
    {
        return Participante::query()
            ->whereHas('user', fn (Builder $q) => $q->where('sistema_origem', User::SISTEMA_ENGAJA))
            ->where(function (Builder $q) use ($evento) {
                $q->whereHas('autorizacoesImagem', fn (Builder $a) => $a->where('evento_id', $evento->id))
                    ->orWhereHas('inscricoes', fn (Builder $inscricoes) => $inscricoes
                        ->where(fn (Builder $vinculo) => $vinculo->where('evento_id', $evento->id)
                            ->orWhereHas('atividade', fn (Builder $atividade) => $atividade->where('evento_id', $evento->id))))
                    ->orWhereHas('inscricoes.presencas', fn (Builder $presencas) => $presencas
                        ->where('status', 'presente')
                        ->whereHas('atividade', fn (Builder $atividade) => $atividade->where('evento_id', $evento->id)));
            });
    }

    public function temPresenca(Participante $participante, Evento $evento): bool
    {
        return Presenca::query()->where('status', 'presente')
            ->whereHas('inscricao', fn (Builder $q) => $q->where('participante_id', $participante->id))
            ->whereHas('atividade', fn (Builder $q) => $q->where('evento_id', $evento->id))->exists();
    }

    public function resposta(Participante $participante, Evento $evento): ?AutorizacaoImagem
    {
        return AutorizacaoImagem::where('participante_id', $participante->id)->where('evento_id', $evento->id)->first();
    }

    public function filtrarSituacao(Builder $query, Evento $evento, ?string $situacao): Builder
    {
        $estado = SituacaoAutorizacaoImagem::tryFrom($situacao ?? '');
        if ($estado === null) {
            return $query;
        }
        $resposta = fn (Builder $q) => $q->where('evento_id', $evento->id);
        if ($estado === SituacaoAutorizacaoImagem::Pendente) {
            return $query->whereDoesntHave('autorizacoesImagem', $resposta);
        }

        return $query->whereHas('autorizacoesImagem', fn (Builder $q) => $resposta($q)
            ->where('autorizado', $estado === SituacaoAutorizacaoImagem::Autorizada));
    }

    public function registrar(
        Participante $participante,
        Evento $evento,
        bool $autorizado,
        OrigemAutorizacaoImagem $origem,
        ?User $responsavel = null,
        ?Presenca $presenca = null,
        ?int $versaoEsperada = null,
        ?string $acaoNome = null,
    ): AutorizacaoImagem {
        abort_unless($evento->exists && ! $evento->trashed(), 404);
        $this->validarResponsavel($participante, $origem, $responsavel);
        abort_unless($this->participantesDaAcao($evento)->whereKey($participante->id)->exists(), 404);
        if (in_array($origem, [OrigemAutorizacaoImagem::PresencaPublica, OrigemAutorizacaoImagem::PresencaAutenticada], true)) {
            abort_unless($presenca && $presenca->status === 'presente'
                && ! $presenca->trashed()
                && $presenca->inscricao?->participante_id === $participante->id
                && $presenca->atividade?->evento_id === $evento->id, 403);
        }

        return DB::transaction(function () use ($participante, $evento, $autorizado, $origem, $responsavel, $presenca, $versaoEsperada, $acaoNome) {
            Participante::whereKey($participante->id)->lockForUpdate()->firstOrFail();
            $registro = $this->resposta($participante, $evento);
            if ($origem === OrigemAutorizacaoImagem::Perfil && ! $registro && ! $this->temPresenca($participante, $evento)) {
                abort(403);
            }

            if ($versaoEsperada === null && $registro) {
                if ($registro->autorizado === $autorizado) {
                    return $registro;
                }
                throw new AutorizacaoImagemConflitoException('Esta ação já possui uma resposta. Consulte suas autorizações para alterá-la explicitamente.');
            }
            if ($versaoEsperada !== null && ($registro?->versao ?? 0) !== $versaoEsperada) {
                throw new AutorizacaoImagemConflitoException('A autorização foi alterada em outro acesso. Atualize a página antes de tentar novamente.');
            }
            if ($registro && $registro->autorizado === $autorizado) {
                return $registro;
            }

            $anterior = $registro?->autorizado;
            $dados = $this->dadosRegistro($evento, $autorizado, $origem, $responsavel, $presenca, $acaoNome);
            if ($registro) {
                $registro->fill([...$dados, 'versao' => $registro->versao + 1])->save();
            } else {
                $registro = AutorizacaoImagem::create([
                    ...$dados, 'participante_id' => $participante->id, 'evento_id' => $evento->id, 'versao' => 1,
                ]);
            }
            AutorizacaoImagemHistorico::create([
                ...$dados, 'autorizacao_imagem_id' => $registro->id, 'autorizado_anterior' => $anterior,
            ]);

            return $registro;
        });
    }

    private function validarResponsavel(Participante $participante, OrigemAutorizacaoImagem $origem, ?User $responsavel): void
    {
        if ($origem === OrigemAutorizacaoImagem::PresencaPublica) {
            abort_unless($responsavel === null, 403);

            return;
        }
        abort_unless($responsavel?->isEngajaUser(), 403);
        if (in_array($origem, [OrigemAutorizacaoImagem::Perfil, OrigemAutorizacaoImagem::PresencaAutenticada], true)) {
            abort_unless($participante->user_id === $responsavel->id, 403);

            return;
        }
        abort_unless($responsavel->can('autorizacao-imagem.registrar'), 403);
    }

    private function dadosRegistro(Evento $evento, bool $autorizado, OrigemAutorizacaoImagem $origem, ?User $responsavel, ?Presenca $presenca, ?string $acaoNome = null): array
    {
        return [
            'autorizado' => $autorizado, 'respondido_em' => now(),
            'origem' => $origem->value, 'registrado_por_user_id' => $responsavel?->id,
            'presenca_origem_id' => $presenca?->id,
            'acao_nome' => $acaoNome ?? $evento->nome,
        ];
    }
}
