@extends('layouts.app')

@section('content')
    <x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="people" title="Importar Autorizações de Imagem">
        <p class="text-muted">Faça o upload de uma planilha para atualizar em lote os usuários que permitiram o uso de imagem.</p>
    </x-ui.page-hero>

    <div class="max-w-4xl mx-auto">
        <div class="mb-4">
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Instruções:</h5>
                <ul class="text-muted small">
                    <li>O arquivo deve ser no formato <strong>.xlsx</strong>.</li>
                    <li>A primeira linha deve conter os cabeçalhos exatos: <strong>nome</strong> e <strong>cpf</strong>.</li>
                    <li>O sistema ignorará formatações como pontos e traços no CPF.</li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('usuarios.autorizacoes.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="arquivo" class="form-label fw-semibold">Selecione a Planilha</label>
                        <input class="form-control" type="file" id="arquivo" name="arquivo" accept=".xlsx, .xls, .csv" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('usuarios.index') }}" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-engaja">Continuar e Pré-visualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
