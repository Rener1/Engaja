@extends('layouts.app')

@section('content')
    <x-ui.page-hero eyebrow="Pessoas" eyebrow-icon="people" title="Pré-visualização da Importação">
        <p class="text-muted">Confira os dados abaixo antes de confirmar a atualização das autorizações de imagem.</p>
    </x-ui.page-hero>

    <div>
        <div class="mb-4">
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Nome na Planilha</th>
                            <th>CPF na Planilha</th>
                            <th>Nome no Sistema</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        {{-- iteração sobre o Paginator --}}
                        @foreach ($paginator as $row)
                            <tr class="{{ $row['pode_atualizar'] ? '' : 'table-danger text-muted' }}">
                                <td>{{ $row['nome_planilha'] }}</td>
                                <td>{{ $row['cpf_planilha'] }}</td>
                                <td>{{ $row['nome_sistema'] }}</td>
                                <td>
                                    @if($row['pode_atualizar'])
                                        <span class="badge bg-success">Pronto para atualizar</span>
                                    @else
                                        <span class="badge bg-danger">Não encontrado</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white p-3">
                <div class="row align-items-center">

                    {{--resumo--}}
                    <div class="col-12 col-xl-4 text-center text-xl-start mb-3 mb-xl-0">
                        <div class="text-muted small">
                            <strong>{{ $prontos }}</strong> usuários serão atualizados.
                            <span class="text-danger ms-xl-1">{{ $erros }} não encontrados.</span>
                        </div>
                    </div>

                    {{--links da paginação --}}
                    <div class="col-12 col-xl-4 d-flex justify-content-center mb-3 mb-xl-0">
                    <x-ui.pagination :paginator="$paginator" stacked class="mt-0" />
                    </div>

                    {{-- botões de ação--}}
                    <div class="col-12 col-xl-4">
                        <form action="{{ route('usuarios.autorizacoes.confirmar') }}" method="POST" class="m-0 d-flex justify-content-center justify-content-xl-end gap-2">
                            @csrf
                            <input type="hidden" name="session_key" value="{{ $sessionKey }}">
                            <a href="{{ route('usuarios.autorizacoes.import') }}" class="btn btn-light border">Cancelar</a>

                            <button type="submit" class="btn btn-engaja" {{ $prontos === 0 ? 'disabled' : '' }}>
                                Confirmar Importação
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
