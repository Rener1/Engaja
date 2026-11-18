@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Administração" eyebrow-icon="people" title="Cadastrar Usuário">
    <div class="text-muted small">Crie um novo usuário pela área de gerenciamento.</div>
    <x-slot:actions>
        <a href="{{ route('usuarios.index') }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

@include('usuarios._form', [
  'formAction' => route('usuarios.store'),
  'submitLabel' => 'Cadastrar usuário',
  'isEdit' => false,
])
@endsection
