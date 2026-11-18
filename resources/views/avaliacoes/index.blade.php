@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="clipboard2-check" title="Avaliações">
    <x-slot:actions>
        <a href="{{ route('avaliacoes.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova avaliação</a>
    </x-slot:actions>
</x-ui.page-hero>

<x-ui.filter-card>
<form method="GET" action="{{ route('avaliacoes.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-lg-3 col-md-6">
        <label for="search" class="eg-filter-label">Buscar (momento, modelo ou descrição)</label>
        <x-ui.input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Digite para filtrar..." />
      </div>
      <div class="col-lg-3 col-md-6">
        <label for="template_id" class="eg-filter-label">Modelo</label>
        <x-ui.select id="template_id" name="template_id">
          <option value="">Todos</option>
          @foreach ($templatesDisponiveis as $id => $nome)
          <option value="{{ $id }}" @selected((string) request('template_id') === (string) $id)>{{ $nome }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-lg-2 col-md-6">
        <label for="de" class="eg-filter-label">Registrada de</label>
        <x-ui.input type="date" id="de" name="de" value="{{ request('de') }}" />
      </div>
      <div class="col-lg-2 col-md-6">
        <label for="ate" class="eg-filter-label">Registrada até</label>
        <x-ui.input type="date" id="ate" name="ate" value="{{ request('ate') }}" />
      </div>
      <div class="col-2 d-flex gap-1 eg-filter-actions">
        <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}">
        <input type="hidden" name="dir"
          value="{{ strtolower(request('dir', request('direction', 'desc'))) === 'asc' ? 'asc' : 'desc' }}">
        <button type="submit" class="btn btn-engaja">Aplicar</button>
        <a href="{{ route('avaliacoes.index') }}" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </div>
</form>
</x-ui.filter-card>

@php
    function avaliacao_sort_link($label, $key) {
        $currentSort = request('sort', 'created_at');
        $dirParam = request('dir', request('direction', 'desc'));
        $currentDir = strtolower((string) $dirParam) === 'asc' ? 'asc' : 'desc';
        $nextDir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
        $params = array_merge(request()->except('page'), ['sort' => $key, 'dir' => $nextDir]);
        $url = request()->url() . '?' . http_build_query($params);
        $isActive = $currentSort === $key;
        $arrow = $isActive ? ($currentDir === 'asc' ? '↑' : '↓') : '';

        return '<a href="' . $url . '" class="text-decoration-none text-nowrap">' . e($label) . ' <span class="text-muted">' . $arrow . '</span></a>';
    }

    $columns = [
        ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 2],
        ['field' => 'momento', 'headerHtml' => avaliacao_sort_link('Momento', 'momento'), 'flex' => 3, 'html' => true],
        ['field' => 'modelo', 'headerHtml' => avaliacao_sort_link('Modelo', 'template'), 'flex' => 2],
        ['field' => 'registrada_em', 'headerHtml' => avaliacao_sort_link('Registrada em', 'created_at'), 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true, 'align' => 'center'],
    ];

    $rows = $avaliacoes->map(function ($avaliacao) {
        $inscricaoExibida = $avaliacao->inscricao ?? $avaliacao->respostas->first()?->inscricao;
        $participanteNome = $inscricaoExibida?->participante?->user?->name;

        $momentoHtml = '<span>' . e($avaliacao->atividade->descricao ?? '—') . '</span>'
            . '<small class="d-block text-muted">'
            . e(($avaliacao->atividade && $avaliacao->atividade->dia ? \Illuminate\Support\Carbon::parse($avaliacao->atividade->dia)->format('d/m/Y') : '') . ' ' . ($avaliacao->atividade->hora_inicio ?? ''))
            . '</small>';

        if ($participanteNome) {
            $momentoHtml .= '<small class="d-block text-muted">Participante: ' . e($participanteNome) . '</small>';
        }

        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end shadow-sm">'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.show', $avaliacao) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.transcricao', $avaliacao) . '">Transcrição</a></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.ficha-pdf', $avaliacao) . '">Baixar ficha para preenchimento à mão (PDF)</a></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.ficha-pdf', ['avaliacao' => $avaliacao, 'formato' => 'docx']) . '">Baixar ficha para preenchimento (Word)</a></li>'
            . '<li><a class="dropdown-item" href="' . route('avaliacoes.edit', $avaliacao) . '">Editar</a></li>';

        if (auth()->user()->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica'])) {
            $acoesHtml .= '<li><hr class="dropdown-divider"></li>'
                . '<li>'
                . '<form method="POST" action="' . route('avaliacoes.destroy', $avaliacao) . '" data-confirm="Tem certeza que deseja excluir esta avaliação?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'descricao' => $avaliacao->descricao_universal ?: '—',
            'momento' => $momentoHtml,
            'modelo' => $avaliacao->templateAvaliacao->nome ?? '—',
            'registrada_em' => $avaliacao->created_at ? $avaliacao->created_at->format('d/m/Y H:i') : '—',
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table id="grid-avaliacoes" :columns="$columns" :rows="$rows" :pagination="false" />
</div>

<div class="mt-3">
  {{ $avaliacoes->links() }}
</div>
@endsection
