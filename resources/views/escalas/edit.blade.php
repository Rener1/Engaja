@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="sliders" title="Editar escala" />

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('escalas.update', $escala) }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <input type="text" id="descricao" name="descricao"
              class="form-control @error('descricao') is-invalid @enderror"
              value="{{ old('descricao', $escala->descricao) }}" required>
            @error('descricao')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="row g-3">
            @for ($i = 1; $i <= 5; $i++)
            @php $campo = 'opcao'.$i; @endphp
            <div class="col-12">
              <label for="opcao{{ $i }}" class="form-label">Opção {{ $i }}</label>
              <textarea id="opcao{{ $i }}" name="opcao{{ $i }}" rows="3"
                class="form-control wysiwyg-field @error($campo) is-invalid @enderror"
                data-wysiwyg>{{ old($campo, $escala->$campo) }}</textarea>
              @error($campo)
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            @endfor
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="{{ route('escalas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-engaja">Salvar alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@include('escalas.partials.wysiwyg')
