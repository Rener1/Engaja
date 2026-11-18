@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="files" title="Editar modelo de avaliação" />

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        @include('templates-avaliacao.partials.form', [
          'action' => route('templates-avaliacao.update', $template),
          'method' => 'PUT',
          'submitLabel' => 'Salvar alterações',
          'template' => $template,
          'evidencias' => $evidencias,
          'escalas' => $escalas,
          'tiposQuestao' => $tiposQuestao,
        ])
      </div>
    </div>
  </div>
</div>
@endsection
