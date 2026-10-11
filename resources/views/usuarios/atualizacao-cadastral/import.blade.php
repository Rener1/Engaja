@extends('layouts.app')
@section('content')
<div class="container py-4">
    <h1 class="h4 mb-3">Atualização de dados cadastrais</h1>
    <p>Envie a planilha, confira os cadastros encontrados e confirme. Pessoas sem CPF nem e-mail encontrados no Engaja serão cadastradas. Não são criadas inscrições.</p>
    <p>Para novos cadastros, informe nome e pelo menos CPF ou e-mail. Quando houver apenas CPF, será gerado um e-mail fictício, seguindo o padrão das importações. Linhas da mesma pessoa geram um único cadastro; havendo mais de um e-mail novo para o mesmo CPF, será usado o primeiro informado na planilha.</p>
    <div class="alert alert-info">Informe CPF ou e-mail para identificar cada pessoa. Valores preenchidos substituem os atuais; campos vazios preservam os dados existentes. Os e-mails das contas são preservados. Um CPF informado pode preencher um cadastro sem CPF localizado por e-mail; CPFs conflitantes exigem revisão.</div>
    <p>Cadastros ativos do Engaja com o mesmo CPF válido e nomes equivalentes serão atualizados juntos, preservando seus e-mails. Grupos com nomes divergentes precisam de revisão.</p>
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    <a class="btn btn-outline-primary mb-3" href="{{ route('usuarios.atualizacao-cadastral.modelo') }}">Baixar modelo da planilha</a>
    <form method="POST" action="{{ route('usuarios.atualizacao-cadastral.upload') }}" enctype="multipart/form-data" class="card card-body">
        @csrf
        <label class="form-label" for="your_file">Planilha de dados cadastrais</label>
        <input class="form-control" id="your_file" name="your_file" type="file" accept=".xlsx,.xls,.csv" required>
        <div class="form-text">XLSX, XLS ou CSV, até 20 MB e 5.000 registros. Mantenha CPF e telefone como texto para preservar zeros iniciais.</div>
        <div class="mt-3"><button class="btn btn-primary">Enviar e visualizar prévia</button></div>
    </form>
    <h2 class="h5 mt-4">Colunas da planilha</h2>
    <p>{{ implode(', ', array_keys($columns)) }}</p>
</div>
@endsection
