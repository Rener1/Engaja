@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações" eyebrow-icon="person-lines-fill" title="Importação Moodle (2 planilhas)" class="mb-0">
    <div class="text-muted small">Ação pedagógica: <strong>{{ $evento->nome }}</strong></div>
    <x-slot:actions>
        <a href="{{ route('eventos.show', $evento) }}" class="eg-hero-btn">Voltar à ação</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
  @if ($errors->any())
  <div class="alert alert-danger">
    <strong>Ops!</strong> Revise as planilhas enviadas.
    <ul class="mb-0 mt-2">
      @foreach (collect($errors->all())->unique()->values() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <div class="alert alert-warning mb-3">
        <div class="fw-semibold mb-1">Fluxo Moodle específico</div>
        <ul class="mb-0">
          <li>Planilha 1: participantes por momento com nome, email e colunas de conclusão.</li>
          <li>Planilha 2: momentos e carga horária.</li>
          <li>Pré-visualização valida nome/email por linha e mostra inconsistências.</li>
          <li>Linhas em branco são ignoradas.</li>
        </ul>
      </div>

      <form method="POST" action="{{ route('inscricoes.moodle.upload', $evento) }}" enctype="multipart/form-data" class="row g-3">
        @csrf

        <div class="col-md-6">
          <label class="form-label">Planilha de participantes (Moodle) <span class="text-danger">*</span></label>
          <input
            type="file"
            name="participants_file"
            class="form-control @error('participants_file') is-invalid @enderror"
            accept=".xlsx,.xls,.csv"
            required>
          @error('participants_file')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text">Inclua colunas de nome, email e momentos.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Planilha de carga horária dos momentos <span class="text-danger">*</span></label>
          <input
            type="file"
            name="workloads_file"
            class="form-control @error('workloads_file') is-invalid @enderror"
            accept=".xlsx,.xls,.csv"
            required>
          @error('workloads_file')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text">Esta é a planilha de importação de momentos, com os campos de <strong>horas</strong> e <strong>minutos</strong>. Se horas ou minutos vierem vazios, o sistema considera 0 no campo correspondente.</div>
          <div class="mt-2">
            <a
              href="{{ route('inscricoes.moodle.template.momentos', $evento) }}"
              class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              Baixar modelo de momentos (.xlsx)
            </a>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="{{ route('eventos.show', $evento) }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-engaja">Validar e pré-visualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
