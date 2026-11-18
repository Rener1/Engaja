@extends('layouts.app')
@section('content')
<x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="people" title="Atualização de dados cadastrais"
    subtitle="Envie a planilha, confira os cadastros encontrados e confirme. Pessoas sem CPF nem e-mail encontrados no Engaja serão cadastradas. Não são criadas inscrições."
    class="mb-0">
    <x-slot:actions>
        <a class="eg-hero-btn" href="{{ route('usuarios.atualizacao-cadastral.modelo') }}">
            <i class="bi bi-file-earmark-arrow-down me-1"></i> Baixar modelo da planilha
        </a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <form method="POST" action="{{ route('usuarios.atualizacao-cadastral.upload') }}" enctype="multipart/form-data" class="eg-section-card h-100">
                @csrf
                <h2 class="eg-section-card__title"><i class="bi bi-cloud-arrow-up"></i> Enviar planilha</h2>
                <label class="form-label" for="your_file">Planilha de dados cadastrais</label>
                <input class="form-control" id="your_file" name="your_file" type="file" accept=".xlsx,.xls,.csv" required>
                <div class="form-text">XLSX, XLS ou CSV, até 20 MB e 5.000 registros. Mantenha CPF e telefone como texto para preservar zeros iniciais.</div>
                <div class="mt-4"><button class="btn btn-engaja"><i class="bi bi-eye me-1"></i> Enviar e visualizar prévia</button></div>

                <h3 class="eg-section-card__title mt-4 mb-2"><i class="bi bi-layout-three-columns"></i> Colunas da planilha</h3>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(array_keys($columns) as $coluna)
                        <code>{{ $coluna }}</code>
                    @endforeach
                </div>
            </form>
        </div>

        <div class="col-lg-6">
            <section class="eg-section-card h-100 d-flex flex-column">
                <h2 class="eg-section-card__title"><i class="bi bi-info-circle"></i> Como funciona</h2>
                <ul class="eg-checklist eg-checklist--fill">
                    <li><i class="bi bi-person-plus"></i><span>Para novos cadastros, informe nome e pelo menos CPF ou e-mail. Quando houver apenas CPF, será gerado um e-mail fictício, seguindo o padrão das importações. Linhas da mesma pessoa geram um único cadastro; havendo mais de um e-mail novo para o mesmo CPF, será usado o primeiro informado na planilha.</span></li>
                    <li><i class="bi bi-arrow-repeat"></i><span>Informe CPF ou e-mail para identificar cada pessoa. Valores preenchidos substituem os atuais; campos vazios preservam os dados existentes. Os e-mails das contas são preservados. Um CPF informado pode preencher um cadastro sem CPF localizado por e-mail; CPFs conflitantes exigem revisão.</span></li>
                    <li><i class="bi bi-people"></i><span>Cadastros ativos do Engaja com o mesmo CPF válido e nomes equivalentes serão atualizados juntos, preservando seus e-mails. Grupos com nomes divergentes precisam de revisão.</span></li>
                </ul>
            </section>
        </div>
    </div>
</div>
@endsection
