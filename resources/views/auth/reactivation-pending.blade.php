@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow-sm">
                <div class="card-header text-center bg-primary">
                    <h5 class="mb-0 fw-bold text-white">Confirme a reativação da sua conta</h5>
                </div>

                <div class="card-body">
                    <p class="text-muted">
                        Encontramos uma conta desativada com este e-mail. Enviamos um link de confirmação —
                        abra a mensagem e clique no link para reativar sua conta com os novos dados informados
                        no cadastro.
                    </p>
                </div>

                <div class="card-footer text-center">
                    <a href="{{ route('register') }}" class="btn btn-link">Voltar ao cadastro</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
