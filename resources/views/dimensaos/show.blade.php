@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="diagram-3" :title="$dimensao->descricao">
    <x-slot:actions>
        <a href="{{ route('dimensaos.edit', $dimensao) }}" class="eg-hero-btn">Editar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="mb-3">
          <span class="fw-semibold">Indicadores relacionados:</span>
          {{ $dimensao->indicadores()->count() }}
        </p>

        <a href="{{ route('dimensaos.index') }}" class="btn btn-link px-0">Voltar para lista</a>
      </div>
    </div>
  </div>
</div>
@endsection
