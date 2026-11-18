@extends('layouts.app')

@section('content')
@php
    $atividade = $relatorio->atividade;
    $evento = $atividade?->evento;
    $usuario = $relatorio->user;
    $nomeResponsavel = $usuario?->name ?? $relatorio->nome_educador ?? 'Usuário não identificado';

    $checklistLabels = [
        'upload_evidencias'       => 'Fez o upload das evidências (fotos, vídeos com depoimentos) na pasta correspondente a essa ação dentro do Drive',
        'lista_presenca_digital'  => 'Conferiu as listas de presença digital (link acima), garantindo que todos os campos estejam devidamente preenchidos',
        'lista_presenca_impressa' => 'Conferiu as listas de presença impressa, garantindo que todos os campos estejam devidamente preenchidos',
        'upload_lista_impressa'   => 'Fez o upload das listas de presença impressas na pasta dentro do Drive, depois de devidamente conferida e ajustada',
    ];

    $checklistSalvo = $relatorio->checklist_pos_acao ?? [];
@endphp

<x-ui.page-hero contained eyebrow="Relatórios" eyebrow-icon="journal-text" title="Visualização do Relatório do Momento" class="mb-0">
    <p class="text-muted mb-0">
        Relatório preenchido por {{ $nomeResponsavel }}
    </p>
    <x-slot:actions>
        <a href="{{ route('avaliacao-atividade.index') }}" class="eg-hero-btn">
            Voltar para relatórios
        </a>
        <a href="{{ route('avaliacao-atividade.download', $relatorio) }}" class="eg-hero-btn" target="_blank">
            Baixar PDF
        </a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="text-muted small d-block">Ação pedagógica</span>
                    <strong>{{ $evento?->nome ?? '—' }}</strong>
                </div>
                <div class="col-md-6">
                    <span class="text-muted small d-block">Momento</span>
                    <strong>{{ $atividade?->descricao ?? '—' }}</strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Data</span>
                    <strong>{{ $atividade?->dia ? \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') : '—' }}</strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Horário</span>
                    <strong>
                        {{ $atividade?->hora_inicio ? \Carbon\Carbon::parse($atividade->hora_inicio)->format('H:i') : '?' }}
                        -
                        {{ $atividade?->hora_fim ? \Carbon\Carbon::parse($atividade->hora_fim)->format('H:i') : '?' }}
                    </strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block">Última atualização</span>
                    <strong>{{ $relatorio->updated_at ? $relatorio->updated_at->format('d/m/Y H:i') : '—' }}</strong>
                </div>
                <div class="col-12">
                    <span class="text-muted small d-block">Município(s)</span>
                    <strong>
                        {{ $atividade?->municipios?->map(fn($m) => $m->nome_com_estado ?? $m->nome)->join(', ') ?: '—' }}
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 fw-bold mb-3" style="color:#421944;">Dados do relatório</h2>

            <div class="mb-3">
                <span class="text-muted small d-block">Nome do(a) educador(a)</span>
                <div class="border rounded p-2 bg-light">{{ $relatorio->nome_educador ?: $nomeResponsavel }}</div>
            </div>

            <div class="mb-3">
                <h2 class="h6 fw-bold mb-2" style="color:#421944;">📊 Quadro Resumo de Público</h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" style="max-width:700px;">
                        <tbody>
                            <tr>
                                <th class="bg-light" style="width:70%">Quantidade prevista de participantes</th>
                                <td class="text-center fw-semibold">{{ $resumoPublico['prevista'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Quantidade de inscritos</th>
                                <td class="text-center fw-semibold">{{ $resumoPublico['inscritos'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Quantidade de presentes na Ação</th>
                                <td class="text-center fw-semibold">{{ $resumoPublico['presentes'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Participantes ligados aos movimentos sociais</th>
                                <td class="text-center fw-semibold">{{ $resumoPublico['movimentos'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Participantes com vínculo com a Prefeitura</th>
                                <td class="text-center fw-semibold">{{ $resumoPublico['prefeitura'] ?? 0 }}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Participantes com vínculo não informado</th>
                                <td class="text-center fw-semibold">{{ $resumoPublico['sem_vinculo'] ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach($camposPerguntas as $campo => $pergunta)
            <div class="mb-3">
                <span class="text-muted small d-block mb-1">{{ $pergunta }}</span>
                <div class="border rounded p-3 bg-white shadow-sm">
                    {!! $relatorio->$campo ?: '<span class="text-muted">—</span>' !!}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 fw-bold mb-3" style="color:#421944;">Checklist pós-ação</h2>

            @if(empty($checklistSalvo))
                <div class="text-muted">Nenhum item marcado.</div>
            @else
                <ul class="list-group">
                    @foreach($checklistLabels as $valor => $label)
                        @if(in_array($valor, $checklistSalvo, true))
                            <li class="list-group-item d-flex align-items-start gap-2">
                                <span class="badge bg-success mt-1">OK</span>
                                <span>{{ $label }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection