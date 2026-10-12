@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-bold text-engaja mb-0">Meu perfil</h1>
        <a href="{{ route('profile.certificados') }}" class="btn btn-outline-secondary btn-sm">Meus certificados</a>
    </div>

    {{-- Mensagens globais --}}
    @if (session('status') === 'profile-updated')
    <div class="alert alert-success">Dados do perfil atualizados com sucesso.</div>
    @elseif (session('status') === 'password-updated')
    <div class="alert alert-success">Senha atualizada com sucesso.</div>
    @elseif (session('status') === 'verification-link-sent')
    <div class="alert alert-info">Um novo link de verificação foi enviado para seu e-mail.</div>
    @endif

    @php
    /** @var \App\Models\User $u */
    $u = $user ?? auth()->user();
    $participante = $u->participante ?? null;

    // Carrega municípios caso o controller não tenha enviado
    $municipios = $municipios
    ?? \App\Models\Municipio::with('estado')
    ->orderBy('nome')
    ->get(['id','nome','estado_id']);

    // Formata label "Município — UF"
    $munLabel = function($m) {
    $uf = $m->estado->sigla ?? null;
    return $uf ? "{$m->nome} — {$uf}" : $m->nome;
    };

    // Valor seguro para o input date (string 'Y-m-d' ou vazio)
    $dataEntradaValue = '';
    if (!empty($participante?->data_entrada)) {
    try {
    $dataEntradaValue = \Carbon\Carbon::parse($participante->data_entrada)->format('Y-m-d');
    } catch (\Throwable $e) {
    $dataEntradaValue = '';
    }
    }
    @endphp

    <div class="row g-4">
        {{-- PERFIL + PARTICIPANTE + DEMOGRÁFICOS (UM ÚNICO FORM) --}}
        <div class="col-12">
            <form method="POST" action="{{ route('profile.update') }}" class="needs-validation" novalidate enctype="multipart/form-data">
                @csrf
                @method('patch')

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <strong>Informações do perfil</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label for="profile_photo" class="form-label">Foto de perfil</label>
                            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                                @if ($u->profile_photo_url)
                                    <img src="{{ $u->profile_photo_url }}" alt="Foto de perfil de {{ $u->name }}"
                                         class="rounded-circle border"
                                         style="width:72px; height:72px; object-fit:cover;">
                                @else
                                    <span class="admin-avatar" style="width:72px; height:72px; font-size:1.5rem;">{{ $u->profile_initial }}</span>
                                @endif

                                <div class="flex-grow-1">
                                    <input id="profile_photo" type="file" name="profile_photo"
                                           class="form-control @error('profile_photo') is-invalid @enderror"
                                           accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                                    @error('profile_photo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Formatos aceitos: JPG, JPEG, PNG, GIF e WEBP. Tamanho máximo: 5 MB.</div>

                                    @if ($u->profile_photo_url)
                                        <button type="submit"
                                                name="remove_profile_photo"
                                                value="1"
                                                class="btn btn-outline-danger btn-sm mt-3"
                                                onclick="return confirm('Deseja remover a foto de perfil?')">
                                            Remover foto
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Nome --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input id="name" type="text"
                                name="name"
                                value="{{ old('name', $u->name) }}"
                                class="form-control @error('name') is-invalid @enderror"
                                required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- E-mail --}}
                        <div class="mb-0">
                            <label for="email" class="form-label">E-mail</label>
                            <input id="email" type="email"
                                name="email"
                                value="{{ old('email', $u->email) }}"
                                class="form-control @error('email') is-invalid @enderror"
                                required>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            @if ($u instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $u->hasVerifiedEmail())
                            <div class="alert alert-warning mt-3 d-flex align-items-center" role="alert">
                                <div class="me-2">Seu e-mail ainda não foi verificado.</div>
                                <form method="POST" action="{{ route('verification.send') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Reenviar verificação
                                    </button>
                                </form>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <strong>Dados do participante</strong>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- CPF --}}
                            <div class="col-md-6">
                                @php
                                    $cpfRaw = old('cpf', $participante->cpf ?? '');
                                    $cpfDigits = preg_replace('/\D+/', '', $cpfRaw);
                                    $cpfFormatado = strlen($cpfDigits) === 11
                                        ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfDigits)
                                        : $cpfRaw;
                                @endphp
                                <label for="cpf" class="form-label">CPF</label>
                                <input id="cpf" type="text" name="cpf"
                                    inputmode="numeric" autocomplete="off"
                                    maxlength="14" required
                                    value="{{ $cpfFormatado }}"
                                    class="form-control @error('cpf') is-invalid @enderror"
                                    placeholder="000.000.000-00">
                                @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Telefone --}}
                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input id="telefone" type="text" name="telefone"
                                    inputmode="numeric" autocomplete="tel"
                                    maxlength="15"
                                    value="{{ old('telefone', $participante->telefone ?? '') }}"
                                    class="form-control @error('telefone') is-invalid @enderror"
                                    placeholder="(99) 99999-9999">
                                @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="tipo_organizacao" class="form-label">Tipo de instituição</label>

                                @php
                                $currentTipoOrg = old('tipo_organizacao', $participante->tipo_organizacao ?? '');
                                @endphp

                                <select id="tipo_organizacao" name="tipo_organizacao"
                                    class="form-select @error('tipo_organizacao') is-invalid @enderror">
                                    <option value="">Selecione...</option>

                                    @foreach($organizacoes as $org)
                                    <option value="{{ $org }}" @selected($currentTipoOrg===$org)>{{ $org }}</option>
                                    @endforeach
                                </select>

                                @error('tipo_organizacao')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="escola_unidade" class="form-label">Nome da instituição</label>
                                <input id="escola_unidade" type="text" name="escola_unidade"
                                    value="{{ old('escola_unidade', $participante->escola_unidade ?? '') }}"
                                    class="form-control @error('escola_unidade') is-invalid @enderror">
                                @error('escola_unidade')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="tag" class="form-label">Vinculo no projeto</label>
                                <select id="tag" name="tag"
                                    class="form-select @error('tag') is-invalid @enderror">
                                    <option value="">Selecione...</option>
                                    @foreach($participanteTags as $tagOption)
                                    <option value="{{ $tagOption }}" @selected(old('tag', $participante->tag ?? '') === $tagOption)>{{ $tagOption }}</option>
                                    @endforeach
                                </select>
                                @error('tag')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="municipio_id" class="form-label">Município</label>
                                <select id="municipio_id" name="municipio_id"
                                    class="form-select @error('municipio_id') is-invalid @enderror">
                                    <option value="">— Nenhum —</option>
                                    @foreach($municipios as $m)
                                    <option value="{{ $m->id }}"
                                        @selected((string)old('municipio_id', $participante->municipio_id ?? '') === (string)$m->id)>
                                        {{ $munLabel($m) }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('municipio_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="sexo" class="form-label">Sexo</label>
                                @php $currentSexo = old('sexo', $participante->sexo ?? 'Não preenchido'); @endphp
                                <select id="sexo" name="sexo" class="form-select @error('sexo') is-invalid @enderror">
                                    <option value="">Selecione...</option>
                                    @foreach(\App\Models\Participante::SEXOS as $opcaoSexo)
                                        <option value="{{ $opcaoSexo }}" @selected($currentSexo === $opcaoSexo)>{{ $opcaoSexo }}</option>
                                    @endforeach
                                </select>
                                @error('sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="data_nascimento" class="form-label">Data de nascimento</label>
                                <input type="date" id="data_nascimento" name="data_nascimento"
                                    max="{{ date('Y-m-d') }}"
                                    value="{{ old('data_nascimento', isset($participante?->data_nascimento) ? \Illuminate\Support\Carbon::parse($participante->data_nascimento)->format('Y-m-d') : '') }}"
                                    class="form-control @error('data_nascimento') is-invalid @enderror">
                                @error('data_nascimento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if(($participante?->idade) !== null)
                                    <div class="form-text">Idade calculada: {{ $participante->idade }} anos</div>
                                @endif
                            </div>

                            {{-- campo de autorização de imagem --}}
                            <div class="col-md-6">
                                <style>
                                    /* div externa */
                                    .auth-image-container {
                                        border: 1px solid #bbc1c1;
                                        border-radius: 0.8rem;
                                        padding: 1rem 1.25rem;
                                        transition: all 0.3s ease;
                                        background-color: #fff;
                                        width: 100%;
                                        height: 50px;
                                    }
                                    .auth-image-container.active {
                                        background-color: #f0fdf4;
                                        border-color: #198754;
                                    }
                                    .custom-switch-auth {
                                        width: 3.2em !important;
                                        height: 1.6em !important;
                                        cursor: pointer;
                                    }
                                    .custom-switch-auth:checked {
                                        background-color: #198754 !important;
                                        border-color: #198754 !important;
                                    }
                                    #camera_icon {
                                        transition: color 0.3s ease;
                                    }
                                </style>

                                <div class="auth-image-container d-flex align-items-center justify-content-between shadow-sm" id="auth_container">

                                    <div class="d-flex align-items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-camera text-muted" id="camera_icon" viewBox="0 0 16 16">
                                            <path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/>
                                            <path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
                                        </svg>

                                        <label class="form-check-label mb-0" for="autorizacao_imagem" style="cursor: pointer; font-size: 1rem;">
                                            Autorização de imagem, voz e nome
                                        </label>

                                        <button type="button" class="btn btn-link p-0 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalTermosImagem" aria-label="Ler termos de uso de imagem" title="Ler termos de uso de imagem">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-question-circle-fill" viewBox="0 0 16 16" style="color: #421944;">
                                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.496 6.033h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286a.237.237 0 0 0 .241.247zm2.325 6.443c.61 0 1.029-.394 1.029-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94 0 .533.425.927 1.009.927z"/>
                                            </svg>
                                        </button>
                                    </div>

                                    {{-- switch --}}
                                    <div class="form-check form-switch m-0 ps-0 d-flex align-items-center">
                                        <input type="hidden" name="autorizacao_imagem" value="0">
                                        <input class="form-check-input custom-switch-auth m-0" type="checkbox" role="switch" id="autorizacao_imagem" name="autorizacao_imagem" value="1" {{ old('autorizacao_imagem', $user->participante->autorizacao_imagem ?? false) ? 'checked' : '' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- DADOS DEMOGRÁFICOS --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <strong>Dados demográficos</strong>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            {{-- 1. Identidade de Gênero --}}
                            <div class="col-md-6">
                                <label for="identidade_genero" class="form-label">
                                    Identidade de Gênero <span class="text-danger">*</span>
                                </label>
                                <select name="identidade_genero" id="identidade_genero"
                                        class="form-select @error('identidade_genero') is-invalid @enderror"
                                        required onchange="toggleOutroDemografico(this, 'ig_outro_wrap')">
                                    <option value="" disabled selected>Selecione...</option>
                                    @foreach([
                                        'Mulher Cisgênero', 'Mulher Transsexual',
                                        'Homem Cisgênero',  'Homem Transsexual',
                                        'Travesti', 'Não binárie',
                                        'Prefiro não responder', 'Outro'
                                    ] as $op)
                                    <option value="{{ $op }}"
                                        {{ old('identidade_genero', $u->identidade_genero ?? '') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('identidade_genero')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div id="ig_outro_wrap" class="mt-2"
                                     style="display:{{ old('identidade_genero', $u->identidade_genero ?? '') == 'Outro' ? 'block' : 'none' }}">
                                    <input type="text" name="identidade_genero_outro"
                                           class="form-control @error('identidade_genero_outro') is-invalid @enderror"
                                           placeholder="Especifique"
                                           value="{{ old('identidade_genero_outro', $u->identidade_genero_outro ?? '') }}">
                                    @error('identidade_genero_outro')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- 2. Raça / Cor --}}
                            <div class="col-md-6">
                                <label for="raca_cor" class="form-label">
                                    Raça / Cor <span class="text-danger">*</span>
                                </label>
                                <select name="raca_cor" id="raca_cor"
                                        class="form-select @error('raca_cor') is-invalid @enderror"
                                        required>
                                    <option value="" disabled selected>Selecione...</option>
                                    @foreach(['Preta','Parda','Branca','Amarela','Indígena','Prefere não declarar'] as $op)
                                    <option value="{{ $op }}"
                                        {{ old('raca_cor', $u->raca_cor ?? '') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('raca_cor')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- 3. Comunidade Tradicional --}}
                            <div class="col-md-6">
                                <label for="comunidade_tradicional" class="form-label">
                                    Pertencimento a Comunidades Tradicionais <span class="text-danger">*</span>
                                </label>
                                <select name="comunidade_tradicional" id="comunidade_tradicional"
                                        class="form-select @error('comunidade_tradicional') is-invalid @enderror"
                                        required onchange="toggleOutroDemografico(this, 'ct_outro_wrap')">
                                    <option value="" disabled selected>Selecione...</option>
                                    @foreach([
                                        'Não','Povos indígenas','Comunidades Quilombolas',
                                        'Povos Ciganos','Ribeirinhos','Extrativistas','Outro'
                                    ] as $op)
                                    <option value="{{ $op }}"
                                        {{ old('comunidade_tradicional', $u->comunidade_tradicional ?? '') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('comunidade_tradicional')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div id="ct_outro_wrap" class="mt-2"
                                     style="display:{{ old('comunidade_tradicional', $u->comunidade_tradicional ?? '') == 'Outro' ? 'block' : 'none' }}">
                                    <input type="text" name="comunidade_tradicional_outro"
                                           class="form-control @error('comunidade_tradicional_outro') is-invalid @enderror"
                                           placeholder="Especifique"
                                           value="{{ old('comunidade_tradicional_outro', $u->comunidade_tradicional_outro ?? '') }}">
                                    @error('comunidade_tradicional_outro')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- 4. Faixa Etária --}}
                            <div class="col-md-6">
                                <label for="faixa_etaria" class="form-label">
                                    Faixa Etária <span class="text-danger">*</span>
                                </label>
                                <select name="faixa_etaria" id="faixa_etaria"
                                        class="form-select @error('faixa_etaria') is-invalid @enderror"
                                        required>
                                    <option value="" disabled selected>Selecione...</option>
                                    @foreach([
                                        'Primeira infância (0 a 6 anos)',
                                        'Criança (7 a 11 anos)',
                                        'Adolescente (12 a 17 anos)',
                                        'Adulto (18 a 59 anos)',
                                        'Idoso (a partir dos 60 anos)',
                                    ] as $op)
                                    <option value="{{ $op }}"
                                        {{ old('faixa_etaria', $u->faixa_etaria ?? '') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('faixa_etaria')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- 5. PcD --}}
                            <div class="col-md-6">
                                <label for="pcd" class="form-label">
                                    Pessoa com Deficiência (PcD) <span class="text-danger">*</span>
                                </label>
                                <select name="pcd" id="pcd"
                                        class="form-select @error('pcd') is-invalid @enderror"
                                        required>
                                    <option value="" disabled selected>Selecione...</option>
                                    @foreach(['Não','Física','Auditiva','Visual','Intelectual','Múltipla'] as $op)
                                    <option value="{{ $op }}"
                                        {{ old('pcd', $u->pcd ?? '') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('pcd')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- 6. Orientação Sexual --}}
                            <div class="col-md-6">
                                <label for="orientacao_sexual" class="form-label">
                                    Orientação Sexual <span class="text-danger">*</span>
                                </label>
                                <select name="orientacao_sexual" id="orientacao_sexual"
                                        class="form-select @error('orientacao_sexual') is-invalid @enderror"
                                        required onchange="toggleOutroDemografico(this, 'os_outra_wrap')">
                                    <option value="" disabled selected>Selecione...</option>
                                    @foreach([
                                        'Lésbica','Gay','Bissexual',
                                        'Heterossexual','Prefere não declarar','Outra'
                                    ] as $op)
                                    <option value="{{ $op }}"
                                        {{ old('orientacao_sexual', $u->orientacao_sexual ?? '') == $op ? 'selected' : '' }}>
                                        {{ $op }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('orientacao_sexual')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div id="os_outra_wrap" class="mt-2"
                                     style="display:{{ old('orientacao_sexual', $u->orientacao_sexual ?? '') == 'Outra' ? 'block' : 'none' }}">
                                    <input type="text" name="orientacao_sexual_outra"
                                           class="form-control @error('orientacao_sexual_outra') is-invalid @enderror"
                                           placeholder="Especifique"
                                           value="{{ old('orientacao_sexual_outra', $u->orientacao_sexual_outra ?? '') }}">
                                    @error('orientacao_sexual_outra')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-engaja" type="submit">Salvar tudo</button>
                </div>
            </form>
        </div>

        {{-- ALTERAR SENHA (form separado) --}}
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100 mt-4">
                <div class="card-header bg-white">
                    <strong>Atualizar senha</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('password.update') }}" class="needs-validation" novalidate>
                        @csrf
                        @method('put')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Senha atual</label>
                            <input id="current_password" type="password"
                                name="current_password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                required>
                            @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nova senha</label>
                            <input id="password" type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required autocomplete="new-password">
                            @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Mínimo 8 caracteres. Use letras, números e/ou símbolos.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                            <input id="password_confirmation" type="password"
                                name="password_confirmation"
                                class="form-control"
                                required autocomplete="new-password">
                        </div>

                        <div class="d-flex justify-content-end">
                            <button class="btn btn-engaja" type="submit">Atualizar senha</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- EXCLUIR CONTA (form separado) --}}
        <div class="col-12 col-lg-6">
            <div class="card border-danger-subtle shadow-sm h-100 mt-4">
                <div class="card-header bg-white text-danger">
                    <strong>Excluir conta</strong>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        Esta ação é irreversível. Todos os seus dados serão removidos.
                    </p>

                    <form method="POST" action="{{ route('profile.destroy') }}" data-confirm="Tem certeza que deseja excluir sua conta?">
                        @csrf
                        @method('delete')

                        <div class="row g-3 align-items-end">
                            <div class="col-md-7">
                                <label for="password_delete" class="form-label">Confirme sua senha</label>
                                <input id="password_delete" type="password"
                                    name="password"
                                    class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                                    required>
                                @error('password', 'userDeletion')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-5 d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-danger">
                                    Excluir minha conta
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal para autorização de imagem --}}
<div class="modal fade" id="modalTermosImagem" tabindex="-1" aria-labelledby="modalTermosImagemLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title text-engaja fw-bold" id="modalTermosImagemLabel">Autorização de Imagem, Voz e Nome</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-muted" style="font-size: 0.95rem;">
                <p>Ao marcar este campo você confirma e possui a ciência da destinação do uso do conteúdo descrito abaixo:</p>
                <ol type="a" class="ps-3 mb-3" style="line-height: 1.2;">
                    <li class="mb-2">Utilizar e veicular as fotografias ou vídeos realizados com o registro da imagem para fins de publicidade institucional, sem qualquer limitação de número de inserções e reproduções;</li>
                    <li class="mb-2">Utilizar e veicular as fotografias ou vídeos captados acima em todos os canais de comunicação do projeto e parcerias, como redes sociais e outros;</li>
                    <li class="mb-2">Utilizar as fotografias ou vídeos na produção de quaisquer materiais publicitários para fins de divulgação do projeto em canais de comunicação e divulgação;</li>
                    <li>Utilizar as fotografias ou cortes de vídeos na criação de conteúdo para a produção de materiais publicitários institucionais e afins.</li>
                </ol>
                <p class="mb-0"> Autorizo, de forma expressa o uso e a reprodução da minha imagem, nome e voz sem qualquer ônus, no Brasil ou no
                   exterior, em favor do Projeto ALFA-EJA Brasil, realizado pelo Instituto de Educação e Direitos Humanos Paulo Freire,
                   com sede na Rua Vespasiano, nº 344, sala F 022 – Vila Romana, São Paulo, SP, CEP 05044-050, com o CNPJ
                   04.950.603/0001-05.
                </p>
            </div>
            <div class="modal-footer border-0 border-top d-flex justify-content-between align-items-center pt-3 mt-1">
                <div class="form-check m-0">
                    <input class="form-check-input" type="checkbox" id="checkConcordoTermos" style="cursor: pointer;">
                    <label class="form-check-label fw-medium text-dark user-select-none mb-2" for="checkConcordoTermos" style="cursor: pointer;">
                        Li todos os termos e concordo com a destinação <span class="text-danger">*</span>
                    </label>
                </div>
                <button type="button" class="btn btn-engaja" id="btnEstouCiente" data-bs-dismiss="modal" disabled>Ok</button>
            </div>
        </div>
    </div>
</div>

<script>
    // aplica máscara enquanto digita (sem libs)
    const onlyDigits = s => (s || '').replace(/\D+/g, '');

    function maskCPF(v) {
        const d = onlyDigits(v).slice(0, 11);
        const p1 = d.slice(0, 3);
        const p2 = d.slice(3, 6);
        const p3 = d.slice(6, 9);
        const p4 = d.slice(9, 11);
        let out = p1;
        if (p2) out += '.' + p2;
        if (p3) out += '.' + p3;
        if (p4) out += '-' + p4;
        return out;
    }

    function maskPhone(v) {
        const d = onlyDigits(v).slice(0, 11);
        const is11 = d.length > 10; // celular com 9 digitos
        const dd = d.slice(0, 2);
        const p1 = is11 ? d.slice(2, 7) : d.slice(2, 6);
        const p2 = is11 ? d.slice(7, 11) : d.slice(6, 10);
        let out = '';
        if (dd) out = `(${dd}`;
        if (dd && (p1 || p2)) out += ') ';
        if (p1) out += p1;
        if (p2) out += '-' + p2;
        return out;
    }

    const cpfEl = document.getElementById('cpf');
    const telEl = document.getElementById('telefone');

    if (cpfEl) {
        cpfEl.addEventListener('input', e => {
            const start = e.target.selectionStart;
            e.target.value = maskCPF(e.target.value);
            // caret: joga pro final (simples e suficiente)
            e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        });
    }
    if (telEl) {
        telEl.addEventListener('input', e => {
            e.target.value = maskPhone(e.target.value);
            e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        });
    }

    // Toggle para campos demográficos "Outros"
    function toggleOutroDemografico(select, wrapId) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) return;
        const mostrar = select.value === 'Outro' || select.value === 'Outra';
        wrap.style.display = mostrar ? 'block' : 'none';
        const input = wrap.querySelector('input');
        if (input) input.required = mostrar;
    }

    function initModalAutorizacaoImagem() {
        const checkboxImagem = document.getElementById('autorizacao_imagem');
        const modalTermos = document.getElementById('modalTermosImagem');

        const checkConcordo = document.getElementById('checkConcordoTermos');
        const btnCiente = document.getElementById('btnEstouCiente');

        if (checkboxImagem && modalTermos) {

            if(checkConcordo && btnCiente) {
                checkConcordo.addEventListener('change', function() {
                    btnCiente.disabled = !this.checked;
                });
            }

            checkboxImagem.addEventListener('change', function () {
                if (this.checked) {

                    if(checkConcordo) checkConcordo.checked = false;
                    if(btnCiente) btnCiente.disabled = true;

                    const modalInstance = new bootstrap.Modal(modalTermos);
                    modalInstance.show();
                }
            });

            modalTermos.addEventListener('hide.bs.modal', function () {
                if(checkConcordo && !checkConcordo.checked && checkboxImagem.checked) {
                    checkboxImagem.checked = false;
                }
            });
        }
    }
    document.addEventListener('DOMContentLoaded', initModalAutorizacaoImagem);


</script>

@endsection
