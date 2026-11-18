@extends('layouts.app')

@section('content')
<div class="mb-4">
  <x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="people" title="Participantes exclusivos por ação">
      <p class="text-muted mb-0">
        Selecione as ações pedagógicas para listar pessoas que possuem inscrição ou presença confirmada somente nelas.
      </p>
      <x-slot:actions>
          <a href="{{ route('usuarios.index') }}" class="eg-hero-btn border">Voltar</a>
      </x-slot:actions>
  </x-ui.page-hero>

  @if($errors->has('eventos'))
    <div class="alert alert-danger">{{ $errors->first('eventos') }}</div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="GET" action="{{ route('usuarios.participantes-exclusivos.resultado') }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h2 class="h6 fw-bold mb-1">Ações pedagógicas</h2>
            <div class="text-muted small">{{ $eventos->count() }} ação(ões) disponível(is)</div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="selecionar-todas-acoes">Selecionar todas</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="limpar-acoes">Limpar</button>
            <button type="submit" class="btn btn-engaja btn-sm">Fazer checagem</button>
          </div>
        </div>

        <div id="eventos-hidden-inputs"></div>

        @php
            $columns = [
                ['field' => 'nome', 'headerName' => 'Ação pedagógica', 'flex' => 3],
                ['field' => 'momentos', 'headerName' => 'Momentos', 'flex' => 1],
                ['field' => 'periodo', 'headerName' => 'Período', 'flex' => 1],
            ];

            $rows = $eventos->map(function ($evento) {
                $periodo = $evento->data_inicio || $evento->data_fim
                    ? trim(($evento->data_inicio ? \Illuminate\Support\Carbon::parse($evento->data_inicio)->format('d/m/Y') : '') . ' - ' . ($evento->data_fim ? \Illuminate\Support\Carbon::parse($evento->data_fim)->format('d/m/Y') : ''), ' -')
                    : ($evento->data_horario ? \Illuminate\Support\Carbon::parse($evento->data_horario)->format('d/m/Y') : '-');

                return [
                    'id' => $evento->id,
                    'nome' => $evento->nome,
                    'momentos' => $evento->atividades_count,
                    'periodo' => $periodo,
                ];
            })->values();
        @endphp

        <x-data-table
            id="grid-participantes-exclusivos"
            :columns="$columns"
            :rows="$rows"
            :pagination="false"
            row-selection="multiple"
            :selected-ids="$selecionados"
        />
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const gridEl = document.getElementById('grid-participantes-exclusivos');
    const hiddenInputs = document.getElementById('eventos-hidden-inputs');
    const selecionarTodas = document.getElementById('selecionar-todas-acoes');
    const limpar = document.getElementById('limpar-acoes');

    if (!gridEl) return;

    gridEl.addEventListener('datatable:selection-changed', (event) => {
      hiddenInputs.innerHTML = '';
      event.detail.rows.forEach((row) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'eventos[]';
        input.value = row.id;
        hiddenInputs.appendChild(input);
      });
    });

    selecionarTodas?.addEventListener('click', () => gridEl._agGridApi?.selectAll());
    limpar?.addEventListener('click', () => gridEl._agGridApi?.deselectAll());
  });
</script>
@endpush
