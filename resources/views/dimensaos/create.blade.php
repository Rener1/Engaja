@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="diagram-3" title="Nova dimensão" />

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('dimensaos.store') }}">
          @csrf

          <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" id="descricao" name="descricao" class="form-control @error('descricao') is-invalid @enderror"
              value="{{ old('descricao') }}" required>
            @error('descricao')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="d-flex justify-content-between">
            <a href="{{ route('dimensaos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
