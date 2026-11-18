@extends('layouts.app')

@section('content')
<style>
    .momento-toggle {
        min-width: 0;
    }
    .momento-toggle:focus-visible {
        outline: 2px solid #421944;
        outline-offset: 2px;
        border-radius: .5rem;
    }
    .badge-relatorios {
        background: #0ea5e9;
        color: #fff;
        font-weight: 600;
    }
    .badge-respostas {
        background: #f3e8f8;
        color: #421944;
        border: 1px solid #d9b7e2;
        font-weight: 600;
    }
    .btn-pdf-geral {
        border: 1px solid #86efac;
        color: #166534;
        background: linear-gradient(180deg, #f0fdf4 0%, #dcfce7 100%);
        font-weight: 600;
        border-radius: 999px;
        padding: .38rem .85rem;
        line-height: 1.1;
        box-shadow: 0 1px 2px rgba(22, 101, 52, 0.10);
        transition: all .15s ease-in-out;
    }
    .btn-pdf-geral:hover {
        background: linear-gradient(180deg, #dcfce7 0%, #bbf7d0 100%);
        color: #14532d;
        border-color: #4ade80;
        box-shadow: 0 2px 6px rgba(22, 101, 52, 0.18);
        transform: translateY(-1px);
    }
    .btn-pdf-geral:focus-visible {
        outline: 2px solid #16a34a;
        outline-offset: 1px;
    }
</style>

<x-ui.page-hero contained
    eyebrow="Relatórios" eyebrow-icon="journal-text"
    title="Relatórios dos Momentos"
    :subtitle="auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica'])
        ? 'Relatórios pós-ação preenchidos por utilizadores do sistema'
        : 'Os seus relatórios individuais pós-ação'"
    class="mb-0" />

<div class="container py-4">

    {{-- Filtro --}}
    <x-ui.filter-card>
        <form method="GET" action="{{ route('avaliacao-atividade.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="eg-filter-label">Ação Pedagógica</label>
                <x-ui.select name="acao_ids[]" id="filter-acao-relatorio" multiple data-multiselect data-placeholder="Selecione uma ou mais ações">
                    @foreach($acoesDisponiveis as $acao)
                        <option value="{{ $acao->id }}" @selected(in_array($acao->id, $acaoIds, true))>
                            {{ $acao->nome }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="col-md-4">
                <label class="eg-filter-label">Momentos</label>
                <x-ui.select name="momento_ids[]" id="filter-momento-relatorio" multiple data-dependent-moment data-placeholder="Selecione primeiro uma ação" :disabled="empty($acaoIds)">
                    @foreach($atividadesDisponiveis->whereIn('evento_id', $acaoIds) as $atividadeOpcao)
                        <option value="{{ $atividadeOpcao->id }}" @selected(in_array($atividadeOpcao->id, $momentoIds, true))>
                            {{ $atividadeOpcao->descricao ?? 'Momento sem descrição' }}
                            @if($atividadeOpcao->dia)
                                ({{ \Carbon\Carbon::parse($atividadeOpcao->dia)->format('d/m/Y') }})
                            @endif
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="col-md-4">
                <label class="eg-filter-label">Municípios</label>
                <x-ui.select name="municipio_ids[]" id="filter-municipio-relatorio" multiple data-multiselect data-placeholder="Todos os municípios">
                    @foreach($municipiosDisponiveis as $municipio)
                        <option value="{{ $municipio->id }}" @selected(in_array((string) $municipio->id, array_map('strval', request()->input('municipio_ids', [])), true))>
                            {{ $municipio->nome_com_estado }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
            <div class="col-auto eg-filter-actions align-items-center">
                <button type="submit" class="btn btn-engaja"><i class="bi bi-funnel me-1"></i>Aplicar</button>
                <a href="{{ route('avaliacao-atividade.index') }}" class="btn btn-outline-secondary">Limpar</a>
                    
                @if(auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica']) && !$acoesAgrupadas->isEmpty())
                    <div class="vr mx-1"></div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="acoesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear me-1"></i> Ações
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="acoesDropdown" style="border-radius: 0.5rem; min-width: 200px;">
                            <li>
                                <button type="submit" formaction="{{ route('avaliacao-atividade.download-consolidated') }}" class="dropdown-item d-flex align-items-center py-2" title="Baixar PDF consolidado">
                                    <i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i> 
                                    <span>Exportar PDF Geral</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                @endif
            </div>
        </form>
    </x-ui.filter-card>

    @if($acoesAgrupadas->isEmpty())
        <x-ui.empty-state icon="journal-x" muted title="Nenhum relatório encontrado">
            Ajuste os filtros ou preencha um relatório a partir do momento.
        </x-ui.empty-state>
    @else
    <div class="vstack gap-3">
        @foreach($acoesAgrupadas as $nomeAcao => $momentos)
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0 text-engaja">{{ $nomeAcao }}</h2>
            </div>
            <div class="card-body">
                @foreach($momentos as $momentoIndex => $momento)
                @php
                    $atividade = $momento['atividade'];
                    $relatoriosMomento = $momento['relatorios'];
                    $collapseId = 'relatorio-momento-' . ($atividade?->id ?? 'x') . '-' . $momentoIndex;
                @endphp
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <button class="btn momento-toggle flex-grow-1 text-start d-flex flex-wrap justify-content-between align-items-start gap-2 p-0 border-0 bg-transparent"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}"
                            aria-expanded="false"
                            aria-controls="{{ $collapseId }}">
                        <span>
                            <span class="fw-semibold d-block">{{ $atividade?->descricao ?? 'Momento não informado' }}</span>
                            <span class="small text-muted d-block">
                                {{ $atividade?->dia ? \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') : '—' }}
                                @if($atividade?->hora_inicio || $atividade?->hora_fim)
                                    • {{ $atividade?->hora_inicio ? \Carbon\Carbon::parse($atividade->hora_inicio)->format('H:i') : '?' }} - {{ $atividade?->hora_fim ? \Carbon\Carbon::parse($atividade->hora_fim)->format('H:i') : '?' }}
                                @endif
                                • {{ $atividade?->municipios?->map(fn($m) => $m->nome_com_estado ?? $m->nome)->join(', ') ?: 'Município não informado' }}
                            </span>
                        </span>
                        <span class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                            <span class="badge rounded-pill badge-relatorios">{{ $relatoriosMomento->count() }} relatório(s)</span>
                            <span class="badge rounded-pill badge-respostas" style="font-size:.85rem; padding:.45rem .7rem;">
                                Ver respostas
                            </span>
                        </span>
                    </button>
                    @if($atividade && auth()->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica']))
                    <a href="{{ route('avaliacao-atividade.download-all', $atividade) }}"
                       class="btn btn-sm btn-pdf-geral text-nowrap"
                       title="Baixar PDF consolidado com todos os relatórios deste momento">
                        📄 PDF Geral
                    </a>
                    <a href="{{ route('avaliacao-atividade.download-all', ['atividade' => $atividade, 'formato' => 'docx']) }}"
                       class="btn btn-sm btn-outline-primary text-nowrap"
                       title="Baixar Word consolidado com todos os relatórios deste momento">
                        <i class="bi bi-file-earmark-word"></i> Word Geral
                    </a>
                    @endif
                    </div>

                    <div id="{{ $collapseId }}" class="collapse mt-3">

                    @foreach($camposPerguntas as $campo => $pergunta)
                        @php
                            $respostas = $relatoriosMomento->filter(function ($relatorio) use ($campo) {
                                return filled(trim((string)($relatorio->$campo ?? '')));
                            });
                        @endphp
                        <div class="mb-3">
                            <div class="fw-semibold mb-2">{{ $pergunta }}</div>

                            @if($respostas->isEmpty())
                                <div class="text-muted small border rounded p-2">Sem respostas para esta pergunta.</div>
                            @else
                                @foreach($respostas as $relatorio)
                                    <div class="border rounded p-2 mb-2 bg-light">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                            <div class="small text-muted">
                                                <strong>{{ $relatorio->user->name ?? $relatorio->nome_educador ?? 'Usuário não informado' }}</strong>
                                                • {{ $relatorio->updated_at ? $relatorio->updated_at->format('d/m/Y H:i') : '—' }}
                                            </div>
                                            <div class="d-flex gap-1">
                                                <a href="{{ route('avaliacao-atividade.show', $relatorio) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                                <a href="{{ route('avaliacao-atividade.download', $relatorio) }}" class="btn btn-sm btn-outline-secondary">PDF</a>
                                                <a href="{{ route('avaliacao-atividade.download', ['relatorio' => $relatorio, 'formato' => 'docx']) }}" class="btn btn-sm btn-outline-secondary">Word</a>
                                            </div>
                                        </div>
                                        <div class="mt-2 bg-white p-3 border rounded">{!! $relatorio->$campo !!}</div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>

@php
    $momentosOptions = $atividadesDisponiveis->map(function ($atividade) {
        return [
            'value' => (string) $atividade->id,
            'acao_id' => (string) $atividade->evento_id,
            'text' => ($atividade->descricao ?? 'Momento sem descrição')
                . ($atividade->dia ? ' (' . \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') . ')' : ''),
        ];
    })->values();
@endphp
<script type="application/json" id="relatorio-momentos-options">@json($momentosOptions)</script>
@endsection
