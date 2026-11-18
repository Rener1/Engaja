@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Certificados" eyebrow-icon="award" title="Pré-visualização da Certificação">
    <p class="text-muted">Confira abaixo todos os usuários que receberão o certificado antes de confirmar a emissão.</p>
</x-ui.page-hero>

<div>
    <div class="mb-4">
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h6 class="fw-bold mb-1">Modelo de Certificado Selecionado:</h6>
            <p class="text-muted mb-0">{{ $modelo->nome }}</p>
        </div>
    </div>

    <div class="d-flex align-items-center mb-3">
        <div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-select-all">Selecionar Todos</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-clear-all">Limpar Todos</button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="table-layout: fixed; min-width: 1000px;">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 5%; text-align: center;">
                            <input type="checkbox" id="checkbox-all-page" class="form-check-input" checked>
                        </th>
                        <th style="width: 22%;">Nome</th>
                        <th style="width: 20%;">E-mail</th>
                        <th style="width: 13%;">CPF</th>
                        <th style="width: 28%;">Ação Pedagógica</th>
                        <th style="width: 12%; text-align: center;">Carga Horária</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($paginator as $row)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input cert-checkbox" value="{{ $row['id'] }}">
                            </td>
                            <td class="fw-medium text-wrap text-break">{{ $row['nome'] }}</td>
                            <td class="text-wrap text-break">{{ $row['email'] }}</td>
                            <td class="text-nowrap">{{ $row['cpf'] }}</td>
                            <td>
                                <div class="text-wrap pe-1" style="max-height: 75px; overflow-y: auto; font-size: 0.9em; line-height: 1.4;">
                                    {{ $row['evento_nome'] }}
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary border">{{ $row['carga_horaria'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6"><x-ui.empty-state compact muted icon="inbox" title="Nenhum participante apto para certificação nestas ações." /></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white p-3">
            <div class="row align-items-center">

                <div class="col-12 col-xl-4 text-center text-xl-start mb-3 mb-xl-0">
                    <div class="text-muted small">
                        <strong>{{ $totalProntos }}</strong> certificados serão emitidos.
                        @if($skippedZeroWorkload > 0)
                        <br><span class="text-warning-emphasis">⚠️ {{ $skippedZeroWorkload }} ignorados (Carga horária zero).</span>
                        @endif
                    </div>
                </div>

                {{-- paginação --}}
                <div class="col-12 col-xl-4 d-flex justify-content-center mb-3 mb-xl-0">
                    <x-ui.pagination :paginator="$paginator" stacked class="mt-0" />
                </div>

                <div class="col-12 col-xl-4">
                    <form id="form-emissao" action="{{ route('certificados.emitir') }}" method="POST" class="m-0 d-flex justify-content-center justify-content-xl-end gap-2">
                        @csrf
                        <input type="hidden" name="session_key" value="{{ $sessionKey }}">

                        <input type="hidden" name="selection_mode" id="selection_mode" value="ALL">
                        <input type="hidden" name="selection_exceptions" id="selection_exceptions" value="[]">

                        <a href="{{ route('eventos.index') }}" class="btn btn-light border">Voltar</a>

                        <button type="submit" class="btn btn-engaja" {{ $totalProntos === 0 ? 'disabled' : '' }}>
                        Confirmar Emissão
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const STORAGE_KEY = 'cert_selection_' + '{{ $sessionKey }}';

        const isPaginated = new URLSearchParams(window.location.search).has('page');
        if (!isPaginated) {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ mode: 'ALL', exceptions: [] }));
        }

        let state = JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || { mode: 'ALL', exceptions: [] };
        const checkboxes = document.querySelectorAll('.cert-checkbox');
        const pageAllCheckbox = document.getElementById('checkbox-all-page');

        const updateCheckboxesUI = () => {
            let allPageChecked = true;
            checkboxes.forEach(cb => {
                const id = cb.value;
                let shouldBeChecked = state.mode === 'ALL';

                //inverte se for exceção
                if (state.exceptions.includes(id)) {
                    shouldBeChecked = !shouldBeChecked;
                }

                cb.checked = shouldBeChecked;
                if (!shouldBeChecked) allPageChecked = false;
            });

            if (pageAllCheckbox) {
                pageAllCheckbox.checked = allPageChecked && checkboxes.length > 0;
            }
        };

        const saveState = () => sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));

        checkboxes.forEach(cb => {
            cb.addEventListener('change', (e) => {
                const id = e.target.value;
                const isChecked = e.target.checked;

                const isException = (state.mode === 'ALL' && !isChecked) || (state.mode === 'NONE' && isChecked);

                if (isException) {
                    if (!state.exceptions.includes(id)) state.exceptions.push(id);
                } else {
                    state.exceptions = state.exceptions.filter(exId => exId !== id);
                }

                saveState();

                //atualiza o master checkbox da página caso todos ou nenhum tenham sido desmarcados manualmente
                let allPageChecked = true;
                checkboxes.forEach(c => { if(!c.checked) allPageChecked = false; });
                if (pageAllCheckbox) pageAllCheckbox.checked = allPageChecked;
            });
        });

        pageAllCheckbox?.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            checkboxes.forEach(cb => {
                if (cb.checked !== isChecked) {
                    cb.checked = isChecked;
                    //dispara o evento change manualmente para atualizar o estado
                    cb.dispatchEvent(new Event('change'));
                }
            });
        });

        document.getElementById('btn-select-all')?.addEventListener('click', () => {
            state = { mode: 'ALL', exceptions: [] };
            saveState();
            updateCheckboxesUI();
        });

        document.getElementById('btn-clear-all')?.addEventListener('click', () => {
            state = { mode: 'NONE', exceptions: [] };
            saveState();
            updateCheckboxesUI();
        });

        //intercepta o submit do form
        const form = document.getElementById('form-emissao');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (state.mode === 'NONE' && state.exceptions.length === 0) {
                    e.preventDefault();
                    alert('Atenção: Nenhum certificado está selecionado para emissão.');
                    return;
                }
                document.getElementById('selection_mode').value = state.mode;
                document.getElementById('selection_exceptions').value = JSON.stringify(state.exceptions);
            });
        }

        updateCheckboxesUI();
    });
</script>
@endsection
