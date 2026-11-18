@extends('layouts.app')

@section('content')
<div class="mb-4">
    <x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="mic" title="Transcrição de Avaliação">
        <p class="text-muted">
            <strong>Momento:</strong> {{ $avaliacao->atividade->descricao ?? '—' }}<br>
            <strong>Modelo:</strong> {{ $avaliacao->templateAvaliacao->nome ?? '—' }}
        </p>
        <x-slot:actions>
            <a href="{{ route('avaliacoes.index') }}" class="eg-hero-btn">Voltar para listagem</a>
        </x-slot:actions>
    </x-ui.page-hero>

    @if(session('error') === 'Usuário não encontrado.' || ($errors->any() && (old('name') || old('email') || old('cpf'))))
    <div class="alert alert-info shadow-sm">
        <h6 class="alert-heading fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Usuário não encontrado. Deseja cadastrá-lo?</h6>
        <p>Preencha os dados abaixo para criar um novo usuário e participante rapidamente para esta transcrição.</p>

        @if($errors->any())
        <div class="alert alert-danger py-2 px-3 mb-3">
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('avaliacoes.transcricao.cadastrar', $avaliacao) }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label for="new_name" class="form-label">Nome Completo</label>
                <input type="text" name="name" id="new_name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                       value="{{ old('type', $type ?? '') === 'nome' ? old('search', $search ?? '') : '' }}">
                @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="new_email" class="form-label">E-mail</label>
                <input type="email" name="email" id="new_email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                       value="{{ old('type', $type ?? '') === 'email' ? old('search', $search ?? '') : '' }}" required>
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label for="new_cpf" class="form-label">CPF</label>
                <input type="text" name="cpf" id="new_cpf" class="form-control form-control-sm @error('cpf') is-invalid @enderror"
                       value="{{ old('type', $type ?? '') === 'cpf' ? old('search', $search ?? '') : '' }}">
                @error('cpf')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-engaja w-100">Cadastrar</button>
            </div>
        </form>
    </div>
    @endif

    @if(session('error') && session('error') !== 'Usuário não encontrado.')
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-4">Buscar usuário para associar à transcrição</h5>
            
            <form action="{{ route('avaliacoes.transcricao.busca', $avaliacao) }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="type" class="form-label">Tipo de busca</label>
                        <select name="type" id="type" class="form-select">
                            <option value="nome" @selected(old('type', $type ?? 'nome') === 'nome')>Nome</option>
                            <option value="cpf" @selected(old('type', $type ?? '') === 'cpf')>CPF</option>
                            <option value="email" @selected(old('type', $type ?? '') === 'email')>E-mail</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label for="search" class="form-label">Valor da busca</label>
                           <input type="text" name="search" id="search" class="form-control"
                               value="{{ old('search', $search ?? '') }}" placeholder="Digite para buscar..."
                               list="sugestoes-nome" autocomplete="off">
                        <datalist id="sugestoes-nome"></datalist>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-engaja w-100">Buscar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(isset($duplicados) && $duplicados)
    <div class="alert alert-warning shadow-sm">
        <h6 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Atenção: Múltiplos usuários encontrados</h6>
        <p class="mb-3">{{ $mensagem }}</p>
        
        <div class="list-group">
            @foreach($usuarios as $usuario)
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 fw-bold">{{ $usuario->name }}</h6>
                    <small class="text-muted">
                        <strong>E-mail:</strong> {{ $usuario->email }} | 
                        <strong>CPF:</strong> {{ $usuario->participante->cpf ?? 'Não informado' }}
                    </small>
                </div>
                <form action="{{ route('avaliacoes.transcricao.busca', $avaliacao) }}" method="POST">
                    @csrf
                    <input type="hidden" name="type" value="email">
                    <input type="hidden" name="search" value="{{ $usuario->email }}">
                    <button type="submit" class="btn btn-sm btn-engaja">Selecionar</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const searchInput = document.getElementById('search');
    const datalist = document.getElementById('sugestoes-nome');

    let timeout = null;

    searchInput.addEventListener('input', function() {
        if (typeSelect.value !== 'nome') {
            datalist.innerHTML = '';
            return;
        }

        const term = this.value.trim();
        if (term.length < 2) {
            datalist.innerHTML = '';
            return;
        }

        clearTimeout(timeout);
        timeout = setTimeout(() => {
            fetch(`{{ route('avaliacoes.usuarios.sugestoes') }}?q=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    datalist.innerHTML = '';
                    if (Array.isArray(data)) {
                        data.forEach(nome => {
                            const option = document.createElement('option');
                            option.value = nome;
                            datalist.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Erro na busca de sugestões:', error));
        }, 300);
    });

    typeSelect.addEventListener('change', function() {
        if (this.value === 'nome') {
            searchInput.placeholder = 'Digite o nome...';
        } else if (this.value === 'cpf') {
            searchInput.placeholder = 'Digite o CPF (apenas números)...';
        } else {
            searchInput.placeholder = 'Digite o e-mail...';
        }
        datalist.innerHTML = '';
        searchInput.value = '';
    });
});
</script>
@endpush
