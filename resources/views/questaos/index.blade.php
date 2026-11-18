@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="question-circle" title="Questões">
    <x-slot:actions>
        <a href="{{ route('questaos.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova questão</a>
    </x-slot:actions>
</x-ui.page-hero>

@php
    $columns = [
        ['field' => 'texto', 'headerName' => 'Texto', 'flex' => 3],
        ['field' => 'indicador', 'headerName' => 'Indicador', 'flex' => 1],
        ['field' => 'evidencia', 'headerName' => 'Evidência', 'flex' => 1],
        ['field' => 'tipo', 'headerName' => 'Tipo', 'flex' => 1],
        ['field' => 'escala', 'headerName' => 'Escala', 'flex' => 1],
        ['field' => 'modelo', 'headerName' => 'Modelo', 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
    ];

    $rows = $questaos->map(function ($questao) {
        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('questaos.show', $questao) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('questaos.edit', $questao) . '">Editar</a></li>'
            . '<li>'
            . '<form method="POST" action="' . route('questaos.destroy', $questao) . '" data-confirm="Tem certeza que deseja excluir esta questão?">'
            . csrf_field() . method_field('DELETE')
            . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
            . '</form>'
            . '</li>'
            . '</ul></div>';

        return [
            'texto' => $questao->texto,
            'indicador' => $questao->indicador->descricao ?? '—',
            'evidencia' => $questao->evidencia->descricao ?? '—',
            'tipo' => ucfirst($questao->tipo),
            'escala' => $questao->escala->descricao ?? '—',
            'modelo' => $questao->template->nome ?? '—',
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table
        id="grid-questaos"
        :columns="$columns"
        :rows="$rows"
        :page-size="15"
    />
</div>
@endsection
