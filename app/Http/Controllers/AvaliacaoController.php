<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Avaliacao;
use App\Models\AvaliacaoQuestao;
use App\Models\Escala;
use App\Models\Evidencia;
use App\Models\Inscricao;
use App\Models\Participante;
use App\Models\Presenca;
use App\Models\Questao;
use App\Models\RespostaAvaliacao;
use App\Models\SubmissaoAvaliacao;
use App\Models\TemplateAvaliacao;
use App\Models\User;
use App\Services\AvaliacaoRespostasDashboardService;
use App\Support\CartasUrl;
use App\ViewModels\Avaliacao\QuestoesFormViewModel;
use App\Word\AvaliacaoFichaWordBuilder;
use App\Word\AvaliacaoResultadosWordBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class AvaliacaoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $avaliacaoTable = (new Avaliacao)->getTable();

        $query = Avaliacao::query()->with([
            'inscricao.participante.user',
            'inscricao.evento',
            'atividade.evento',
            'templateAvaliacao',
            'respostas.submissaoAvaliacao',
        ])->whereNotNull('atividade_id');

        $searchTerm = trim((string) $request->query('search', ''));
        if ($searchTerm !== '') {
            $query->where(function ($nested) use ($searchTerm) {
                $nested->whereHas('atividade', function ($atividade) use ($searchTerm) {
                    $atividade->where('descricao', 'like', '%'.$searchTerm.'%')
                        ->orWhereHas('evento', function ($evento) use ($searchTerm) {
                            $evento->where('nome', 'like', '%'.$searchTerm.'%');
                        });
                })
                    ->orWhereHas('templateAvaliacao', function ($template) use ($searchTerm) {
                        $template->where('nome', 'like', '%'.$searchTerm.'%');
                    })
                    ->orWhereHas('inscricao.participante.user', function ($usuario) use ($searchTerm) {
                        $usuario->where('name', 'like', '%'.$searchTerm.'%');
                    })
                    ->orWhereHas('inscricao.evento', function ($evento) use ($searchTerm) {
                        $evento->where('nome', 'like', '%'.$searchTerm.'%');
                    })
                    ->orWhere('descricao_universal', 'like', '%'.$searchTerm.'%');

                if (ctype_digit($searchTerm)) {
                    $nested->orWhere('id', (int) $searchTerm);
                }
            });
        }

        $templateId = $request->query('template_id');
        if ($templateId) {
            $query->where('template_avaliacao_id', $templateId);
        }

        $from = $request->query('de');
        if ($from) {
            $query->whereDate("{$avaliacaoTable}.created_at", '>=', $from);
        }

        $to = $request->query('ate');
        if ($to) {
            $query->whereDate("{$avaliacaoTable}.created_at", '<=', $to);
        }

        $hasRespostas = $request->query('has_respostas');
        if ($hasRespostas === 'with') {
            $query->whereHas('respostas');
        } elseif ($hasRespostas === 'without') {
            $query->whereDoesntHave('respostas');
        }

        $sort = $request->query('sort', 'created_at');
        $directionParam = $request->query('dir', $request->query('direction', 'desc'));
        $direction = Str::lower((string) $directionParam) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'momento') {
            $query->orderBy(
                Atividade::select('dia')
                    ->whereColumn('atividades.id', "{$avaliacaoTable}.atividade_id"),
                $direction
            )->orderBy(
                Atividade::select('hora_inicio')
                    ->whereColumn('atividades.id', "{$avaliacaoTable}.atividade_id"),
                $direction
            );
        } elseif ($sort === 'template') {
            $query->orderBy(
                TemplateAvaliacao::select('nome')
                    ->whereColumn('template_avaliacaos.id', "{$avaliacaoTable}.template_avaliacao_id"),
                $direction
            );
        } elseif ($sort === 'created_at') {
            $query->orderBy("{$avaliacaoTable}.created_at", $direction);
        } else {
            $query->orderBy("{$avaliacaoTable}.created_at", 'desc');
        }

        if ($sort !== 'created_at') {
            $query->orderBy("{$avaliacaoTable}.created_at", 'desc');
        }

        $avaliacoes = $query->paginate(15)->appends($request->query());
        $templatesDisponiveis = TemplateAvaliacao::orderBy('nome')->pluck('nome', 'id');

        return view('avaliacoes.index', compact('avaliacoes', 'templatesDisponiveis'));
    }

    public function universaisIndex(Request $request)
    {
        $avaliacaoTable = (new Avaliacao)->getTable();

        $query = Avaliacao::query()
            ->with(['templateAvaliacao', 'respostas.submissaoAvaliacao'])
            ->whereNull('atividade_id');

        $searchTerm = trim((string) $request->query('search', ''));
        if ($searchTerm !== '') {
            $query->where(function ($nested) use ($searchTerm) {
                $nested->whereHas('templateAvaliacao', function ($template) use ($searchTerm) {
                    $template->where('nome', 'like', '%'.$searchTerm.'%');
                })
                    ->orWhere('descricao_universal', 'like', '%'.$searchTerm.'%');

                if (ctype_digit($searchTerm)) {
                    $nested->orWhere('id', (int) $searchTerm);
                }
            });
        }

        $templateId = $request->query('template_id');
        if ($templateId) {
            $query->where('template_avaliacao_id', $templateId);
        }

        $from = $request->query('de');
        if ($from) {
            $query->whereDate("{$avaliacaoTable}.created_at", '>=', $from);
        }

        $to = $request->query('ate');
        if ($to) {
            $query->whereDate("{$avaliacaoTable}.created_at", '<=', $to);
        }

        $sort = $request->query('sort', 'created_at');
        $directionParam = $request->query('dir', $request->query('direction', 'desc'));
        $direction = Str::lower((string) $directionParam) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'template') {
            $query->orderBy(
                TemplateAvaliacao::select('nome')
                    ->whereColumn('template_avaliacaos.id', "{$avaliacaoTable}.template_avaliacao_id"),
                $direction
            );
        } else {
            $query->orderBy("{$avaliacaoTable}.created_at", $direction);
        }

        if ($sort !== 'created_at') {
            $query->orderBy("{$avaliacaoTable}.created_at", 'desc');
        }

        $avaliacoes = $query->paginate(15)->appends($request->query());
        $templatesDisponiveis = TemplateAvaliacao::orderBy('nome')->pluck('nome', 'id');
        $linksFormulario = $avaliacoes->getCollection()->mapWithKeys(fn (Avaliacao $avaliacao) => [
            $avaliacao->id => $this->formularioPublicoUrl($avaliacao),
        ]);

        return view('avaliacoes.universais.index', compact('avaliacoes', 'templatesDisponiveis', 'linksFormulario'));
    }

    public function create(Request $request)
    {
        $atividades = Atividade::with('evento')
            ->orderByDesc('created_at')
            ->get();

        $templates = TemplateAvaliacao::with(['questoes.escala', 'questoes.indicador.dimensao', 'questoes.evidencia.indicador'])
            ->orderBy('nome')
            ->get();

        $evidencias = Evidencia::with('indicador.dimensao')
            ->orderBy('descricao')
            ->get();

        $escalas = Escala::orderBy('descricao')->get();

        $tiposQuestao = $this->tiposQuestao();

        $selectedTemplateId = $request->old('template_avaliacao_id', $templates->first()->id ?? null);
        $oldInput = $request->old();
        if (! is_array($oldInput)) {
            $oldInput = [];
        }

        $questoesAdicionaisInput = $request->old('questoes_adicionais', []);
        if (! is_array($questoesAdicionaisInput)) {
            $questoesAdicionaisInput = [];
        }

        $evidenciasOptions = $this->buildEvidenciasOptions($evidencias);

        $escalasOptions = $escalas->pluck('descricao', 'id')->toArray();

        $questoesForm = QuestoesFormViewModel::make(
            $templates,
            $selectedTemplateId !== null ? (string) $selectedTemplateId : null,
            [],
            $questoesAdicionaisInput,
            $tiposQuestao,
            $evidenciasOptions,
            $evidencias->keyBy('id'),
            $escalasOptions,
            $escalas->keyBy('id'),
            [],
            false,
            $this->validationErrors($request),
            $oldInput
        )->toArray();

        return view('avaliacoes.create', [
            'atividades' => $atividades,
            'templates' => $templates,
            'selectedTemplateId' => $selectedTemplateId,
            'questoesForm' => $questoesForm,
        ]);
    }

    public function universaisCreate(Request $request)
    {
        $templates = TemplateAvaliacao::with(['questoes.escala', 'questoes.indicador.dimensao', 'questoes.evidencia.indicador'])
            ->orderBy('nome')
            ->get();

        $evidencias = Evidencia::with('indicador.dimensao')
            ->orderBy('descricao')
            ->get();

        $escalas = Escala::orderBy('descricao')->get();
        $selectedTemplateId = $request->old('template_avaliacao_id', $templates->first()->id ?? null);
        $oldInput = $request->old();
        if (! is_array($oldInput)) {
            $oldInput = [];
        }

        $questoesAdicionaisInput = $request->old('questoes_adicionais', []);
        if (! is_array($questoesAdicionaisInput)) {
            $questoesAdicionaisInput = [];
        }

        $questoesForm = QuestoesFormViewModel::make(
            $templates,
            $selectedTemplateId !== null ? (string) $selectedTemplateId : null,
            [],
            $questoesAdicionaisInput,
            $this->tiposQuestao(),
            $this->buildEvidenciasOptions($evidencias),
            $evidencias->keyBy('id'),
            $escalas->pluck('descricao', 'id')->toArray(),
            $escalas->keyBy('id'),
            [],
            false,
            $this->validationErrors($request),
            $oldInput
        )->toArray();

        return view('avaliacoes.create', [
            'atividades' => collect(),
            'templates' => $templates,
            'selectedTemplateId' => $selectedTemplateId,
            'questoesForm' => $questoesForm,
            'universal' => true,
            'formAction' => route('avaliacoes-universais.store'),
            'cancelUrl' => route('avaliacoes-universais.index'),
        ]);
    }

    public function store(Request $request)
    {
        $dados = $this->validateAvaliacao($request);

        $template = TemplateAvaliacao::with([
            'questoes.indicador',
            'questoes.escala',
            'questoes.evidencia',
        ])
            ->findOrFail($dados['template_avaliacao_id']);

        $customizacoes = $this->validarQuestoesPersonalizadas($request, $template->questoes);
        [$questoesAdicionais, $questoesAdicionaisRemovidas] = $this->processaQuestoesAdicionais($request);

        $duplicadaQuery = Avaliacao::where('atividade_id', $dados['atividade_id']);

        if ($dados['inscricao_id'] !== null) {
            $duplicadaQuery->where('inscricao_id', $dados['inscricao_id']);
        } else {
            $duplicadaQuery->whereNull('inscricao_id');
        }

        $duplicada = $duplicadaQuery->exists();

        if ($duplicada) {
            return back()
                ->withInput()
                ->withErrors(['atividade_id' => 'Ja existe uma avaliacao para esta inscricao nesta atividade.']);
        }

        DB::transaction(function () use ($dados, $template, $customizacoes, $questoesAdicionais) {
            $avaliacao = Avaliacao::create($dados);

            $this->sincronizaQuestoesPersonalizadas(
                $avaliacao,
                $template->questoes,
                $customizacoes,
                true
            );

            $this->sincronizaQuestoesAdicionais($avaliacao, $questoesAdicionais);
        });

        return redirect()
            ->route('avaliacoes.index')
            ->with('success', 'Avaliação registrada com sucesso!');
    }

    public function universaisStore(Request $request)
    {
        $dados = $this->validateAvaliacaoUniversal($request, creating: true);

        $template = TemplateAvaliacao::with([
            'questoes.indicador',
            'questoes.escala',
            'questoes.evidencia',
        ])->findOrFail($dados['template_avaliacao_id']);

        $customizacoes = $this->validarQuestoesPersonalizadas($request, $template->questoes);
        [$questoesAdicionais] = $this->processaQuestoesAdicionais($request);

        DB::transaction(function () use ($dados, $template, $customizacoes, $questoesAdicionais) {
            $avaliacao = Avaliacao::create($dados);

            $this->sincronizaQuestoesPersonalizadas(
                $avaliacao,
                $template->questoes,
                $customizacoes,
                true
            );

            $this->sincronizaQuestoesAdicionais($avaliacao, $questoesAdicionais);
        });

        return redirect()
            ->route('avaliacoes-universais.index')
            ->with('success', 'Avaliação universal registrada com sucesso!');
    }

    public function show(Avaliacao $avaliacao)
    {
        $avaliacao->load([
            'atividade.evento',
            'templateAvaliacao',
            'avaliacaoQuestoes.indicador.dimensao',
            'avaliacaoQuestoes.evidencia',
            'avaliacaoQuestoes.escala',
        ]);

        $isUniversal = $avaliacao->atividade_id === null;
        $isTranscricao = $avaliacao->transcricao;

        return view('avaliacoes._form', [
            'avaliacao' => $avaliacao,
            'atividade' => $avaliacao->atividade,
            'tiposQuestao' => $this->tiposQuestao(),
            'inscricaoRespondente' => null,
            'token' => '',
            'respostasExistentes' => collect(),
            'jaRespondeu' => false,
            'isUniversal' => $isUniversal,
            'isTranscricao' => $isTranscricao,
            'formularioFechado' => false,
            'somenteVisualizacao' => true,
        ]);
    }

    public function universaisShow(Avaliacao $avaliacao)
    {
        abort_unless($avaliacao->atividade_id === null, 404);

        return $this->show($avaliacao);
    }

    public function edit(Request $request, Avaliacao $avaliacao)
    {
        $avaliacao->load([
            'templateAvaliacao',
            'avaliacaoQuestoes.indicador.dimensao',
            'avaliacaoQuestoes.evidencia',
            'avaliacaoQuestoes.escala',
            'inscricao.participante.user',
            'inscricao.evento',
            'atividade.evento',
        ]);

        $atividades = Atividade::with('evento')
            ->orderByDesc('created_at')
            ->get();

        $templates = TemplateAvaliacao::with(['questoes.escala', 'questoes.indicador.dimensao', 'questoes.evidencia.indicador'])
            ->orderBy('nome')
            ->get();

        $evidencias = Evidencia::with('indicador.dimensao')
            ->orderBy('descricao')
            ->get();

        $escalas = Escala::orderBy('descricao')->get();

        $personalizacoes = $avaliacao->avaliacaoQuestoes
            ->mapWithKeys(fn ($questao) => [
                $questao->questao_id ?? $questao->id => [
                    'texto' => $questao->texto,
                    'tipo' => $questao->tipo,
                    'opcoes_resposta' => $questao->opcoes_resposta ?? [],
                    'evidencia_id' => $questao->evidencia_id,
                    'escala_id' => $questao->escala_id,
                ],
            ])
            ->all();

        $questoesAdicionais = $avaliacao->avaliacaoQuestoes
            ->whereNull('questao_id')
            ->map(fn ($questao) => [
                'id' => $questao->id,
                'texto' => $questao->texto,
                'tipo' => $questao->tipo,
                'opcoes_resposta' => $questao->opcoes_resposta ?? [],
                'evidencia_id' => $questao->evidencia_id,
                'escala_id' => $questao->escala_id,
                'ordem' => $questao->ordem,
            ])
            ->values()
            ->all();

        $tiposQuestao = $this->tiposQuestao();

        $selectedTemplateId = $request->old('template_avaliacao_id', $avaliacao->template_avaliacao_id);
        $oldInput = $request->old();
        if (! is_array($oldInput)) {
            $oldInput = [];
        }

        $questoesAdicionaisInput = $request->old('questoes_adicionais', $questoesAdicionais);
        if (! is_array($questoesAdicionaisInput)) {
            $questoesAdicionaisInput = $questoesAdicionais;
        }

        $evidenciasOptions = $this->buildEvidenciasOptions($evidencias);

        $escalasOptions = $escalas->pluck('descricao', 'id')->toArray();

        $questoesForm = QuestoesFormViewModel::make(
            $templates,
            $selectedTemplateId !== null ? (string) $selectedTemplateId : null,
            $personalizacoes,
            $questoesAdicionaisInput,
            $tiposQuestao,
            $evidenciasOptions,
            $evidencias->keyBy('id'),
            $escalasOptions,
            $escalas->keyBy('id'),
            [],
            false,
            $this->validationErrors($request),
            $oldInput
        )->toArray();

        return view('avaliacoes.edit', [
            'avaliacao' => $avaliacao,
            'atividades' => $atividades,
            'templates' => $templates,
            'templateSelecionado' => $avaliacao->templateAvaliacao,
            'selectedTemplateId' => $selectedTemplateId,
            'questoesForm' => $questoesForm,
            'bloquearEstrutura' => $avaliacao->respostas()->exists(),
        ]);
    }

    public function universaisEdit(Request $request, Avaliacao $avaliacao)
    {
        abort_unless($avaliacao->atividade_id === null, 404);

        $avaliacao->load([
            'templateAvaliacao',
            'avaliacaoQuestoes.indicador.dimensao',
            'avaliacaoQuestoes.evidencia',
            'avaliacaoQuestoes.escala',
        ]);

        $templates = TemplateAvaliacao::with(['questoes.escala', 'questoes.indicador.dimensao', 'questoes.evidencia.indicador'])
            ->orderBy('nome')
            ->get();

        $evidencias = Evidencia::with('indicador.dimensao')
            ->orderBy('descricao')
            ->get();

        $escalas = Escala::orderBy('descricao')->get();

        $personalizacoes = $avaliacao->avaliacaoQuestoes
            ->mapWithKeys(fn ($questao) => [
                $questao->questao_id ?? $questao->id => [
                    'texto' => $questao->texto,
                    'tipo' => $questao->tipo,
                    'opcoes_resposta' => $questao->opcoes_resposta ?? [],
                    'evidencia_id' => $questao->evidencia_id,
                    'escala_id' => $questao->escala_id,
                ],
            ])
            ->all();

        $questoesAdicionais = $avaliacao->avaliacaoQuestoes
            ->whereNull('questao_id')
            ->map(fn ($questao) => [
                'id' => $questao->id,
                'texto' => $questao->texto,
                'tipo' => $questao->tipo,
                'opcoes_resposta' => $questao->opcoes_resposta ?? [],
                'evidencia_id' => $questao->evidencia_id,
                'escala_id' => $questao->escala_id,
                'ordem' => $questao->ordem,
            ])
            ->values()
            ->all();

        $selectedTemplateId = $request->old('template_avaliacao_id', $avaliacao->template_avaliacao_id);
        $oldInput = $request->old();
        if (! is_array($oldInput)) {
            $oldInput = [];
        }

        $questoesAdicionaisInput = $request->old('questoes_adicionais', $questoesAdicionais);
        if (! is_array($questoesAdicionaisInput)) {
            $questoesAdicionaisInput = $questoesAdicionais;
        }

        $questoesForm = QuestoesFormViewModel::make(
            $templates,
            $selectedTemplateId !== null ? (string) $selectedTemplateId : null,
            $personalizacoes,
            $questoesAdicionaisInput,
            $this->tiposQuestao(),
            $this->buildEvidenciasOptions($evidencias),
            $evidencias->keyBy('id'),
            $escalas->pluck('descricao', 'id')->toArray(),
            $escalas->keyBy('id'),
            [],
            false,
            $this->validationErrors($request),
            $oldInput
        )->toArray();

        return view('avaliacoes.edit', [
            'avaliacao' => $avaliacao,
            'atividades' => collect(),
            'templates' => $templates,
            'templateSelecionado' => $avaliacao->templateAvaliacao,
            'selectedTemplateId' => $selectedTemplateId,
            'questoesForm' => $questoesForm,
            'universal' => true,
            'formAction' => route('avaliacoes-universais.update', $avaliacao),
            'cancelUrl' => route('avaliacoes-universais.index'),
            'showUrl' => route('avaliacoes-universais.show', $avaliacao),
            'bloquearEstrutura' => $avaliacao->respostas()->exists(),
        ]);
    }

    public function update(Request $request, Avaliacao $avaliacao)
    {
        $dados = $this->validateAvaliacao($request, $avaliacao->id);

        // Anonimato não pode ser alterado após criação. Mantém valor original
        $dados['anonima'] = $avaliacao->getRawOriginal('anonima');

        $template = TemplateAvaliacao::with([
            'questoes.indicador',
            'questoes.escala',
            'questoes.evidencia',
        ])
            ->findOrFail($dados['template_avaliacao_id']);

        $customizacoes = $this->validarQuestoesPersonalizadas($request, $template->questoes);
        $respostas = $request->input('respostas');
        [$questoesAdicionais, $questoesAdicionaisRemovidas] = $this->processaQuestoesAdicionais($request, $avaliacao);

        $duplicadaQuery = Avaliacao::where('atividade_id', $dados['atividade_id'])
            ->where('id', '<>', $avaliacao->id);

        if ($dados['inscricao_id'] !== null) {
            $duplicadaQuery->where('inscricao_id', $dados['inscricao_id']);
        } else {
            $duplicadaQuery->whereNull('inscricao_id');
        }

        $duplicada = $duplicadaQuery->exists();

        if ($duplicada) {
            return back()
                ->withInput()
                ->withErrors(['atividade_id' => 'Ja existe outra avaliacao para esta inscricao nesta atividade.']);
        }

        $jaTemRespostas = $avaliacao->respostas()->exists();

        $templateAlterado = (int) $avaliacao->template_avaliacao_id !== (int) $dados['template_avaliacao_id'];
        $tentouAlterarQuestoes = $templateAlterado
            || ! empty($customizacoes)
            || ($questoesAdicionais && $questoesAdicionais->isNotEmpty())
            || ! empty($questoesAdicionaisRemovidas)
            || (is_array($respostas) && count($respostas) > 0);

        if ($jaTemRespostas && $tentouAlterarQuestoes) {
            return back()
                ->withInput()
                ->withErrors(['template_avaliacao_id' => 'Esta avaliação já possui respostas. Não é possível alterar modelo ou questões.']);
        }

        DB::transaction(function () use ($avaliacao, $dados, $template, $customizacoes, $respostas, $questoesAdicionais, $questoesAdicionaisRemovidas, $jaTemRespostas, $templateAlterado) {
            // Se possui respostas, apenas campos básicos são atualizados.
            $mudouQuestoesOuRespostas = ! $jaTemRespostas && (
                $templateAlterado
                || ! empty($customizacoes)
                || (is_array($respostas) && count($respostas) > 0)
                || ($questoesAdicionais && $questoesAdicionais->isNotEmpty())
                || ! empty($questoesAdicionaisRemovidas)
            );

            $avaliacao->update($dados);
            $avaliacao->refresh();

            if ($mudouQuestoesOuRespostas) {
                $questoesSincronizadas = $this->sincronizaQuestoesPersonalizadas(
                    $avaliacao,
                    $template->questoes,
                    $customizacoes,
                    $templateAlterado
                );

                $this->sincronizaQuestoesAdicionais($avaliacao, $questoesAdicionais, $questoesAdicionaisRemovidas);

                if (is_array($respostas)) {
                    $this->sincronizaRespostas($avaliacao, $respostas, $questoesSincronizadas);
                }
            }
        });

        return redirect()
            ->route('avaliacoes.index')
            ->with('success', 'Avaliacao atualizada com sucesso!');
    }

    public function universaisUpdate(Request $request, Avaliacao $avaliacao)
    {
        abort_unless($avaliacao->atividade_id === null, 404);

        $dados = $this->validateAvaliacaoUniversal($request);
        $template = TemplateAvaliacao::with([
            'questoes.indicador',
            'questoes.escala',
            'questoes.evidencia',
        ])->findOrFail($dados['template_avaliacao_id']);

        $customizacoes = $this->validarQuestoesPersonalizadas($request, $template->questoes);
        $respostas = $request->input('respostas');
        [$questoesAdicionais, $questoesAdicionaisRemovidas] = $this->processaQuestoesAdicionais($request, $avaliacao);

        $jaTemRespostas = $avaliacao->respostas()->exists();
        $templateAlterado = (int) $avaliacao->template_avaliacao_id !== (int) $dados['template_avaliacao_id'];

        if ($jaTemRespostas) {
            if ($templateAlterado) {
                return back()
                    ->withInput()
                    ->withErrors(['template_avaliacao_id' => 'Esta avaliação já possui respostas. Não é possível alterar modelo ou questões.']);
            }

            $avaliacao->update([
                'descricao_universal' => $dados['descricao_universal'] ?? null,
            ]);

            return redirect()
                ->route('avaliacoes-universais.index')
                ->with('success', 'Descrição da avaliação universal atualizada com sucesso!');
        }

        $tentouAlterarQuestoes = $templateAlterado
            || ! empty($customizacoes)
            || ($questoesAdicionais && $questoesAdicionais->isNotEmpty())
            || ! empty($questoesAdicionaisRemovidas)
            || (is_array($respostas) && count($respostas) > 0);

        if ($jaTemRespostas && $tentouAlterarQuestoes) {
            return back()
                ->withInput()
                ->withErrors(['template_avaliacao_id' => 'Esta avaliação já possui respostas. Não é possível alterar modelo ou questões.']);
        }

        DB::transaction(function () use ($avaliacao, $dados, $template, $customizacoes, $respostas, $questoesAdicionais, $questoesAdicionaisRemovidas, $jaTemRespostas, $templateAlterado) {
            $mudouQuestoesOuRespostas = ! $jaTemRespostas && (
                $templateAlterado
                || ! empty($customizacoes)
                || (is_array($respostas) && count($respostas) > 0)
                || ($questoesAdicionais && $questoesAdicionais->isNotEmpty())
                || ! empty($questoesAdicionaisRemovidas)
            );

            $avaliacao->update($dados);
            $avaliacao->refresh();

            if ($mudouQuestoesOuRespostas) {
                $questoesSincronizadas = $this->sincronizaQuestoesPersonalizadas(
                    $avaliacao,
                    $template->questoes,
                    $customizacoes,
                    $templateAlterado
                );

                $this->sincronizaQuestoesAdicionais($avaliacao, $questoesAdicionais, $questoesAdicionaisRemovidas);

                if (is_array($respostas)) {
                    $this->sincronizaRespostas($avaliacao, $respostas, $questoesSincronizadas);
                }
            }
        });

        return redirect()
            ->route('avaliacoes-universais.index')
            ->with('success', 'Avaliação universal atualizada com sucesso!');
    }

    public function destroy(Avaliacao $avaliacao)
    {
        $avaliacao->delete();

        return redirect()
            ->route('avaliacoes.index')
            ->with('success', 'Avaliacao removida com sucesso!');
    }

    public function universaisDestroy(Avaliacao $avaliacao)
    {
        abort_unless($avaliacao->atividade_id === null, 404);

        $avaliacao->delete();

        return redirect()
            ->route('avaliacoes-universais.index')
            ->with('success', 'Avaliação universal removida com sucesso!');
    }

    public function universaisLinkQrCode(Avaliacao $avaliacao)
    {
        abort_unless($avaliacao->atividade_id === null, 404);

        $link = $this->formularioPublicoUrl($avaliacao);

        return view('avaliacoes.universais.link-qrcode', compact('avaliacao', 'link'));
    }

    public function universaisToggleFormulario(Avaliacao $avaliacao)
    {
        abort_unless($avaliacao->atividade_id === null, 404);

        $avaliacao->update([
            'formulario_aberto' => ! $avaliacao->formulario_aberto,
        ]);

        $status = $avaliacao->formulario_aberto ? 'aberto' : 'fechado';

        return back()->with('success', "Formulário {$status} para respostas.");
    }

    private function sincronizaRespostas(
        Avaliacao $avaliacao,
        array $respostas,
        ?Collection $questoesAtualizadas = null
    ): void {
        $questoes = $questoesAtualizadas ?? $avaliacao->avaliacaoQuestoes()->get();
        $inscricaoParaResposta = $avaliacao->inscricao_id;

        foreach ($questoes as $questao) {
            $chaveResposta = $questao->questao_id ?? $questao->id;
            $valor = $respostas[$chaveResposta] ?? null;

            if ($valor === null || $valor === '') {
                continue;
            }

            $jaExiste = RespostaAvaliacao::where('avaliacao_id', $avaliacao->id)
                ->where('avaliacao_questao_id', $questao->id)
                ->where('inscricao_id', $inscricaoParaResposta)
                ->exists();

            if (! $jaExiste) {
                $avaliacao->respostas()->create([
                    'avaliacao_questao_id' => $questao->id,
                    'inscricao_id' => $inscricaoParaResposta,
                    'resposta' => is_array($valor) ? json_encode($valor) : $valor,
                ]);
            }
        }
    }

    /**
     * @return array{0: Collection, 1: array<int>}
     */
    private function processaQuestoesAdicionais(Request $request, ?Avaliacao $avaliacao = null): array
    {
        $questoesInput = collect($request->input('questoes_adicionais', []));

        if ($questoesInput->isEmpty()) {
            return [collect(), []];
        }

        $questoesAtivas = $questoesInput
            ->filter(fn ($questao) => empty($questao['_delete']))
            ->values();

        $idsRemovidos = $questoesInput
            ->filter(fn ($questao) => ! empty($questao['_delete']) && ! empty($questao['id']))
            ->pluck('id')
            ->all();

        $tipos = array_keys($this->tiposQuestao());

        $questoesValidadas = $questoesAtivas->map(function ($questao, int $index) use ($avaliacao, $tipos) {
            $validator = Validator::make(
                $questao,
                [
                    'id' => $avaliacao
                        ? ['nullable', 'integer', Rule::exists('avaliacao_questoes', 'id')
                            ->where('avaliacao_id', $avaliacao->id)
                            ->whereNull('questao_id')]
                        : ['prohibited'],
                    'texto' => ['required', 'string', 'max:1000'],
                    'tipo' => ['required', 'string', Rule::in($tipos)],
                    'opcoes_resposta' => ['nullable', 'array'],
                    'opcoes_resposta.*' => ['nullable', 'string', 'max:255'],
                    'evidencia_id' => ['nullable', 'integer', Rule::exists('evidencias', 'id')],
                    'escala_id' => ['nullable', 'integer', Rule::exists('escalas', 'id')],
                    'ordem' => ['nullable', 'integer', 'min:1', 'max:999'],
                ],
                [],
                [
                    'id' => "questoes_adicionais.$index.id",
                    'texto' => "questoes_adicionais.$index.texto",
                    'tipo' => "questoes_adicionais.$index.tipo",
                    'opcoes_resposta' => "questoes_adicionais.$index.opcoes_resposta",
                    'evidencia_id' => "questoes_adicionais.$index.evidencia_id",
                    'escala_id' => "questoes_adicionais.$index.escala_id",
                    'ordem' => "questoes_adicionais.$index.ordem",
                ]
            );

            $validator->after(function ($validator) use ($questao, $index) {
                if (($questao['tipo'] ?? null) === 'escala' && empty($questao['escala_id'])) {
                    $validator->errors()->add("questoes_adicionais.$index.escala_id", 'Selecione uma escala quando o tipo da questão for Escala.');
                }

                if (in_array(($questao['tipo'] ?? null), ['unica', 'multipla']) && empty($this->normalizaOpcoesResposta($questao['opcoes_resposta'] ?? []))) {
                    $validator->errors()->add("questoes_adicionais.$index.opcoes_resposta", 'Informe pelo menos uma opção para questões do tipo "Resposta única".');
                }
            });

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $dados = $validator->validated();
            $dados['texto'] = trim($dados['texto']);

            if (array_key_exists('ordem', $dados) && $dados['ordem'] !== null) {
                $dados['ordem'] = (int) $dados['ordem'];
            }

            if ($avaliacao === null) {
                unset($dados['id']);
            }

            if (($dados['tipo'] ?? null) !== 'escala') {
                $dados['escala_id'] = null;
            }

            $dados['opcoes_resposta'] = in_array(($dados['tipo'] ?? null), ['unica', 'multipla'])
                ? $this->normalizaOpcoesResposta($dados['opcoes_resposta'] ?? [])
                : null;

            $dados['ordem'] = $dados['ordem'] ?? null;

            return $dados;
        });

        return [$questoesValidadas, $idsRemovidos];
    }

    private function sincronizaQuestoesAdicionais(
        Avaliacao $avaliacao,
        Collection $questoes,
        array $removidas = []
    ): void {
        if (! empty($removidas)) {
            $avaliacao->avaliacaoQuestoes()
                ->whereNull('questao_id')
                ->whereIn('id', $removidas)
                ->delete();
        }

        $existentes = $avaliacao->avaliacaoQuestoes()
            ->whereNull('questao_id')
            ->get()
            ->keyBy('id');

        if ($questoes->isEmpty()) {
            return;
        }

        $evidenciaIds = $questoes->pluck('evidencia_id')->filter()->unique();
        $evidencias = Evidencia::select('id', 'indicador_id')
            ->whereIn('id', $evidenciaIds)
            ->get()
            ->keyBy('id');

        $idsMantidos = [];

        foreach ($questoes as $dados) {
            $questaoId = $dados['id'] ?? null;
            unset($dados['id']);

            $indicadorId = null;
            if (! empty($dados['evidencia_id']) && $evidencias->has($dados['evidencia_id'])) {
                $indicadorId = $evidencias[$dados['evidencia_id']]->indicador_id;
            }

            $payload = [
                'questao_id' => null,
                'indicador_id' => $indicadorId,
                'escala_id' => $dados['escala_id'] ?? null,
                'evidencia_id' => $dados['evidencia_id'] ?? null,
                'texto' => $dados['texto'],
                'tipo' => $dados['tipo'],
                'opcoes_resposta' => $dados['opcoes_resposta'] ?? null,
                'ordem' => $dados['ordem'] ?? null,
                'fixa' => false,
            ];

            if ($questaoId && $existentes->has($questaoId)) {
                $existentes[$questaoId]->update($payload);
                $idsMantidos[] = $questaoId;
            } else {
                $novo = $avaliacao->avaliacaoQuestoes()->create($payload);
                $idsMantidos[] = $novo->id;
            }
        }

        if (! empty($idsMantidos)) {
            $avaliacao->avaliacaoQuestoes()
                ->whereNull('questao_id')
                ->whereNotIn('id', $idsMantidos)
                ->delete();
        }
    }

    private function validateAvaliacao(Request $request, ?int $avaliacaoId = null): array
    {
        $anonRule = $avaliacaoId ? ['prohibited'] : ['sometimes', 'boolean'];

        $dados = $request->validate([
            'inscricao_id' => ['nullable', Rule::exists('inscricaos', 'id')],
            'atividade_id' => ['required', Rule::exists('atividades', 'id')],
            'template_avaliacao_id' => ['required', Rule::exists('template_avaliacaos', 'id')],
            'descricao_universal' => ['nullable', 'string', 'max:255'],
            'respostas' => ['nullable', 'array'],
            'anonima' => $anonRule,
        ]);

        $resultado = [
            'inscricao_id' => $dados['inscricao_id'] ?? null,
            'atividade_id' => $dados['atividade_id'],
            'template_avaliacao_id' => $dados['template_avaliacao_id'],
            'descricao_universal' => $dados['descricao_universal'] ?? null,
            'transcricao' => false,
        ];

        if (! $avaliacaoId) {
            $resultado['anonima'] = (bool) ($dados['anonima'] ?? false);
        }

        return $resultado;
    }

    private function validationErrors(Request $request): ?MessageBag
    {
        $errors = $request->session()->get('errors');

        if ($errors instanceof ViewErrorBag) {
            return $errors->getBag('default');
        }

        return $errors instanceof MessageBag ? $errors : null;
    }

    private function validateAvaliacaoUniversal(Request $request, bool $creating = false): array
    {
        $dados = $request->validate([
            'template_avaliacao_id' => ['required', Rule::exists('template_avaliacaos', 'id')],
            'descricao_universal' => ['nullable', 'string', 'max:255'],
            'respostas' => ['nullable', 'array'],
            'is_cartas' => $creating ? ['sometimes', 'boolean'] : ['prohibited'],
        ]);

        $atributos = [
            'inscricao_id' => null,
            'atividade_id' => null,
            'template_avaliacao_id' => $dados['template_avaliacao_id'],
            'descricao_universal' => $dados['descricao_universal'] ?? null,
            'anonima' => true,
            'transcricao' => false,
        ];

        if ($creating) {
            $atributos['is_cartas'] = $request->boolean('is_cartas');
        }

        return $atributos;
    }

    /**
     * @param  Collection<int,Questao>  $questoesTemplate
     * @return array<int, array{texto?: string|null}>
     */
    private function validarQuestoesPersonalizadas(Request $request, Collection $questoesTemplate): array
    {
        $rules = [];

        $tipos = array_keys($this->tiposQuestao());

        foreach ($questoesTemplate as $questao) {
            if (! $questao->fixa) {
                $rules["questoes.{$questao->id}.texto"] = ['nullable', 'string', 'max:1000'];
                $rules["questoes.{$questao->id}.tipo"] = ['nullable', 'string', Rule::in($tipos)];
                $rules["questoes.{$questao->id}.opcoes_resposta"] = ['nullable', 'array'];
                $rules["questoes.{$questao->id}.opcoes_resposta.*"] = ['nullable', 'string', 'max:255'];
                $rules["questoes.{$questao->id}.evidencia_id"] = ['nullable', 'integer', Rule::exists('evidencias', 'id')];
                $rules["questoes.{$questao->id}.escala_id"] = ['nullable', 'integer', Rule::exists('escalas', 'id')];
            }
        }

        if (empty($rules)) {
            return [];
        }

        $dados = $request->validate($rules);

        $resultado = [];

        foreach ($dados['questoes'] ?? [] as $questaoId => $config) {
            $resultado[$questaoId] = [
                'texto' => $config['texto'] ?? null,
                'tipo' => isset($config['tipo']) && $config['tipo'] !== '' ? $config['tipo'] : null,
                'opcoes_resposta' => $this->normalizaOpcoesResposta($config['opcoes_resposta'] ?? []),
                'evidencia_id' => array_key_exists('evidencia_id', $config) && $config['evidencia_id'] !== ''
                    ? (int) $config['evidencia_id']
                    : null,
                'escala_id' => array_key_exists('escala_id', $config) && $config['escala_id'] !== ''
                    ? (int) $config['escala_id']
                    : null,
            ];
        }

        return $resultado;
    }

    /**
     * @param  Collection<int,Questao>  $questoesTemplate
     * @return Collection<int,AvaliacaoQuestao>
     */
    private function sincronizaQuestoesPersonalizadas(
        Avaliacao $avaliacao,
        Collection $questoesTemplate,
        array $customizacoes,
        bool $recriar = false
    ): Collection {
        if ($recriar) {
            $avaliacao->avaliacaoQuestoes()->delete();
        }

        $existentes = $avaliacao->avaliacaoQuestoes()
            ->get()
            ->keyBy(fn ($questao) => $questao->questao_id ?? $questao->id);

        $sincronizadas = collect();

        $customizacoesCollection = collect($customizacoes);
        $evidenciaIds = $customizacoesCollection
            ->pluck('evidencia_id')
            ->filter()
            ->unique()
            ->all();

        $evidencias = Evidencia::with('indicador')
            ->whereIn('id', $evidenciaIds)
            ->get()
            ->keyBy('id');

        $tiposValidos = array_keys($this->tiposQuestao());

        foreach ($questoesTemplate as $questao) {
            $personalizacao = $customizacoesCollection->get($questao->id, []);

            $textoOriginal = $questao->texto;
            $textoPersonalizado = is_string($personalizacao['texto'] ?? null)
                ? trim($personalizacao['texto'])
                : '';
            $texto = $questao->fixa || $textoPersonalizado === ''
                ? $textoOriginal
                : $textoPersonalizado;

            $tipoPersonalizado = $personalizacao['tipo'] ?? null;
            if (! $questao->fixa && $tipoPersonalizado && in_array($tipoPersonalizado, $tiposValidos, true)) {
                $tipo = $tipoPersonalizado;
            } else {
                $tipo = $questao->tipo;
            }

            $evidenciaId = $questao->evidencia_id;
            $indicadorId = $questao->indicador_id;
            $escalaId = $questao->escala_id;
            $opcoesResposta = $questao->opcoes_resposta ?? [];

            if (! $questao->fixa) {
                $evidenciaId = $personalizacao['evidencia_id'] ?? $evidenciaId;
                $evidenciaId = $evidenciaId ? (int) $evidenciaId : null;
                $escalaId = $personalizacao['escala_id'] ?? $escalaId;
                $escalaId = $escalaId ? (int) $escalaId : null;
                $opcoesResposta = array_key_exists('opcoes_resposta', $personalizacao)
                    ? $personalizacao['opcoes_resposta']
                    : $opcoesResposta;

                if ($evidenciaId && $evidencias->has($evidenciaId)) {
                    $indicadorId = $evidencias[$evidenciaId]->indicador_id;
                } else {
                    $indicadorId = $questao->indicador_id;
                }
            }

            if ($tipo !== 'escala') {
                $escalaId = $questao->fixa ? $questao->escala_id : null;
            }

            if ($tipo === 'escala' && ! $escalaId) {
                throw ValidationException::withMessages([
                    "questoes.{$questao->id}.escala_id" => 'Selecione uma escala quando o tipo da questão for Escala.',
                ]);
            }

            $opcoesResposta = in_array($tipo, ['unica', 'multipla'])
                ? $this->normalizaOpcoesResposta($opcoesResposta)
                : null;

            if (in_array($tipo, ['unica', 'multipla']) && empty($opcoesResposta)) {
                throw ValidationException::withMessages([
                    "questoes.{$questao->id}.opcoes_resposta" => 'Informe pelo menos uma opção para questões de múltipla/única escolha.',
                ]);
            }

            $payload = [
                'questao_id' => $questao->id,
                'indicador_id' => $indicadorId,
                'escala_id' => $escalaId,
                'evidencia_id' => $questao->fixa ? $questao->evidencia_id : $evidenciaId,
                'texto' => $texto,
                'tipo' => $tipo,
                'opcoes_resposta' => $opcoesResposta,
                'ordem' => $questao->ordem,
                'fixa' => (bool) $questao->fixa,
            ];

            if ($recriar || ! $existentes->has($questao->id)) {
                $avaliacaoQuestao = $avaliacao->avaliacaoQuestoes()->create($payload);
            } else {
                $avaliacaoQuestao = $existentes[$questao->id];
                $avaliacaoQuestao->fill($payload);
                $avaliacaoQuestao->save();
            }

            $sincronizadas->push($avaliacaoQuestao);
        }

        if (! $recriar) {
            $manterIds = $sincronizadas->pluck('id')->all();
            $avaliacao->avaliacaoQuestoes()
                ->whereNotIn('id', $manterIds)
                ->delete();
        }

        return $sincronizadas;
    }

    public function transcricao(Avaliacao $avaliacao)
    {
        if ($avaliacao->anonima) {
            return redirect()->route('avaliacao.formulario', ['avaliacao' => $avaliacao, 'transcricao' => 1]);
        }

        return view('avaliacoes.transcricao_busca', compact('avaliacao'));
    }

    public function transcricaoBusca(Request $request, Avaliacao $avaliacao)
    {
        $search = trim((string) $request->input('search'));
        $type = $request->input('type', 'nome');

        if (empty($search)) {
            return back()->with('error', 'Informe um valor para buscar.')->withInput();
        }

        $query = User::query()->with(['participante', 'roles']);

        if ($type === 'cpf') {
            $cpf = preg_replace('/\D/', '', $search);
            $query->whereHas('participante', function ($q) use ($cpf) {
                $q->whereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [$cpf]);
            });
        } elseif ($type === 'email') {
            $query->where('email', $search);
        } else {
            // nome
            $query->where('name', 'ilike', "%{$search}%");
        }

        $usuarios = $query->get();

        if ($usuarios->isEmpty()) {
            return back()->with('error', 'Usuário não encontrado.')->withInput();
        }

        if ($usuarios->count() > 1) {
            $msg = "Foram encontrados {$usuarios->count()} usuários com este ".($type === 'nome' ? 'nome' : $type).'.';
            if ($type === 'nome') {
                $msg .= ' Sugerimos buscar por CPF ou E-mail para maior precisão.';
            }

            return view('avaliacoes.transcricao_busca', [
                'avaliacao' => $avaliacao,
                'usuarios' => $usuarios,
                'duplicados' => true,
                'mensagem' => $msg,
                'search' => $search,
                'type' => $type,
            ]);
        }

        $user = $usuarios->first();
        $participante = $user->participante;

        if (! $participante) {
            return back()->with('error', 'Este usuário não possui perfil de participante completo.')->withInput();
        }

        $atividade = $avaliacao->atividade;
        if (! $atividade) {
            return back()->with('error', 'Esta avaliação não possui um momento (atividade) vinculado.')->withInput();
        }

        $evento = $atividade->evento;

        // Lógica de inscrição e presença (baseada no PresencaController)
        $inscricao = Inscricao::withTrashed()
            ->where('participante_id', $participante->id)
            ->where('atividade_id', $atividade->id)
            ->first();

        if (! $inscricao) {
            $inscricao = Inscricao::withTrashed()
                ->where('participante_id', $participante->id)
                ->where('evento_id', $evento->id)
                ->whereNull('atividade_id')
                ->first();
        }

        if ($inscricao) {
            $inscricao->fill([
                'evento_id' => $evento->id,
                'atividade_id' => $atividade->id,
                'participante_id' => $participante->id,
            ]);
            $inscricao->deleted_at = null;
            $inscricao->save();
        } else {
            $inscricao = Inscricao::create([
                'evento_id' => $evento->id,
                'atividade_id' => $atividade->id,
                'participante_id' => $participante->id,
                'ouvinte' => true,
            ]);
        }

        $presenca = $atividade->presencas()->updateOrCreate(
            ['inscricao_id' => $inscricao->id],
            ['status' => 'presente']
        );

        if (is_null($presenca->avaliacao_respondida)) {
            $presenca->avaliacao_respondida = false;
            $presenca->save();
        }

        return redirect()->route('avaliacao.formulario', [
            'avaliacao' => $avaliacao->id,
            'token' => encrypt($presenca->id),
            'transcricao' => 1,
        ]);
    }

    public function transcricaoCadastrar(Request $request, Avaliacao $avaliacao)
    {
        $cpfDigits = preg_replace('/\D+/', '', (string) $request->input('cpf'));
        $payload = [
            'name' => isset($request->name) ? trim((string) $request->name) : null,
            'email' => isset($request->email) ? trim((string) $request->email) : null,
            'cpf' => $cpfDigits !== '' ? $cpfDigits : null,
        ];

        $validator = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where('sistema_origem', User::SISTEMA_ENGAJA),
            ],
            'cpf' => ['nullable', 'digits:11'],
        ]);

        $validator->after(function ($v) use ($payload) {
            $cpf = $payload['cpf'] ?? null;
            if ($cpf) {
                if (! $this->isValidCpf($cpf)) {
                    $v->errors()->add('cpf', 'CPF invalido.');

                    return;
                }

                $duplicado = Participante::query()
                    ->whereNotNull('cpf')
                    ->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$cpf])
                    ->exists();

                if ($duplicado) {
                    $v->errors()->add('cpf', 'Este CPF ja possui cadastro no sistema.');
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $user = DB::transaction(function () use ($validated) {
            $email = strtolower(trim($validated['email']));
            $name = trim($validated['name'] ?? '');
            $cpf = $validated['cpf'] ?? null;

            $user = User::firstOrCreate(
                [
                    'email' => $email,
                    'sistema_origem' => User::SISTEMA_ENGAJA,
                ],
                [
                    'name' => $name !== '' ? $name : ($cpf ?? 'Participante'),
                    'password' => Hash::make(Str::random(12)),
                ]
            );

            Participante::updateOrCreate(
                ['user_id' => $user->id],
                ['cpf' => $cpf]
            );

            return $user;
        });

        // Agora procede como se tivesse encontrado o usuário na busca
        $participante = $user->participante;
        $atividade = $avaliacao->atividade;
        $evento = $atividade->evento;

        $inscricao = Inscricao::create([
            'evento_id' => $evento->id,
            'atividade_id' => $atividade->id,
            'participante_id' => $participante->id,
            'ouvinte' => true,
        ]);

        $presenca = $atividade->presencas()->create([
            'inscricao_id' => $inscricao->id,
            'status' => 'presente',
            'avaliacao_respondida' => false,
        ]);

        return redirect()->route('avaliacao.formulario', [
            'avaliacao' => $avaliacao->id,
            'token' => encrypt($presenca->id),
            'transcricao' => 1,
        ]);
    }

    public function usuariosSugestao(Request $request)
    {
        $term = $request->query('q');
        if (empty($term)) {
            return response()->json([]);
        }

        $sugestoes = User::where('name', 'ilike', "%{$term}%")
            ->orderBy('name')
            ->limit(10)
            ->pluck('name');

        return response()->json($sugestoes);
    }

    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D+/', '', $cpf ?? '');
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum += (int) $cpf[$i] * $w;
        }
        $r = $sum % 11;
        $dv1 = ($r < 2) ? 0 : 11 - $r;

        $sum = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum += (int) $cpf[$i] * $w;
        }
        $r = $sum % 11;
        $dv2 = ($r < 2) ? 0 : 11 - $r;

        return ($cpf[9] == $dv1) && ($cpf[10] == $dv2);
    }

    public function tiposQuestao(): array
    {
        return [
            'texto' => 'Texto aberto',
            'escala' => 'Escala',
            'numero' => 'Número',
            'boolean' => 'Sim/Não',
            'unica' => 'Resposta única',
            'multipla' => 'Múltipla escolha',
        ];
    }

    private function normalizaOpcoesResposta($opcoes): array
    {
        if (! is_array($opcoes)) {
            return [];
        }

        return collect($opcoes)
            ->map(fn ($opcao) => is_string($opcao) ? trim($opcao) : '')
            ->filter(fn ($opcao) => $opcao !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function buildEvidenciasOptions(Collection $evidencias): array
    {
        return $evidencias
            ->mapWithKeys(fn ($evidencia) => [
                $evidencia->id => ($evidencia->indicador && $evidencia->indicador->dimensao
                        ? $evidencia->indicador->dimensao->descricao.' - '
                        : '').($evidencia->indicador->descricao ?? '').' | '.$evidencia->descricao,
            ])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->toArray();
    }

    public function formularioAvaliacao(Request $request, Avaliacao $avaliacao)
    {
        $isCartas = $this->validarOrigemFormulario($request, $avaliacao);
        $atividade = Atividade::find($avaliacao->atividade_id);

        $avaliacao->load([
            'inscricao.participante.user',
            'inscricao.evento',
            'atividade.evento',
            'templateAvaliacao',
            'avaliacaoQuestoes.indicador.dimensao',
            'avaliacaoQuestoes.evidencia',
            'avaliacaoQuestoes.escala',
            'respostas.avaliacaoQuestao',
        ]);

        $token = $request->query('token', $request->input('token'));
        $isUniversal = $avaliacao->atividade_id === null;
        $isTranscricao = $avaliacao->transcricao;
        $exigePresenca = ! $isUniversal && ! $isTranscricao;
        $presencaRespondente = $exigePresenca ? $this->resolverPresencaPorToken($token, $avaliacao) : null;
        $inscricaoRespondente = $presencaRespondente?->inscricao;
        $formularioFechado = $isUniversal && ! $avaliacao->formulario_aberto;
        $formBloqueado = $formularioFechado || ($exigePresenca ? ($presencaRespondente?->avaliacao_respondida ?? false) : false);
        $respostasExistentes = collect();

        $viewData = [
            'avaliacao' => $avaliacao,
            'atividade' => $atividade,
            'tiposQuestao' => $this->tiposQuestao(),
            'inscricaoRespondente' => $inscricaoRespondente,
            'token' => $token,
            'respostasExistentes' => $respostasExistentes,
            'jaRespondeu' => $formBloqueado,
            'isUniversal' => $isUniversal,
            'isTranscricao' => $isTranscricao,
            'formularioFechado' => $formularioFechado,
            'isCartas' => $isCartas,
        ];

        if ($isCartas) {
            $viewData['formAction'] = CartasUrl::route('cartas.avaliacao.formulario.responder', $avaliacao);

            return view()->first([
                'cartas.avaliacoes.formulario',
                'avaliacoes._form',
            ], $viewData);
        }

        return view('avaliacoes._form', $viewData);
    }

    public function responderFormulario(Request $request, Avaliacao $avaliacao)
    {
        $isCartas = $this->validarOrigemFormulario($request, $avaliacao);
        $avaliacao->load(['avaliacaoQuestoes.escala', 'atividade']);
        $isUniversal = $avaliacao->atividade_id === null;
        $isTranscricao = $avaliacao->transcricao;
        $exigePresenca = ! $isUniversal && ! $isTranscricao;

        $token = $request->input('token', $request->query('token'));
        $presenca = $exigePresenca ? $this->resolverPresencaPorToken($token, $avaliacao) : null;

        if ($isUniversal && ! $avaliacao->formulario_aberto) {
            if ($isCartas) {
                return redirect()
                    ->to(CartasUrl::route('cartas.avaliacao.formulario', $avaliacao))
                    ->withErrors(['avaliacao' => 'Este formulário não está recebendo respostas no momento.']);
            }

            return redirect()
                ->route('avaliacao.formulario', $avaliacao)
                ->withErrors(['avaliacao' => 'Este formulário não está recebendo respostas no momento.']);
        }

        if ($exigePresenca && ! $presenca) {
            return redirect()
                ->route('avaliacao.formulario', ['avaliacao' => $avaliacao->id, 'token' => $token])
                ->withErrors(['token' => 'Nao encontramos sua inscricao para esta avaliacao. Confirme a presenca novamente.']);
        }

        if ($exigePresenca && $presenca->avaliacao_respondida) {
            return redirect()
                ->route('avaliacao.formulario', ['avaliacao' => $avaliacao->id, 'token' => $token])
                ->withErrors(['avaliacao' => 'Voce ja respondeu este formulario.']);
        }

        if ($exigePresenca && ! $avaliacao->anonima) {
            $jaEnviou = SubmissaoAvaliacao::where('avaliacao_id', $avaliacao->id)
                ->where('presenca_id', $presenca->id)
                ->exists();

            if ($jaEnviou) {
                return redirect()
                    ->route('avaliacao.formulario', ['avaliacao' => $avaliacao->id, 'token' => $token])
                    ->withErrors(['avaliacao' => 'Voce ja respondeu este formulario.']);
            }
        }

        $rules = [];
        foreach ($avaliacao->avaliacaoQuestoes as $questao) {
            if ($questao->tipo === 'multipla') {
                // exige que seja um array e valida cada item selecionado
                $rules["respostas.{$questao->id}"] = ['nullable', 'array'];
                $opcoes = $questao->opcoes_resposta ?? [];
                if (! empty($opcoes)) {
                    $rules["respostas.{$questao->id}.*"] = ['string', Rule::in($opcoes)];
                }
            } else {
                $rules["respostas.{$questao->id}"] = $this->regraRespostaParaQuestao($questao);
            }
        }

        $dados = $request->validate($rules);
        $respostas = $dados['respostas'] ?? [];

        DB::transaction(function () use ($avaliacao, $presenca, $respostas, $isUniversal, $exigePresenca) {
            $submissao = SubmissaoAvaliacao::create([
                'codigo' => (string) Str::ulid(),
                'atividade_id' => $avaliacao->atividade_id,
                'avaliacao_id' => $avaliacao->id,
                'presenca_id' => $isUniversal || $avaliacao->anonima || ! $exigePresenca ? null : $presenca->id,
                'universal' => $isUniversal,
            ]);

            foreach ($avaliacao->avaliacaoQuestoes as $questao) {
                $valor = $respostas[$questao->id] ?? null;

                if ($valor === null || $valor === '') {
                    continue;
                }

                RespostaAvaliacao::create([
                    'avaliacao_id' => $avaliacao->id,
                    'avaliacao_questao_id' => $questao->id,
                    'submissao_avaliacao_id' => $submissao->id,
                    'resposta' => is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : $valor,
                ]);
            }

            if ($exigePresenca && $presenca) {
                $presenca->avaliacao_respondida = true;
                $presenca->save();
            }
        });

        if ($isUniversal) {
            if ($isCartas) {
                return redirect()->to(CartasUrl::route('cartas.avaliacao.formulario.obrigado', $avaliacao));
            }

            return redirect()
                ->route('avaliacao.formulario.obrigado', $avaliacao);
        }

        if ($request->input('transcricao')) {
            return redirect()
                ->route('avaliacoes.index')
                ->with('success', 'Resposta de transcrição registrada com sucesso!');
        }

        return redirect()
            ->route('presenca.confirmar', $presenca->atividade_id)
            ->with([
                'success' => 'Avaliação registrada com sucesso!',
                'avaliacao_token' => null,
                'avaliacao_disponivel' => false,
            ]);
    }

    public function formularioAvaliacaoObrigado(Request $request, Avaliacao $avaliacao)
    {
        $isCartas = $this->validarOrigemFormulario($request, $avaliacao);
        abort_unless($avaliacao->atividade_id === null, 404);

        $avaliacao->load('templateAvaliacao');

        $viewData = [
            'avaliacao' => $avaliacao,
            'isCartas' => $isCartas,
            'homeUrl' => $isCartas ? CartasUrl::route('cartas.apresentacao') : null,
        ];

        if ($isCartas) {
            return view()->first([
                'cartas.avaliacoes.obrigado',
                'avaliacoes.obrigado',
            ], $viewData);
        }

        return view('avaliacoes.obrigado', $viewData);
    }

    private function formularioPublicoUrl(Avaliacao $avaliacao): string
    {
        if ($avaliacao->is_cartas) {
            return CartasUrl::route('cartas.avaliacao.formulario', $avaliacao);
        }

        return route('avaliacao.formulario', $avaliacao);
    }

    private function validarOrigemFormulario(Request $request, Avaliacao $avaliacao): bool
    {
        $isCartas = $request->routeIs('cartas.avaliacao.formulario*');

        if ($isCartas) {
            abort_unless($avaliacao->atividade_id === null && $avaliacao->is_cartas, 404);
        } else {
            abort_if($avaliacao->is_cartas, 404);
        }

        return $isCartas;
    }

    private function regraRespostaParaQuestao(AvaliacaoQuestao $questao): array
    {
        if ($questao->tipo === 'escala') {
            $valores = $questao->escala?->valores ?? [];

            return empty($valores) ? ['nullable', 'string'] : ['nullable', Rule::in($valores)];
        }

        if ($questao->tipo === 'unica') {
            $opcoes = $questao->opcoes_resposta ?? [];

            return empty($opcoes) ? ['nullable', 'string'] : ['nullable', Rule::in($opcoes)];
        }

        return match ($questao->tipo) {
            'numero' => ['nullable', 'numeric'],
            'boolean' => ['nullable', Rule::in(['0', '1'])],
            default => ['nullable', 'string', 'max:2000'],
        };
    }

    private function resolverPresencaPorToken(?string $token, Avaliacao $avaliacao): ?Presenca
    {
        if (! $token) {
            return null;
        }

        try {
            $presencaId = decrypt($token);
        } catch (Throwable $exception) {
            return null;
        }

        $presenca = Presenca::with(['inscricao.participante.user', 'inscricao.evento'])->find($presencaId);
        if (! $presenca) {
            return null;
        }

        if ($avaliacao->atividade_id && $presenca->atividade_id !== $avaliacao->atividade_id) {
            return null;
        }

        return $presenca;
    }

    public function respostas(Avaliacao $avaliacao)
    {
        $isUniversal = $avaliacao->atividade_id === null;
        $isTranscricao = $avaliacao->transcricao;
        abort_if($avaliacao->anonima && ! $isUniversal && ! $isTranscricao, 404);

        $avaliacao->load(['atividade.evento', 'templateAvaliacao']);

        $submissoes = SubmissaoAvaliacao::with([
            'presenca.inscricao.participante.user',
            'respostas.avaliacaoQuestao',
        ])
            ->where('avaliacao_id', $avaliacao->id)
            ->orderByDesc('created_at')
            ->get();

        return view('avaliacoes.respostas', compact('avaliacao', 'submissoes'));
    }

    public function respostasMostrar(Avaliacao $avaliacao, SubmissaoAvaliacao $submissao)
    {
        $isUniversal = $avaliacao->atividade_id === null;
        $isTranscricao = $avaliacao->transcricao;
        abort_if($avaliacao->anonima && ! $isUniversal && ! $isTranscricao, 404);
        abort_unless($submissao->avaliacao_id === $avaliacao->id, 404);

        $submissao->load([
            'presenca.inscricao.participante.user',
            'respostas.avaliacaoQuestao',
        ]);

        $avaliacao->load(['avaliacaoQuestoes']);

        $respostasPorQuestao = $submissao->respostas->keyBy('avaliacao_questao_id');

        return view('avaliacoes.resposta_detalhe', [
            'avaliacao' => $avaliacao,
            'submissao' => $submissao,
            'respostasPorQuestao' => $respostasPorQuestao,
        ]);
    }

    /**
     * Listagem anónima das respostas de participantes para um Momento (Atividade).
     * Nunca expõe nome, e-mail ou qualquer dado identificador do participante.
     */
    public function resultadosAtividade(Atividade $atividade, AvaliacaoRespostasDashboardService $avaliacaoRespostas)
    {
        $this->authorize('update', $atividade->evento);

        $atividade->load(['evento', 'municipios']);

        $avaliacao = $atividade->avaliacoes()
            ->with(['avaliacaoQuestoes.escala', 'templateAvaliacao'])
            ->orderByDesc('id')
            ->first();

        if (! $avaliacao) {
            return redirect()
                ->route('eventos.show', $atividade->evento_id)
                ->with('info', 'Nenhum formulário de avaliação configurado para este momento.');
        }

        // Carrega submissões SEM dados do participante (totalmente anónimo)
        $submissoes = SubmissaoAvaliacao::with(['respostas.avaliacaoQuestao'])
            ->where('avaliacao_id', $avaliacao->id)
            ->orderByDesc('created_at')
            ->get();

        $filterRequest = Request::create('/dashboards/avaliacoes/dados', 'GET', [
            'atividade_id' => $atividade->id,
        ]);
        $payload = $avaliacaoRespostas->buildDashboardPayload($filterRequest);

        $submissoesComRespostas = $submissoes->filter(fn ($s) => $s->respostas->isNotEmpty())->count();

        return view('avaliacoes.resultados_atividade', [
            'atividade' => $atividade,
            'avaliacao' => $avaliacao,
            'submissoes' => $submissoes,
            'totais' => $payload['totais'],
            'perguntas' => $payload['perguntas'],
            'submissoesComRespostas' => $submissoesComRespostas,
        ]);
    }

    /**
     * PDF agregado das respostas do momento (mesma lógica do dashboard de avaliações, barras horizontais no layout).
     */
    public function downloadResultadosPdf(Atividade $atividade, AvaliacaoRespostasDashboardService $avaliacaoRespostas)
    {
        $this->authorize('update', $atividade->evento);

        $atividade->load(['evento', 'municipios.estado']);

        $avaliacao = $atividade->avaliacoes()
            ->with(['templateAvaliacao', 'avaliacaoQuestoes.escala'])
            ->orderByDesc('id')
            ->first();

        if (! $avaliacao) {
            return redirect()
                ->route('eventos.show', $atividade->evento_id)
                ->with('info', 'Nenhum formulário de avaliação configurado para este momento.');
        }

        $filterRequest = Request::create('/dashboards/avaliacoes/dados', 'GET', [
            'atividade_id' => $atividade->id,
        ]);

        $payload = $avaliacaoRespostas->buildDashboardPayload($filterRequest);

        $fileSlug = Str::slug($atividade->descricao ?: 'momento');
        $fileBase = 'avaliacoes-momento-'.$atividade->id.'-'.$fileSlug.'-'.now()->format('Ymd_His');
        $fileName = $fileBase.'.pdf';

        if (request()->get('formato') === 'docx') {
            return AvaliacaoResultadosWordBuilder::build(
                $atividade,
                $avaliacao,
                $payload['totais'],
                $payload['perguntas'],
            )->download($fileBase.'.docx');
        }

        return Pdf::view('avaliacoes.resultados_atividade_pdf', [
            'atividade' => $atividade,
            'avaliacao' => $avaliacao,
            'totais' => $payload['totais'],
            'perguntas' => $payload['perguntas'],
            'geradoEm' => now(),
        ])
            ->format('a4')
            ->withAlfaEjaBrand()
            ->download($fileName);
    }

    public function downloadFichaPdf(Avaliacao $avaliacao)
    {
        $avaliacao->load([
            'templateAvaliacao',
            'atividade.evento',
            'avaliacaoQuestoes.escala',
            'avaliacaoQuestoes.indicador.dimensao',
        ]);

        $fileBase = 'ficha-avaliacao-'.$avaliacao->id.'-'.now()->format('Ymd_His');

        if (request()->get('formato') === 'docx') {
            return AvaliacaoFichaWordBuilder::build($avaliacao)->download($fileBase.'.docx');
        }

        return Pdf::view('avaliacoes.ficha_pdf', [
            'avaliacao' => $avaliacao,
        ])
            ->format('a4')
            ->withAlfaEjaBrand()
            ->download($fileBase.'.pdf');
    }
}
