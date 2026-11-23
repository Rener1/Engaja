<?php

namespace App\Services\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class RelatorioCartasService
{
    private const POR_PAGINA = 15;

    public function resumo(): array
    {
        $voluntarios = $this->voluntarios();
        $quantidade = (clone $voluntarios)->count();
        $capacidade = (int) (clone $voluntarios)->sum('cartas_limite_respostas');
        $atribuidas = Carta::query()
            ->whereIn('voluntario_user_id', (clone $voluntarios)->select('users.id'))
            ->count();

        $trocas = $this->iniciadasPeloEducando(Carta::query());
        $totalTrocas = (clone $trocas)->count();
        $respondidas = (clone $trocas)->whereHas('mensagens', $this->respostasEnviadas(...))->count();

        $vinculos = (clone $voluntarios)
            ->selectRaw('cartas_tipo_vinculo, COUNT(*) as total')
            ->groupBy('cartas_tipo_vinculo')
            ->pluck('total', 'cartas_tipo_vinculo');
        $labels = array_values(User::VINCULOS_CARTAS);
        $values = array_map(fn (string $vinculo): int => (int) ($vinculos[$vinculo] ?? 0), array_keys(User::VINCULOS_CARTAS));
        $semVinculo = $quantidade - array_sum($values);

        if ($semVinculo > 0) {
            $labels[] = 'Não informado';
            $values[] = $semVinculo;
        }

        return [
            'metricas' => [
                'voluntarios' => $quantidade,
                'capacidade' => $capacidade,
                'atribuidas' => $atribuidas,
                'saldo' => $capacidade - $atribuidas,
            ],
            'graficos' => [
                'respostas' => [
                    'labels' => ['Com resposta', 'Sem resposta'],
                    'values' => [$respondidas, $totalTrocas - $respondidas],
                    'total' => $totalTrocas,
                ],
                'vinculos' => [
                    'labels' => $labels,
                    'values' => $values,
                    'total' => $quantidade,
                ],
            ],
        ];
    }

    public function voluntariosComPendencias(int $pagina = 1): LengthAwarePaginator
    {
        return $this->voluntarios()
            ->select(['users.id', 'users.name', 'users.email'])
            ->whereHas('cartasComoVoluntario', $this->semPrimeiraResposta(...))
            ->withCount(['cartasComoVoluntario as cartas_pendentes_count' => $this->semPrimeiraResposta(...)])
            ->orderByDesc('cartas_pendentes_count')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::POR_PAGINA, ['*'], 'pendentes_page', $pagina)
            ->fragment('voluntarios-pendentes');
    }

    public function voluntariosSemCartas(int $pagina = 1): LengthAwarePaginator
    {
        return $this->voluntarios()
            ->select(['users.id', 'users.name', 'users.email'])
            ->whereDoesntHave('cartasComoVoluntario')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::POR_PAGINA, ['*'], 'sem_cartas_page', $pagina)
            ->fragment('voluntarios-sem-cartas');
    }

    private function voluntarios(): Builder
    {
        return User::query()
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->role('cartas_voluntario');
    }

    private function iniciadasPeloEducando(Builder $query): Builder
    {
        return $query->whereHas('mensagens', fn (Builder $mensagens): Builder => $this->mensagensEnviadas($mensagens)
            ->where('rodada', 1)
            ->where('tipo_remetente', CartaMensagem::TIPO_REMETENTE_EDUCANDO));
    }

    private function semPrimeiraResposta(Builder $query): Builder
    {
        return $this->iniciadasPeloEducando($query)
            ->whereDoesntHave('mensagens', $this->respostasEnviadas(...));
    }

    private function respostasEnviadas(Builder $query): Builder
    {
        // A segunda mensagem enviada já é a primeira resposta, mesmo antes da aprovação.
        return $this->mensagensEnviadas($query)
            ->where('tipo_remetente', CartaMensagem::TIPO_REMETENTE_VOLUNTARIO)
            ->where('rodada', '>', 1);
    }

    private function mensagensEnviadas(Builder $query): Builder
    {
        return $query->whereNotNull('enviada_em')
            ->whereNotIn('status', [CartaMensagem::STATUS_RASCUNHO, CartaMensagem::STATUS_CANCELADA]);
    }
}
