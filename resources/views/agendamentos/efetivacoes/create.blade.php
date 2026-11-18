@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Efetivar agendamento">
    <div class="text-muted">{{ optional($agendamento->data_horario)->format('d/m/Y H:i') }} · {{ $agendamento->atividadeAcao?->nome ?? 'Atividade/Ação' }}</div>
    <x-slot:actions>
        <a href="{{ route('agendamentos.efetivacoes.index') }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-xl-10">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <form method="POST" action="{{ route('agendamentos.efetivacoes.confirm', $agendamento) }}">
              @csrf

              <div class="mb-3">
                <label for="evento_id" class="form-label">Ação institucional</label>
                <select name="evento_id" id="evento_id" class="form-select @error('evento_id') is-invalid @enderror" required>
                  <option value="">Selecione a ação</option>
                  @foreach($eventos as $evento)
                    <option value="{{ $evento->id }}" @selected(old('evento_id') == $evento->id)>
                      {{ $evento->nome }}{{ $evento->data_inicio ? ' · ' . \Illuminate\Support\Carbon::parse($evento->data_inicio)->format('d/m/Y') : '' }}
                    </option>
                  @endforeach
                </select>
                @error('evento_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="mb-3">
                <label for="descricao" class="form-label">Descrição do momento</label>
                <textarea name="descricao" id="descricao" rows="4" class="form-control @error('descricao') is-invalid @enderror" required>{{ old('descricao', $dadosPadrao['descricao']) }}</textarea>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label for="dia" class="form-label">Dia</label>
                  <input type="date" name="dia" id="dia" value="{{ old('dia', $dadosPadrao['dia']) }}" class="form-control @error('dia') is-invalid @enderror" required>
                  @error('dia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                  <label for="hora_inicio" class="form-label">Hora de início</label>
                  <input type="time" name="hora_inicio" id="hora_inicio" value="{{ old('hora_inicio', $dadosPadrao['hora_inicio']) }}" class="form-control @error('hora_inicio') is-invalid @enderror" required>
                  @error('hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                  <label for="hora_fim" class="form-label">Hora de término</label>
                  <input type="time" name="hora_fim" id="hora_fim" value="{{ old('hora_fim', $dadosPadrao['hora_fim']) }}" class="form-control @error('hora_fim') is-invalid @enderror" required>
                  @error('hora_fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-6">
                  <label for="publico_esperado" class="form-label">Público esperado</label>
                  <input type="number" name="publico_esperado" id="publico_esperado" min="0" step="1" value="{{ old('publico_esperado', $dadosPadrao['publico_esperado']) }}" class="form-control @error('publico_esperado') is-invalid @enderror">
                  @error('publico_esperado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Carga horária</label>
                  <div class="row g-2">
                    <div class="col-6">
                      <label for="carga_horas" class="form-label small text-muted mb-0">Horas</label>
                      <input type="number" name="carga_horas" id="carga_horas" min="0" step="1" value="{{ old('carga_horas', $dadosPadrao['carga_horas'] ?? 0) }}" class="form-control @error('carga_horas') is-invalid @enderror">
                      @error('carga_horas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                      <label for="carga_minutos" class="form-label small text-muted mb-0">Minutos</label>
                      <input type="number" name="carga_minutos" id="carga_minutos" min="0" max="59" step="1" value="{{ old('carga_minutos', $dadosPadrao['carga_minutos'] ?? 0) }}" class="form-control @error('carga_minutos') is-invalid @enderror">
                      @error('carga_minutos') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                  </div>
                  <div class="form-text">Deixe em 0 para calcular pela duração entre início e fim.</div>
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('agendamentos.show', $agendamento) }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-engaja">Revisar efetivação</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Resumo do agendamento</h2>
            <dl class="row mb-0">
              <dt class="col-sm-5">Município</dt>
              <dd class="col-sm-7">{{ $agendamento->municipio?->nome_com_estado ?? '—' }}</dd>
              <dt class="col-sm-5">Atividade/Ação</dt>
              <dd class="col-sm-7">{{ $agendamento->atividadeAcao?->nome ?? '—' }}</dd>
              <dt class="col-sm-5">Turma</dt>
              <dd class="col-sm-7">{{ $agendamento->turma ?: '—' }}</dd>
              <dt class="col-sm-5">Público</dt>
              <dd class="col-sm-7">{{ $agendamento->publico_participante }}</dd>
              <dt class="col-sm-5">Local</dt>
              <dd class="col-sm-7">{{ $agendamento->local_acao }}</dd>
            </dl>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-body">
            <h2 class="h6 text-uppercase text-muted mb-3">Participantes</h2>
            <div class="d-flex justify-content-between mb-2"><span>Total</span><strong>{{ $resumo['total'] }}</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>Usuários já localizados</span><strong>{{ $resumo['usuarios_existentes'] }}</strong></div>
            <div class="d-flex justify-content-between"><span>Usuários a criar</span><strong>{{ $resumo['usuarios_a_criar'] }}</strong></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
