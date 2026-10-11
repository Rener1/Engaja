@extends('layouts.app')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h4">Prévia — Atualização de dados cadastrais</h1>
    <p>{{ $rows->total() }} linha(s). Campos vazios preservam os valores existentes. Salve as alterações de cada página antes de confirmar o lote.</p>
    <p><strong>{{ $analysis['existingCount'] }} pessoa(s) existente(s)</strong> para conferir/atualizar e <strong>{{ $analysis['newCount'] }} nova(s) pessoa(s)</strong> para cadastrar. Contagem dos grupos válidos; a confirmação exige corrigir todos os erros.</p>
    <p class="text-muted">A localização indica a linha original do arquivo. Corrija os campos nesta tabela e clique em “Salvar alterações desta página” para verificar novamente.</p>
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">
            @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul></div>
    @endif
    <section id="pendencias-cadastrais" class="card border-{{ count($analysis['errors']) ? 'danger' : 'success' }} mb-3" style="scroll-margin-top: 100px;" aria-labelledby="titulo-pendencias">
        <div class="card-body">
            <h2 id="titulo-pendencias" class="h5">Pendências de todo o arquivo: {{ count($analysis['errors']) }} linha(s)</h2>
            @if($analysis['errors'])
                <p>Estas pendências incluem todas as páginas e permanecem aqui até serem corrigidas. Salvar uma página não confirma o lote.</p>
                <ul class="mb-0">
                    @foreach($analysis['errors'] as $index => $message)
                        @php
                            $pendingPage = intdiv(array_search($index, array_keys($analysis['rows']), true), $rows->perPage()) + 1;
                        @endphp
                        <li class="mb-2">{{ $message }}
                            <a href="{{ route('usuarios.atualizacao-cadastral.preview', ['session_key' => $sessionKey, 'page' => $pendingPage, 'per_page' => $rows->perPage()]) }}#editar-cadastro">Corrigir na página {{ $pendingPage }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mb-0">Nenhuma pendência encontrada. Confira os dados antes de confirmar o lote.</p>
            @endif
        </div>
    </section>
    <div class="d-flex gap-2 mb-3">
        <a class="btn btn-outline-secondary" href="{{ route('usuarios.atualizacao-cadastral.index') }}">Voltar</a>
        <form method="POST" action="{{ route('usuarios.atualizacao-cadastral.confirmar') }}" id="confirmar-cadastro">
            @csrf
            <input type="hidden" name="session_key" value="{{ $sessionKey }}">
            <button class="btn btn-primary" @disabled(count($analysis['errors']) > 0)>Confirmar atualização de todas as páginas</button>
        </form>
    </div>
    <form method="POST" id="editar-cadastro" action="{{ route('usuarios.atualizacao-cadastral.save', ['page' => $rows->currentPage(), 'per_page' => $rows->perPage()]) }}">
        @csrf
        <input type="hidden" name="session_key" value="{{ $sessionKey }}">
        @php
            $gridColumns = [['field' => 'origem', 'headerName' => 'Localização na planilha / cadastros', 'html' => true, 'minWidth' => 320]];
            $otherFields = collect(config('engaja.demograficos', []))->pluck('campo_outro')->filter()->all();
            foreach ($columns as $field => $label) {
                if (in_array($field, $otherFields, true)) continue;
                $gridColumns[] = ['field' => $field, 'headerName' => $label, 'html' => true, 'minWidth' => 210, 'pinned' => $field === 'nome' ? 'left' : null];
            }
            $gridRows = [];
            foreach ($rows as $index => $row) {
                $targets = $analysis['plans'][$index]['targets'] ?? collect();
                $identities = $targets->map(fn($p) => '#'.$p->id.' — '.$p->user->name.' ('.$p->user->email.')')->implode('; ');
                $description = $targets->isEmpty()
                    ? 'Novo cadastro — e-mail: '.(($analysis['plans'][$index]['newEmail'] ?? '') ?: 'será gerado um e-mail fictício')
                    : $targets->count().' cadastro(s): '.$identities;
                $item = ['id' => $index, 'origem' => '<div><strong>'.e('Linha '.$row['linha_original']).'</strong><div class="small text-wrap" style="max-height: 65px; overflow-y: auto;">'.e($analysis['errors'][$index] ?? $description).'</div></div>'];
                foreach ($columns as $field => $label) {
                    if (in_array($field, $otherFields, true)) continue;
                    $item[$field] = view('usuarios.atualizacao-cadastral._field', compact('field', 'label', 'index', 'row', 'selectOptions', 'municipios'))->render();
                }
                $gridRows[] = $item;
            }
        @endphp
        <x-data-table id="atualizacao-cadastral-grid" :columns="$gridColumns" :rows="$gridRows" :pagination="false" :row-height="100" dom-layout="normal" data-suppress-row-virtualisation="true" style="height: 60vh; min-height: 280px;" />
        <button class="btn btn-success my-3">Salvar alterações desta página</button>
    </form>
    {{ $rows->links() }}
</div>
<template id="opcoes-municipios-cadastro">
    @foreach($municipios as $municipio)
        <option value="{{ $municipio->nome }}" data-uf="{{ $municipio->estado?->sigla }}" data-id="{{ $municipio->id }}">{{ $municipio->nome_com_estado }}</option>
    @endforeach
</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let dirty = false;
    const edit = document.getElementById('editar-cadastro');
    const municipalityOptions = document.getElementById('opcoes-municipios-cadastro');
    const loadMunicipalities = event => {
        const select = event.target;
        if (!select.matches('[data-municipio]') || select.dataset.loaded) return;
        const selectedId = select.selectedOptions[0]?.dataset.id;
        const fragment = municipalityOptions.content.cloneNode(true);
        if (selectedId) {
            select.querySelector('option[data-id]')?.remove();
            const selected = fragment.querySelector(`option[data-id="${selectedId}"]`);
            if (selected) selected.selected = true;
        }
        select.append(fragment);
        select.dataset.loaded = 'true';
    };
    edit.addEventListener('pointerdown', loadMunicipalities);
    edit.addEventListener('focusin', loadMunicipalities);
    edit.addEventListener('focusout', event => {
        const select = event.target;
        if (!select.matches('[data-municipio]')) return;
        for (const option of Array.from(select.options)) {
            if (!option.selected && option.value !== '') option.remove();
        }
        delete select.dataset.loaded;
    });
    edit.addEventListener('input', event => {
        dirty = true;
        const input = event.target;
        if (!input.matches('[data-date-mask]')) return;
        const position = input.selectionStart ?? input.value.length;
        const digitsBefore = input.value.slice(0, position).replace(/\D/g, '').length;
        const digits = input.value.replace(/\D/g, '').slice(0, 8);
        const masked = digits.slice(0, 2)
            + (digits.length > 2 ? '/' + digits.slice(2, 4) : '')
            + (digits.length > 4 ? '/' + digits.slice(4) : '');
        if (input.value === masked) return;
        input.value = masked;
        let caret = 0;
        let seen = 0;
        while (caret < masked.length && seen < digitsBefore) {
            if (/\d/.test(masked[caret])) seen++;
            caret++;
        }
        input.setSelectionRange(caret, caret);
    });
    edit.addEventListener('change', event => {
        dirty = true;
        const select = event.target;
        if (select.matches('[data-municipio]')) {
            const uf = select.selectedOptions[0]?.dataset.uf;
            const state = edit.querySelector(`[name="rows[${select.dataset.municipio}][estado]"]`);
            if (state && uf !== undefined) state.value = uf;
        }
        if (select.matches('[data-outro-valor]')) {
            const other = select.parentElement.querySelector('[data-outro-input]');
            if (other) other.hidden = select.value !== select.dataset.outroValor;
        }
    });
    edit.addEventListener('submit', () => { dirty = false; });
    document.getElementById('confirmar-cadastro').addEventListener('submit', event => {
        if (dirty) { event.preventDefault(); alert('Salve as alterações desta página antes de confirmar.'); }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
});
</script>
@endsection
