@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <p class="text-uppercase text-muted small mb-1">Administração</p>
      <h1 class="h4 fw-bold mb-3">Editar certificado emitido</h1>
      <div class="card shadow-sm">
        <div class="card-body">
          <form method="POST" action="{{ route('certificados.update', $certificado) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
              <label class="form-label">Participante</label>
              <input type="text" class="form-control" value="{{ $certificado->participante?->user?->name ?? '-' }}" disabled>
            </div>

            <div class="mb-3">
              <label class="form-label">Ação pedagógica</label>
              <input type="text" class="form-control" value="{{ $certificado->evento_nome ?? '-' }}" disabled>
            </div>

            <div class="mb-3">
              <label class="form-label">Modelo</label>
              <input type="text" class="form-control" value="{{ $certificado->modelo?->nome ?? '-' }}" disabled>
            </div>

            <div class="mb-3">
              <label class="form-label" for="texto_frente">Texto da frente</label>
              <textarea id="texto_frente" name="texto_frente" rows="5" class="form-control @error('texto_frente') is-invalid @enderror">{{ old('texto_frente', $certificado->texto_frente) }}</textarea>
              @error('texto_frente') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="texto_verso">Texto do verso</label>
              <textarea id="texto_verso" name="texto_verso" rows="5" class="form-control @error('texto_verso') is-invalid @enderror">{{ old('texto_verso', $certificado->texto_verso) }}</textarea>
              @error('texto_verso') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex gap-2 justify-content-end">
              <a href="{{ route('certificados.emitidos') }}" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-engaja">Salvar alterações</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
