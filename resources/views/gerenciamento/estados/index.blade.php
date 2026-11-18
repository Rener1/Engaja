@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Administração" eyebrow-icon="geo-alt" title="Estados" class="mb-0">
    <p class="text-muted mb-0">Gerencie os estados cadastrados.</p>
    <x-slot:actions>
        <button class="eg-hero-btn eg-hero-btn--solid" data-bs-toggle="modal" data-bs-target="#modalCreateEstado">Novo estado</button>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
  @php
      $columns = [
          ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
          ['field' => 'regiao', 'headerName' => 'Região', 'flex' => 1],
          ['field' => 'sigla', 'headerName' => 'Sigla', 'flex' => 1],
          ['field' => 'acoes', 'headerName' => 'Ações', 'flex' => 1, 'html' => true],
      ];

      $rows = $estados->map(fn ($estado) => [
          'nome' => $estado->nome,
          'regiao' => $estado->regiao->nome ?? '-',
          'sigla' => $estado->sigla,
          'acoes' => '<button class="btn btn-outline-secondary btn-sm btn-edit-estado"'
              . ' data-id="' . $estado->id . '"'
              . ' data-nome="' . e($estado->nome) . '"'
              . ' data-sigla="' . e($estado->sigla) . '"'
              . ' data-regiao="' . $estado->regiao_id . '"'
              . ' data-action="' . route('estados.update', $estado) . '"'
              . ' data-delete="' . route('estados.destroy', $estado) . '"'
              . ' data-bs-toggle="modal" data-bs-target="#modalEditEstado">Editar</button>',
      ])->values();
  @endphp

  <div class="card shadow-sm">
      <x-data-table
          id="grid-estados"
          :columns="$columns"
          :rows="$rows"
          :page-size="15"
      />
  </div>
</div>

<!-- Modal Criar Estado -->
<div class="modal fade" id="modalCreateEstado" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('estados.store') }}">
        @csrf
        <x-ui.modal-header title="Novo estado" />
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Região</label>
            <select name="regiao_id" class="form-select" required>
              <option value="">Selecione</option>
              @foreach($regioes as $r)
                <option value="{{ $r->id }}">{{ $r->nome }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Sigla</label>
            <input type="text" name="sigla" class="form-control" maxlength="5" required>
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

<!-- Modal Editar Estado -->
<div class="modal fade" id="modalEditEstado" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="formEditEstado">
        @csrf
        @method('PUT')
        <x-ui.modal-header title="Editar estado" />
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Região</label>
            <select name="regiao_id" id="editEstadoRegiao" class="form-select" required>
              @foreach($regioes as $r)
                <option value="{{ $r->id }}">{{ $r->nome }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" id="editEstadoNome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Sigla</label>
            <input type="text" name="sigla" id="editEstadoSigla" class="form-control" maxlength="5" required>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-outline-danger" id="btnDeleteEstado">Excluir</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-engaja">Salvar</button>
          </div>
        </div>
      </form>
      <form method="POST" id="formDeleteEstado" class="d-none">
        @csrf @method('DELETE')
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const formEdit = document.getElementById('formEditEstado');
  const formDelete = document.getElementById('formDeleteEstado');
  const inputNome = document.getElementById('editEstadoNome');
  const inputSigla = document.getElementById('editEstadoSigla');
  const selectRegiao = document.getElementById('editEstadoRegiao');

  // Delegação de evento: os botões .btn-edit-estado são renderizados pelo
  // AG Grid de forma assíncrona (depois do DOMContentLoaded), então um
  // querySelectorAll+forEach direto não os encontraria.
  document.addEventListener('click', (event) => {
    const btn = event.target.closest('.btn-edit-estado');
    if (!btn) return;

    formEdit.setAttribute('action', btn.dataset.action);
    formDelete.setAttribute('action', btn.dataset.delete);
    inputNome.value = btn.dataset.nome || '';
    inputSigla.value = btn.dataset.sigla || '';
    if (selectRegiao) selectRegiao.value = btn.dataset.regiao || '';
  });

  const btnDelete = document.getElementById('btnDeleteEstado');
  if (btnDelete && formDelete) {
    btnDelete.addEventListener('click', () => {
      if (confirm('Confirma remover este estado?')) {
        formDelete.submit();
      }
    });
  }
});
</script>
@endpush
@endsection
