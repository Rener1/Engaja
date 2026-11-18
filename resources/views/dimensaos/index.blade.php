@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="diagram-3" title="Dimensões">
    <x-slot:actions>
        <a href="{{ route('dimensaos.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova dimensão</a>
    </x-slot:actions>
</x-ui.page-hero>

<x-ui.filter-card>
<form method="GET" action="{{ route('dimensaos.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-md-5 col-lg-4">
        <label for="search" class="eg-filter-label">Buscar por descrição</label>
        <x-ui.input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Digite parte da descrição" />
      </div>
      <div class="col-md-4 col-lg-3">
        <label for="has_indicators" class="eg-filter-label">Filtro por indicador</label>
        <x-ui.select id="has_indicators" name="has_indicators">
          <option value="">Todas</option>
          <option value="with" @selected(request('has_indicators') === 'with')>Com indicadores</option>
          <option value="without" @selected(request('has_indicators') === 'without')>Sem indicadores</option>
        </x-ui.select>
      </div>
      <div class="col-3 d-flex gap-2 eg-filter-actions">
        <button type="submit" class="btn btn-engaja">Aplicar</button>
        <a href="{{ route('dimensaos.index') }}" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </div>
</form>
</x-ui.filter-card>

@php
    $columns = [
        ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 3],
        ['field' => 'indicadores', 'headerName' => 'Qtd. indicadores', 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
    ];

    $podeExcluir = auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica']);

    $rows = $dimensaos->map(function ($dimensao) use ($podeExcluir) {
        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('dimensaos.show', $dimensao) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('dimensaos.edit', $dimensao) . '">Editar</a></li>';

        if ($podeExcluir) {
            $acoesHtml .= '<li>'
                . '<form method="POST" action="' . route('dimensaos.destroy', $dimensao) . '" data-confirm="Tem certeza que deseja excluir esta dimensão?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'descricao' => $dimensao->descricao,
            'indicadores' => $dimensao->indicadores_count,
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table
        id="grid-dimensaos"
        :columns="$columns"
        :rows="$rows"
        :page-size="15"
    />
</div>
@endsection
