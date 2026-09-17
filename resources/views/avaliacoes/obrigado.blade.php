@extends('layouts.app')

@section('content')
@php
  $titulo = $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação');
@endphp

<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-4">
    <div class="card shadow-sm border-0 text-center my-5">
      <div class="card-body p-5">
        <img src="{{ asset('images/engaja-bg.png') }}" alt="Engaja" class="mb-4" style="max-width: 150px;">

        <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center"
          style="width: 72px; height: 72px; background: rgba(44, 181, 124, .14); color: #2cb57c;">
          <span class="fw-bold" style="font-size: 2rem;">✓</span>
        </div>

        <h1 class="h3 fw-bold text-engaja mb-3">Suas respostas foram registradas com sucesso</h1>
        <p class="text-muted mb-4">
          As informações compartilhadas serão tratadas em conformidade com a Lei Geral de Proteção de Dados - LGPD.
        </p>

        <a href="{{ $homeUrl ?? url('/') }}" class="btn btn-outline-secondary">
          Ir para a página inicial
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
