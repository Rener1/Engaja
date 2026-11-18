@extends('layouts.app')

@section('content')
    <x-ui.page-hero contained
        eyebrow="Dashboards" eyebrow-icon="bar-chart-line"
        title="BI Educacional"
        subtitle="Painel de indicadores educacionais para análise territorial e acompanhamento anual."
        class="mb-0">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="eg-hero-btn"><i class="bi bi-grid me-1"></i> Hub de dashboards</a>
            <a href="{{ route('dashboards.avaliacoes') }}" class="eg-hero-btn"><i class="bi bi-chat-square-text me-1"></i> Dashboard de respostas</a>
            <a href="{{ route('dashboards.presencas') }}" class="eg-hero-btn"><i class="bi bi-people me-1"></i> Dashboard de presenças</a>
        </x-slot:actions>
    </x-ui.page-hero>

    <livewire:dashboards.bi-dashboard />
@endsection
