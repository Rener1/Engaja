@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Meu perfil" eyebrow-icon="person-circle" title="Meus certificados">
    <x-slot:actions>
        <a href="{{ route('profile.edit') }}" class="eg-hero-btn">Voltar para o perfil</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container">
  @if ($certificados->isEmpty())
    <x-ui.empty-state icon="inbox" muted>Você ainda não possui certificados emitidos.</x-ui.empty-state>
  @else
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Ação pedagógica</th>
              <th>Emitido em</th>
              <th>Carga horária</th>
              <th class="text-end pe-4">Ações</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($certificados as $c)
              <tr>
                <td class="text-truncate" style="max-width: 300px;" title="{{ $c->evento_nome ?? $c->modelo?->nome ?? 'Evento' }}">{{ $c->evento_nome ?? $c->modelo?->nome ?? 'Evento' }}</td>
                <td>{{ $c->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ \App\Support\CargaHoraria::formatMinutos(isset($c->carga_horaria) ? (int) $c->carga_horaria : null) }}</td>
                <td class="text-end pe-4">
                  <a href="{{ route('certificados.download', $c) }}" class="btn btn-sm btn-engaja">
                    Baixar PDF
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
