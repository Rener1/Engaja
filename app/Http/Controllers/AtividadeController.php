<?php

namespace App\Http\Controllers;

use App\Enums\OrigemAutorizacaoImagem;
use App\Http\Requests\AgendarPresencaRequest;
use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Inscricao;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\Presenca;
use App\Pdf\AutorizacaoDeImagem\ListaAutorizacaoImagem;
use App\Pdf\ListaDePresenca\ListaPresencaFactory;
use App\Services\AutorizacaoImagemContexto;
use App\Support\AgendamentoPresenca;
use App\Support\CargaHoraria;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

class AtividadeController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Evento $evento)
    {
        $filtros = $request->validate([
            'municipio_id' => ['nullable', 'string'],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);
        $userId = auth()->id();

        $municipiosDisponiveis = Municipio::whereHas('atividades', fn ($q) => $q->where('evento_id', $evento->id))
            ->with('estado')
            ->orderBy('nome')
            ->get();

        $temAbrangenciaNacional = $evento->atividades()
            ->where('abrangencia_nacional', true)
            ->exists();

        $atividades = $evento->atividades()
            ->withCount('presencas')
            ->with([
                'municipios.estado',
                'avaliacaoAtividades' => fn ($rel) => $rel->when($userId, fn ($query) => $query->where('user_id', $userId)),
            ])
            ->when(($filtros['municipio_id'] ?? null) === 'brasil', fn ($q) => $q->where('abrangencia_nacional', true))
            ->when(! empty($filtros['municipio_id']) && $filtros['municipio_id'] !== 'brasil', fn ($q) => $q->whereHas(
                'municipios',
                fn ($q2) => $q2->where('municipios.id', $filtros['municipio_id'])
            ))
            ->when(! empty($filtros['de']), fn ($q) => $q->whereDate('dia', '>=', $filtros['de']))
            ->when(! empty($filtros['ate']), fn ($q) => $q->whereDate('dia', '<=', $filtros['ate']))
            ->when(! empty($filtros['descricao']), fn ($q) => $q->where('descricao', 'like', '%'.addcslashes($filtros['descricao'], '%_\\').'%'))
            ->orderByDesc('dia')
            ->orderByDesc('hora_inicio')
            ->paginate(12)
            ->appends($request->query());

        return view('atividades.index', compact('evento', 'atividades', 'municipiosDisponiveis', 'temAbrangenciaNacional'));
    }

    public function create(Evento $evento)
    {
        $this->authorize('atividade.criar');

        $marcadosRaw = request()->query('marcados', []);
        if (is_string($marcadosRaw)) {
            $marcadosRaw = $marcadosRaw === '' ? [] : explode(',', $marcadosRaw);
        }
        if (! is_array($marcadosRaw)) {
            $marcadosRaw = [];
        }

        $marcadosPlanejamento = $this->normalizarChecklistIndices($marcadosRaw);

        $municipios = Municipio::with(['estado.regiao'])
            ->get(['id', 'nome', 'estado_id'])
            ->sortBy(function ($m) {
                $regiao = $m->estado->regiao->nome ?? '';
                $regiaoLower = mb_strtolower(trim($regiao));
                $ordemRegiao = match ($regiaoLower) {
                    'nordeste i', 'nordeste 1' => 1,
                    'nordeste ii', 'nordeste 2' => 2,
                    'norte' => 3,
                    default => 9,
                };

                return sprintf('%02d-%s', $ordemRegiao, $m->nome);
            })
            ->values();

        $atividadesCopiaveis = $this->listarAtividadesCopiaveis();

        $municipiosJson = $this->municipiosParaSelecaoJson($municipios);

        return view('atividades.create', compact('evento', 'municipios', 'municipiosJson', 'atividadesCopiaveis', 'marcadosPlanejamento'));
    }

    public function saveChecklist(Request $request, Atividade $atividade)
    {
        $request->validate([
            'tipo' => 'required|in:planejamento,encerramento',
            'itens' => 'nullable|array',
            'itens.*' => 'integer|min:0',
        ]);

        $campo = 'checklist_'.$request->tipo;
        $atividade->$campo = $this->normalizarChecklistIndices($request->input('itens', []));
        $atividade->save();

        return response()->json(['status' => 'ok', 'saved' => $atividade->$campo]);
    }

    public function store(Request $request, Evento $evento)
    {
        $this->authorize('atividade.criar');

        $dados = $request->validate([
            'municipios' => 'nullable|array',
            'municipios.*' => 'exists:municipios,id',
            'abrangencia_nacional' => 'nullable|boolean',
            'descricao' => 'required|string',
            'dia' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fim' => 'required|date_format:H:i|after:hora_inicio',
            'publico_esperado' => 'nullable|integer|min:0',
            'carga_horas' => 'nullable|integer|min:0',
            'carga_minutos' => 'nullable|integer|min:0|max:59',
            'copiar_inscritos_de' => 'nullable|exists:atividades,id',
            'checklist_planejamento' => 'nullable|array',
            'checklist_planejamento.*' => 'integer|min:0',
            'checklist_encerramento' => 'nullable|array',
            'checklist_encerramento.*' => 'integer|min:0',
        ] + $this->regrasAgendamentoFormulario($request), AgendamentoPresenca::mensagens());

        $copiarDe = $dados['copiar_inscritos_de'] ?? null;
        unset($dados['copiar_inscritos_de']);

        $dados['carga_horaria'] = CargaHoraria::totalMinutosFromPartes(
            (int) ($dados['carga_horas'] ?? 0),
            (int) ($dados['carga_minutos'] ?? 0)
        );
        unset($dados['carga_horas'], $dados['carga_minutos']);

        $dados['checklist_planejamento'] = $this->normalizarChecklistIndices($dados['checklist_planejamento'] ?? []);
        $dados['checklist_encerramento'] = $this->normalizarChecklistIndices($dados['checklist_encerramento'] ?? []);

        $municipiosSelecionados = $dados['municipios'] ?? [];
        unset($dados['municipios']);

        $dados['abrangencia_nacional'] = $request->boolean('abrangencia_nacional');
        if ($dados['abrangencia_nacional']) {
            $municipiosSelecionados = [];
        }

        // Mantém o campo legado municipio_id preenchido com o primeiro selecionado (para compatibilidade).
        $dados['municipio_id'] = $municipiosSelecionados[0] ?? null;

        $dadosAtividade = $this->converterAgendamentoFormulario($request, $dados);

        // Se o agendamento de abertura estiver vazio/não preenchido (ou já estiver no passado),
        // o momento deve ser criado com a confirmação de presença ABERTA (presenca_ativa = true).
        // Se houver um horário de abertura agendado no futuro, inicia com presença fechada (false).
        $abreEm = $dadosAtividade['presenca_abre_em'] ?? null;
        $temAberturaFutura = $abreEm?->isFuture() ?? false;
        $dadosAtividade['presenca_ativa'] = ! $temAberturaFutura;

        $atividade = $evento->atividades()->make($dadosAtividade);
        $atividade->consolidarAgendamentoPresenca();
        $atividade->save();
        $atividade->municipios()->sync($municipiosSelecionados);
        $copiados = $this->copiarInscritos($copiarDe, $atividade);

        return redirect()
            ->route('eventos.show', $evento)
            ->with('success', 'Momento criado com sucesso!');
    }

    public function edit(Atividade $atividade)
    {
        $evento = $atividade->evento;
        $this->authorize('atividade.editar');

        $atividade->load('municipios');

        $municipios = Municipio::with(['estado.regiao'])
            ->get(['id', 'nome', 'estado_id'])
            ->sortBy(function ($m) {
                $regiao = $m->estado->regiao->nome ?? '';
                $regiaoLower = mb_strtolower(trim($regiao));
                $ordemRegiao = match ($regiaoLower) {
                    'nordeste i', 'nordeste 1' => 1,
                    'nordeste ii', 'nordeste 2' => 2,
                    'norte' => 3,
                    default => 9,
                };

                return sprintf('%02d-%s', $ordemRegiao, $m->nome);
            })
            ->values();

        $atividadesCopiaveis = $this->listarAtividadesCopiaveis($atividade);

        $municipiosJson = $this->municipiosParaSelecaoJson($municipios);

        // Apenas para exibição: horários vencidos não aparecem no formulário.
        $atividade->consolidarAgendamentoPresenca();

        return view('atividades.edit', compact('evento', 'atividade', 'municipios', 'municipiosJson', 'atividadesCopiaveis'));
    }

    public function update(Request $request, Atividade $atividade)
    {
        $evento = $atividade->evento;
        $this->authorize('atividade.editar');

        $dados = $request->validate([
            'municipios' => 'nullable|array',
            'municipios.*' => 'exists:municipios,id',
            'abrangencia_nacional' => 'nullable|boolean',
            'descricao' => 'required|string',
            'dia' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fim' => 'required|date_format:H:i|after:hora_inicio',
            'publico_esperado' => 'nullable|integer|min:0',
            'carga_horas' => 'nullable|integer|min:0',
            'carga_minutos' => 'nullable|integer|min:0|max:59',
            'copiar_inscritos_de' => 'nullable|exists:atividades,id',
            'checklist_planejamento' => 'nullable|array',
            'checklist_planejamento.*' => 'integer|min:0',
            'checklist_encerramento' => 'nullable|array',
            'checklist_encerramento.*' => 'integer|min:0',
        ] + $this->regrasAgendamentoFormulario($request), AgendamentoPresenca::mensagens());

        $copiarDe = $dados['copiar_inscritos_de'] ?? null;
        unset($dados['copiar_inscritos_de']);

        $dados['carga_horaria'] = CargaHoraria::totalMinutosFromPartes(
            (int) ($dados['carga_horas'] ?? 0),
            (int) ($dados['carga_minutos'] ?? 0)
        );
        unset($dados['carga_horas'], $dados['carga_minutos']);

        $dados['checklist_planejamento'] = $this->normalizarChecklistIndices($dados['checklist_planejamento'] ?? []);
        $dados['checklist_encerramento'] = $this->normalizarChecklistIndices($dados['checklist_encerramento'] ?? []);

        $municipiosSelecionados = $dados['municipios'] ?? [];
        unset($dados['municipios']);

        $dados['abrangencia_nacional'] = $request->boolean('abrangencia_nacional');
        if ($dados['abrangencia_nacional']) {
            $municipiosSelecionados = [];
        }

        $dados['municipio_id'] = $municipiosSelecionados[0] ?? null;

        // Consolida o agendamento atual antes de sobrescrever (preserva aberturas/fechamentos já ocorridos)
        // e de novo depois (horários informados no passado valem imediatamente).
        $atividade->consolidarAgendamentoPresenca();
        $atividade->fill($this->converterAgendamentoFormulario($request, $dados));
        $atividade->consolidarAgendamentoPresenca();
        $atividade->save();
        $atividade->municipios()->sync($municipiosSelecionados);
        $copiados = $this->copiarInscritos($copiarDe, $atividade);

        return redirect()
            ->route('eventos.show', $evento)
            ->with('success', $this->mensagemSucesso('Momento atualizado com sucesso!', $copiados));
    }

    /**
     * Regras dos campos de agendamento da presença no formulário do momento
     * (somente para quem pode abrir/fechar presença).
     */
    private function regrasAgendamentoFormulario(Request $request): array
    {
        if (! $request->user()?->can('presenca.abrir')) {
            return [];
        }

        $ativo = $request->boolean('agendamento_presenca_ativo');
        $regras = ['agendamento_presenca_ativo' => ['sometimes', 'boolean']];
        foreach (AgendamentoPresenca::regras(exigirAoMenosUm: $ativo) as $campo => $validacoes) {
            $regras[$campo] = ['exclude_if:agendamento_presenca_ativo,0', ...$validacoes];
        }

        return $regras;
    }

    /**
     * Converte os campos de agendamento validados (horário de Brasília) para UTC.
     * Sem a permissão `presenca.abrir`, os campos são descartados e o agendamento atual é preservado.
     */
    private function converterAgendamentoFormulario(Request $request, array $dados): array
    {
        unset($dados['agendamento_presenca_ativo']);

        if (! $request->user()?->can('presenca.abrir')) {
            unset($dados['presenca_abre_em'], $dados['presenca_fecha_em']);

            return $dados;
        }

        if ($request->exists('agendamento_presenca_ativo') && ! $request->boolean('agendamento_presenca_ativo')) {
            $dados['presenca_abre_em'] = null;
            $dados['presenca_fecha_em'] = null;

            return $dados;
        }

        foreach (['presenca_abre_em', 'presenca_fecha_em'] as $campo) {
            if ($request->exists($campo)) {
                $dados[$campo] = AgendamentoPresenca::paraUtc($dados[$campo] ?? null);
            } else {
                unset($dados[$campo]);
            }
        }

        return $dados;
    }

    public function destroy(Atividade $atividade)
    {
        $this->authorize('atividade.excluir');

        $totalPresencas = $atividade->presencas()->count();

        if ($totalPresencas > 0) {
            $msg = $totalPresencas === 1
                ? 'Não é possível excluir este momento porque ele possui 1 presença associada. Remova a presença antes de excluir.'
                : "Não é possível excluir este momento porque ele possui {$totalPresencas} presenças associadas. Remova as presenças antes de excluir.";

            return back()->with('error', $msg);
        }

        $atividade->delete();

        return back()->with('success', 'Momento removido com sucesso.');
    }

    /**
     * Dados dos municípios para o seletor: apenas o nome no município; UF e região por associação.
     *
     * @param  Collection<int, Municipio>  $municipios
     * @return array<int, array{id: string, nome: string, prioritario: bool, estado: array{sigla: string, nome: string}, regiao: array{nome: string}}>
     */
    private function municipiosParaSelecaoJson($municipios): array
    {
        return $municipios->map(function (Municipio $m) {
            return [
                'id' => (string) $m->id,
                'nome' => $m->nome,
                'prioritario' => $m->isPrioritario(),
                'estado' => [
                    'sigla' => $m->estado->sigla ?? '',
                    'nome' => $m->estado->nome ?? '',
                ],
                'regiao' => [
                    'nome' => $m->estado->regiao->nome ?? '',
                ],
            ];
        })->values()->all();
    }

    private function normalizarChecklistIndices(array $itens): array
    {
        return array_values(array_unique(array_map('intval', $itens)));
    }

    public function show(Atividade $atividade)
    {
        $atividade->load(['evento', 'municipios.estado']);

        $presencas = $atividade->presencas()
            ->with([
                'inscricao.participante.user:id,name,email',
                'inscricao.participante.municipio.estado:id,nome,sigla',
            ])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $user = auth()->user();
        $podeImportar = $user?->can('presenca.import') ?? false;
        $podeAbrir = $user?->can('presenca.abrir') ?? false;

        return view('atividades.show', compact('atividade', 'presencas', 'podeImportar', 'podeAbrir'));
    }

    public function togglePresenca(Atividade $atividade)
    {
        // Aplica horários agendados já vencidos antes de inverter, para o toggle
        // partir do estado que o usuário está vendo na tela.
        $atividade->consolidarAgendamentoPresenca();
        $atividade->presenca_ativa = ! $atividade->presencaEstaAberta();
        $atividade->save();

        $mensagem = $atividade->presenca_ativa ? 'Presença aberta para este momento.' : 'Presença fechada para este momento.';

        if (request()->expectsJson()) {
            return response()->json([
                'aberta' => (bool) $atividade->presenca_ativa,
                'message' => $mensagem,
            ]);
        }

        return back()->with('success', $mensagem);
    }

    public function agendarPresenca(AgendarPresencaRequest $request, Atividade $atividade)
    {
        $atividade->consolidarAgendamentoPresenca();
        $atividade->fill($request->horariosUtc());
        // Um horário de abertura no passado significa "abrir agora".
        $atividade->consolidarAgendamentoPresenca();
        $atividade->save();

        return back()->with('success', 'Agendamento da presença salvo. Status: '.$atividade->status_presenca_label.'.');
    }

    public function limparAgendamentoPresenca(Atividade $atividade)
    {
        $atividade->consolidarAgendamentoPresenca();
        $atividade->presenca_abre_em = null;
        $atividade->presenca_fecha_em = null;
        $atividade->save();

        return back()->with('success', 'Agendamento da presença removido.');
    }

    public function checkin(Atividade $atividade)
    {
        if (! $atividade->presencaEstaAberta()) {
            return back()->withErrors(['checkin' => 'Presença não está aberta para este momento.']);
        }

        $user = auth()->user();

        if (! $user->demograficosCompletos()) {
            return back()->with('erro_demograficos', 'Para confirmar sua presença, é necessário preencher seus dados demográficos no Engaja. Por favor, preencha o formulário acima e tente novamente.');
        }

        // 1) Garante Participante para o usuário
        $participante = Participante::firstOrCreate(['user_id' => $user->id], []);

        // 2) Garante Inscrição no evento (cria/reativa)
        $evento = $atividade->evento;

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
                'ouvinte' => $inscricao->atividade_id === $atividade->id ? $inscricao->ouvinte : true,
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

        // 3) Marca presenca (idempotente)
        $presenca = Presenca::updateOrCreate(
            ['inscricao_id' => $inscricao->id, 'atividade_id' => $atividade->id],
            ['status' => 'presente', 'justificativa' => null]
        );

        return back()->with('success', 'Presença confirmada com sucesso!')
            ->with(AutorizacaoImagemContexto::FLASH_KEY, app(AutorizacaoImagemContexto::class)->paraPresenca($presenca, session()->driver(), OrigemAutorizacaoImagem::PresencaAutenticada, $user));
    }

    private function listarAtividadesCopiaveis(?Atividade $ignorar = null)
    {
        return Atividade::with('evento')
            ->withCount('inscricoes')
            ->whereHas('inscricoes')
            ->when($ignorar, fn ($q) => $q->where('id', '!=', $ignorar->id))
            ->orderByDesc('dia')
            ->orderBy('hora_inicio')
            ->get();
    }

    private function copiarInscritos(?int $origemId, Atividade $destino): int
    {
        if (! $origemId || $origemId === $destino->id) {
            return 0;
        }

        $origem = Atividade::find($origemId);
        if (! $origem) {
            return 0;
        }

        $inscricoes = Inscricao::withTrashed()
            ->where('atividade_id', $origem->id)
            ->get();

        $copiados = 0;
        foreach ($inscricoes as $inscricao) {
            $existente = Inscricao::withTrashed()
                ->where('atividade_id', $destino->id)
                ->where('participante_id', $inscricao->participante_id)
                ->first();

            if ($existente) {
                $existente->evento_id = $destino->evento_id;
                $existente->ouvinte = false;
                if ($existente->trashed()) {
                    $existente->restore();
                    $copiados++;
                }
                $existente->save();

                continue;
            }

            Inscricao::create([
                'evento_id' => $destino->evento_id,
                'atividade_id' => $destino->id,
                'participante_id' => $inscricao->participante_id,
                'ouvinte' => false,
            ]);
            $copiados++;
        }

        return $copiados;
    }

    private function mensagemSucesso(string $mensagem, int $copiados = 0): string
    {
        if ($copiados <= 0) {
            return $mensagem;
        }

        $sufixo = $copiados === 1 ? ' 1 inscrito copiado.' : " {$copiados} inscritos copiados.";

        return $mensagem.$sufixo;
    }

    public function downloadListaPresencaPdf(Request $request, Atividade $atividade)
    {
        $this->authorize('presenca.abrir');

        $dados = $request->validate([
            'tipo' => 'sometimes|string|in:assessoria,oficina,escuta_territorial,feira_pedagogica',
        ]);
        $tipoTemplate = $dados['tipo'] ?? 'assessoria';

        // puxa os dados
        $inscritos = $atividade->inscricoes()->with([
            'participante.user',
            'participante.municipio.estado',
        ])->get()->pluck('participante');

        $presentes = $atividade->presencas()->with([
            'inscricao.participante.user',
            'inscricao.participante.municipio.estado',
        ])->get()->pluck('inscricao.participante');

        $participantes = $inscritos->concat($presentes)
            ->filter()
            ->unique('id')
            ->sortBy(function ($participante) {
                $nome = mb_strtolower($participante->user->name ?? '');

                return Str::ascii($nome);
            })->values();

        try {
            $estrategia = ListaPresencaFactory::criar($tipoTemplate);
            $conteudoPdf = $estrategia->gerarPdf($atividade, $participantes);

            $fileName = 'Lista_Presenca_'.Str::slug($atividade->descricao).'.pdf';

            return response($conteudoPdf, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function downloadListaPresencaSimplesView(Atividade $atividade)
    {
        $this->authorize('presenca.abrir');

        $inscricoes = $atividade->inscricoes()->with([
            'participante.user',
            'participante.municipio.estado',
            'presencas' => fn ($q) => $q->where('atividade_id', $atividade->id),
        ])->get()->sortBy(function ($inscricao) {
            return Str::ascii(mb_strtolower($inscricao->participante->user->name ?? ''));
        })->values();

        $fileName = 'Lista_Presenca_'.Str::slug($atividade->descricao).'.pdf';

        return Pdf::view('pdf.lista-presenca-simples', [
            'atividade' => $atividade,
            'inscricoes' => $inscricoes,
        ])
            ->format('a4')
            ->withAlfaEjaBrand()
            ->download($fileName);
    }

    public function downloadListaAutorizacaoImagemPdf(Atividade $atividade)
    {
        $this->authorize('presenca.abrir');

        // logica do codigo alterada para puxar de inscrição e presença
        $inscritos = $atividade->inscricoes()->with([
            'participante.user',
            'participante.municipio.estado',
        ])->get()->pluck('participante');

        $presentes = $atividade->presencas()->with([
            'inscricao.participante.user',
            'inscricao.participante.municipio.estado',
        ])->get()->pluck('inscricao.participante');

        // unifica e ordena as incrições e presenças, tirando duplicações por id
        $participantes = $inscritos->concat($presentes)
            ->filter()
            ->unique('id')
            ->sortBy(function ($participante) {
                $nome = mb_strtolower($participante->user->name ?? '');

                return Str::ascii($nome);
            })->values();

        $templatePath = storage_path('app/templates/base_lista_autorizacao.pdf');

        if (! file_exists($templatePath)) {
            return back()->with('error', 'O template base em PDF não foi encontrado.');
        }

        $pdf = new ListaAutorizacaoImagem;
        $pdf->setBaseTemplate($templatePath);

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 30);

        $pdf->AddPage();

        $pdf->SetFont('Helvetica', '', 8);

        $contador = 1;

        if ($participantes->isEmpty()) {
            $pdf->Cell(190, 8, utf8_decode('Nenhum participante inscrito neste momento.'), 1, 1, 'C');
        } else {
            foreach ($participantes as $participante) {
                $user = $participante->user;

                $nome = utf8_decode(substr($user->name ?? '', 0, 50));
                while ($pdf->GetStringWidth($nome) > 89) {
                    $nome = substr($nome, 0, -1);
                }

                // formatando o CPF
                $cpfSujo = $participante->cpf ?? '';
                $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfSujo);
                if (strlen($cpfLimpo) === 11) {
                    $cpfFormatado = preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", '$1.$2.$3-$4', $cpfLimpo);
                } else {
                    $cpfFormatado = $cpfSujo ?: '';
                }
                $cpf = utf8_decode($cpfFormatado);

                $pdf->Cell(8, 8, $contador++, 1, 0, 'C');
                $pdf->Cell(90, 8, $nome, 1, 0, 'L');          // nome
                $pdf->Cell(45, 8, $cpf, 1, 0, 'C');           // CPF
                $pdf->Cell(47, 8, '', 1, 1, 'C');         // assinatura (em branco)
            }
        }
        $fileName = 'Lista_Autorizacao_'.Str::slug($atividade->descricao).'.pdf';

        return response($pdf->Output('S'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"');
    }

    public function diario(Atividade $atividade)
    {
        // carrega as inscrições do evento com os dados do usuário para listar na tela
        $inscricoes = $atividade->inscricoes()
            ->with(['participante.user', 'participante.municipio.estado'])
            ->get()
            ->sortBy(function ($inscricao) {
                return $inscricao->participante->user->name ?? '';
            });

        // pega apenas os IDs das inscrições que já estão marcadas como "presente" nesta atividade
        $presencasAtuais = $atividade->presencas()
            ->where('status', 'presente')
            ->pluck('inscricao_id')
            ->toArray();

        return view('atividades.diario', compact('atividade', 'inscricoes', 'presencasAtuais'));
    }

    public function salvarDiario(Request $request, Atividade $atividade)
    {
        $request->validate([
            'inscricoes' => 'nullable|array',
            'inscricoes.*' => 'exists:inscricaos,id',
        ]);

        // array com os IDs das inscrições que foram confirmadas na tela
        $presentes = $request->input('inscricoes', []);

        DB::transaction(function () use ($atividade, $presentes) {
            foreach ($presentes as $inscricaoId) {
                $atividade->presencas()->updateOrCreate(
                    ['inscricao_id' => $inscricaoId],
                    ['status' => 'presente']
                );
            }

            // quem estava como presente no banco, mas NÃO veio no array atual, significa que foi desmarcado.
            // atualizo para ausente
            if (! empty($presentes)) {
                $atividade->presencas()
                    ->whereNotIn('inscricao_id', $presentes)
                    ->where('status', 'presente')
                    ->update(['status' => 'ausente']);
            } else {
                // se o array veio vazio,todos viram ausentes
                $atividade->presencas()
                    ->where('status', 'presente')
                    ->update(['status' => 'ausente']);
            }
        });

        return redirect()->route('atividades.show', $atividade)
            ->with('success', 'Diário de presenças salvo com sucesso!');
    }
}
