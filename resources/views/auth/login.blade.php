@extends('layouts.app')

@section('content')
<div class="row justify-content-center py-4 auth-center">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card eg-form-card eg-form">
            <div class="card-header">
                <h1 class="eg-form-card__title"><i class="bi bi-box-arrow-in-right"></i> {{ __('Entrar no Engaja') }}</h1>
                <p class="eg-form-card__subtitle">Acesse com seu e-mail ou CPF.</p>
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="alert alert-success mb-3">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    {{-- E-mail ou CPF --}}
                    <div class="mb-3">
                        <label for="login" class="form-label">{{ __('E-mail') }}</label>
                        <input id="login" type="text"
                            class="form-control @error('login') is-invalid @enderror"
                            name="login" value="{{ old('login') }}" placeholder="seu@email.com ou 000.000.000-00"
                            autocomplete="username" required autofocus>
                        @error('login')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                <script>
                  document.addEventListener('DOMContentLoaded', function () {
                    const loginInput = document.getElementById('login');
                    if (!loginInput) return;

                    const onlyDigits = (str) => (str || '').replace(/\D+/g, '').slice(0, 11);
                    const maskCPF = (digits) => {
                      const d = onlyDigits(digits);
                      const p1 = d.slice(0, 3);
                      const p2 = d.slice(3, 6);
                      const p3 = d.slice(6, 9);
                      const p4 = d.slice(9, 11);
                      let out = p1;
                      if (p2) out += '.' + p2;
                      if (p3) out += '.' + p3;
                      if (p4) out += '-' + p4;
                      return out;
                    };

                    const hasLettersOrAt = (value) => /[A-Za-z@]/.test(value);

                    const maybeMask = (value) => {
                      // se tem letras ou @, assume e-mail e não mexe
                      if (hasLettersOrAt(value)) return value;
                      const digits = onlyDigits(value);
                      if (digits.length === 0) return value;
                      return maskCPF(digits);
                    };

                    const applyMask = (e) => {
                      const originalPos = e.target.selectionStart;
                      const masked = maybeMask(e.target.value);
                      e.target.value = masked;
                      // move cursor para o fim para evitar quebra da digitação
                      e.target.setSelectionRange(masked.length, masked.length);
                    };

                    loginInput.addEventListener('input', applyMask);
                    loginInput.addEventListener('blur', applyMask);
                  });
                </script>

                    {{-- Password --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <label for="password" class="form-label">{{ __('Senha') }}</label>
                            @if (Route::has('password.request'))
                                <a class="eg-form-link" href="{{ route('password.request') }}">{{ __('Esqueceu a senha?') }}</a>
                            @endif
                        </div>
                        <input id="password" type="password"
                            class="form-control @error('password') is-invalid @enderror"
                            name="password" autocomplete="current-password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Remember --}}
                    <div class="mb-4 form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">
                            {{ __('Lembrar-me') }}
                        </label>
                    </div>

                    <button type="submit" class="btn btn-engaja w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('Entrar') }}
                    </button>
                </form>
            </div>
            @if (Route::has('register'))
                <div class="eg-form-card__footer">
                    {{ __('Ainda não tem conta?') }}
                    <a class="eg-form-link" href="{{ route('register') }}">{{ __('Cadastre-se') }}</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
