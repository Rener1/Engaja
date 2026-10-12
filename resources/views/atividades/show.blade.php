@extends('layouts.app')

@section('content')
    <style>
        :root {
            --engaja: #421944;
        }

        .ev-card {
            border-radius: .8rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .06);
        }

        .ev-chip {
            display: inline-block;
            padding: .35rem .65rem;
            border-radius: 999px;
            border: 1px solid #dee2e6;
            font-size: .85rem;
        }

        .program-card {
            border: 1px solid #ececec;
            border-radius: .9rem;
            padding: 1rem;
            background: #fff;
        }

        .program-time {
            font-weight: 800;
            font-size: .95rem;
            color: #6c757d;
            letter-spacing: .3px;
        }

        .chip {
            border: 1px solid #e6e6e6;
            border-radius: 999px;
            padding: .2rem .55rem;
            font-size: .8rem;
        }
    </style>

    @php
        $ini = \Carbon\Carbon::parse($atividade->hora_inicio)->format('H:i');
        $dia = \Carbon\Carbon::parse($atividade->dia)
        ->locale('pt_BR')->translatedFormat('l, d \\d\\e F \\d\\e Y');
        $presencaAberta = $atividade->presencaEstaAberta();
    @endphp
    <div class="container py-4">

        {{-- Cabeçalho do momento --}}
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <x-header-atividade :atividade="$atividade" />
                <div class="mt-2">
                    <span class="badge {{ $presencaAberta ? 'bg-success' : 'bg-secondary' }} d-inline-flex align-items-center gap-1">
                        <i class="bi {{ $presencaAberta ? 'bi-check-circle-fill' : 'bi-lock-fill' }}"></i>
                        {{ $atividade->status_presenca_label }}
                    </span>
                </div>
            </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
                {{-- Alerta de dados demográficos pendentes --}}
                @if (session('erro_demograficos'))
                    <div class="w-100 mb-2">
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" viewBox="0 0 16 16">
                                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                            </svg>
                            <div>
                                <strong>Dados demográficos pendentes!</strong><br>
                                {{ session('erro_demograficos') }}
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Ação Principal (Sempre visível para o usuário final) --}}
                @auth
                    <form action="{{ route('atividades.presenca.checkin', $atividade) }}" method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-primary" {{ $presencaAberta ? '' : 'disabled' }}>
                            Confirmar minha presença
                        </button>
                    </form>
                @else
                    @if($presencaAberta)
                        <a class="btn btn-primary" href="{{ route('presenca.confirmar', $atividade) }}">
                            Confirmar presença
                        </a>
                    @endif
                @endauth

                {{-- menu gerenciar --}}
                @php
                    $mostrarMenuGerenciarAtividade = $podeImportar || auth()->user()?->can('presenca.abrir');
                @endphp

                @if($mostrarMenuGerenciarAtividade)
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownGerenciarAtividade"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            Gerenciar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownGerenciarAtividade">

                            @if($podeImportar)
                                <li>
                                    <a class="dropdown-item text-engaja" href="{{ route('atividades.presencas.import', $atividade) }}">
                                        Importar presenças
                                    </a>
                                </li>
                            @endif

                            @can('presenca.abrir')
                                <li>
                                    <button type="button" class="dropdown-item text-engaja" data-bs-toggle="modal" data-bs-target="#modalListaPresenca">
                                        Baixar Lista de Presença
                                    </button>
                                </li>
                                <li>
                                    <a class="dropdown-item text-engaja" href="{{ route('atividades.lista-autorizacao.pdf', $atividade) }}">
                                        Baixar Autorização de Imagem
                                    </a>
                                </li>
                                <li>
                                     <a class="dropdown-item text-engaja" href="{{ route('atividades.diario', $atividade) }}">
                                         Diário de Presenças
                                     </a>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-engaja" data-bs-toggle="modal" data-bs-target="#modalAgendamentoPresenca">
                                        </i>Agendar abertura/fechamento
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('atividades.presenca.toggle', $atividade) }}" method="POST" class="m-0">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="dropdown-item {{ $presencaAberta ? 'text-danger' : 'text-success' }}">
                                            {{ $presencaAberta ? 'Fechar presença' : 'Abrir presença' }}
                                        </button>
                                    </form>
                                </li>
                            @endcan

                        </ul>
                    </div>
                @endif
            </div>
        </div>

        @auth
            {{-- QR Code de presença --}}
            <div class="mb-4">
                <h2 class="h6 fw-bold mb-2">Confirmação de presença (QR)</h2>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="p-2 border rounded bg-white">
                        @php
                            $qrFormat = env('QR_CODE_FORMAT', 'png');
                            $qrData = QrCode::format($qrFormat)
                                ->style('round')
                                ->color(129,18,131)
                                ->eye('circle')
                                ->eyeColor(0, 0,156,209,0,156,209)
                                ->eyeColor(1, 44,181,124,44,181,124)
                                ->eyeColor(2, 192,12,142,192,12,142)
                                ->size(200)
                                ->margin(0)
                                ->merge(public_path('/images/favicon-eja.png'), 0.3, true)
                                ->errorCorrection('H')
                                ->generate(route('presenca.confirmar', $atividade));
                            $qrMime = $qrFormat === 'svg' ? 'image/svg+xml' : 'image/'.$qrFormat;
                            $qrSrc = 'data:'.$qrMime.';base64,'.base64_encode($qrData);
                        @endphp
                        <img src="{{ $qrSrc }}" alt="QR Code">
                    </div>
                </div>
            </div>
        @endauth

        @can('presenca.abrir')
            {{-- Lista de presenças --}}
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 fw-bold mb-0">Participantes com presença registrada</h2>
                    <a href="{{ route('atividades.lista-presenca-simples.pdf', $atividade) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i>Baixar lista (PDF)
                    </a>
                </div>

                @php
                    $lista = $atividade->presencas()->with([
                    'inscricao.participante.user:id,name,email',
                    'inscricao.participante.municipio.estado:id,nome,sigla'
                    ])->orderByDesc('id')->get();

                    $statusBadges = [
                        'ouvinte'     => '<span class="badge bg-info">Ouvinte</span>',
                        'presente'    => '<span class="badge bg-success">Presente</span>',
                        'ausente'     => '<span class="badge bg-secondary">Ausente</span>',
                        'justificado' => '<span class="badge bg-warning text-dark">Justificado</span>',
                    ];

                    $columns = [
                        ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
                        ['field' => 'email', 'headerName' => 'E-mail', 'flex' => 2],
                        ['field' => 'municipio', 'headerName' => 'Município', 'flex' => 2],
                        ['field' => 'status', 'headerName' => 'Status', 'minWidth' => 140, 'html' => true],
                        ['field' => 'marcado_em', 'headerName' => 'Marcado em', 'minWidth' => 140],
                    ];

                    $rows = $lista->map(function ($pr) use ($statusBadges) {
                        $p = $pr->inscricao->participante ?? null;
                        $u = $p?->user;
                        $m = $p?->municipio;
                        $uf = $m?->estado?->sigla;
                        $munLabel = $m ? ($m->nome . ($uf ? " - $uf" : "")) : '—';
                        $status = ($pr->inscricao?->ouvinte ?? false) ? 'ouvinte' : ($pr->status_participacao ?? $pr->status ?? null);

                        return [
                            'id' => $pr->id,
                            'nome' => $u->name ?? '—',
                            'email' => $u->email ?? '—',
                            'municipio' => $munLabel,
                            'status' => $statusBadges[$status] ?? '<span class="badge bg-light text-muted">—</span>',
                            'marcado_em' => optional($pr->created_at)->format('d/m/Y H:i') ?? '—',
                        ];
                    })->values();
                @endphp

                @if($lista->isEmpty())
                    <div class="ev-card p-3 text-muted">Nenhuma presença registrada para este momento.</div>
                @else
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <x-data-table
                                id="grid-presencas-{{ $atividade->id }}"
                                :columns="$columns"
                                :rows="$rows"
                                :page-size="25"
                            />
                        </div>
                    </div>
                @endif
            </div>
        @endcan

        {{-- Modal para escolher o Modelo de Lista de Presença --}}
        <div class="modal fade" id="modalListaPresenca" tabindex="-1" aria-labelledby="modalListaPresencaLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">

                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold text-engaja" id="modalListaPresencaLabel">Baixar Lista de Presença</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form action="{{ route('atividades.lista-presenca.pdf', $atividade) }}" method="GET">
                        <div class="modal-body py-4">
                            <p class="text-muted small mb-3">Selecione o modelo que deseja gerar para esta atividade:</p>

                            <div class="form-group">
                                <label for="tipoTemplate" class="form-label fw-semibold">Modelo da Lista</label>
                                <select name="tipo" id="tipoTemplate" class="form-select form-select-lg" style="border-radius: 0.6rem; font-size: 0.95rem;">
                                    <option value="assessoria">Assessoria e Formação</option>
                                    <option value="oficina">Oficina de Leitura e Escrita</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer border-top-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 0.5rem;">Cancelar</button>
                            <button type="submit" class="btn btn-engaja" style="border-radius: 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download me-1" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                Gerar PDF
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        @can('presenca.abrir')
            @php
                $sugestaoModal = \App\Support\AgendamentoPresenca::sugestao($atividade);
                $temAgendamentoAtivo = $atividade->presenca_abre_em || $atividade->presenca_fecha_em;
                $valModalAbre = old('presenca_abre_em', $temAgendamentoAtivo ? \App\Support\AgendamentoPresenca::paraInput($atividade->presenca_abre_em) : ($sugestaoModal['abre'] ?? null));
                $valModalFecha = old('presenca_fecha_em', $temAgendamentoAtivo ? \App\Support\AgendamentoPresenca::paraInput($atividade->presenca_fecha_em) : ($sugestaoModal['fecha'] ?? null));
            @endphp
            <div class="modal fade" id="modalAgendamentoPresenca" tabindex="-1" aria-labelledby="modalAgendamentoPresencaLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">

                        <div class="modal-header border-bottom-0 pb-0">
                            <h5 class="modal-title fw-bold text-engaja" id="modalAgendamentoPresencaLabel">
                                <i class="bi bi-clock-history me-1"></i> Agendar Confirmação de Presença
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <form action="{{ route('atividades.presenca.agendamento', $atividade) }}" method="POST" id="form-agendamento-presenca">
                            @csrf
                            @method('PATCH')
                            <div class="modal-body py-3">
                                <p class="text-muted small mb-3">
                                    Defina a data e a hora para abertura e/ou fechamento automático da presença neste momento (horário de Brasília).
                                </p>

                                @if ($errors->agendamentoPresenca->any())
                                    <div class="alert alert-danger py-2 small mb-3">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->agendamentoPresenca->all() as $erro)
                                                <li>{{ $erro }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label for="modal_presenca_abre_em" class="form-label fw-semibold">Abrir presença em</label>
                                    <input type="datetime-local"
                                           name="presenca_abre_em"
                                           id="modal_presenca_abre_em"
                                           value="{{ $valModalAbre }}"
                                           class="form-control @if($errors->agendamentoPresenca->has('presenca_abre_em')) is-invalid @endif">
                                    @if($errors->agendamentoPresenca->has('presenca_abre_em'))
                                        <div class="invalid-feedback">{{ $errors->agendamentoPresenca->first('presenca_abre_em') }}</div>
                                    @endif
                                    <div class="form-text">Deixe em branco se já estiver aberta ou se desejar abrir manualmente.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="modal_presenca_fecha_em" class="form-label fw-semibold">Fechar presença em</label>
                                    <input type="datetime-local"
                                           name="presenca_fecha_em"
                                           id="modal_presenca_fecha_em"
                                           value="{{ $valModalFecha }}"
                                           class="form-control @if($errors->agendamentoPresenca->has('presenca_fecha_em')) is-invalid @endif">
                                    @if($errors->agendamentoPresenca->has('presenca_fecha_em'))
                                        <div class="invalid-feedback">{{ $errors->agendamentoPresenca->first('presenca_fecha_em') }}</div>
                                    @endif
                                    <div class="form-text">Deixe em branco se desejar fechar manualmente.</div>
                                </div>
                            </div>

                            <div class="modal-footer border-top-0 pt-0 d-flex justify-content-between">
                                <div>
                                    @if($temAgendamentoAtivo)
                                        <button type="submit"
                                                form="form-limpar-agendamento"
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Tem certeza que deseja remover o agendamento?');">
                                            Remover agendamento
                                        </button>
                                    @endif
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-engaja">Salvar agendamento</button>
                                </div>
                            </div>
                        </form>

                        @if($temAgendamentoAtivo)
                            <form id="form-limpar-agendamento" action="{{ route('atividades.presenca.agendamento.limpar', $atividade) }}" method="POST" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif

                    </div>
                </div>
            </div>

            @if ($errors->agendamentoPresenca->any())
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const modalEl = document.getElementById('modalAgendamentoPresenca');
                        if (modalEl) {
                            const modal = new bootstrap.Modal(modalEl);
                            modal.show();
                        }
                    });
                </script>
            @endif
        @endcan

    </div>
@endsection
