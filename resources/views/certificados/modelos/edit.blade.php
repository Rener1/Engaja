@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Certificados" eyebrow-icon="award" title="Editar modelo">
    <div class="text-muted small">Atualize textos e imagens do modelo.</div>
    <x-slot:actions>
        <a href="{{ route('certificados.modelos.index') }}" class="eg-hero-btn">&lt; Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<form action="{{ route('certificados.modelos.update', $modelo) }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
  @csrf
  @method('PUT')
  @include('certificados.modelos.form', ['modelo' => $modelo])
</form>
@endsection
