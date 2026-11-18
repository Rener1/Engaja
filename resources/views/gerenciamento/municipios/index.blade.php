@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Administração" eyebrow-icon="geo-alt" title="Municípios" class="mb-0">
    <p class="text-muted mb-0">Gerencie os municípios cadastrados.</p>
    <x-slot:actions>
        <button class="eg-hero-btn eg-hero-btn--solid" data-bs-toggle="modal" data-bs-target="#modalCreateMunicipio">Novo município</button>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4">
  @php
      $columns = [
          ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
          ['field' => 'estado', 'headerName' => 'Estado', 'flex' => 1],
          ['field' => 'regiao', 'headerName' => 'Região', 'flex' => 1],
          ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
      ];

      $rows = $municipios->map(fn ($municipio) => [
          'nome' => $municipio->nome,
          'estado' => trim(($municipio->estado?->nome ?? '') . ' (' . ($municipio->estado?->sigla ?? '') . ')'),
          'regiao' => $municipio->estado?->regiao?->nome ?? '-',
          'acoes' => '<button class="btn btn-outline-secondary btn-sm btn-edit-municipio"'
              . ' data-id="' . $municipio->id . '"'
              . ' data-nome="' . e($municipio->nome) . '"'
              . ' data-estado="' . $municipio->estado_id . '"'
              . ' data-action="' . route('municipios.update', $municipio) . '"'
              . ' data-delete="' . route('municipios.destroy', $municipio) . '"'
              . ' data-bs-toggle="modal" data-bs-target="#modalEditMunicipio">Editar</button>',
      ])->values();
  @endphp

  <div class="card shadow-sm">
      <x-data-table
          id="grid-municipios"
          :columns="$columns"
          :rows="$rows"
          :page-size="15"
      />
  </div>
</div>

<!-- Modal Criar Município -->
<div class="modal fade" id="modalCreateMunicipio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('municipios.store') }}">
        @csrf
        <x-ui.modal-header title="Novo município" />
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado_id" class="form-select" required>
              <option value="">Selecione</option>
              @foreach($estados as $e)
                <option value="{{ $e->id }}">{{ $e->nome }} ({{ $e->sigla }}) - {{ $e->regiao->nome ?? '' }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-engaja">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar Município -->
<div class="modal fade" id="modalEditMunicipio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="formEditMunicipio">
        @csrf
        @method('PUT')
        <x-ui.modal-header title="Editar município" />
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado_id" id="editMunicipioEstado" class="form-select" required>
              @foreach($estados as $e)
                <option value="{{ $e->id }}">{{ $e->nome }} ({{ $e->sigla }}) - {{ $e->regiao->nome ?? '' }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" id="editMunicipioNome" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-danger" id="btnDeleteMunicipio">Excluir</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-engaja">Salvar</button>
          </div>
        </div>
      </form>
      <form method="POST" id="formDeleteMunicipio" class="d-none">
        @csrf @method('DELETE')
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const formEdit = document.getElementById('formEditMunicipio');
  const formDelete = document.getElementById('formDeleteMunicipio');
  const inputNome = document.getElementById('editMunicipioNome');
  const selectEstado = document.getElementById('editMunicipioEstado');

  // Delegação de evento: os botões .btn-edit-municipio são renderizados pelo
  // AG Grid de forma assíncrona (depois do DOMContentLoaded).
  document.addEventListener('click', (event) => {
    const btn = event.target.closest('.btn-edit-municipio');
    if (!btn) return;

    formEdit.setAttribute('action', btn.dataset.action);
    formDelete.setAttribute('action', btn.dataset.delete);
    inputNome.value = btn.dataset.nome || '';
    if (selectEstado) selectEstado.value = btn.dataset.estado || '';
  });

  const btnDelete = document.getElementById('btnDeleteMunicipio');
  if (btnDelete && formDelete) {
    btnDelete.addEventListener('click', () => {
      if (confirm('Confirma remover este município?')) {
        formDelete.submit();
      }
    });
  }
});
</script>
@endpush
@endsection
