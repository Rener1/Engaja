@extends('layouts.app')

@section('content')
<div class="mb-4">
  <x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="people" title="Conferência de participantes exclusivos">
      <p class="text-muted mb-0">
        Pessoas com inscrição ou presença confirmada apenas nas ações selecionadas.
      </p>
      <x-slot:actions>
          <a href="{{ route('usuarios.participantes-exclusivos.index', ['eventos' => $eventoIds]) }}" class="eg-hero-btn border">Alterar seleção</a>
          <a href="{{ route('usuarios.participantes-exclusivos.exportar', ['eventos' => $eventoIds]) }}" class="eg-hero-btn">Exportar XLSX</a>
          <a href="{{ route('usuarios.participantes-exclusivos.exportar', ['eventos' => $eventoIds, 'formato' => 'docx']) }}" class="eg-hero-btn"><i class="bi bi-file-earmark-word"></i> Word</a>
      </x-slot:actions>
  </x-ui.page-hero>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="fw-semibold">Ações selecionadas:</span>
        @foreach($eventosSelecionados as $evento)
          <span class="badge bg-engaja">{{ $evento->nome }}</span>
        @endforeach
      </div>
      <div class="text-muted small mt-2">
        A checagem considera inscrições realizadas, presenças confirmadas e participações como ouvinte.
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="text-muted">
      <strong>{{ $participantes->total() }}</strong> pessoa(s) encontrada(s)
      @if($participantes->hasPages())
        | página {{ $participantes->currentPage() }} de {{ $participantes->lastPage() }}
      @endif
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Nome</th>
              <th>Email</th>
              <th>CPF</th>
              <th>Telefone</th>
              <th>Município</th>
              <th>Instituição</th>
              <th class="text-center">Inscrições</th>
              <th class="text-center pe-4">Presenças</th>
            </tr>
          </thead>
          <tbody>
            @forelse($participantes as $participante)
              <tr>
                <td class="ps-4">
                  <div class="fw-semibold">{{ $participante->nome ?? '-' }}</div>
                  @if($participante->tag)
                    <div class="text-muted small">{{ $participante->tag }}</div>
                  @endif
                </td>
                <td>{{ $participante->email ?? '-' }}</td>
                <td>{{ $participante->cpf ?? '-' }}</td>
                <td>{{ $participante->telefone ?? '-' }}</td>
                <td>
                  @if($participante->municipio && $participante->estado)
                    {{ $participante->municipio }} - {{ $participante->estado }}
                  @else
                    {{ $participante->municipio ?? $participante->estado ?? '-' }}
                  @endif
                </td>
                <td>
                  <div>{{ $participante->escola_unidade ?? '-' }}</div>
                  @if($participante->tipo_organizacao)
                    <div class="text-muted small">{{ $participante->tipo_organizacao }}</div>
                  @endif
                </td>
                <td class="text-center">{{ $participante->inscricoes_selecionadas }}</td>
                <td class="text-center pe-4">{{ $participante->presencas_selecionadas }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8"><x-ui.empty-state compact muted icon="inbox" title="Nenhuma pessoa atende aos critérios para as ações selecionadas." /></td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($participantes->hasPages())
      <div class="dt-footer">
        <x-ui.pagination :paginator="$participantes" />
      </div>
    @endif
  </div>
</div>
@endsection
