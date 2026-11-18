@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Certificados" eyebrow-icon="award" title="Certificados emitidos" class="mb-0">
    <p class="text-muted mb-0">Lista de todos os certificados já emitidos no sistema.</p>
    <x-slot:actions>
        <a href="{{ route('certificados.emitidos.zip', request()->query()) }}" class="eg-hero-btn">
          Baixar tudo (ZIP)
        </a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
  <x-ui.filter-card>
      <form method="GET" action="{{ route('certificados.emitidos') }}" class="row g-3 align-items-end">
        @if(!empty($contextoEvento) && !empty($filtroEventoId))
          <input type="hidden" name="evento_id" value="{{ $filtroEventoId }}">
          <input type="hidden" name="contexto" value="evento">
        @endif

        <x-ui.filter-field @class(['col-md-6' => empty($contextoEvento), 'col-md-9' => ! empty($contextoEvento)]) label="Participante" icon="person" for="filtro-participante">
          <x-ui.input type="text" id="filtro-participante" name="participante" value="{{ $filtroParticipante ?? '' }}" placeholder="Nome ou CPF" />
        </x-ui.filter-field>

        @if(empty($contextoEvento))
          <x-ui.filter-field class="col-md-3" label="Ação pedagógica" icon="calendar-event" for="filtro-acao">
            <x-ui.select name="evento_id" id="filtro-acao">
              <option value="">Todas as ações pedagógicas</option>
              @foreach($acoesCertificado as $acao)
                <option value="{{ $acao->id }}" @selected((string) ($filtroEventoId ?? '') === (string) $acao->id)>
                  {{ $acao->nome }}
                </option>
              @endforeach
            </x-ui.select>
          </x-ui.filter-field>
        @endif

        <div class="col-md-3 eg-filter-actions">
          <button class="btn btn-engaja" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
          <a href="{{ !empty($contextoEvento) && !empty($filtroEventoId) ? route('certificados.emitidos', ['evento_id' => $filtroEventoId, 'contexto' => 'evento']) : route('certificados.emitidos') }}" class="btn btn-outline-secondary">Limpar</a>
        </div>
      </form>
  </x-ui.filter-card>
  @php
      $podeEditarCertificados = auth()->user()?->hasAnyRole(['administrador', 'gerente']) ?? false;
      $columns = [
          ['field' => 'participante', 'headerName' => 'Participante', 'flex' => 2],
          ['field' => 'acao', 'headerName' => 'Ação pedagógica', 'flex' => 2],
          ['field' => 'modelo', 'headerName' => 'Modelo', 'flex' => 1, 'align' => 'center'],
          ['field' => 'carga_horaria', 'headerName' => 'Carga horária', 'flex' => 1, 'align' => 'center'],
          ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true, 'align' => 'center'],
      ];

      $rows = $certificados->map(fn ($cert) => [
          'participante' => $cert->participante?->user?->name ?? '-',
          'acao' => $cert->evento_nome ?? '-',
          'modelo' => $cert->modelo?->nome ?? '-',
          'carga_horaria' => \App\Support\CargaHoraria::formatMinutos(isset($cert->carga_horaria) ? (int) $cert->carga_horaria : null),
          'acoes' => '<div class="dropdown">'
              . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
              . '<ul class="dropdown-menu dropdown-menu-end">'
              . '<li><a class="dropdown-item" href="' . route('certificados.download', $cert) . '">Baixar PDF</a></li>'
              . ($podeEditarCertificados ? '<li><a class="dropdown-item" href="' . route('certificados.edit', $cert) . '">Editar</a></li>' : '')
              . '</ul></div>',
      ])->values();

      $temFiltroAtivo = ! empty($filtroParticipante) || ! empty($filtroAcao) || (! empty($filtroEventoId) && empty($contextoEvento));
      $mensagemVazia = $temFiltroAtivo
          ? 'Nenhum certificado encontrado com esses filtros.'
          : 'Nenhum certificado emitido até o momento.';
  @endphp

  {{-- Sem registros, o próprio grid mostra o aviso (empty-text) — não duplicar com outro empty state. --}}
  <div class="shadow-sm rounded-3 bg-white">
      <x-data-table id="grid-certificados-emitidos" :columns="$columns" :rows="$rows" :pagination="false" :empty-text="$mensagemVazia" :paginator="$certificados" />
  </div>
</div>

@endsection
