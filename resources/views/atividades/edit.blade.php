@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Operações" eyebrow-icon="calendar3" :title="'Editar momento — '.$evento->nome">
    <x-slot:actions>
        <a href="{{ route('eventos.show', $evento) }}" class="eg-hero-btn">Voltar à ação pedagógica</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container">
  @if ($errors->any())
    <div class="alert alert-danger"><strong>Ops!</strong> Verifique os campos abaixo.</div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('atividades.update', $atividade) }}">
        @method('PUT')
        @include('atividades._form', [
          'evento' => $evento,
          'atividade' => $atividade,
          'atividadesCopiaveis' => $atividadesCopiaveis,
          'submitLabel' => 'Salvar alterações'
        ])
      </form>
    </div>
  </div>
</div>
@endsection
