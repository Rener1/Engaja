@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Cadastrar participante no agendamento" />

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('agendamentos.participantes.store', $agendamento) }}">
          @include('agendamentos.participantes._form', ['submitLabel' => 'Salvar participante'])
        </form>
      </div>
    </div>
  </div>
</div>
@endsection