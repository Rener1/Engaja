@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="sliders" title="Escalas">
    <x-slot:actions>
        <a href="{{ route('escalas.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova escala</a>
    </x-slot:actions>
</x-ui.page-hero>

<x-ui.filter-card>
<form method="GET" action="{{ route('escalas.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label for="search" class="eg-filter-label">Buscar por descrição</label>
        <x-ui.input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Digite parte da descrição" />
      </div>
      <div class="col-md-3">
        <label for="has_options" class="eg-filter-label">Filtro de opções</label>
        <x-ui.select id="has_options" name="has_options">
          <option value="">Todas</option>
          <option value="with" @selected(request('has_options') === 'with')>Com opções</option>
          <option value="without" @selected(request('has_options') === 'without')>Sem opções</option>
        </x-ui.select>
      </div>
      <div class="col-4 d-flex gap-2 eg-filter-actions">
        <button type="submit" class="btn btn-engaja">Aplicar</button>
        <a href="{{ route('escalas.index') }}" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </div>
</form>
</x-ui.filter-card>

@php
    $columns = [
        ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 2],
        ['field' => 'opcoes', 'headerName' => 'Opções', 'flex' => 3],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
    ];

    $podeExcluir = auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica']);

    $rows = $escalas->map(function ($escala) use ($podeExcluir) {
        $opcoes = collect([$escala->opcao1, $escala->opcao2, $escala->opcao3, $escala->opcao4, $escala->opcao5])->filter()->values();
        $opcoesPreview = $opcoes->map(fn ($texto) => trim(strip_tags($texto)))->filter()->implode(' | ');

        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('escalas.show', $escala) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('escalas.edit', $escala) . '">Editar</a></li>';

        if ($podeExcluir) {
            $acoesHtml .= '<li>'
                . '<form method="POST" action="' . route('escalas.destroy', $escala) . '" data-confirm="Tem certeza que deseja excluir esta escala?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'descricao' => $escala->descricao,
            'opcoes' => $opcoes->isEmpty() ? '-' : $opcoesPreview,
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table
        id="grid-escalas"
        :columns="$columns"
        :rows="$rows"
        :page-size="15"
    />
</div>
@endsection
