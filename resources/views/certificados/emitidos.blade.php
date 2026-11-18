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
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
    <div></div>
    <x-ui.filter-card>
        <form method="GET" action="{{ route('certificados.emitidos') }}" class="d-flex gap-2 w-100 w-md-auto">
          @if(!empty($contextoEvento) && !empty($filtroEventoId))
            <input type="hidden" name="evento_id" value="{{ $filtroEventoId }}">
            <input type="hidden" name="contexto" value="evento">
          @endif

          <x-ui.input type="text" name="participante" value="{{ $filtroParticipante ?? '' }}" placeholder="Filtrar por participante ou CPF" aria-label="Filtrar por participante ou CPF" />

          @if(empty($contextoEvento))
            <x-ui.select name="evento_id" aria-label="Filtrar por Ação pedagógica">
              <option value="">Todas as ações pedagógicas</option>
              @foreach($acoesCertificado as $acao)
                <option value="{{ $acao->id }}" @selected((string) ($filtroEventoId ?? '') === (string) $acao->id)>
                  {{ $acao->nome }}
                </option>
              @endforeach
            </x-ui.select>
          @endif

          <button class="btn btn-engaja" type="submit">Filtrar</button>
          <a href="{{ !empty($contextoEvento) && !empty($filtroEventoId) ? route('certificados.emitidos', ['evento_id' => $filtroEventoId, 'contexto' => 'evento']) : route('certificados.emitidos') }}" class="btn btn-outline-secondary">Limpar</a>
        </form>
    </x-ui.filter-card>
  </div>
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
  @endphp

  <div class="shadow-sm rounded-3 bg-white">
      <x-data-table id="grid-certificados-emitidos" :columns="$columns" :rows="$rows" :pagination="false" />
      @if($certificados->isEmpty())
        <x-ui.empty-state icon="award" muted compact>
          @if(!empty($filtroParticipante) || !empty($filtroAcao) || (!empty($filtroEventoId) && empty($contextoEvento)))
            Nenhum certificado encontrado com esses filtros.
          @else
            Nenhum certificado emitido até o momento.
          @endif
        </x-ui.empty-state>
      @endif
  </div>
  <x-ui.pagination :paginator="$certificados" />
</div>

@endsection
