@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Certificados" eyebrow-icon="award" title="Novo modelo">
    <div class="text-muted small">Defina textos e imagens base para certificados.</div>
    <x-slot:actions>
        <a href="{{ route('certificados.modelos.index') }}" class="eg-hero-btn">&lt; Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<form action="{{ route('certificados.modelos.store') }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
  @csrf
  @include('certificados.modelos.form', ['modelo' => null])
</form>
@endsection
