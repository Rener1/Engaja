<?php

namespace App\Http\Controllers;

use App\Mail\CertificadoEmitidoMail;
use App\Models\Certificado;
use App\Models\Evento;
use App\Models\ModeloCertificado;
use App\Models\Participante;
use App\Models\Presenca;
use App\Support\CargaHoraria;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use ZipArchive;

class CertificadoController extends Controller
{
    public function emitir(Request $request)
    {

        $sessionKey = $request->input('session_key');
        if ($sessionKey) {
            $payload = session($sessionKey);
            if (! $payload) {
                return redirect()->route('eventos.index')->with('error', 'Sessão expirada. Tente novamente.');
            }
            $request->merge([
                'modelo_id' => $payload['modelo_id'],
                'eventos' => $payload['eventos'],
                'unificar' => $payload['unificar'] ?? false,
            ]);
        }
        $unificar = $request->boolean('unificar', false);

        $data = $request->validate([
            'modelo_id' => ['required', 'exists:modelo_certificados,id'],
            'eventos' => ['required'],
        ]);

        $eventosIds = $data['eventos'];
        if (is_string($eventosIds)) {
            $eventosIds = array_filter(explode(',', $eventosIds));
        }
        if (is_array($eventosIds)) {
            $eventosIds = array_map('intval', $eventosIds);
        } else {
            $eventosIds = [];
        }
        $eventosIds = array_unique(array_filter($eventosIds));
        if (empty($eventosIds)) {
            return back()->with('error', 'Selecione ao menos uma ação pedagógica.');
        }

        $modelo = ModeloCertificado::findOrFail($data['modelo_id']);

        $selectionMode = $request->input('selection_mode', 'ALL');
        $selectionExceptions = json_decode($request->input('selection_exceptions', '[]'), true);

        $eventos = Evento::with(['presencas.inscricao.participante.user', 'presencas.atividade'])
            ->whereIn('id', $eventosIds)
            ->get();

        $created = 0;
        $skippedZeroWorkload = 0;
        $paraNotificar = [];

        if ($unificar) {
            $todasPresencas = collect();
            foreach ($eventos as $evento) {
                $presencasValidas = $evento->presencas->filter(function ($presenca) {
                    return ($presenca->status ?? null) === 'presente' && ! $presenca->certificado_emitido && $presenca->inscricao?->participante?->id;
                });
                $presencasValidas->each(function ($p) use ($evento) {
                    $p->evento_pai = $evento;
                });
                $todasPresencas = $todasPresencas->merge($presencasValidas);
            }

            $presencasPorParticipante = $todasPresencas->groupBy(fn ($p) => $p->inscricao->participante->id);

            foreach ($presencasPorParticipante as $participanteId => $presencas) {
                $certKey = $participanteId.'_unificado';
                $isException = in_array($certKey, $selectionExceptions);

                if ($selectionMode === 'ALL' && $isException) {
                    continue;
                }
                if ($selectionMode === 'NONE' && ! $isException) {
                    continue;
                }

                $participante = $presencas->first()->inscricao?->participante;
                if (! $participante || ! $participante->user) {
                    continue;
                }

                $cargaTotal = (int) $presencas->sum(fn ($p) => (int) ($p->atividade?->carga_horaria ?? 0));
                if ($cargaTotal <= 0) {
                    $skippedZeroWorkload++;

                    continue;
                }

                $nomesEventos = $presencas->map(fn ($p) => $p->evento_pai->nome)->unique()->values();
                $eventoNomeFormatado = $this->formatarListaNomes($nomesEventos->toArray());

                $map = [
                    '%participante%' => $participante->user->name,
                    '%acao%' => $eventoNomeFormatado,
                    '%carga_horaria%' => CargaHoraria::formatMinutos($cargaTotal),
                    '%cpf%' => $this->formatarCpf($participante->cpf),
                ];

                $textoFrente = $this->renderPlaceholders($modelo->texto_frente ?? '', $map);
                $textoVerso = $this->renderPlaceholders($modelo->texto_verso ?? '', $map);

                $cert = Certificado::create([
                    'modelo_certificado_id' => $modelo->id,
                    'participante_id' => $participante->id,
                    'evento_nome' => $eventoNomeFormatado,
                    'codigo_validacao' => Str::uuid()->toString(),
                    'ano' => (int) date('Y'),
                    'texto_frente' => $textoFrente,
                    'texto_verso' => $textoVerso,
                    'carga_horaria' => $cargaTotal,
                ]);

                $eventosIdsDoParticipante = $presencas->map(fn ($p) => $p->evento_pai->id)->unique()->values()->toArray();
                $cert->eventos()->attach($eventosIdsDoParticipante);

                if (! empty($participante->user?->email)) {
                    $paraNotificar[] = [$participante->user->email, $participante->user->name, $eventoNomeFormatado, $cert->id];
                }

                foreach ($presencas as $presenca) {
                    $presenca->certificado_emitido = true;

                    // remove o atributo dinamico para nao ter loop de JSON no eloquent
                    unset($presenca->evento_pai);

                    $presenca->save();
                }
                $created++;
            }
        } else {
            foreach ($eventos as $evento) {
                // Somat?rio por participante para este evento, apenas presen?as confirmadas ainda n?o certificadas
                $presencasEvento = $evento->presencas
                    ->filter(function ($presenca) {
                        return ($presenca->status ?? null) === 'presente'
                            && ! $presenca->certificado_emitido
                            && $presenca->inscricao?->participante?->id;
                    });

                $presencasPorParticipante = $presencasEvento
                    ->groupBy(fn ($p) => $p->inscricao->participante->id);

                foreach ($presencasPorParticipante as $participanteId => $presencas) {
                    // logica do filtro de seleção para certificar ou não
                    $certKey = $participanteId.'_'.$evento->id;
                    $isException = in_array($certKey, $selectionExceptions);

                    if ($selectionMode === 'ALL' && $isException) {
                        continue;
                    }
                    if ($selectionMode === 'NONE' && ! $isException) {
                        continue;
                    }
                    // fim da lógica de seleção

                    $participante = $presencas->first()->inscricao?->participante;
                    if (! $participante || ! $participante->user) {
                        continue;
                    }

                    $cargaTotal = (int) $presencas->sum(function ($p) {
                        return (int) ($p->atividade?->carga_horaria ?? 0);
                    });

                    if ($cargaTotal <= 0) {
                        $skippedZeroWorkload++;

                        continue;
                    }

                    $map = [
                        '%participante%' => $participante->user->name,
                        '%acao%' => $evento->nome,
                        '%carga_horaria%' => CargaHoraria::formatMinutos($cargaTotal),
                        '%cpf%' => $this->formatarCpf($participante->cpf),
                    ];

                    $textoFrente = $this->renderPlaceholders($modelo->texto_frente ?? '', $map);
                    $textoVerso = $this->renderPlaceholders($modelo->texto_verso ?? '', $map);

                    $cert = Certificado::create([
                        'modelo_certificado_id' => $modelo->id,
                        'participante_id' => $participante->id,
                        'evento_nome' => $evento->nome,
                        'codigo_validacao' => Str::uuid()->toString(),
                        'ano' => (int) ($evento->data_inicio ? date('Y', strtotime($evento->data_inicio)) : date('Y')),
                        'texto_frente' => $textoFrente,
                        'texto_verso' => $textoVerso,
                        'carga_horaria' => $cargaTotal,
                    ]);
                    $cert->eventos()->attach([$evento->id]);

                    if (! empty($participante->user?->email)) {
                        $paraNotificar[] = [$participante->user->email, $participante->user->name, $evento->nome, $cert->id];
                    }

                    // Marca todas as presen?as deste participante no evento como certificadas
                    foreach ($presencas as $presenca) {
                        $presenca->certificado_emitido = true;
                        $presenca->save();
                    }

                    $created++;
                }
            }
        }

        $this->notificarLote($paraNotificar);

        $message = "{$created} certificado(s) emitidos com sucesso.";
        if ($skippedZeroWorkload > 0) {
            $message .= " {$skippedZeroWorkload} certificado(s) não emitido(s) por carga horária total igual a 0.";
        }

        if ($sessionKey) {
            session()->forget($sessionKey);
        }

        return redirect()
            ->route('eventos.index')
            ->with('success', $message);
    }

    public function prepararEmissao(Request $request)
    {
        $data = $request->validate([
            'modelo_id' => ['required', 'exists:modelo_certificados,id'],
            'eventos' => ['required'],
            'unificar' => ['nullable', 'boolean'],
        ]);

        $sessionKey = 'emissao_certificados_'.Str::uuid();
        session([$sessionKey => [
            'modelo_id' => $data['modelo_id'],
            'eventos' => $data['eventos'],
            'unificar' => $request->boolean('unificar', false),
        ]]);

        return redirect()->route('certificados.emitir.preview_lista', ['session_key' => $sessionKey]);
    }

    public function previewLista(Request $request)
    {

        $sessionKey = $request->query('session_key');
        $payload = session($sessionKey);

        if (! $payload) {
            return redirect()->route('eventos.index')->with('error', 'Sessão expirada. Refaça a seleção das ações pedagógicas.');
        }

        $modelo = ModeloCertificado::findOrFail($payload['modelo_id']);

        $eventosIds = array_unique(array_filter(array_map('intval', explode(',', $payload['eventos']))));
        $eventos = Evento::with(['presencas.inscricao.participante.user', 'presencas.atividade'])
            ->whereIn('id', $eventosIds)
            ->get()
            ->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE);

        $previewData = collect();
        $skippedZeroWorkload = 0;
        $unificar = $payload['unificar'] ?? false;

        if ($unificar) {
            // logica de unificar certificados
            $todasPresencas = collect();

            // junto todas as presenças válidas de todos os eventos
            foreach ($eventos as $evento) {
                $presencasValidas = $evento->presencas->filter(function ($presenca) {
                    return ($presenca->status ?? null) === 'presente'
                        && ! $presenca->certificado_emitido
                        && $presenca->inscricao?->participante?->id;
                });

                // injeto a referência do evento pai para usar o nome depois
                $presencasValidas->each(function ($p) use ($evento) {
                    $p->evento_pai = $evento;
                });

                $todasPresencas = $todasPresencas->merge($presencasValidas);
            }

            // agrupo globalmente pelo participante
            $presencasPorParticipante = $todasPresencas->groupBy(fn ($p) => $p->inscricao->participante->id);
            $participantesUnificados = collect();

            foreach ($presencasPorParticipante as $participanteId => $presencas) {
                $participante = $presencas->first()->inscricao?->participante;
                if (! $participante || ! $participante->user) {
                    continue;
                }

                $cargaTotal = (int) $presencas->sum(fn ($p) => (int) ($p->atividade?->carga_horaria ?? 0));
                if ($cargaTotal <= 0) {
                    $skippedZeroWorkload++;

                    continue;
                }

                // aqui resgata os nomes únicos dos eventos que ele participou
                $nomesEventos = $presencas->map(fn ($p) => $p->evento_pai->nome)->unique()->values();
                $eventoNomeFormatado = $this->formatarListaNomes($nomesEventos->toArray());

                $participantesUnificados->push([
                    'id' => $participante->id.'_unificado',
                    'nome' => $participante->user->name,
                    'email' => $participante->user->email,
                    'cpf' => $participante->cpf ?? '-',
                    'carga_horaria' => CargaHoraria::formatMinutos($cargaTotal),
                    'evento_nome' => $eventoNomeFormatado,
                ]);
            }

            $participantesOrdenados = $participantesUnificados->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)->values();
            $previewData = $previewData->merge($participantesOrdenados);
        } else {

            foreach ($eventos as $evento) {
                $presencasEvento = $evento->presencas->filter(function ($presenca) {
                    return ($presenca->status ?? null) === 'presente'
                        && ! $presenca->certificado_emitido
                        && $presenca->inscricao?->participante?->id;
                });

                $presencasPorParticipante = $presencasEvento->groupBy(fn ($p) => $p->inscricao->participante->id);

                $participantesDestaAcao = collect();

                foreach ($presencasPorParticipante as $participanteId => $presencas) {
                    $participante = $presencas->first()->inscricao?->participante;
                    if (! $participante || ! $participante->user) {
                        continue;
                    }

                    $cargaTotal = (int) $presencas->sum(fn ($p) => (int) ($p->atividade?->carga_horaria ?? 0));

                    if ($cargaTotal <= 0) {
                        $skippedZeroWorkload++;

                        continue;
                    }

                    $participantesDestaAcao->push([
                        'id' => $participante->id.'_'.$evento->id,
                        'nome' => $participante->user->name,
                        'email' => $participante->user->email,
                        'cpf' => $participante->cpf ?? '-',
                        'carga_horaria' => CargaHoraria::formatMinutos($cargaTotal),
                        'evento_nome' => $evento->nome,
                    ]);
                }

                $participantesOrdenados = $participantesDestaAcao->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)->values();

                $previewData = $previewData->merge($participantesOrdenados);
            }
        }

        // paginação
        $perPage = 50;
        $page = (int) max(1, $request->query('page', 1));
        $slice = $previewData->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $previewData->count(),
            $perPage,
            $page,
            [
                'path' => route('certificados.emitir.preview_lista'),
                'query' => ['session_key' => $sessionKey],
            ]
        );

        return view('certificados.preview_lista', [
            'paginator' => $paginator,
            'sessionKey' => $sessionKey,
            'totalProntos' => $previewData->count(),
            'skippedZeroWorkload' => $skippedZeroWorkload,
            'modelo' => $modelo,
        ]);
    }

    private function notificarLote(array $paraNotificar): void
    {
        // Limitamos a ~2 e-mails/segundo (100 e-mails em ~50s) e aguardamos completar 60s antes do próximo bloco.
        $chunks = collect($paraNotificar)->chunk(100);
        foreach ($chunks as $chunkIndex => $chunk) {
            $base = Carbon::now()->addSeconds($chunkIndex * 60);
            foreach ($chunk->values() as $i => [$email, $nome, $acao, $certId]) {
                // Dois e-mails por segundo: delay incremental a cada par.
                $pairDelay = intdiv($i, 2); // 0,0,1,1,2,2...
                $scheduleAt = $base->copy()->addSeconds($pairDelay);
                Mail::to($email)->later($scheduleAt, new CertificadoEmitidoMail($nome, $acao, $certId));
            }
        }
    }

    public function emitirPorParticipantes(Request $request)
    {
        $data = $request->validate([
            'modelo_id' => ['required', 'exists:modelo_certificados,id'],
            'participantes' => ['sometimes', 'array'],
            'participantes.*' => ['integer'],
            'select_all_pages' => ['sometimes', 'boolean'],
        ]);

        $modelo = ModeloCertificado::findOrFail($data['modelo_id']);
        $participantesIds = array_unique(array_filter($data['participantes'] ?? []));
        $selectAllPages = (bool) ($data['select_all_pages'] ?? false);

        // Busca presen?as pendentes, filtrando se houver sele??o
        $presencasPendentes = Presenca::with(['atividade.evento', 'inscricao.participante.user'])
            ->where('status', 'presente')
            ->where('certificado_emitido', false)
            ->when(! empty($participantesIds) && ! $selectAllPages, function ($q) use ($participantesIds) {
                $q->whereHas('inscricao.participante', fn ($sub) => $sub->whereIn('id', $participantesIds));
            })
            ->whereHas('inscricao.participante') // garante participante
            ->get()
            ->filter(fn ($p) => $p->atividade?->evento); // garante evento carregado

        $created = 0;
        $skippedZeroWorkload = 0;
        $paraNotificar = [];

        // Agrupa por participante para emitir um cert por evento
        $presencasPorParticipante = $presencasPendentes->groupBy(fn ($p) => $p->inscricao->participante->id);

        foreach ($presencasPorParticipante as $participanteId => $presencasDoParticipante) {
            $participante = $presencasDoParticipante->first()->inscricao->participante;
            if (! $participante || ! $participante->user) {
                continue;
            }

            $presencasPorEvento = $presencasDoParticipante->groupBy(fn ($p) => $p->atividade->evento_id);

            foreach ($presencasPorEvento as $eventoId => $presencasEvento) {
                $evento = $presencasEvento->first()->atividade->evento;
                if (! $evento) {
                    continue;
                }

                $cargaTotal = (int) $presencasEvento->sum(function ($p) {
                    return (int) ($p->atividade?->carga_horaria ?? 0);
                });

                if ($cargaTotal <= 0) {
                    $skippedZeroWorkload++;

                    continue;
                }

                $map = [
                    '%participante%' => $participante->user->name,
                    '%acao%' => $evento->nome,
                    '%carga_horaria%' => CargaHoraria::formatMinutos($cargaTotal),
                    '%cpf%' => $this->formatarCpf($participante->cpf),
                ];

                $textoFrente = $this->renderPlaceholders($modelo->texto_frente ?? '', $map);
                $textoVerso = $this->renderPlaceholders($modelo->texto_verso ?? '', $map);

                $cert = Certificado::create([
                    'modelo_certificado_id' => $modelo->id,
                    'participante_id' => $participante->id,
                    'evento_nome' => $evento->nome,
                    'codigo_validacao' => Str::uuid()->toString(),
                    'ano' => (int) ($evento->data_inicio ? date('Y', strtotime($evento->data_inicio)) : date('Y')),
                    'texto_frente' => $textoFrente,
                    'texto_verso' => $textoVerso,
                    'carga_horaria' => $cargaTotal,
                ]);
                $cert->eventos()->attach([$evento->id]);

                if (! empty($participante->user?->email)) {
                    $paraNotificar[] = [$participante->user->email, $participante->user->name, $evento->nome, $cert->id];
                }

                foreach ($presencasEvento as $presenca) {
                    $presenca->certificado_emitido = true;
                    $presenca->save();
                }

                $created++;
            }
        }

        // $this->notificarLote($paraNotificar);

        $message = "{$created} certificado(s) emitidos com sucesso.";
        if ($skippedZeroWorkload > 0) {
            $message .= " {$skippedZeroWorkload} certificado(s) não emitido(s) por carga horária total igual a 0.";
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    private function renderPlaceholders(string $texto, array $map): string
    {
        return strtr($texto, $map);
    }

    private function formatarCpf(?string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', (string) $cpf);

        if (strlen($digits) !== 11) {
            return $cpf ?? '';
        }

        return (string) preg_replace(
            '/^(\d{3})(\d{3})(\d{3})(\d{2})$/',
            '$1.$2.$3-$4',
            $digits
        );
    }

    public function show(Certificado $certificado)
    {
        if (! $this->usuarioPodeBaixarCertificado($certificado)) {
            abort(403);
        }

        $certificado->load('modelo');

        return view('certificados.show', compact('certificado'));
    }

    public function validar(string $codigo)
    {
        $certificado = Certificado::with('modelo', 'participante.user')
            ->where('codigo_validacao', $codigo)
            ->firstOrFail();

        return view('certificados.validacao', compact('certificado'));
    }

    public function download(Certificado $certificado)
    {
        if (! $this->usuarioPodeBaixarCertificado($certificado)) {
            abort(403);
        }

        $certificado->load('modelo');
        $fileName = 'certificado-'.$certificado->id.'.pdf';

        return $this->certificadoPdf($certificado)->download($fileName);
    }

    /**
     * Monta o PdfBuilder do certificado com a configuração de página compartilhada
     * pelos pontos de download/zip/preview.
     *
     * `preferCSSPageSize` alinha a folha do PDF ao `@page` do CSS (A4 paisagem,
     * 297x210mm). Sem isso o Puppeteer gera a folha alguns décimos de mm maior que
     * a área pintada, deixando uma faixa branca fina nas bordas do certificado.
     */
    private function certificadoPdf(Certificado $certificado): PdfBuilder
    {
        return Pdf::view('certificados.pdf', ['certificado' => $certificado])
            ->format('a4')
            ->landscape()
            ->margins(0, 0, 0, 0)
            ->withBrowsershot(fn (Browsershot $browsershot) => $browsershot->setOption('preferCSSPageSize', true));
    }

    private function usuarioPodeBaixarCertificado(Certificado $certificado): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $isOwner = $certificado->participante_id === optional($user->participante)->id;
        if ($isOwner) {
            return true;
        }

        if ($user->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica'])) {
            return true;
        }

        if (! $user->hasRole('articulador')) {
            return false;
        }

        return $this->aplicarEscopoCertificadosDoUsuario(Certificado::query(), $user)
            ->whereKey($certificado->id)
            ->exists();
    }

    public function downloadZipEmitidos(Request $request)
    {
        $certificados = $this->certificadosEmitidosQuery($request)
            ->latest()
            ->get();

        if ($certificados->isEmpty()) {
            return back()->with('error', 'Nenhum certificado encontrado para baixar com os filtros atuais.');
        }

        return $this->downloadZipCertificados($certificados, 'certificados-emitidos-'.now()->format('Ymd_His').'.zip');
    }

    public function emitidos(Request $request)
    {
        $filtroParticipante = trim($request->query('participante', ''));
        $filtroAcao = trim($request->query('acao', ''));
        $filtroEventoId = $request->query('evento_id');
        $contextoEvento = $request->query('contexto') === 'evento';

        $query = Certificado::with(['participante.user', 'modelo']);

        if ($filtroParticipante) {
            $termoParticipante = $this->normalizarBuscaTexto($filtroParticipante);
            $cpfParticipante = preg_replace('/\D+/', '', $filtroParticipante);

            $query->whereHas('participante', function ($q) use ($termoParticipante, $cpfParticipante) {
                $q->where(function ($participanteQuery) use ($termoParticipante, $cpfParticipante) {
                    if ($termoParticipante !== '') {
                        $participanteQuery->whereHas('user', function ($userQuery) use ($termoParticipante) {
                            $userQuery->whereRaw(
                                "translate(lower(name), 'áàâãäåéèêëíìîïóòôõöúùûüçñ', 'aaaaaaeeeeiiiiooooouuuucn') like ?",
                                ['%'.$termoParticipante.'%']
                            );
                        });
                    }

                    if ($cpfParticipante !== '') {
                        $method = $termoParticipante !== '' ? 'orWhereRaw' : 'whereRaw';
                        $participanteQuery->{$method}(
                            "regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') like ?",
                            ['%'.$cpfParticipante.'%']
                        );
                    }
                });
            });
        }

        if ($filtroEventoId) {
            $query->whereHas('eventos', function ($q) use ($filtroEventoId) {
                $q->where('eventos.id', (int) $filtroEventoId);
            });
        }

        if ($filtroAcao) {
            $query->where('evento_nome', 'ilike', "%{$filtroAcao}%");
        }

        $this->aplicarEscopoCertificadosDoUsuario($query, auth()->user());

        $acoesCertificado = Evento::query()
            ->when(auth()->user()?->hasRole('articulador'), function ($query) {
                $municipioId = optional(auth()->user()?->participante)->municipio_id;
                if (! $municipioId) {
                    return $query->whereRaw('1 = 0');
                }

                $query->whereHas('atividades', function ($atividadeQuery) use ($municipioId) {
                    $atividadeQuery->where('municipio_id', $municipioId)
                        ->orWhereHas('municipios', fn ($municipioQuery) => $municipioQuery->whereKey($municipioId));
                });
            })
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $certificados = $query->latest()
            ->paginate(20)
            ->appends([
                'participante' => $filtroParticipante,
                'acao' => $filtroAcao,
                'evento_id' => $filtroEventoId,
                'contexto' => $contextoEvento ? 'evento' : null,
            ]);

        return view('certificados.emitidos', compact('certificados', 'filtroParticipante', 'filtroAcao', 'filtroEventoId', 'contextoEvento', 'acoesCertificado'));
    }

    private function certificadosEmitidosQuery(Request $request)
    {
        $query = Certificado::with(['participante.user', 'modelo']);

        $filtroParticipante = trim($request->query('participante', ''));
        $filtroAcao = trim($request->query('acao', ''));
        $filtroEventoId = $request->query('evento_id');

        if ($filtroParticipante) {
            $termoParticipante = $this->normalizarBuscaTexto($filtroParticipante);
            $cpfParticipante = preg_replace('/\D+/', '', $filtroParticipante);

            $query->whereHas('participante', function ($q) use ($termoParticipante, $cpfParticipante) {
                $q->where(function ($participanteQuery) use ($termoParticipante, $cpfParticipante) {
                    if ($termoParticipante !== '') {
                        $participanteQuery->whereHas('user', function ($userQuery) use ($termoParticipante) {
                            $userQuery->whereRaw(
                                "translate(lower(name), 'Ã¡Ã Ã¢Ã£Ã¤Ã¥Ã©Ã¨ÃªÃ«Ã­Ã¬Ã®Ã¯Ã³Ã²Ã´ÃµÃ¶ÃºÃ¹Ã»Ã¼Ã§Ã±', 'aaaaaaeeeeiiiiooooouuuucn') like ?",
                                ['%'.$termoParticipante.'%']
                            );
                        });
                    }

                    if ($cpfParticipante !== '') {
                        $method = $termoParticipante !== '' ? 'orWhereRaw' : 'whereRaw';
                        $participanteQuery->{$method}(
                            "regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') like ?",
                            ['%'.$cpfParticipante.'%']
                        );
                    }
                });
            });
        }

        if ($filtroEventoId) {
            $query->whereHas('eventos', function ($q) use ($filtroEventoId) {
                $q->where('eventos.id', (int) $filtroEventoId);
            });
        }

        if ($filtroAcao) {
            $query->where('evento_nome', 'ilike', "%{$filtroAcao}%");
        }

        return $this->aplicarEscopoCertificadosDoUsuario($query, auth()->user());
    }

    private function aplicarEscopoCertificadosDoUsuario($query, $user)
    {
        if (! $user || ! $user->hasRole('articulador')) {
            return $query;
        }

        $municipioId = optional($user->participante)->municipio_id;
        if (! $municipioId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($subQuery) use ($municipioId) {
            $subQuery->selectRaw('1')
                ->from('presencas')
                ->join('inscricaos', 'inscricaos.id', '=', 'presencas.inscricao_id')
                ->join('atividades', 'atividades.id', '=', 'presencas.atividade_id')
                ->join('eventos', 'eventos.id', '=', 'atividades.evento_id')
                ->join('certificado_evento', 'certificado_evento.evento_id', '=', 'eventos.id')
                ->leftJoin('atividade_municipio', 'atividade_municipio.atividade_id', '=', 'atividades.id')
                ->whereColumn('inscricaos.participante_id', 'certificados.participante_id')
                ->whereColumn('certificado_evento.certificado_id', 'certificados.id')
                ->where('presencas.status', 'presente')
                ->whereNull('presencas.deleted_at')
                ->whereNull('inscricaos.deleted_at')
                ->whereNull('atividades.deleted_at')
                ->where(function ($cidadeQuery) use ($municipioId) {
                    $cidadeQuery->where('atividades.municipio_id', $municipioId)
                        ->orWhere('atividade_municipio.municipio_id', $municipioId);
                });
        });
    }

    private function downloadZipCertificados($certificados, string $nomeZip)
    {
        if (! class_exists(ZipArchive::class)) {
            return back()->with('error', 'A extensao ZIP do PHP nao esta habilitada neste ambiente.');
        }

        $zipPath = tempnam(storage_path('app'), 'certificados-');
        if ($zipPath === false) {
            return back()->with('error', 'Nao foi possivel preparar o arquivo ZIP.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);

            return back()->with('error', 'Nao foi possivel criar o arquivo ZIP.');
        }

        $nomesUsados = [];
        foreach ($certificados as $certificado) {
            $pdfConteudo = base64_decode(
                $this->certificadoPdf($certificado)->base64(),
                true
            );

            if ($pdfConteudo === false) {
                continue;
            }

            $zip->addFromString($this->nomeArquivoCertificado($certificado, $nomesUsados), $pdfConteudo);
        }

        $totalArquivos = $zip->numFiles;
        $zip->close();

        if ($totalArquivos === 0) {
            @unlink($zipPath);

            return back()->with('error', 'Nao foi possivel gerar os PDFs dos certificados.');
        }

        return response()->download($zipPath, $nomeZip, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function nomeArquivoCertificado(Certificado $certificado, array &$nomesUsados): string
    {
        $participante = Str::slug($certificado->participante?->user?->name ?: 'participante');
        $base = 'certificado-'.$participante.'-'.$certificado->id;
        $nome = $base.'.pdf';
        $contador = 2;

        while (isset($nomesUsados[$nome])) {
            $nome = $base.'-'.$contador.'.pdf';
            $contador++;
        }

        $nomesUsados[$nome] = true;

        return $nome;
    }

    private function normalizarBuscaTexto(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $normalizado = iconv('UTF-8', 'ASCII//TRANSLIT', $valor);

        if ($normalizado !== false) {
            $valor = $normalizado;
        }

        return trim(preg_replace('/[^a-z0-9\s]+/', ' ', $valor) ?? $valor);
    }

    public function edit(Certificado $certificado)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['administrador', 'gerente'])) {
            abort(403);
        }

        $certificado->load(['participante.user', 'modelo']);

        return view('certificados.edit', compact('certificado'));
    }

    public function update(Request $request, Certificado $certificado)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['administrador', 'gerente'])) {
            abort(403);
        }

        $data = $request->validate([
            'texto_frente' => ['required', 'string'],
            'texto_verso' => ['nullable', 'string'],
        ]);

        $certificado->update([
            'texto_frente' => $data['texto_frente'],
            'texto_verso' => $data['texto_verso'] ?? null,
        ]);

        return redirect()
            ->route('certificados.emitidos')
            ->with('success', 'Certificado atualizado com sucesso.');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'modelo_id' => ['required', 'exists:modelo_certificados,id'],
            'eventos' => ['nullable', 'string'],
        ]);

        $modelo = ModeloCertificado::findOrFail($request->modelo_id);
        $eventos = collect(explode(',', (string) $request->eventos))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $eventoNome = 'Ação pedagógica';
        if ($eventos->count()) {
            $evento = Evento::find($eventos->first());
            if ($evento) {
                $eventoNome = $evento->nome;
            }
        }

        $map = [
            '%participante%' => '[NOME DO PARTICIPANTE]',
            '%acao%' => '[NOME DA AÇÃO PEDAGÓGICA]',
            '%carga_horaria%' => CargaHoraria::formatMinutos(600),
            '%cpf%' => '[CPF DO PARTICIPANTE]',
        ];

        $certificado = new Certificado;
        $certificado->modelo = $modelo;
        $certificado->texto_frente = strtr($modelo->texto_frente ?? '', $map);
        $certificado->texto_verso = strtr($modelo->texto_verso ?? '', $map);
        $certificado->evento_nome = $eventoNome;
        $certificado->codigo_validacao = Str::uuid()->toString();
        $certificado->carga_horaria = 600;

        return $this->certificadoPdf($certificado)->inline('certificado-preview.pdf');
    }

    private function formatarListaNomes(array $nomes): string
    {
        if (count($nomes) === 0) {
            return '';
        }
        if (count($nomes) === 1) {
            return $nomes[0];
        }
        $ultimo = array_pop($nomes);

        return implode(', ', $nomes).' e '.$ultimo;
    }
}
