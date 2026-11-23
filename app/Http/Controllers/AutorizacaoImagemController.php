<?php

namespace App\Http\Controllers;

use App\Enums\OrigemAutorizacaoImagem;
use App\Enums\SituacaoAutorizacaoImagem;
use App\Exceptions\AutorizacaoImagemConflitoException;
use App\Exports\AutorizacoesImagemPorAcaoExport;
use App\Http\Requests\ResponderAutorizacaoImagemRequest;
use App\Models\AutorizacaoImagem;
use App\Models\Evento;
use App\Models\Participante;
use App\Services\AutorizacaoImagemContexto;
use App\Services\AutorizacaoImagemService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AutorizacaoImagemController extends Controller
{
    public function __construct(
        private readonly AutorizacaoImagemService $autorizacoes,
        private readonly AutorizacaoImagemContexto $contextos,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', AutorizacaoImagem::class);
        $dados = $this->filtros($request);
        $eventos = Evento::orderBy('nome')->get(['id', 'nome']);
        $evento = isset($dados['evento_id']) ? Evento::findOrFail($dados['evento_id']) : null;
        $participantes = null;
        $contextos = [];
        $totais = [];
        if ($evento) {
            $base = $this->autorizacoes->participantesDaAcao($evento);
            foreach (SituacaoAutorizacaoImagem::cases() as $situacao) {
                $totais[$situacao->value] = $this->autorizacoes->filtrarSituacao(clone $base, $evento, $situacao->value)->count();
            }
            $participantes = $this->consulta($evento, $dados)
                ->with(['user', 'autorizacoesImagem' => fn ($q) => $q->where('evento_id', $evento->id)
                    ->with(['historico.registradoPor'])])
                ->orderBy('id')->paginate(20)->withQueryString();
            if (Gate::allows('create', AutorizacaoImagem::class)) {
                foreach ($participantes as $participante) {
                    $contextos[$participante->id] = $this->contextos->paraFormulario($participante, $evento, $request->session(), OrigemAutorizacaoImagem::Gestao, $request->user());
                }
            }
        }

        return view('autorizacoes-imagem.index', compact('eventos', 'evento', 'participantes', 'contextos', 'totais'));
    }

    public function registrar(ResponderAutorizacaoImagemRequest $request, Evento $evento, Participante $participante)
    {
        Gate::authorize('create', AutorizacaoImagem::class);

        return $this->salvar($request, $evento, $participante, OrigemAutorizacaoImagem::Gestao);
    }

    public function minhas(Request $request)
    {
        abort_unless($request->user()->isEngajaUser(), 403);
        $participante = $request->user()->participante;
        $eventos = Evento::query()->where(function (Builder $q) use ($participante) {
            $q->whereHas('autorizacoesImagem', fn ($a) => $a->where('participante_id', $participante?->id ?? 0))
                ->orWhereHas('atividades.presencas', fn ($p) => $p->where('status', 'presente')
                    ->whereHas('inscricao', fn ($i) => $i->where('participante_id', $participante?->id ?? 0)));
        })->with(['autorizacoesImagem' => fn ($q) => $q->where('participante_id', $participante?->id ?? 0)
            ->with('historico')])->orderByDesc('id')->paginate(20);
        $contextos = [];
        foreach ($eventos as $evento) {
            $contextos[$evento->id] = $this->contextos->paraFormulario($participante, $evento, $request->session(), OrigemAutorizacaoImagem::Perfil, $request->user());
        }

        return view('autorizacoes-imagem.minhas', compact('participante', 'eventos', 'contextos'));
    }

    public function salvarMinha(ResponderAutorizacaoImagemRequest $request, Evento $evento)
    {
        $participante = $request->user()->participante;
        abort_unless($participante, 404);

        return $this->salvar($request, $evento, $participante, OrigemAutorizacaoImagem::Perfil);
    }

    public function exportar(Request $request)
    {
        Gate::authorize('export', AutorizacaoImagem::class);
        $dados = $this->filtros($request, true);
        $evento = Evento::findOrFail($dados['evento_id']);

        return Excel::download(new AutorizacoesImagemPorAcaoExport($evento, $this->consulta($evento, $dados)), 'autorizacoes-acao-'.$evento->id.'.xlsx');
    }

    private function salvar(ResponderAutorizacaoImagemRequest $request, Evento $evento, Participante $participante, OrigemAutorizacaoImagem $origem)
    {
        try {
            $this->contextos->responder($request->input('contexto'), $request->boolean('autorizado'), $request->session(), $origem, $request->user(),
                evento: $evento, participante: $participante);
        } catch (AutorizacaoImagemConflitoException $e) {
            return back()->withErrors(['autorizacao_imagem' => $e->getMessage()]);
        }

        return back()->with('success', 'Resposta sobre o uso de imagem registrada para esta ação.');
    }

    private function filtros(Request $request, bool $obrigatorio = false): array
    {
        return $request->validate([
            'evento_id' => [$obrigatorio ? 'required' : 'nullable', 'integer', Rule::exists('eventos', 'id')->whereNull('deleted_at')],
            'situacao' => ['nullable', Rule::in(array_column(SituacaoAutorizacaoImagem::cases(), 'value'))],
            'busca' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function consulta(Evento $evento, array $dados): Builder
    {
        $query = $this->autorizacoes->filtrarSituacao($this->autorizacoes->participantesDaAcao($evento), $evento, $dados['situacao'] ?? null);
        if (filled($dados['busca'] ?? null)) {
            $busca = '%'.$dados['busca'].'%';
            $query->where(fn ($q) => $q->where('cpf', 'ilike', $busca)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $busca)->orWhere('email', 'ilike', $busca)));
        }

        return $query;
    }
}
