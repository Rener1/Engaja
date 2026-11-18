@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Agendamento">
    <x-slot:actions>
        <a href="{{ route('agendamentos.participantes.index', $agendamento) }}" class="eg-hero-btn">Participantes</a>
        @unless($agendamento->efetivado)
          <a href="{{ route('agendamentos.edit', $agendamento) }}" class="eg-hero-btn">Editar</a>
        @endunless
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Data e horário</dt>
          <dd class="col-sm-8">{{ optional($agendamento->data_horario)->format('d/m/Y H:i') }}</dd>

          <dt class="col-sm-4">Município</dt>
          <dd class="col-sm-8">{{ $agendamento->municipio?->nome_com_estado ?? '—' }}</dd>

          <dt class="col-sm-4">Atividade/Ação</dt>
          <dd class="col-sm-8">{{ $agendamento->atividadeAcao?->nome ?? '—' }}</dd>

          <dt class="col-sm-4">Turma</dt>
          <dd class="col-sm-8">{{ $agendamento->turma ?: '—' }}</dd>

          <dt class="col-sm-4">Público participante</dt>
          <dd class="col-sm-8">{{ $agendamento->publico_participante }}</dd>

          <dt class="col-sm-4">Local da ação</dt>
          <dd class="col-sm-8">{{ $agendamento->local_acao }}</dd>

          <dt class="col-sm-4">Registrado por</dt>
          <dd class="col-sm-8">{{ $agendamento->user?->name ?? '—' }}</dd>

          <dt class="col-sm-4">Participantes cadastrados</dt>
          <dd class="col-sm-8">{{ $agendamento->participantes_clonados_count ?? 0 }}</dd>

          <dt class="col-sm-4">Status</dt>
          <dd class="col-sm-8">
            @if($agendamento->efetivado)
              <span class="badge bg-success">Efetivado em {{ optional($agendamento->efetivado_em)->format('d/m/Y H:i') }}</span>
            @else
              <span class="badge bg-warning text-dark">Pendente de efetivação</span>
            @endif
          </dd>

          <dt class="col-sm-4">Detalhe da atividade/ação</dt>
          <dd class="col-sm-8">{{ $agendamento->atividadeAcao?->detalhe ?: '—' }}</dd>

          @if($agendamento->atividade)
            <dt class="col-sm-4">Momento criado</dt>
            <dd class="col-sm-8">
              <a href="{{ route('atividades.show', $agendamento->atividade) }}" class="link-primary text-decoration-none">
                {{ $agendamento->atividade->descricao }}
              </a>
              @if($agendamento->atividade->evento)
                <div class="small text-muted">{{ $agendamento->atividade->evento->nome }}</div>
              @endif
            </dd>
          @endif
        </dl>
      </div>
    </div>

    <a href="{{ route('agendamentos.index') }}" class="btn btn-link px-0 mt-3">Voltar para lista</a>
  </div>
</div>
@endsection
