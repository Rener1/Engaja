@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="file-earmark-check" title="Evidências">
    <x-slot:actions>
        <a href="{{ route('evidencias.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova evidência</a>
    </x-slot:actions>
</x-ui.page-hero>

<x-ui.filter-card>
<form method="GET" action="{{ route('evidencias.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-lg-3 col-md-5">
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
      <div class="col-lg-3 col-md-3">
        <label for="indicador_id" class="eg-filter-label">Filtrar por indicador</label>
        <x-ui.select id="indicador_id" name="indicador_id">
          <option value="">Todos</option>
          @foreach ($indicadores as $id => $descricao)
          <option value="{{ $id }}" @selected((string)request('indicador_id') === (string)$id)>{{ $descricao }}</option>
          @endforeach
        </x-ui.select>
      </div>
      <div class="col-2 d-flex gap-2 eg-filter-actions">
        <button type="submit" class="btn btn-engaja">Aplicar</button>
        <a href="{{ route('evidencias.index') }}" class="btn btn-outline-secondary">Limpar</a>
      </div>
    </div>
</form>
</x-ui.filter-card>

@php
    $columns = [
        ['field' => 'descricao', 'headerName' => 'Descrição', 'flex' => 2],
        ['field' => 'dimensao', 'headerName' => 'Dimensão', 'flex' => 1],
        ['field' => 'indicador', 'headerName' => 'Indicador', 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
    ];

    $podeExcluir = auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica']);

    $rows = $evidencias->map(function ($evidencia) use ($podeExcluir) {
        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('evidencias.show', $evidencia) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('evidencias.edit', $evidencia) . '">Editar</a></li>'
            . '<li>'
            . '<form method="POST" action="' . route('evidencias.duplicar', $evidencia) . '" data-confirm="Tem certeza que deseja duplicar esta evidência?">'
            . csrf_field()
            . '<button type="submit" class="dropdown-item">Duplicar</button>'
            . '</form>'
            . '</li>';

        if ($podeExcluir) {
            $acoesHtml .= '<li>'
                . '<form method="POST" action="' . route('evidencias.destroy', $evidencia) . '" data-confirm="Tem certeza que deseja excluir esta evidência?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'descricao' => $evidencia->descricao,
            'dimensao' => $evidencia->indicador->dimensao->descricao ?? '—',
            'indicador' => $evidencia->indicador->descricao ?? '—',
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table
        id="grid-evidencias"
        :columns="$columns"
        :rows="$rows"
        :page-size="15"
    />
</div>
@endsection
