@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="graph-up" :title="$indicador->descricao">
    <x-slot:actions>
        <a href="{{ route('indicadors.edit', $indicador) }}" class="eg-hero-btn">Editar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="mb-2"><span class="fw-semibold">Dimensão:</span> {{ $indicador->dimensao->descricao ?? '—' }}</p>
        <p class="mb-3"><span class="fw-semibold">Questões cadastradas:</span> {{ $indicador->questoes()->count() }}</p>

        <a href="{{ route('indicadors.index') }}" class="btn btn-link px-0">Voltar para lista</a>
      </div>
    </div>
  </div>
</div>
@endsection
