@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="diagram-2" title="Editar atividade/ação" />

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('atividade-acoes.update', $atividadeAcao) }}">
          @method('PUT')
          @include('atividade-acoes._form', ['submitLabel' => 'Salvar alterações'])
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

