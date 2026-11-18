@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Agendamentos">
    <div class="text-muted">Gerencie os registros e acompanhe o status de efetivação.</div>
    <x-slot:actions>
        <a href="{{ route('agendamentos.create') }}" class="eg-hero-btn eg-hero-btn--solid">Novo agendamento</a>
    </x-slot:actions>
</x-ui.page-hero>

@php
    $columns = [
        ['field' => 'data_horario', 'headerName' => 'Data e horário', 'flex' => 1],
        ['field' => 'atividade_acao', 'headerName' => 'Atividade/Ação', 'flex' => 2],
        ['field' => 'turma', 'headerName' => 'Turma', 'flex' => 1],
        ['field' => 'publico_participante', 'headerName' => 'Público participante', 'flex' => 1],
        ['field' => 'local_acao', 'headerName' => 'Local da ação', 'flex' => 1],
        ['field' => 'participantes', 'headerName' => 'Participantes', 'flex' => 1],
        ['field' => 'status', 'headerName' => 'Status', 'flex' => 1, 'html' => true],
        ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
    ];

    $rows = $agendamentos->map(function ($agendamento) {
        $statusHtml = $agendamento->efetivado
            ? '<span class="badge bg-success">Efetivado</span>'
            : '<span class="badge bg-warning text-dark">Pendente</span>';

        $acoesHtml = '<div class="dropdown">'
            . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . route('agendamentos.show', $agendamento) . '">Ver</a></li>'
            . '<li><a class="dropdown-item" href="' . route('agendamentos.participantes.index', $agendamento) . '">Participantes</a></li>';

        if (! $agendamento->efetivado) {
            $acoesHtml .= '<li><a class="dropdown-item" href="' . route('agendamentos.edit', $agendamento) . '">Editar</a></li>'
                . '<li>'
                . '<form method="POST" action="' . route('agendamentos.destroy', $agendamento) . '" data-confirm="Tem certeza que deseja excluir este agendamento?">'
                . csrf_field() . method_field('DELETE')
                . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                . '</form>'
                . '</li>';
        }

        $acoesHtml .= '</ul></div>';

        return [
            'data_horario' => optional($agendamento->data_horario)->format('d/m/Y H:i') ?? '—',
            'atividade_acao' => $agendamento->atividadeAcao?->nome ?? '—',
            'turma' => $agendamento->turma ?: '—',
            'publico_participante' => $agendamento->publico_participante,
            'local_acao' => $agendamento->local_acao,
            'participantes' => $agendamento->participantes_clonados_count ?? 0,
            'status' => $statusHtml,
            'acoes' => $acoesHtml,
        ];
    })->values();
@endphp

<div class="card shadow-sm">
    <x-data-table
        id="grid-agendamentos"
        :columns="$columns"
        :rows="$rows"
        :page-size="15"
    />
</div>
@endsection
