@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Relatórios" eyebrow-icon="journal-text" title="Relatório do Momento">
    <small class="text-muted">Seu relatório individual para {{ $atividade->evento->nome ?? '' }} — {{ $atividade->descricao }}</small>
    <x-slot:actions>
        <a href="{{ route('eventos.show', $atividade->evento_id) }}"
           class="eg-hero-btn">Voltar à ação pedagógica</a>
    </x-slot:actions>
</x-ui.page-hero>

<div>
    @if($errors->any())
    <div class="alert alert-danger">
        <strong>Ops!</strong> Verifique os campos abaixo.
    </div>
    @endif

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('avaliacao-atividade.update', $atividade) }}">
                @method('PUT')
                @include('avaliacao-atividade._form', [
                    'submitLabel' => 'Salvar relatório',
                ])
            </form>
        </div>
    </div>
</div>
@endsection