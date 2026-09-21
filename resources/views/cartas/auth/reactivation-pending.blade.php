@extends('cartas.auth._shell')

@section('title', 'Confirme a reativação - Cartas para Esperançar')
@section('auth-bg-style', 'background-color: #c893df; background-image: none; position: relative; overflow: hidden;')

@section('auth-side-content')
    {{-- Ilustração 1: menina na janela --}}
    <img
        src="{{ asset('images/cartas/ilustracao1-verificar-email.png') }}"
        alt=""
        style="
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -58%);
            width: 65%;
            height: auto;
            object-fit: contain;
            z-index: 1;
            pointer-events: none;
        "
    >
    {{-- Ilustração 2: envelope com notas --}}
    <img
        src="{{ asset('images/cartas/ilustracao2-verificar-email.png') }}"
        alt=""
        style="
            position: absolute;
            bottom: 0;
            right: 0;
            width: 20%;
            height: auto;
            object-fit: contain;
            z-index: 2;
            pointer-events: none;
        "
    >
@endsection

@section('auth-content')
    <h1 class="cartas-title cartas-title--strong">Confirme a reativação da sua conta</h1>
    <p class="cartas-copy">Encontramos uma conta desativada com este e-mail. Enviamos uma mensagem de confirmação para ela — abra a mensagem e clique no link para reativar sua conta com os novos dados informados no cadastro.</p>

    <a href="{{ route('cartas.register') }}" class="cartas-link">Voltar ao cadastro</a>
@endsection
