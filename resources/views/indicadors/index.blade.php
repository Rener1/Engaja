@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="graph-up" title="Indicadores">
    <x-slot:actions>
        <a href="{{ route('indicadors.create') }}" class="eg-hero-btn eg-hero-btn--solid">Novo indicador</a>
    </x-slot:actions>
</x-ui.page-hero>

<x-ui.filter-card>
<form method="GET" action="{{ route('indicadors.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-lg-4 col-md-5">
        <label for="search" class="eg-filter-label">Buscar por descrição</label>
        <x-ui.input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Digite parte da descrição" />
      </div>
      <div class="col-lg-3 col-md-4">
        <label for="dimensao_id" class="eg-filter-label">Filtrar por dimensão</label>
        <x-ui.select id="dimensao_id" name="dimensao_id">
          <option value="">Todas</option>
          @foreach ($dimensoes as $id => $descricao)
          <option value="{{ $id }}" @selected((string)request('dimensao_id') === (string)$id)>{{ $descricao }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-3 d-flex gap-2 eg-filter-actions">
        <button type="submit" class="btn btn-engaja">Aplicar</button>
        <a href="{{ route('indicadors.index') }}" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </div>
</form>
</x-ui.filter-card>

@php
    $columns = [
        ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 3],
        ['field' => 'dimensao', 'headerName' => 'Dimensão', 'flex' => 2],
        ['field' => 'questoes', 'headerName' => 'Qtd. questões', 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
    ];

    $podeExcluir = auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica']);

    $rows = $indicadors->map(function ($indicador) use ($podeExcluir) {
        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('indicadors.show', $indicador) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('indicadors.edit', $indicador) . '">Editar</a></li>';

        if ($podeExcluir) {
            $acoesHtml .= '<li>'
                . '<form method="POST" action="' . route('indicadors.destroy', $indicador) . '" data-confirm="Tem certeza que deseja excluir este indicador?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'descricao' => $indicador->descricao,
            'dimensao' => $indicador->dimensao->descricao ?? '—',
            'questoes' => $indicador->questoes_count,
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table
        id="grid-indicadores"
        :columns="$columns"
        :rows="$rows"
        :page-size="15"
    />
</div>
@endsection
