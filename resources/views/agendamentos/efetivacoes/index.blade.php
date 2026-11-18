@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Efetuar agendamentos">
    <div class="text-muted">Somente agendamentos ainda não efetivados aparecem aqui.</div>
    <x-slot:actions>
        <a href="{{ route('agendamentos.efetivados.index') }}" class="eg-hero-btn">Ver agendamentos efetivados</a>
    </x-slot:actions>
</x-ui.page-hero>

@php
    $columns = [
        ['field' => 'data_horario', 'headerName' => 'Data e horário', 'flex' => 1],
        ['field' => 'municipio', 'headerName' => 'Município', 'flex' => 2],
        ['field' => 'atividade_acao', 'headerName' => 'Atividade/Ação', 'flex' => 2],
        ['field' => 'turma', 'headerName' => 'Turma', 'flex' => 1],
        ['field' => 'participantes', 'headerName' => 'Participantes', 'flex' => 1, 'align' => 'end'],
        ['field' => 'responsavel', 'headerName' => 'Responsável', 'flex' => 1],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true, 'align' => 'center'],
    ];

    $rows = $agendamentos->map(fn ($agendamento) => [
        'data_horario' => optional($agendamento->data_horario)->format('d/m/Y H:i') ?? '—',
        'municipio' => $agendamento->municipio?->nome_com_estado ?? '—',
        'atividade_acao' => $agendamento->atividadeAcao?->nome ?? '—',
        'turma' => $agendamento->turma ?: '—',
        'participantes' => $agendamento->participantes_clonados_count ?? 0,
        'responsavel' => $agendamento->user?->name ?? '—',
        'acoes' => '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('agendamentos.show', $agendamento) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('agendamentos.efetivacoes.create', $agendamento) . '">Efetivar</a></li>'
            . '</ul></div>',
    ])->values();
@endphp

<div class="card shadow-sm">
    <x-data-table id="grid-agendamentos-efetivacoes" :columns="$columns" :rows="$rows" :pagination="false" />
</div>

<div class="mt-3">
  {{ $agendamentos->links() }}
</div>
@endsection
