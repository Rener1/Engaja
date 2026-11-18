@extends('layouts.app')

@section('content')
    <x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="file-earmark-check" title="Editar evidência" />

    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('evidencias.update', $evidencia) }}">
                        @csrf
                        @method('PUT')

                        {{-- Selecionar DIMENSÃO (apenas para filtrar) --}}
                        <div class="mb-3">
                            <label for="dimensao_id" class="form-label">Dimensão (Filtro)</label>
                            <select id="dimensao_id" class="form-select">
                                <option value="">Selecione uma dimensão para filtrar...</option>
                                @foreach ($dimensoes as $id => $descricao)
                                    <option value="{{ $id }}" @selected(old('dimensao_id', $dimensaoAtualId) == $id)>{{ $descricao }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Selecionar INDICADOR --}}
                        <div class="mb-3">
                            <label for="indicador_id" class="form-label">Indicador <span class="text-danger">*</span></label>
                            <select id="indicador_id" name="indicador_id" class="form-select @error('indicador_id') is-invalid @enderror" required>
                                <option value="">Selecione primeiro uma dimensão...</option>
                            </select>
                            @error('indicador_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <input type="text" id="descricao" name="descricao"
                                   class="form-control @error('descricao') is-invalid @enderror"
                                   value="{{ old('descricao', $evidencia->descricao) }}" required>
                            @error('descricao')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('evidencias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-engaja">Salvar alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dimensaoSelect = document.getElementById('dimensao_id');
            const indicadorSelect = document.getElementById('indicador_id');

            const todosIndicadores = Object.values(@json($indicadores ?? []));
            const indicadorSalvo = "{{ old('indicador_id', $evidencia->indicador_id ?? '') }}";

            function atualizarCascata() {
                const dimensaoSelecionada = dimensaoSelect.value;

                //limpa as opções de indicador
                indicadorSelect.innerHTML = '<option value="">Selecione um Indicador...</option>';

                if (!dimensaoSelecionada) {
                    indicadorSelect.innerHTML = '<option value="">Selecione primeiro uma dimensão...</option>';
                    return;
                }

                //filtra os indicadores correspondentes
                const filtrados = todosIndicadores.filter(ind => ind.dimensao_id == dimensaoSelecionada);

                if (filtrados.length === 0) {
                    indicadorSelect.innerHTML = '<option value="">Nenhum indicador cadastrado nesta dimensão.</option>';
                    return;
                }

                //popula os indicadores
                filtrados.forEach(ind => {
                    const option = document.createElement('option');
                    option.value = ind.id;
                    option.textContent = ind.descricao;

                    if (ind.id == indicadorSalvo) {
                        option.selected = true;
                    }

                    indicadorSelect.appendChild(option);
                });
            }

            dimensaoSelect.addEventListener('change', atualizarCascata);

            //inicia a cascata assim que a tela abre
            atualizarCascata();
        });
    </script>
@endpush
