@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="diagram-2" title="Nova atividade/ação" />

<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('atividade-acoes.store') }}">
          @include('atividade-acoes._form', ['submitLabel' => 'Criar atividade/ação'])
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

