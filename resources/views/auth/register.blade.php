@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-header text-center bg-primary text-white fw-bold">
                    {{ __('Criar conta no Engaja') }}
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- Name --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Nome') }}</label>
                            <input id="name" type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   name="name" value="{{ old('name') }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('E-mail') }}</label>
                            <input id="email" type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   name="email" value="{{ old('email') }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Senha') }}</label>
                            <input id="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   name="password" required autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                {{ __('Min. 8 caracteres. Use letras, números e/ou símbolos.') }}
                            </div>
                        </div>

                        {{-- Confirm Password --}}
                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">{{ __('Confirmar senha') }}</label>
                            <input id="password_confirmation" type="password"
                                   class="form-control"
                                   name="password_confirmation" required autocomplete="new-password">
                        </div>

                        {{-- Inicializa $u como nulo para a view de registro não quebrar --}}
                        @php $u = null; @endphp

                        <div class="card shadow-sm mb-4 border-light">
                            <div class="card-header bg-light">
                                <strong>Dados do participante</strong>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="profile_photo" class="form-label">Foto de perfil</label>
                                        <input id="profile_photo" type="file" name="profile_photo"
                                               class="form-control @error('profile_photo') is-invalid @enderror"
                                               accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                                        @error('profile_photo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Formatos aceitos: JPG, JPEG, PNG, GIF e WEBP. Tamanho máximo: 5 MB.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="cpf" class="form-label">CPF <span class="text-danger">*</span></label>
                                        <input id="cpf" type="text" name="cpf" inputmode="numeric" autocomplete="off"
                                               maxlength="14" value="{{ old('cpf') }}"
                                               class="form-control @error('cpf') is-invalid @enderror"
                                               placeholder="000.000.000-00" required>
                                        @error('cpf')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="telefone" class="form-label">Telefone</label>
                                        <input id="telefone" type="text" name="telefone" inputmode="numeric"
                                               autocomplete="tel" maxlength="15" value="{{ old('telefone') }}"
                                               class="form-control @error('telefone') is-invalid @enderror"
                                               placeholder="(99) 99999-9999">
                                        @error('telefone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="tipo_organizacao" class="form-label">Tipo de Instituição</label>
                                        <select id="tipo_organizacao" name="tipo_organizacao"
                                                class="form-select @error('tipo_organizacao') is-invalid @enderror">
                                            <option value="">Selecione...</option>
                                            @foreach(config('engaja.organizacoes', []) as $org)
                                                <option value="{{ $org }}" @selected(old('tipo_organizacao') === $org)>{{ $org }}</option>
                                            @endforeach
                                        </select>
                                        @error('tipo_organizacao')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="escola_unidade" class="form-label">Nome da instituição</label>
                                        <input id="escola_unidade" type="text" name="escola_unidade"
                                               value="{{ old('escola_unidade') }}"
                                               class="form-control @error('escola_unidade') is-invalid @enderror">
                                        @error('escola_unidade')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="tag" class="form-label">Vínculo no projeto</label>
                                        <select id="tag" name="tag"
                                                class="form-select @error('tag') is-invalid @enderror">
                                            <option value="">Selecione...</option>
                                            @foreach(($participanteTags ?? config('engaja.participante_tags', \App\Models\Participante::TAGS)) as $tagOption)
                                                <option value="{{ $tagOption }}" @selected(old('tag') === $tagOption)>{{ $tagOption }}</option>
                                            @endforeach
                                        </select>
                                        @error('tag')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    @include('auth.partials.municipio-select')

                                    <div class="col-md-6">
                                        <label for="sexo" class="form-label">Sexo</label>
                                        <select id="sexo" name="sexo" class="form-select @error('sexo') is-invalid @enderror">
                                            <option value="">Selecione...</option>
                                            @foreach(\App\Models\Participante::SEXOS as $opcaoSexo)
                                                <option value="{{ $opcaoSexo }}" @selected(old('sexo', 'Não preenchido') === $opcaoSexo)>{{ $opcaoSexo }}</option>
                                            @endforeach
                                        </select>
                                        @error('sexo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="data_nascimento" class="form-label">Data de nascimento</label>
                                        <input type="date" id="data_nascimento" name="data_nascimento"
                                               max="{{ date('Y-m-d') }}"
                                               value="{{ old('data_nascimento') }}"
                                               class="form-control @error('data_nascimento') is-invalid @enderror">
                                        @error('data_nascimento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- DADOS DEMOGRÁFICOS --}}
                        <div class="card shadow-sm mb-4 border-light">
                            <div class="card-header bg-light">
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
                                                {{ old('identidade_genero') == $op ? 'selected' : '' }}>
                                                {{ $op }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('identidade_genero')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div id="ig_outro_wrap" class="mt-2"
                                             style="display:{{ old('identidade_genero') == 'Outro' ? 'block' : 'none' }}">
                                            <input type="text" name="identidade_genero_outro"
                                                   class="form-control @error('identidade_genero_outro') is-invalid @enderror"
                                                   placeholder="Especifique"
                                                   value="{{ old('identidade_genero_outro') }}">
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
                                                {{ old('raca_cor') == $op ? 'selected' : '' }}>
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
                                                {{ old('comunidade_tradicional') == $op ? 'selected' : '' }}>
                                                {{ $op }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('comunidade_tradicional')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div id="ct_outro_wrap" class="mt-2"
                                             style="display:{{ old('comunidade_tradicional') == 'Outro' ? 'block' : 'none' }}">
                                            <input type="text" name="comunidade_tradicional_outro"
                                                   class="form-control @error('comunidade_tradicional_outro') is-invalid @enderror"
                                                   placeholder="Especifique"
                                                   value="{{ old('comunidade_tradicional_outro') }}">
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
                                                {{ old('faixa_etaria') == $op ? 'selected' : '' }}>
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
                                                {{ old('pcd') == $op ? 'selected' : '' }}>
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
                                                {{ old('orientacao_sexual') == $op ? 'selected' : '' }}>
                                                {{ $op }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('orientacao_sexual')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div id="os_outra_wrap" class="mt-2"
                                             style="display:{{ old('orientacao_sexual') == 'Outra' ? 'block' : 'none' }}">
                                            <input type="text" name="orientacao_sexual_outra"
                                                   class="form-control @error('orientacao_sexual_outra') is-invalid @enderror"
                                                   placeholder="Especifique"
                                                   value="{{ old('orientacao_sexual_outra') }}">
                                            @error('orientacao_sexual_outra')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a class="btn btn-link p-0" href="{{ route('login') }}">
                                {{ __('Já tem conta? Entrar') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                {{ __('Cadastrar') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card-footer text-center">
                    <small class="text-muted">
                        {{ __('Ao criar a conta, você concorda com os termos de uso e a política de privacidade.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
        const is11 = d.length > 10;
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

    function toggleOutroDemografico(select, wrapId) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) return;
        const mostrar = select.value === 'Outro' || select.value === 'Outra';
        wrap.style.display = mostrar ? 'block' : 'none';
        const input = wrap.querySelector('input');
        if (input) input.required = mostrar;
    }

    const cpfEl = document.getElementById('cpf');
    const telEl = document.getElementById('telefone');

    if (cpfEl) {
        cpfEl.addEventListener('input', e => {
            e.target.value = maskCPF(e.target.value);
            e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        });
    }

    if (telEl) {
        telEl.addEventListener('input', e => {
            e.target.value = maskPhone(e.target.value);
            e.target.setSelectionRange(e.target.value.length, e.target.value.length);
        });
    }
</script>
@endsection
