@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Operações" eyebrow-icon="calendar-check" title="Participantes do agendamento" class="mb-0">
    <div class="text-muted small">
      {{ optional($agendamento->data_horario)->format('d/m/Y H:i') }} · {{ $agendamento->atividadeAcao?->nome ?? 'Atividade/Ação' }}
    </div>
    <x-slot:actions>
        @unless($agendamento->efetivado)
          <div class="d-flex gap-2">
            <a href="{{ route('agendamentos.participantes.import', $agendamento) }}" class="eg-hero-btn">Importar XLSX</a>
            <a href="{{ route('agendamentos.participantes.create', $agendamento) }}" class="eg-hero-btn eg-hero-btn--solid">Novo participante</a>
          </div>
        @endunless
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
  <div class="alert alert-light border mb-3">
    <strong>Agendamento selecionado:</strong>
    {{ optional($agendamento->data_horario)->format('d/m/Y H:i') }} ·
    {{ $agendamento->atividadeAcao?->nome ?? 'Atividade/Ação' }} ·
    {{ $agendamento->local_acao }}
    @if($agendamento->turma)
      · Turma {{ $agendamento->turma }}
    @endif
  </div>

  <x-ui.filter-card>
      <form method="GET" action="{{ route('agendamentos.participantes.index', $agendamento) }}" class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="eg-filter-label">Buscar</label>
          <x-ui.input type="text" name="q" value="{{ $search }}" placeholder="Nome, e-mail, CPF ou telefone" />
        </div>
        <div class="col-md-2">
          <label class="eg-filter-label">Por página</label>
          <x-ui.select name="per_page">
            @foreach([25, 50, 100] as $pp)
              <option value="{{ $pp }}" @selected($perPage == $pp)>{{ $pp }}</option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-engaja">Filtrar</button>
        </div>
        <div class="col-md-2 d-grid">
          <a href="{{ route('agendamentos.show', $agendamento) }}" class="btn btn-outline-secondary">Voltar</a>
        </div>
      </form>
  </x-ui.filter-card>

  @php
      $columns = [
          ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
          ['field' => 'cpf', 'headerName' => 'CPF', 'flex' => 1],
          ['field' => 'email', 'headerName' => 'E-mail', 'flex' => 2],
          ['field' => 'nascimento', 'headerName' => 'Nascimento', 'flex' => 1],
          ['field' => 'telefone', 'headerName' => 'Telefone', 'flex' => 1],
          ['field' => 'vinculo', 'headerName' => 'Vínculo', 'flex' => 1],
          ['field' => 'turma', 'headerName' => 'Turma', 'flex' => 1],
          ['field' => 'origem', 'headerName' => 'Origem', 'flex' => 1],
      ];

      if (! $agendamento->efetivado) {
          $columns[] = ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true, 'align' => 'center'];
      }

      $rows = $participantes->map(function ($participante) use ($agendamento) {
          $row = [
              'nome' => $participante->nome,
              'cpf' => $participante->cpf ?: '—',
              'email' => $participante->email ?: '—',
              'nascimento' => $participante->data_nascimento ? \Illuminate\Support\Carbon::parse($participante->data_nascimento)->format('d/m/Y') : '—',
              'telefone' => $participante->telefone ?: '—',
              'vinculo' => $participante->vinculo ?: '—',
              'turma' => $participante->turma ?: '—',
              'origem' => $participante->origem,
          ];

          if (! $agendamento->efetivado) {
              $row['acoes'] = '<a href="' . route('agendamentos.participantes.edit', [$agendamento, $participante]) . '" class="btn btn-sm btn-outline-secondary">Editar</a> '
                  . '<form method="POST" action="' . route('agendamentos.participantes.destroy', [$agendamento, $participante]) . '" class="d-inline" data-confirm="Tem certeza que deseja excluir este participante?">'
                  . csrf_field() . method_field('DELETE')
                  . '<button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>'
                  . '</form>';
          }

          return $row;
      })->values();
  @endphp

  <x-data-table id="grid-agendamento-participantes" :columns="$columns" :rows="$rows" :pagination="false" class="bg-white" />

  <div class="d-flex justify-content-between align-items-center mt-2">
    <div class="small text-muted">Total: {{ $participantes->total() }}</div>
    {{ $participantes->links() }}
  </div>
</div>
@endsection
