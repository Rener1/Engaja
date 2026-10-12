<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Municipio;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PresencaGerenciamentoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('presenca.abrir');

        $agora = now();
        $eventoId = $request->integer('evento_id');
        $municipioId = $request->integer('municipio_id');
        $statusFiltro = $request->get('status'); // 'aberta', 'fechada', 'agendada'
        $de = $request->date('de');
        $ate = $request->date('ate');
        $q = trim((string) $request->get('q', ''));

        $query = Atividade::query()
            ->with([
                'evento:id,nome',
                'municipio.estado:id,nome,sigla',
                'municipios.estado:id,nome,sigla',
            ])
            ->withCount([
                'presencas as presentes_count' => fn ($rel) => $rel->where('status', 'presente'),
                'inscricoes as inscritos_count' => fn ($rel) => $rel->whereNull('deleted_at'),
            ])
            ->whereNull('atividades.deleted_at')
            ->whereNotNull('atividades.evento_id')
            ->whereHas('evento', fn ($ev) => $ev->whereNull('deleted_at'));

        // Filtro por Ação pedagógica
        $query->when($eventoId, fn ($builder) => $builder->where('evento_id', $eventoId));

        // Filtro por Município
        $query->when($municipioId, function ($builder) use ($municipioId) {
            $builder->where(function ($sub) use ($municipioId) {
                $sub->where('municipio_id', $municipioId)
                    ->orWhereHas('municipios', fn ($m) => $m->where('municipios.id', $municipioId));
            });
        });

        // Filtro por período
        $query->when($de && $ate, fn ($b) => $b->whereBetween('dia', [$de, $ate]));
        $query->when($de && ! $ate, fn ($b) => $b->where('dia', '>=', $de));
        $query->when(! $de && $ate, fn ($b) => $b->where('dia', '<=', $ate));

        // Filtro por texto
        $query->when($q !== '', function ($builder) use ($q) {
            $like = '%'.$q.'%';
            $builder->where(function ($sub) use ($like) {
                $sub->where('descricao', 'like', $like)
                    ->orWhereHas('evento', fn ($ev) => $ev->where('nome', 'like', $like));
            });
        });

        // Filtro por Status da Presença
        if ($statusFiltro === 'agendada') {
            $query->where(function ($b) use ($agora) {
                $b->where(function ($sub) use ($agora) {
                    $sub->whereNotNull('presenca_abre_em')->where('presenca_abre_em', '>', $agora);
                })->orWhere(function ($sub) use ($agora) {
                    $sub->whereNotNull('presenca_fecha_em')->where('presenca_fecha_em', '>', $agora);
                });
            });
        } elseif ($statusFiltro === 'aberta') {
            // Aberta: presenca_fecha_em > agora ou nulo E (presenca_abre_em <= agora OU presenca_ativa = true)
            $query->where(function ($b) use ($agora) {
                $b->where(function ($sub) use ($agora) {
                    $sub->whereNull('presenca_fecha_em')
                        ->orWhere('presenca_fecha_em', '>', $agora);
                })->where(function ($sub) use ($agora) {
                    $sub->where(function ($sub2) use ($agora) {
                        $sub2->whereNotNull('presenca_abre_em')
                            ->where('presenca_abre_em', '<=', $agora);
                    })->orWhere(function ($sub2) use ($agora) {
                        $sub2->where('presenca_ativa', true)
                            ->where(function ($sub3) use ($agora) {
                                $sub3->whereNull('presenca_abre_em')
                                    ->orWhere('presenca_abre_em', '<=', $agora);
                            });
                    });
                });
            });
        } elseif ($statusFiltro === 'fechada') {
            // Fechada: presenca_fecha_em <= agora OU ((presenca_abre_em nulo ou > agora) E presenca_ativa = false)
            $query->where(function ($b) use ($agora) {
                $b->where(function ($sub) use ($agora) {
                    $sub->whereNotNull('presenca_fecha_em')
                        ->where('presenca_fecha_em', '<=', $agora);
                })->orWhere(function ($sub) use ($agora) {
                    $sub->where('presenca_ativa', false)
                        ->where(function ($sub2) use ($agora) {
                            $sub2->whereNull('presenca_abre_em')
                                ->orWhere('presenca_abre_em', '>', $agora);
                        });
                });
            });
        }

        $query->orderByDesc('dia')->orderByDesc('hora_inicio')->orderByDesc('id');

        $atividades = $query->paginate(25)->appends($request->query());

        $totalAtividades = Atividade::query()->whereNotNull('evento_id')->count();
        $comAgendamentoFuturo = Atividade::query()
            ->whereNotNull('evento_id')
            ->where(function ($b) use ($agora) {
                $b->where(fn ($sub) => $sub->whereNotNull('presenca_abre_em')->where('presenca_abre_em', '>', $agora))
                    ->orWhere(fn ($sub) => $sub->whereNotNull('presenca_fecha_em')->where('presenca_fecha_em', '>', $agora));
            })
            ->count();

        // Lista de eventos e municípios para os filtros
        $eventos = Evento::query()->orderBy('nome')->pluck('nome', 'id');
        $municipios = Municipio::query()->with('estado:id,sigla')->orderBy('nome')->get(['id', 'nome', 'estado_id']);

        return view('presencas.gerenciamento', compact(
            'atividades',
            'eventos',
            'municipios',
            'totalAtividades',
            'comAgendamentoFuturo'
        ));
    }
}
