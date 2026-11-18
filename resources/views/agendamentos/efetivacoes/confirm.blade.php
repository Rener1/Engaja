@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Confirmar efetivação">
    <div class="text-muted">Revise os dados antes de criar o momento e inscrever os participantes.</div>
    <x-slot:actions>
        <a href="{{ route('agendamentos.efetivacoes.create', $agendamento) }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-xl-9">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-4">
          <div class="col-md-6">
            <h2 class="h6 text-uppercase text-muted mb-3">Ação institucional</h2>
            <p class="mb-1 fw-semibold">{{ $evento->nome }}</p>
            @if($evento->data_inicio || $evento->data_fim)
              <p class="text-muted mb-0">
                {{ $evento->data_inicio ? \Illuminate\Support\Carbon::parse($evento->data_inicio)->format('d/m/Y') : '—' }}
                @if($evento->data_fim)
                  até {{ \Illuminate\Support\Carbon::parse($evento->data_fim)->format('d/m/Y') }}
                @endif
              </p>
            @endif
          </div>
          <div class="col-md-6">
            <h2 class="h6 text-uppercase text-muted mb-3">Agendamento</h2>
            <p class="mb-1 fw-semibold">{{ $agendamento->atividadeAcao?->nome ?? '—' }}</p>
            <p class="text-muted mb-0">{{ optional($agendamento->data_horario)->format('d/m/Y H:i') }} · {{ $agendamento->municipio?->nome_com_estado ?? '—' }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">Momento a ser criado</h2>
        <dl class="row mb-0">
          <dt class="col-sm-3">Descrição</dt>
          <dd class="col-sm-9">{{ $dados['descricao'] }}</dd>
          <dt class="col-sm-3">Dia</dt>
          <dd class="col-sm-9">{{ \Illuminate\Support\Carbon::parse($dados['dia'])->format('d/m/Y') }}</dd>
          <dt class="col-sm-3">Horário</dt>
          <dd class="col-sm-9">{{ $dados['hora_inicio'] }} às {{ $dados['hora_fim'] }}</dd>
          <dt class="col-sm-3">Público esperado</dt>
          <dd class="col-sm-9">{{ $dados['publico_esperado'] }}</dd>
          <dt class="col-sm-3">Carga horária</dt>
          <dd class="col-sm-9">{{ \App\Support\CargaHoraria::formatMinutos($dados['carga_horaria'] ?? null) }}</dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">Impacto da efetivação</h2>
        <div class="d-flex justify-content-between mb-2"><span>Participantes a inscrever</span><strong>{{ $resumo['total'] }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Usuários já localizados</span><strong>{{ $resumo['usuarios_existentes'] }}</strong></div>
        <div class="d-flex justify-content-between"><span>Usuários a criar automaticamente</span><strong>{{ $resumo['usuarios_a_criar'] }}</strong></div>
      </div>
    </div>

    <form method="POST" action="{{ route('agendamentos.efetivacoes.store', $agendamento) }}" class="d-flex justify-content-end gap-2">
      @csrf
      <input type="hidden" name="evento_id" value="{{ $evento->id }}">
      <input type="hidden" name="descricao" value="{{ $dados['descricao'] }}">
      <input type="hidden" name="dia" value="{{ $dados['dia'] }}">
      <input type="hidden" name="hora_inicio" value="{{ $dados['hora_inicio'] }}">
      <input type="hidden" name="hora_fim" value="{{ $dados['hora_fim'] }}">
      <input type="hidden" name="publico_esperado" value="{{ $dados['publico_esperado'] }}">
      <input type="hidden" name="carga_horas" value="{{ $dados['carga_horas'] ?? 0 }}">
      <input type="hidden" name="carga_minutos" value="{{ $dados['carga_minutos'] ?? 0 }}">
      <a href="{{ route('agendamentos.efetivacoes.create', $agendamento) }}" class="btn btn-outline-secondary">Ajustar</a>
      <button type="submit" class="btn btn-engaja">Confirmar e efetivar</button>
    </form>
  </div>
</div>
@endsection
