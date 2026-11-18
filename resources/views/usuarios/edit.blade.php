@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Administração" eyebrow-icon="people" title="Editar Usuário">
    <div class="text-muted small">Atualize dados cadastrais e do participante.</div>
    <x-slot:actions>
        <a href="{{ route('usuarios.index') }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

@include('usuarios._form', [
  'formAction' => route('usuarios.update', $user),
  'formMethod' => 'put',
  'submitLabel' => 'Salvar usuário',
  'isEdit' => true,
])
@endsection
