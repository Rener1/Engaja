@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="sliders" title="Nova escala" />

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('escalas.store') }}">
          @csrf

          <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" id="descricao" name="descricao"
              class="form-control @error('descricao') is-invalid @enderror"
              value="{{ old('descricao') }}" required>
            @error('descricao')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="row g-3">
            @for ($i = 1; $i <= 5; $i++)
            <div class="col-12">
              <label for="opcao{{ $i }}" class="form-label">Opção {{ $i }}</label>
              <textarea id="opcao{{ $i }}" name="opcao{{ $i }}" rows="3"
                class="form-control wysiwyg-field @error('opcao'.$i) is-invalid @enderror"
                data-wysiwyg>{{ old('opcao'.$i) }}</textarea>
              @error('opcao'.$i)
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            @endfor
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('escalas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@include('escalas.partials.wysiwyg')
