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

  .ev-icon {
    width: 48px;
    height: 48px;
    border-radius: .75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef2e6;
  }

  .ev-chip {
    display: inline-block;
    padding: .35rem .65rem;
    border-radius: 999px;
    border: 1px solid #dee2e6;
    font-size: .85rem;
  }

  .nav-day .nav-link {
    border-radius: 999px;
  }

  .nav-day .nav-link.active {
    background: var(--engaja);
    color: #fff;
  }

  .program-sec {
    position: relative;
  }

  .day-tabs {
    overflow: auto;
    white-space: nowrap;
    gap: .5rem;
  }

  .day-tabs .nav-link {
    border-radius: 999px;
    padding: .4rem .9rem;
    font-weight: 600;
    border: 1px solid #e7e7e7;
    color: #333;
  }

  .day-tabs .nav-link.active {
    background: var(--engaja);
    color: #fff;
    border-color: var(--engaja);
  }

  .timeline {
    position: relative;
    padding-left: 2.25rem;
  }

  .timeline::before {
    content: "";
    position: absolute;
    left: 1rem;
    top: .25rem;
    bottom: .25rem;
    width: 2px;
    background: linear-gradient(#ececec, #d9d9d9);
  }

  .t-item {
    position: relative;
    margin-bottom: 1rem;
  }

  .t-dot {
    display: none;
  }

  .program-card {
    border: 1px solid #ececec;
    border-radius: .9rem;
    padding: 1rem;
    transition: transform .15s ease, box-shadow .15s ease;
    background: #fff;
  }

  .program-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
  }

  .program-time {
    font-weight: 800;
    font-size: .95rem;
    color: #6c757d;
    letter-spacing: .3px;
  }

  .program-title {
    font-weight: 700;
    margin: .15rem 0 .35rem;
  }

  .program-meta {
    font-size: .85rem;
    color: #6c757d;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
  }

  .chip {
    border: 1px solid #e6e6e6;
    border-radius: 999px;
    padding: .2rem .55rem;
    font-size: .8rem;
  }

  .actions .btn {
    padding: .25rem .5rem;
  }

  .empty-state {
    border: 1px dashed #d8d8d8;
    border-radius: .9rem;
    padding: 1.25rem;
    text-align: center;
    color: #6c757d;
  }
</style>

@php
$participanteId = optional(auth()->user()?->participante)->id;
$jaInscrito = false;
if ($participanteId) {
$jaInscrito = \Illuminate\Support\Facades\DB::table('inscricaos')
->where('evento_id', $evento->id)
->where('participante_id', $participanteId)
->whereNull('deleted_at')
->exists();
}
@endphp
@php
  $dataInicio = $evento->data_inicio ? \Carbon\Carbon::parse($evento->data_inicio) : null;
  $dataFim = $evento->data_fim ? \Carbon\Carbon::parse($evento->data_fim) : null;
  $mesmoDia = $dataInicio && $dataFim && $dataInicio->isSameDay($dataFim);
@endphp
@php
  $mostrarMenuGerenciar = auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica', 'articulador'])
    || auth()->user()?->can('update', $evento);
@endphp

<x-ui.page-hero eyebrow="Ação pedagógica" eyebrow-icon="calendar-event" :title="$evento->nome">
  <x-slot:media>
    <img src="{{ $evento->imagem ? asset('storage/' . $evento->imagem) : asset('images/logo-aeb.png') }}" alt="Capa do evento">
  </x-slot:media>
  <ul class="list-unstyled mb-0">
    @if($dataInicio || $dataFim)
    <li class="mb-1">
      📅
      {{ $dataInicio ? $dataInicio->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') : 'Início não informado' }}
      @if($dataFim && !$mesmoDia)
      <br><small class="text-muted">Até {{ $dataFim->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }}</small>
      @endif
    </li>
    @endif

    @if(!empty($evento->local))
    <li class="mb-1">📍 {{ $evento->local }}</li>
    @endif

    @if($evento->modalidade)
    <li class="mb-1">🛰️ {{ ucfirst($evento->modalidade) }}</li>
    @endif

    @if($evento->user?->name)
    <li class="mb-1">👤 Organizado por: {{ $evento->user->name }}</li>
    @endif
  </ul>
  <x-slot:actions>
    @if($evento->link)
    <a href="{{ $evento->link }}" target="_blank" class="eg-hero-btn">Acessar link</a>
    @endif

    <a href="{{ route('eventos.planejamento.pdf', $evento) }}" target="_blank" rel="noopener noreferrer" class="eg-hero-btn">
      <i class="bi bi-file-earmark-pdf-fill me-1"></i> Ver planejamento da ação
    </a>

    <a href="{{ route('eventos.planejamento.pdf', ['evento' => $evento, 'formato' => 'docx']) }}" class="eg-hero-btn">
      <i class="bi bi-file-earmark-word me-1"></i> Planejamento (Word)
    </a>

    @hasanyrole('administrador|gerente|eq_pedagogica')
      <a href="{{ route('inscricoes.selecionar', $evento)}}" class="eg-hero-btn eg-hero-btn--solid">Inscrever participantes</a>
    @endhasanyrole

    @can('participante.ver')
      <a href="{{ route('inscricoes.inscritos', $evento) }}" class="eg-hero-btn">Ver inscritos</a>
    @endcan

      @if($mostrarMenuGerenciar)
      <div class="dropdown">
        <button class="eg-hero-btn dropdown-toggle" type="button" id="dropdownGerenciarEvento"
          data-bs-toggle="dropdown" aria-expanded="false">
          Gerenciar
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownGerenciarEvento">
          @hasanyrole('administrador|gerente|eq_pedagogica')
            <li>
              <a class="dropdown-item" href="{{ route('inscricoes.import', $evento)}}">Importar participantes</a>
            </li>
            <li>
              <a class="dropdown-item" href="{{ route('inscricoes.moodle.import', $evento)}}">Importação Moodle</a>
            </li>
          @endhasanyrole

          @role('administrador|gerente|eq_pedagogica|articulador')
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="{{ route('dashboards.presencas', ['evento_id' => $evento->id]) }}">
                Relação de presentes/ausentes na ação
              </a>
            </li>
            <li>
              @hasanyrole('administrador|gerente|eq_pedagogica')
              <a class="dropdown-item" href="{{ route('eventos.avaliacoes.consolidado', $evento) }}">
                Consolidação de avaliações
              </a>
              @endhasrole
            </li>
            <li>
              <button type="button" class="dropdown-item" data-bs-toggle="modal"
                data-bs-target="#modalRelatoriosEvento">
                Relatórios de participantes
              </button>
            </li>

          @hasanyrole('administrador|gerente')
            <li>
              <a class="dropdown-item" href="{{ route('certificados.emitidos', ['evento_id' => $evento->id, 'contexto' => 'evento']) }}">
                Certificados
              </a>
            </li>
          @endhasanyrole

          <li><hr class="dropdown-divider"></li>
          <li>
            <button type="button" class="dropdown-item"
              data-bs-toggle="modal" data-bs-target="#modalCronogramaAgrupamento">
              <i class="fas fa-file-pdf text-danger me-1"></i>Cronograma da ação
            </button>
          </li>
          @endrole

          @can('update', $evento)
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="{{ route('eventos.edit', $evento) }}">Editar</a>
            </li>
            @role('administrador')
              <li>
                <form action="{{ route('eventos.destroy', $evento) }}" method="POST"
                  class="m-0" data-confirm="Tem certeza que deseja excluir esta ação pedagógica?">
                  @csrf @method('DELETE')
                  <button type="submit" class="dropdown-item text-danger">Excluir</button>
                </form>
              </li>
            @endrole
          @endcan
        </ul>
      </div>
    @endif
  </x-slot:actions>
</x-ui.page-hero>

<div>

  {{-- Chips (Modificado para Ação Geral e Sub-ação) --}}
  @php
  $totalInscritos = $evento->participantes()->wherePivotNull('deleted_at')->count();
  @endphp
  <div class="mb-4">
    <div class="d-flex flex-wrap gap-2">
      @if($evento->acao_geral)
      <span class="ev-chip">
        Ação: <strong class="ms-1" title="{{ \App\Models\Evento::ACOES_GERAIS[$evento->acao_geral] ?? '' }}">
            Ação Geral {{ $evento->acao_geral }}
        </strong>
      </span>
      @endif
      @if($evento->subacao)
      <span class="ev-chip">
          Sub-Ação: <strong class="ms-1" title="{{ $evento->subacao }}">
            {{ \Illuminate\Support\Str::limit($evento->subacao, 50) }}
          </strong>
      </span>
      @endif
      @if($evento->tipo)
      <span class="ev-chip">Tipo: <strong class="ms-1">{{ $evento->tipo }}</strong></span>
      @endif
      @if($dataInicio || $dataFim)
      @php
        $chipInicio = $dataInicio ? $dataInicio->format('d/m/Y') : null;
        $chipFim = $dataFim && !$mesmoDia ? $dataFim->format('d/m/Y') : null;
      @endphp
      <span class="ev-chip">
        Período:
        <strong class="ms-1">{{ $chipInicio ?? '—' }}</strong>
        @if($chipFim)
        <span class="text-muted px-1">até</span>
        <strong>{{ $chipFim }}</strong>
        @endif
      </span>
      @endif
      @if($evento->modalidade)
      <span class="ev-chip">Modalidade: <strong class="ms-1">{{ $evento->modalidade }}</strong></span>
      @endif
      <span class="ev-chip">Inscritos: <strong class="ms-1">{{ $totalInscritos }}</strong></span>
    </div>
  </div>

  @if($evento->objetivos_gerais)
  <div class="mb-4">
    <h2 class="h5 fw-bold mb-2">Objetivos Gerais</h2>
    <div class="ev-card p-3">
      <p class="mb-0">{{ $evento->objetivos_gerais }}</p>
    </div>
  </div>
  @endif

    {{-- Descrição / Objetivo --}}
  @if($evento->objetivos_especificos)
      <div class="mb-4">
          <h2 class="h5 fw-bold mb-2">Objetivos Específicos</h2>
          <div class="ev-card p-3">
              <p class="mb-0">{{ $evento->objetivos_especificos }}</p>
          </div>
      </div>
  @endif

  {{-- Programação --}}
  @php
  use Carbon\Carbon;
  $porDia = $evento->atividades
  ->sortBy(fn($a) => Carbon::parse($a->dia)->toDateString() . ' ' . Carbon::parse($a->hora_inicio)->format('H:i'))
  ->groupBy(fn($a) => Carbon::parse($a->dia)->toDateString());
  $dias = $porDia->keys()->values();
  @endphp

  <div class="program-sec mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h5 fw-bold mb-0">Programação</h2>

      <div class="d-flex gap-2">
        @hasanyrole('administrador|gerente|eq_pedagogica')
        <button type="button"
                class="btn btn-engaja btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalChecklistPreAcao">
            + Novo momento
        </button>
        @endhasanyrole

        <a href="{{ route('eventos.atividades.index', $evento) }}" class="btn btn-outline-secondary btn-sm">
          Ver todos
        </a>
      </div>
    </div>

    @if($porDia->isNotEmpty())
    <ul class="nav day-tabs mb-3" role="tablist">
      @foreach($dias as $i => $dia)
      @php
      $label = \Carbon\Carbon::parse($dia)
      ->locale('pt_BR')
      ->translatedFormat('D\, j M \d\e Y'); // ex.: "seg • 9 de set"
      @endphp
      <li class="nav-item" role="presentation">
        <button class="nav-link {{ $i === 0 ? 'active' : '' }}" id="tab-{{ $i }}" data-bs-toggle="pill"
          data-bs-target="#pane-{{ $i }}" type="button" role="tab" aria-controls="pane-{{ $i }}"
          aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
          {{ $label }}
        </button>
      </li>
      @endforeach
    </ul>
    @endif

    <div class="tab-content">
      @if($porDia->isEmpty())
      <div class="empty-state">
        <div class="mb-1" style="font-size:1.6rem">🗓️</div>
        Nenhum momento cadastrado ainda.
      </div>
      @else
      @foreach($dias as $i => $dia)
      @php $lista = $porDia[$dia]; @endphp

      <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="pane-{{ $i }}" role="tabpanel"
        aria-labelledby="tab-{{ $i }}">
        <div class="timeline">
          @foreach($lista as $at)
          @php

          $ini = \Carbon\Carbon::parse($at->hora_inicio);
          $fimObj = !empty($at->hora_fim) ? \Carbon\Carbon::parse($at->hora_fim) : null;

            if ($fimObj && $fimObj->lessThanOrEqualTo($ini)) {
            $fimObj->addDay();
            }

            $iniStr = $ini->format('H:i');
            $fimStr = $fimObj ? $fimObj->format('H:i') : null;

            $chLabel = null;
            if ($fimObj) {
            $mins = $ini->diffInMinutes($fimObj, false);
            if ($mins < 0) { $mins +=24*60; }
              $h=intdiv($mins, 60);
              $m=$mins % 60;
              $chLabel=$h> 0 ? ($h.'h'.($m ? ' '.$m.'min' : '')) : ($m.'min');
              }

              $momento = trim($at->descricao ?? '') !== '' ? $at->descricao : 'Momento';
              $local = $at->local ?? null;
              $municipio = $at->abrangencia_nacional
                ? 'Brasil'
                : ($at->municipios->isNotEmpty()
                  ? $at->municipios->map(fn($m) => $m->nome_com_estado ?? $m->nome)->join(', ')
                  : null);
              $publicoEsperado = $at->publico_esperado;
              $cargaHoraria = $at->carga_horaria;
              $cargaLabel = !is_null($cargaHoraria) ? \App\Support\CargaHoraria::formatMinutos((int) $cargaHoraria) : null;
              $minhaPresenca = ($presencasPorAtividade ?? collect())[$at->id] ?? null;
              $primeiraAvaliacao = $at->avaliacoes->first();
              @endphp

              <div class="t-item">
                <span class="t-dot"></span>
                <div class="program-card">
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div class="flex-grow-1">
                      <div class="program-time">{{ $iniStr }}{{ $fimStr ? ' – ' . $fimStr : '' }}</div>

                      <div class="program-title d-flex align-items-center flex-wrap gap-2">
                        <span>{{ $momento }}</span>
                        @if($at->checklists_incompletos)
                          <button type="button"
                                  class="badge bg-warning text-dark border-0 btn-checklist-reabrir"
                                  data-atividade-id="{{ $at->id }}"
                                  data-checklist-pl="{{ json_encode($at->checklist_planejamento ?? []) }}"
                                  data-checklist-en="{{ json_encode($at->checklist_encerramento ?? []) }}"
                                  style="cursor:pointer; font-size: 0.75rem; padding: 0.35rem 0.5rem;">
                            ⚠️ Checklist incompleto
                          </button>
                        @endif
                      </div>

                      @if($local || $municipio || $chLabel || $publicoEsperado || $cargaLabel)
                      <div class="program-meta">
                        @if($municipio) <span class="chip">Município: {{ $municipio }}</span> @endif
                        @if($local) <span class="chip">Local: {{ $local }}</span> @endif
                        @if($chLabel) <span class="chip">Duração: {{ $chLabel }}</span> @endif
                        @if($publicoEsperado) <span class="chip">Público esperado: {{ number_format($publicoEsperado, 0, ',', '.') }} pessoas</span> @endif
                        @if($cargaLabel) <span class="chip">Carga horária: {{ $cargaLabel }}</span> @endif
                      </div>
                      @endif
                    </div>

                    <div class="actions d-flex flex-wrap gap-2 justify-content-end align-items-center flex-shrink-0">
                      {{--
                      Avaliação para o participante (se tem presença neste momento)
                      @if($minhaPresenca && $primeiraAvaliacao)
                        @if($minhaPresenca->avaliacao_respondida)
                          <span class="badge bg-success py-2 px-3" style="font-size:.8rem;">✅ Avaliado</span>
                        @else
                          <a href="{{ route('avaliacao.formulario', ['avaliacao' => $primeiraAvaliacao->id, 'token' => encrypt($minhaPresenca->id)]) }}"
                             class="btn btn-sm btn-outline-success">
                            ✏️ Avaliar
                          </a>
                        @endif
                      @endif
                      --}}

                      {{--
                      @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
                        @if($primeiraAvaliacao)
                          <a href="{{ route('atividades.avaliacoes', $at) }}"
                             class="btn btn-sm btn-outline-info">
                            📊 Ver Avaliações
                          </a>
                        @endif
                      @endhasanyrole
                      --}}

                      @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
                        <a href="{{ $at->avaliacaoAtividade
                              ? route('avaliacao-atividade.edit',   $at)
                              : route('avaliacao-atividade.create', $at) }}"
                           class="btn btn-sm {{ $at->avaliacaoAtividade ? 'btn-warning' : 'btn-outline-warning' }}">
                          📝 {{ $at->avaliacaoAtividade ? 'Relatório' : 'Criar relatório' }}
                        </a>
                      @endhasanyrole

                      @can('atividade.ver')
                        <a href="{{ route('atividades.show', $at) }}" class="btn btn-sm btn-outline-primary">
                          👁️ Ver
                        </a>
                      @endcan

                      @hasanyrole('administrador|gerente|eq_pedagogica')
                        <a href="{{ route('atividades.edit', $at) }}" class="btn btn-sm btn-outline-secondary">
                          ✏️ Editar
                        </a>
                      @endhasanyrole

                      @hasanyrole('administrador|gerente')
                        @if (($at->presencas_count ?? 0) > 0)
                          <button type="button" class="btn btn-sm btn-outline-danger"
                                  data-blocked-delete="Este momento possui {{ $at->presencas_count == 1 ? '1 presença registrada' : $at->presencas_count . ' presenças registradas' }} e não pode ser excluído."
                                  data-blocked-delete-title="Momento possui presenças associadas">
                            🗑️ Excluir
                          </button>
                        @else
                          <form action="{{ route('atividades.destroy', $at) }}" method="POST"
                                class="d-inline m-0 p-0"
                                data-confirm="Tem certeza que deseja excluir este momento?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">🗑️ Excluir</button>
                          </form>
                        @endif
                      @endhasanyrole
                    </div>
                  </div>
                </div>
              </div>
              @endforeach
        </div>
      </div>
      @endforeach

      @endif
    </div>
  </div>

</div>
@hasanyrole('administrador|gerente|eq_pedagogica|articulador')
<div class="modal fade" id="modalRelatoriosEvento" tabindex="-1" aria-labelledby="modalRelatoriosEventoLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <x-ui.modal-header title-id="modalRelatoriosEventoLabel" title="Relatórios da ação pedagógica" />
      <div class="modal-body">
        <p class="text-muted small mb-4">
          Apenas participantes com presença confirmada serão considerados.
        </p>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="h-100 border rounded p-3 d-flex flex-column">
              <h6 class="fw-bold mb-1">Participantes únicos</h6>
              <p class="text-muted small mb-3">Consolida todos os participantes que tiveram presença confirmada em
                qualquer momento desta ação.</p>
              <div class="d-grid gap-2 mt-auto">
                <a href="{{ route('eventos.relatorios', ['evento' => $evento, 'tipo' => 'geral']) }}"
                  class="btn btn-engaja w-100">
                  Baixar XLSX
                </a>
                <a href="{{ route('eventos.relatorios', ['evento' => $evento, 'tipo' => 'geral', 'sem_ouvintes' => 1]) }}"
                  class="btn btn-outline-secondary w-100">
                  Baixar XLSX sem ouvintes
                </a>
                <a href="{{ route('eventos.relatorios', ['evento' => $evento, 'tipo' => 'geral', 'formato' => 'docx']) }}"
                  class="btn btn-outline-primary w-100">
                  <i class="bi bi-file-earmark-word"></i> Baixar Word
                </a>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="h-100 border rounded p-3 d-flex flex-column">
              <h6 class="fw-bold mb-1">Participantes por momento</h6>
              <p class="text-muted small mb-3">Lista os presentes por momento, com data e horários.</p>
              <div class="d-grid gap-2 mt-auto">
                <a href="{{ route('eventos.relatorios', ['evento' => $evento, 'tipo' => 'momentos']) }}"
                  class="btn btn-outline-secondary w-100">
                  Baixar XLSX
                </a>
                <a href="{{ route('eventos.relatorios', ['evento' => $evento, 'tipo' => 'momentos', 'sem_ouvintes' => 1]) }}"
                  class="btn btn-outline-secondary w-100">
                  Baixar XLSX sem ouvintes
                </a>
                <a href="{{ route('eventos.relatorios', ['evento' => $evento, 'tipo' => 'momentos', 'formato' => 'docx']) }}"
                  class="btn btn-outline-primary w-100">
                  <i class="bi bi-file-earmark-word"></i> Baixar Word
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
@endhasanyrole

{{-- Modal: seleção de agrupamento do cronograma --}}
@role('administrador|gerente|eq_pedagogica|articulador')
<div class="modal fade" id="modalCronogramaAgrupamento" tabindex="-1"
  aria-labelledby="modalCronogramaAgrupamentoLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <x-ui.modal-header icon="file-pdf" title-id="modalCronogramaAgrupamentoLabel" title="Cronograma da Ação" />
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Escolha como os momentos devem ser organizados no cronograma:
        </p>
        <div class="d-grid gap-2">
          <a href="{{ route('eventos.cronograma.pdf', ['evento' => $evento, 'agrupamento' => 'data']) }}"
            class="btn btn-engaja">
            <i class="fas fa-calendar-day me-2"></i>Agrupar por Data
          </a>
          <a href="{{ route('eventos.cronograma.pdf', ['evento' => $evento, 'agrupamento' => 'municipio']) }}"
            class="btn btn-outline-engaja" style="border: 1px solid #dee2e6;">
            <i class="fas fa-map-marker-alt me-2"></i>Agrupar por Município
          </a>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>
@endrole

{{-- Instância do Modal de Pré-ação --}}

<x-checklist-modal
    id="modalChecklistPreAcao"
    title="Checklist de Planejamento"
    btn-label="Prosseguir para criar momento"
    tipo="planejamento"
    :marcados="[]"
    :items="[
        'Ao planejar cada ação, recorri aos objetivos gerais do projeto, em diálogo com os dados da Leitura do Mundo?',
        'Ao planejar, estabeleci conexão com as outras ações do projeto? (Ex: Cartas para Esperançar, Semear Palavras)',
        'Preparei listas de presença impressas de acordo com os dados a serem inseridos no sistema ENGAJA?',
        'Preparei formulários de avaliação de cada ação de formação, para medir os impactos?',
        'Organizei a lista de materiais necessários e apresentei à coordenação com antecedência?',
        'Organizei a demanda de infraestrutura local com antecedência?',
        'A inscrição do público esperado na formação foi feita?',
        'A informação sobre o dia e horário chegou com antecedência aos públicos participantes?',
        'Os materiais institucionais do projeto para entregar aos participantes estão organizados?',
        'Equipe Pedagógica e Educadores estão com clareza de quem fará o que durante os encontros?',
        'Planejei os momentos de registros audiovisual de cada ação?',
        'Sei como nomear os arquivos e o local onde compartilhar os registros processuais?',
        'Estou de posse de todos os contatos estratégicos em caso de necessidade?'
    ]"
/>

{{-- Modal de reabertura de checklist --}}
<div class="modal fade" id="modalReopenChecklist" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <x-ui.modal-header icon="exclamation-triangle" title="Checklist Incompleto" :dismissible="false" />
      <div class="modal-body" id="reopen-checklist-body">
        {{-- preenchido por JS --}}
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-engaja" id="btn-salvar-checklist-reopen">Salvar progresso</button>
      </div>
    </div>
  </div>
</div>

{{-- Modal de pessoas não encontradas após importação Moodle --}}
@if(session('usuarios_nao_encontrados') && count(session('usuarios_nao_encontrados')) > 0)
<div class="modal fade" id="modalUsuariosNaoEncontrados" tabindex="-1" aria-labelledby="modalUsuariosNaoEncontradosLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning-subtle">
        <h5 class="modal-title text-warning-emphasis" id="modalUsuariosNaoEncontradosLabel">⚠️ Pessoas não inseridas na importação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
        <div class="alert alert-danger mb-3">
          <strong>{{ count(session('usuarios_nao_encontrados')) }}</strong> pessoa(s) da planilha <strong>NÃO foram inseridas para criação de certificado</strong>
          pois não possuem cadastro no Engaja.
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 80px;">Linha</th>
                <th>Nome</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              @foreach(session('usuarios_nao_encontrados') as $usuario)
              <tr>
                <td>{{ $usuario['linha'] ?? '—' }}</td>
                <td>{{ $usuario['nome'] }}</td>
                <td>{{ $usuario['email'] }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {

      // Auto-abrir modal de usuários não encontrados após importação Moodle
      const modalNaoEncontrados = document.getElementById('modalUsuariosNaoEncontrados');
      if (modalNaoEncontrados) {
          const modal = new bootstrap.Modal(modalNaoEncontrados);
          modal.show();
      }

      // Lógica 1: Criação de novo Momento
      const btnConfirmarPreAcao = document.querySelector('.js-checklist-confirm[data-modal="modalChecklistPreAcao"]');

      if (btnConfirmarPreAcao) {
          btnConfirmarPreAcao.addEventListener('click', function () {
              const marcados = [];
              document.querySelectorAll('#modalChecklistPreAcao .js-checklist-item:checked').forEach(cb => {
                  marcados.push(cb.dataset.index);
              });

              const url = new URL("{{ route('eventos.atividades.create', $evento) }}");

              if (marcados.length > 0) {
                  url.searchParams.append('marcados', marcados.join(','));
              }

              const modalEl = document.getElementById('modalChecklistPreAcao');
              bootstrap.Modal.getInstance(modalEl)?.hide();

              window.location.href = url.toString();
          });
      }

      // Lógica 2: Edição/Reabertura de Checklist existente (Tarefa 2)
      const ITENS_PLANEJAMENTO = [
          'Ao planejar cada ação, recorri aos objetivos gerais do projeto, em diálogo com os dados da Leitura do Mundo?',
          'Ao planejar, estabeleci conexão com as outras ações do projeto? (Ex: Cartas para Esperançar, Semear Palavras)',
          'Preparei listas de presença impressas de acordo com os dados a serem inseridos no sistema ENGAJA?',
          'Preparei formulários de avaliação de cada ação de formação, para medir os impactos?',
          'Organizei a lista de materiais necessários e apresentei à coordenação com antecedência?',
          'Organizei a demanda de infraestrutura local com antecedência?',
          'A inscrição do público esperado na formação foi feita?',
          'A informação sobre o dia e horário chegou com antecedência aos públicos participantes?',
          'Os materiais institucionais do projeto para entregar aos participantes estão organizados?',
          'Equipe Pedagógica e Educadores estão com clareza de quem fará o que durante os encontros?',
          'Planejei os momentos de registros audiovisual de cada ação?',
          'Sei como nomear os arquivos e o local onde compartilhar os registros processuais?',
          'Estou de posse de todos os contatos estratégicos em caso de necessidade?'
      ];
      const ITENS_ENCERRAMENTO = [
          'Verifiquei se os municípios estão corretos?',
          'Confirmei a carga horária e os horários de início e término?',
          'O público esperado e os dados do momento estão preenchidos corretamente?'
      ];

      let atividadeIdAtual = null;

      document.querySelectorAll('.btn-checklist-reabrir').forEach(btn => {
          btn.addEventListener('click', function () {
              atividadeIdAtual = this.dataset.atividadeId;
            const marcadosPl = normalizeMarkedIndexes(ITENS_PLANEJAMENTO, JSON.parse(this.dataset.checklistPl || '[]'));
            const marcadosEn = normalizeMarkedIndexes(ITENS_ENCERRAMENTO, JSON.parse(this.dataset.checklistEn || '[]'));

              const body = document.getElementById('reopen-checklist-body');
              body.innerHTML = renderChecklist('planejamento', ITENS_PLANEJAMENTO, marcadosPl)
                             + renderChecklist('encerramento', ITENS_ENCERRAMENTO, marcadosEn);

              new bootstrap.Modal(document.getElementById('modalReopenChecklist')).show();
          });
      });

        function normalizeMarkedIndexes(itens, marcados) {
          if (!Array.isArray(marcados)) {
            return [];
          }

          const normalizedTextToIndex = new Map(
            itens.map((item, index) => [String(item).trim().toLowerCase(), index])
          );

          return [...new Set(
            marcados
              .map((valor) => {
                if (Number.isInteger(valor)) {
                  return valor;
                }

                const asNumber = Number(valor);
                if (Number.isInteger(asNumber)) {
                  return asNumber;
                }

                const textKey = String(valor).trim().toLowerCase();
                return normalizedTextToIndex.has(textKey) ? normalizedTextToIndex.get(textKey) : null;
              })
              .filter((index) => Number.isInteger(index) && index >= 0 && index < itens.length)
          )];
        }

      function renderChecklist(tipo, itens, marcados) {
          let html = `<h6 class="fw-bold mt-2" style="color: #421944;">${tipo === 'planejamento' ? '📋 Planejamento' : '✅ Encerramento'}</h6><div class="vstack gap-2 mb-4">`;
          itens.forEach((item, i) => {
              const checked = marcados.includes(i) ? 'checked' : '';
              html += `<label class="checklist-card d-flex align-items-center gap-3 ${checked ? 'checked' : ''}" style="cursor:pointer;border:2px solid #dee2e6;border-radius:10px;padding:12px 16px; ${checked ? 'background-color: #421944; color: #fff; border-color: #421944;' : ''}">
                  <input type="checkbox" class="js-reopen-item" data-tipo="${tipo}" data-index="${i}" ${checked} style="display:none">
                  <span class="checklist-check-icon" style="width:22px;height:22px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#421944;font-weight:900;opacity:${checked ? 1 : 0}">✓</span>
                  <span>${item}</span>
              </label>`;
          });
          html += '</div>';
          return html;
      }

      // Toggle visual dos cards no modal de reabertura
      document.getElementById('reopen-checklist-body')?.addEventListener('change', function(e) {
          if (e.target.classList.contains('js-reopen-item')) {
              const label = e.target.closest('label');
              label.classList.toggle('checked', e.target.checked);
              label.style.backgroundColor = e.target.checked ? '#421944' : '';
              label.style.color = e.target.checked ? '#fff' : '';
              label.style.borderColor = e.target.checked ? '#421944' : '#dee2e6';
              label.querySelector('.checklist-check-icon').style.opacity = e.target.checked ? 1 : 0;
          }
      });

      document.getElementById('btn-salvar-checklist-reopen')?.addEventListener('click', function () {
          if (!atividadeIdAtual) return;

          // Salva planejamento
          const pl = [...document.querySelectorAll('.js-reopen-item[data-tipo="planejamento"]:checked')].map(c => parseInt(c.dataset.index));
          const en = [...document.querySelectorAll('.js-reopen-item[data-tipo="encerramento"]:checked')].map(c => parseInt(c.dataset.index));

          const salvar = (tipo, itens) => fetch(`/atividades/${atividadeIdAtual}/checklist`, {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              },
              body: JSON.stringify({ tipo, itens })
          });

          Promise.all([salvar('planejamento', pl), salvar('encerramento', en)])
              .then(() => { window.location.reload(); })
              .catch(() => alert('Erro ao salvar. Tente novamente.'));
      });
  });
</script>
@endpush
