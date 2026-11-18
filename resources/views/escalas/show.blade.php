@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="sliders" :title="$escala->descricao">
    <x-slot:actions>
        <a href="{{ route('escalas.edit', $escala) }}" class="eg-hero-btn">Editar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Opções</h2>
        <ul class="list-group list-group-flush mb-3">
          @php
            $opcoes = collect([$escala->opcao1, $escala->opcao2, $escala->opcao3, $escala->opcao4, $escala->opcao5]);
          @endphp
          @forelse ($opcoes->filter() as $opcao)
          <li class="list-group-item px-0">{!! $opcao !!}</li>
          @empty
          <li class="list-group-item px-0 text-muted">Nenhuma opção configurada.</li>
          @endforelse
        </ul>

        <a href="{{ route('escalas.index') }}" class="btn btn-link px-0">Voltar para lista</a>
      </div>
    </div>
  </div>
</div>
@endsection
