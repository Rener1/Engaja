@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Agendamentos efetivados">
    <div class="text-muted">Lista apenas os agendamentos já convertidos em momento.</div>
    <x-slot:actions>
        <a href="{{ route('agendamentos.efetivacoes.index') }}" class="eg-hero-btn">Voltar para efetivação</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="card shadow-sm">
  <x-ui.table-frame :paginator="$agendamentos" :bordered="false">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Efetivado em</th>
              <th>Data do agendamento</th>
              <th>Município</th>
              <th>Atividade/Ação</th>
              <th>Participantes</th>
              <th>Momento criado</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($agendamentos as $agendamento)
              <tr>
                <td>{{ optional($agendamento->efetivado_em)->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ optional($agendamento->data_horario)->format('d/m/Y H:i') }}</td>
                <td>{{ $agendamento->municipio?->nome_com_estado ?? '—' }}</td>
                <td>{{ $agendamento->atividadeAcao?->nome ?? '—' }}</td>
                <td>{{ $agendamento->participantes_clonados_count ?? 0 }}</td>
                <td>
                  @if($agendamento->atividade)
                    <div>{{ $agendamento->atividade->descricao }}</div>
                    @if($agendamento->atividade->evento)
                      <div class="small text-muted">{{ $agendamento->atividade->evento->nome }}</div>
                    @endif
                  @else
                    —
                  @endif
                </td>
                <td class="text-end">
                  <a href="{{ route('agendamentos.show', $agendamento) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                  @if($agendamento->atividade)
                    <a href="{{ route('atividades.show', $agendamento->atividade) }}" class="btn btn-sm btn-outline-success">Momento</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7"><x-ui.empty-state compact muted icon="inbox" title="Nenhum agendamento efetivado encontrado." /></td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
  </x-ui.table-frame>
</div>

@endsection
