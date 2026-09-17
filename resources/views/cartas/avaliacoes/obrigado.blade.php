@extends('cartas.layouts.app')

@section('title', 'Obrigado - Cartas para Esperançar')

@section('body')
  @include('cartas.shared._styles')

  <main class="cpe-page">
    <div class="cpe-logo-top">
      <a href="{{ $homeUrl ?? route('cartas.apresentacao') }}" aria-label="Voltar ao início do Cartas">
        <img src="{{ asset('images/cartas/cartas-logo.png') }}" alt="Cartas para Esperançar" class="cpe-logo">
      </a>
    </div>

    @php
      $titulo = $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação');
    @endphp

    <section style="max-width: 560px; margin: 0 auto; padding: 0 24px 48px;">
      <div style="background: #fff; border-radius: 12px; box-shadow: 0 2px 14px rgba(0,0,0,.06); text-align: center; padding: 48px 32px;">

        {{-- Ícone de sucesso --}}
        <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 111, 49, .12); color: #106f31;
                    display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
          <span style="font-size: 2rem; font-weight: bold;">✓</span>
        </div>

        <h1 class="cpe-title" style="font-size: 24px; margin-bottom: 14px;">
          Suas respostas foram registradas com sucesso
        </h1>

        <a href="{{ $homeUrl ?? url('/') }}" class="cpe-button cpe-button--ghost" style="width: auto; padding: 0 28px;">
          Ir para a página inicial
        </a>
      </div>
    </section>
  </main>
@endsection
