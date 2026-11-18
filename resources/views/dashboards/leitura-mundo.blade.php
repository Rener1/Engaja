@extends('layouts.app')

@section('content')
<x-ui.page-hero contained
    eyebrow="Dashboards" eyebrow-icon="globe2"
    title="Leitura do mundo"
    subtitle="Selecione uma leitura para abrir o dashboard de respostas."
    class="mb-0">
  <x-slot:actions>
    <a href="{{ route('dashboard') }}" class="eg-hero-btn"><i class="bi bi-grid me-1"></i> Hub de dashboards</a>
    <a href="{{ route('dashboards.avaliacoes') }}" class="eg-hero-btn"><i class="bi bi-chat-square-text me-1"></i> Dashboard de respostas</a>
  </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
  @if($erro)
    <div class="alert alert-danger border-0 shadow-sm">
      Não foi possível carregar as leituras do LimeSurvey: {{ $erro }}
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">SID</th>
              <th scope="col">Leitura do mundo</th>
              <th scope="col">Status</th>
              <th scope="col">Dados atualizados em:</th>
              <th scope="col" class="text-end">Ação</th>
            </tr>
          </thead>
          <tbody>
            @forelse($surveys as $survey)
              <tr>
                <td class="fw-semibold">{{ $survey['sid'] }}</td>
                <td>{{ $survey['titulo'] }}</td>
                <td>
                  @if($survey['ativo'])
                    <span class="badge text-bg-success">Ativo</span>
                  @else
                    <span class="badge text-bg-secondary">Inativo</span>
                  @endif
                </td>
                <td>
                  @if($survey['cached_at'])
                    <span class="text-success small">{{ $survey['cached_at'] }}</span>
                  @else
                    <span class="text-muted small">Sem registros</span>
                  @endif
                </td>
                <td class="text-end">
                  @if($survey['cached_at'])
                    <a
                      href="{{ route('dashboards.avaliacoes', ['fonte' => 'limesurvey', 'survey_id' => $survey['sid']]) }}"
                      class="btn btn-sm btn-primary">
                      Abrir dashboard
                    </a>
                  @else
                    <button class="btn btn-sm btn-secondary" disabled
                            title="Sem dados em cache. Aguarde a atualização automática ou execute o importador.">
                      Sem dados
                    </button>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7"><x-ui.empty-state compact muted icon="journal-x" title="Nenhuma leitura encontrada." /></td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
