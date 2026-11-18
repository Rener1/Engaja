@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Pré-visualização da importação" class="mb-0">
    <div class="text-muted small">Agendamento: {{ optional($agendamento->data_horario)->format('d/m/Y H:i') }} · {{ $agendamento->atividadeAcao?->nome ?? 'Atividade/Ação' }}</div>
    <x-slot:actions>
        <a href="{{ route('agendamentos.participantes.import', $agendamento) }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
  @if($observacoes)
    <div class="alert alert-info mb-3"><strong>Observações do envio:</strong> {{ $observacoes }}</div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="small text-muted">{{ $rows->total() }} linha(s) encontradas.</div>
    <form method="POST" action="{{ route('agendamentos.participantes.import.confirm', $agendamento) }}">
      @csrf
      <input type="hidden" name="session_key" value="{{ $sessionKey }}">
      <button class="btn btn-primary">Confirmar importação</button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle bg-white">
      <thead class="table-light">
        <tr>
          <th>Nome</th>
          <th>CPF</th>
          <th>E-mail</th>
          <th>Data nascimento</th>
          <th>Telefone</th>
          <th>Vínculo</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr>
            <td>{{ $row['nome'] }}</td>
            <td>{{ $row['cpf'] ?? '—' }}</td>
            <td>{{ $row['email'] ?? '—' }}</td>
            <td>{{ !empty($row['data_nascimento']) ? \Illuminate\Support\Carbon::parse($row['data_nascimento'])->format('d/m/Y') : '—' }}</td>
            <td>{{ $row['telefone'] ?? '—' }}</td>
            <td>{{ $row['vinculo'] ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="mt-2">
    <x-ui.pagination :paginator="$rows" />
  </div>
</div>
@endsection