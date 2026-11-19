@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-4 auth-center">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card eg-form-card eg-form">
            <div class="card-header">
                <h1 class="eg-form-card__title"><i class="bi bi-key"></i> Esqueci minha senha</h1>
                <p class="eg-form-card__subtitle">Informe seu e-mail e enviaremos um link para você redefinir sua senha.</p>
            </div>

            <div class="card-body">
                {{-- status de sucesso (link enviado) --}}
                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="needs-validation" novalidate>
                    @csrf

                    {{-- E-mail --}}
                    <div class="mb-4">
                        <label for="email" class="form-label">E-mail</label>
                        <input id="email" type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               name="email" value="{{ old('email') }}" placeholder="seu@email.com"
                               autocomplete="email" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Verifique sua caixa de entrada e o spam.</div>
                    </div>

                    <button type="submit" class="btn btn-engaja w-100">
                        <i class="bi bi-send me-1"></i> Enviar link
                    </button>
                </form>
            </div>

            <div class="eg-form-card__footer">
                Lembrou a senha?
                <a href="{{ route('login') }}" class="eg-form-link">Voltar ao login</a>
            </div>
        </div>
    </div>
</div>
@endsection
