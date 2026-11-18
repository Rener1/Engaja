@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="question-circle" title="Nova questão" />

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('questaos.store') }}">
          @csrf

          <div class="row g-3">
            <div class="col-md-6">
              <label for="template_avaliacao_id" class="form-label">Modelo de avaliação</label>
              <select id="template_avaliacao_id" name="template_avaliacao_id"
                class="form-select @error('template_avaliacao_id') is-invalid @enderror" required>
                <option value="">Selecione...</option>
                @foreach ($templates as $id => $nome)
                <option value="{{ $id }}" @selected(old('template_avaliacao_id') == $id)>{{ $nome }}</option>
                @endforeach
              </select>
              @error('template_avaliacao_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6">
              <label for="evidencia_id" class="form-label">Evidência</label>
              <select id="evidencia_id" name="evidencia_id"
                class="form-select @error('evidencia_id') is-invalid @enderror" required>
                <option value="">Selecione...</option>
                @foreach ($evidencias as $id => $descricao)
                <option value="{{ $id }}" @selected(old('evidencia_id') == $id)>{{ $descricao }}</option>
                @endforeach
              </select>
              @error('evidencia_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label for="texto" class="form-label">Enunciado</label>
            <textarea id="texto" name="texto"
              class="form-control @error('texto') is-invalid @enderror" rows="4" required>{{ old('texto') }}</textarea>
            @error('texto')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="row g-3">
            <div class="col-md-4">
              <label for="tipo" class="form-label">Tipo de resposta</label>
              <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                @foreach (['texto' => 'Texto aberto', 'escala' => 'Escala', 'numero' => 'Numérica', 'boolean' => 'Sim/Não'] as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(old('tipo') == $valor)>{{ $rotulo }}</option>
                @endforeach
              </select>
              @error('tipo')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-4">
              <label for="ordem" class="form-label">Ordem</label>
              <input type="number" id="ordem" name="ordem" min="1" max="999"
                class="form-control @error('ordem') is-invalid @enderror" value="{{ old('ordem') }}"
                placeholder="1, 2, 3...">
              @error('ordem')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-4">
              <label for="escala_id" class="form-label">Escala (quando tipo = Escala)</label>
              <select id="escala_id" name="escala_id"
                class="form-select @error('escala_id') is-invalid @enderror">
                <option value="">Selecione...</option>
                @foreach ($escalas as $id => $descricao)
                <option value="{{ $id }}" @selected(old('escala_id') == $id)>{{ $descricao }}</option>
                @endforeach
              </select>
              @error('escala_id')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="fixa" name="fixa"
                  value="1" @checked(old('fixa'))>
                <label class="form-check-label" for="fixa">Questão fixa</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('questaos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
