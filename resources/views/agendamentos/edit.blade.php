@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-check" title="Editar agendamento" />

<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('agendamentos.update', $agendamento) }}">
          @method('PUT')
          @include('agendamentos._form', ['submitLabel' => 'Salvar alterações'])
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

