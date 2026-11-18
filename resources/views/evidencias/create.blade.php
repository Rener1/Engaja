@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="file-earmark-check" title="Nova evidência" />

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('evidencias.store') }}">
          @csrf

          <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" id="descricao" name="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" required>
            @error('descricao')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="indicador_id" class="form-label">Indicador</label>
            <select id="indicador_id" name="indicador_id" class="form-select @error('indicador_id') is-invalid @enderror" required>
              <option value="">Selecione...</option>
              @foreach ($indicadores as $id => $descricao)
                <option value="{{ $id }}" @selected(old('indicador_id') == $id)>{{ $descricao }}</option>
              @endforeach
            </select>
            @error('indicador_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('evidencias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

