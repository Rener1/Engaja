@extends('layouts.app')

@section('content')
@php
  use Carbon\Carbon;
  $totalMomentosEvento = $totalMomentosEvento ?? 0;
  $momentosInscritosPorParticipante = $momentosInscritosPorParticipante ?? [];
  $modoTodosMomentos = ! $atividadeId && $totalMomentosEvento > 0;
@endphp
<x-ui.page-hero contained eyebrow="Operações" eyebrow-icon="person-plus" title="Inscrever participantes existentes" class="mb-0">
    <div class="text-muted small">
      Ação pedagógica: <strong>{{ $evento->nome }}</strong>
    </div>
    <x-slot:actions>
        <a href="{{ route('inscricoes.import', $evento) }}" class="eg-hero-btn">Importar participantes</a>
        <a href="{{ route('inscricoes.moodle.import', $evento) }}" class="eg-hero-btn fw-semibold">Importação Moodle</a>
        <a href="{{ route('eventos.show', $evento) }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      @if($atividadeSelecionada)
        @php
          $diaSel = Carbon::parse($atividadeSelecionada->dia)->translatedFormat('d/m/Y');
          $horaSel = $atividadeSelecionada->hora_inicio ? Carbon::parse($atividadeSelecionada->hora_inicio)->format('H:i') : null;
        @endphp
        <div class="mt-3 small text-muted">
          Momento selecionado: <strong>{{ $atividadeSelecionada->descricao ?: 'Momento' }}</strong> - {{ $diaSel }}{{ $horaSel ? ' às '.$horaSel : '' }}
        </div>
      @elseif($modoTodosMomentos)
        <div class="mt-3 small text-muted">
          Escopo: <strong>todos os {{ $totalMomentosEvento }} momento(s)</strong> desta ação. Os participantes selecionados serão inscritos em cada momento.
        </div>
      @elseif($totalMomentosEvento === 0)
        <div class="mt-3 alert alert-warning mb-0">
          Não há momentos cadastrados neste evento. Cadastre momentos antes de inscrever participantes.
        </div>
        <br>
      @else
        <div class="mt-3 alert alert-info mb-0">
          Escolha um momento específico ou <strong>todos os momentos</strong> para habilitar a inscrição dos participantes selecionados.
        </div>
        <br>
      @endif
      @error('atividade_id')
        <div class="text-danger small mt-2">{{ $message }}</div>
      @enderror
      <x-ui.filter-card>
          <form method="GET" action="{{ route('inscricoes.selecionar', $evento) }}" id="selecionarFiltrosForm">
            <div class="row g-3 mb-2">
              <div class="col-12">
                <label class="eg-filter-label">Momento</label>
                <div class="eg-select-wrapper"><select name="atividade_id" id="atividadeIdSelect" class="eg-select" data-total-momentos="{{ $totalMomentosEvento }}">
                  <option value="" @selected($atividadeId === null || $atividadeId === '')>Todos os momentos desta ação</option>
                  @foreach($atividades as $at)
                    @php
                      $dia = Carbon::parse($at->dia)->format('d/m/Y');
                      $hora = $at->hora_inicio ? Carbon::parse($at->hora_inicio)->format('H:i') : null;
                      $label = trim(($at->descricao ?: 'Momento') . ' - ' . $dia . ($hora ? ' ' . $hora : ''));
                    @endphp
                    <option value="{{ $at->id }}" @selected((string) $atividadeId === (string) $at->id)>{{ $label }}</option>
                  @endforeach
                </select><i class="bi bi-chevron-down eg-select-chevron" aria-hidden="true"></i></div>
              </div>
            </div>

            <div class="row g-3 align-items-end">
              <div class="col-xl-2 col-md-4">
                <label class="eg-filter-label">Município</label>
                <x-ui.select name="municipio_id">
                  <option value="">Todos</option>
                  @foreach($municipios as $m)
                    <option value="{{ $m->id }}" @selected((string) $municipioId === (string) $m->id)>
                      {{ $m->nome_com_estado ?? ($m->nome . ($m->estado?->sigla ? ' - '.$m->estado?->sigla : '')) }}
                    </option>
                  @endforeach
                </x-ui.select>
              </div>

              <div class="col-xl-2 col-md-4">
                <label class="eg-filter-label">Tag</label>
                <x-ui.select name="tag">
                  <option value="">Todas</option>
                  @foreach($participanteTags as $tag)
                    <option value="{{ $tag }}" @selected($tagSelecionada === $tag)>{{ $tag }}</option>
                  @endforeach
                </x-ui.select>
              </div>

              <div class="col-xl-1 col-md-4">
                <label class="eg-filter-label">Por página</label>
                <x-ui.select name="per_page">
                  @foreach([25, 50, 100, 200] as $pp)
                    <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
                  @endforeach
                </x-ui.select>
              </div>

              <div class="col-xl-4 col-md-4">
                <label class="eg-filter-label">Disponibilidade</label>
                <div class="form-check form-switch">
                  <input type="hidden" name="apenas_disponiveis" value="0">
                  <input class="form-check-input" type="checkbox" role="switch" id="apenasDisponiveisSwitch"
                    name="apenas_disponiveis" value="1" @checked($apenasDisponiveis) @disabled($totalMomentosEvento === 0)>
                  <label class="form-check-label small" for="apenasDisponiveisSwitch">
                    @if($atividadeId)
                      Mostrar apenas quem não está neste momento
                    @else
                      Mostrar apenas quem não está em todos os momentos
                    @endif
                  </label>
                </div>
              </div>

              <div class="col-12 d-flex gap-2 eg-filter-actions">
                <button class="btn btn-engaja">Filtrar</button>
                <a href="{{ route('inscricoes.selecionar', $evento) }}" class="btn btn-outline-secondary">Limpar</a>
              </div>
            </div>
          </form>
      </x-ui.filter-card>
    </div>
  </div>

  <form method="POST" action="{{ route('inscricoes.selecionar.store', $evento) }}" class="card shadow-sm">
    @csrf
    <input type="hidden" name="atividade_id" value="{{ $atividadeId }}" id="atividadeIdHidden">
    <input type="hidden" name="q" value="{{ $search }}">
    <input type="hidden" name="municipio_id" value="{{ $municipioId }}">
    <input type="hidden" name="tag" value="{{ $tagSelecionada }}">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <input type="hidden" name="apenas_disponiveis" value="{{ $apenasDisponiveis ? 1 : 0 }}">

    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center flex-wrap gap-3">
        <div>
          <strong>{{ $participantes->total() }}</strong> participantes encontrados
          <span class="text-muted small ms-1">
            Página {{ $participantes->currentPage() }} de {{ $participantes->lastPage() }}
          </span>
        </div>
        <div style="min-width: 320px; width: 100%; max-width: 420px;">
          <input
            type="text"
            name="q"
            form="selecionarFiltrosForm"
            value="{{ $search }}"
            class="form-control form-control-sm"
            id="liveParticipantSearch"
            placeholder="Buscar participante por nome">
        </div>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="selectAll">
          <label class="form-check-label small" for="selectAll">Selecionar página</label>
        </div>
        <button type="submit" class="btn btn-engaja btn-sm" id="inscreverSelecionadosBtn" @disabled($participantes->isEmpty() || (! $atividadeId && $totalMomentosEvento === 0))>
          Inscrever selecionados
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;"></th>
            <th>Nome</th>
            <th>Email</th>
            <th>CPF</th>
            <th>Município</th>
            <th>Tag</th>
            <th>Status no evento</th>
          </tr>
        </thead>
        <tbody>
          @forelse($participantes as $participante)
            @php
              $user = $participante->user;
              $municipio = $participante->municipio;
              $nMomentosInscrito = (int) ($momentosInscritosPorParticipante[$participante->id] ?? 0);
              if ($atividadeId) {
                $jaNoMomento = in_array($participante->id, $inscritosNaAtividade, true);
              } else {
                $jaNoMomento = $totalMomentosEvento > 0 && $nMomentosInscrito >= $totalMomentosEvento;
              }
              $jaNoEvento = in_array($participante->id, $inscritosNoEvento, true);
            @endphp
            <tr>
              <td>
                <div class="form-check mb-0">
                  <input class="form-check-input participant-checkbox" type="checkbox"
                    name="participantes[]" value="{{ $participante->id }}"
                    @disabled($jaNoMomento)>
                </div>
              </td>
              <td>{{ $user->name ?? '-' }}</td>
              <td>{{ $user->email ?? '-' }}</td>
              <td>{{ $participante->cpf ?? '-' }}</td>
              <td>{{ $municipio?->nome_com_estado ?? '-' }}</td>
              <td>{{ $participante->tag ?? '-' }}</td>
              <td>
                @if($atividadeId)
                  @if($jaNoMomento)
                    <span class="badge bg-success-subtle text-success">Já inscrito neste momento</span>
                  @elseif($jaNoEvento)
                    <span class="badge bg-primary-subtle text-primary">Já inscrito no evento</span>
                  @else
                    <span class="badge bg-secondary-subtle text-secondary">Disponível</span>
                  @endif
                @else
                  @if($jaNoMomento)
                    <span class="badge bg-success-subtle text-success">Já inscrito em todos os momentos</span>
                  @elseif($nMomentosInscrito > 0)
                    <span class="badge bg-info-subtle text-info">Inscrito em {{ $nMomentosInscrito }} de {{ $totalMomentosEvento }} momento(s)</span>
                  @elseif($jaNoEvento)
                    <span class="badge bg-primary-subtle text-primary">Já inscrito no evento</span>
                  @else
                    <span class="badge bg-secondary-subtle text-secondary">Disponível</span>
                  @endif
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7"><x-ui.empty-state compact muted icon="inbox" title="Nenhum participante encontrado com os filtros selecionados." /></td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="small text-muted">
        Exibindo {{ $participantes->count() }} registros nesta página
      </div>
      @error('participantes')
        <div class="text-danger small">{{ $message }}</div>
      @enderror
      <div>
        {{ $participantes->links() }}
      </div>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const filtrosForm = document.getElementById('selecionarFiltrosForm');
    const liveParticipantSearch = document.getElementById('liveParticipantSearch');
    const atividadeSelect = document.getElementById('atividadeIdSelect');
    const atividadeHidden = document.getElementById('atividadeIdHidden');
    const disponibilidadeSwitch = document.getElementById('apenasDisponiveisSwitch');
    const selectAll = document.getElementById('selectAll');
    const submitButton = document.getElementById('inscreverSelecionadosBtn');
    const participantCheckboxes = () => Array.from(document.querySelectorAll('.participant-checkbox:not(:disabled)'));

    const totalMomentosEvento = Number(atividadeSelect?.dataset?.totalMomentos || 0);

    const temEscopoInscricao = () => {
      const v = atividadeSelect?.value ?? '';
      return v !== '' || totalMomentosEvento > 0;
    };

    const updateSubmitButton = () => {
      if (!submitButton) return;

      const hasEscopo = temEscopoInscricao();
      const hasSelectedParticipants = participantCheckboxes().some((checkbox) => checkbox.checked);

      submitButton.disabled = !hasEscopo || !hasSelectedParticipants;
    };

    if (selectAll) {
      selectAll.addEventListener('change', () => {
        participantCheckboxes().forEach((checkbox) => {
          checkbox.checked = selectAll.checked;
        });
        updateSubmitButton();
      });
    }

    participantCheckboxes().forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        if (selectAll) {
          const checkboxes = participantCheckboxes();
          selectAll.checked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);
        }
        updateSubmitButton();
      });
    });

    if (atividadeSelect) {
      atividadeSelect.addEventListener('change', () => {
        const escopoOk = temEscopoInscricao();

        if (atividadeHidden) {
          atividadeHidden.value = atividadeSelect.value;
        }

        if (disponibilidadeSwitch) {
          disponibilidadeSwitch.disabled = !escopoOk;
          if (!escopoOk) {
            disponibilidadeSwitch.checked = false;
          }
        }

        updateSubmitButton();

        if (filtrosForm) {
          filtrosForm.requestSubmit();
        }
      });
    }

    if (disponibilidadeSwitch) {
      disponibilidadeSwitch.addEventListener('change', () => {
        if (filtrosForm) {
          filtrosForm.requestSubmit();
        }
      });
    }

    if (liveParticipantSearch && filtrosForm) {
      let searchTimeout;

      liveParticipantSearch.addEventListener('input', () => {
        window.clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(() => {
          filtrosForm.requestSubmit();
        }, 300);
      });
    }

    updateSubmitButton();
  });
</script>
@endsection
