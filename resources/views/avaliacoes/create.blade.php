@extends('layouts.app')

@section('content')
@php
  $universal = $universal ?? false;
  $transcricao = $transcricao ?? false;
  $formAction = $formAction ?? route('avaliacoes.store');
  $cancelUrl = $cancelUrl ?? route('avaliacoes.index');
@endphp
<div class="row justify-content-center">
  <div class="col-xl-10">
    <h1 class="h3 fw-bold text-engaja mb-4">
      @if($universal)
        Nova avaliação universal
      @else
        Nova avaliação
      @endif
    </h1>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form method="POST" action="{{ $formAction }}">
          @csrf

          <div class="row g-3">

            @unless($universal)
            <div class="col-md-6">
              <label for="atividade_id" class="form-label">Atividade</label>
              <select id="atividade_id" name="atividade_id"
                class="form-select @error('atividade_id') is-invalid @enderror" required>
                <option value="">Selecione...</option>
                @foreach ($atividades as $atividade)
                <option value="{{ $atividade->id }}" @selected(old('atividade_id') == $atividade->id)>
                  {{ $atividade->descricao }} — {{ $atividade->evento->nome ?? 'Sem evento' }}
                </option>
                @endforeach
              </select>
              @error('atividade_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            @endunless

            <div class="col-md-6">
              <label for="template_avaliacao_id" class="form-label">Modelo de avaliação</label>
              <select id="template_avaliacao_id" name="template_avaliacao_id"
                class="form-select @error('template_avaliacao_id') is-invalid @enderror" required>
                <option value="">Selecione...</option>
                @foreach ($templates as $template)
                <option value="{{ $template->id }}" @selected($selectedTemplateId == $template->id)>
                  {{ $template->nome }}
                </option>
                @endforeach
              </select>
              @error('template_avaliacao_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label for="descricao_universal" class="form-label">Descrição</label>
              <input type="text" id="descricao_universal" name="descricao_universal"
                class="form-control @error('descricao_universal') is-invalid @enderror"
                value="{{ old('descricao_universal') }}"
                placeholder="Ex.: Avaliação geral do ciclo formativo">
              @error('descricao_universal')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            @if(!$universal)
            <div class="col-md-6 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" value="1" id="anonima" name="anonima"
                  @checked(old('anonima'))>
                <label class="form-check-label" for="anonima">
                  Avaliação anônima
                </label>
                <div class="form-text">Se marcada, não vinculará as respostas à presença do participante.</div>
              </div>
            </div>
            @else
            <div class="col-md-6 d-flex align-items-center">
              <div class="form-check mt-4">
                <input class="form-check-input @error('is_cartas') is-invalid @enderror" type="checkbox"
                  value="1" id="is_cartas" name="is_cartas" @checked(old('is_cartas'))>
                <label class="form-check-label" for="is_cartas">
                  Avaliação para o Cartas para Esperançar
                </label>
                <div class="form-text">O link público será gerado no domínio do Cartas.</div>
                @error('is_cartas')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
            <div class="col-12">
              <div class="form-text">
                Avaliações universais são sempre anônimas e não ficam vinculadas a um momento.
              </div>
            </div>
            @endif
          </div>

          <div class="mt-4">
            @include('avaliacoes._questoes', [
        'questoesForm' => $questoesForm,
            ])
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar avaliação</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
