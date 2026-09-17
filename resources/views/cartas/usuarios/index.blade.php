@extends('cartas.layouts.app')

@section('title', 'Gerenciar usuários - Cartas para Esperançar')

@section('body')
    @include('cartas.shared._styles')

    <main class="cpe-page cpe-users-page">
        @include('cartas.shared._logo')

        <section class="cpe-users-shell">
            <div class="cpe-users-header">
                <div>
                    <h1 class="cpe-title">Gerenciar usuários</h1>
                    <p>Altere dados básicos e o perfil de acesso dos usuários do Cartas.</p>
                </div>

                <form method="GET" action="{{ route('cartas.usuarios.index') }}" class="cpe-search">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Pesquisar">
                    <button type="submit" aria-label="Pesquisar">⌕</button>
                </form>
            </div>

            @if (session('status'))
                <div class="cpe-alert">{{ session('status') }}</div>
            @endif

            <div class="cpe-table-card">
                <table class="cpe-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Vínculo</th>
                            <th>Verificado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php($roleName = $user->roles->pluck('name')->first(fn ($name) => array_key_exists($name, $roles)))
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="cpe-pill {{ $roleName === 'cartas_admin' ? 'cpe-pill--blue' : ($roleName === 'cartas_gestao' ? 'cpe-pill--yellow' : 'cpe-pill--green') }}">
                                        {{ $roles[$roleName] ?? 'Sem perfil' }}
                                    </span>
                                </td>
                                <td>{{ \App\Models\User::VINCULOS_CARTAS[$user->cartas_tipo_vinculo] ?? 'Não informado' }}</td>
                                <td>{{ $user->email_verified_at ? 'Sim' : 'Não' }}</td>
                                <td>
                                    <div class="cpe-dropdown">
                                        <button type="button" class="cpe-link cpe-dropdown__trigger">Gerenciar ▾</button>
                                        <div class="cpe-dropdown__menu">
                                            <a href="{{ route('cartas.usuarios.edit', $user) }}" class="cpe-dropdown__item">Editar</a>
                                            <button type="button" class="cpe-dropdown__item" data-modal-open="avaliacaoModal-{{ $user->id }}">Enviar formulário</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="cpe-empty">Nenhum usuário encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Modais de enviar avaliação por usuário --}}
            @foreach($users as $user)
                <div class="cpe-modal" id="avaliacaoModal-{{ $user->id }}">
                    <div class="cpe-modal__backdrop"></div>
                    <div class="cpe-modal__dialog">
                        <h2>Enviar formulário</h2>
                        @if($avaliacoes->isNotEmpty())
                            <p>Selecione a avaliação para enviar por e-mail para <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>
                            <form method="POST" action="{{ route('cartas.usuarios.enviar-avaliacao', $user) }}">
                                @csrf
                                <select name="avaliacao_id" class="cpe-select" required style="width: 100%;">
                                    <option value="">Selecione a avaliação...</option>
                                    @foreach($avaliacoes as $avaliacao)
                                        <option value="{{ $avaliacao->id }}">
                                            {{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? "Avaliação #{$avaliacao->id}") }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="cpe-modal-actions">
                                    <button type="button" class="cpe-button cpe-button--ghost" data-modal-close>Cancelar</button>
                                    <button type="submit" class="cpe-button">Enviar</button>
                                </div>
                            </form>
                        @else
                            <p>Nenhuma avaliação disponível para envio. Crie uma avaliação universal com <code>is_cartas</code> e formulário aberto primeiro.</p>
                            <div style="margin-top: 14px;">
                                <button type="button" class="cpe-button cpe-button--ghost" data-modal-close style="width: 100%;">Fechar</button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="cpe-users-footer">
                <a href="{{ route('cartas.dashboard') }}" class="cpe-button cpe-button--ghost">Voltar</a>
                <div>{{ $users->links() }}</div>
            </div>
        </section>

        @include('cartas.shared._user-menu')
    </main>

    @include('cartas.shared._scripts')

    <style>
        .cpe-users-page {
            padding: 0 28px 72px;
        }

        .cpe-users-shell {
            width: min(100%, 1020px);
            margin: 120px auto 0;
            display: grid;
            gap: 18px;
        }

        .cpe-users-header {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 24px;
        }

        .cpe-users-header p {
            margin: 10px 0 0;
            color: #666;
            font-size: 14px;
        }

        .cpe-search {
            width: min(260px, 100%);
            height: 38px;
            border: 1px solid #ddd;
            border-radius: 999px;
            background: #fff;
            display: flex;
            align-items: center;
            padding: 0 10px 0 14px;
        }

        .cpe-search input {
            flex: 1;
            border: 0;
            outline: 0;
            font-size: 14px;
            background: transparent;
        }

        .cpe-search button {
            border: 0;
            background: transparent;
            font-size: 18px;
        }

        .cpe-empty {
            text-align: center;
            padding: 42px !important;
        }

        .cpe-users-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        /* Permite que o dropdown da última linha não seja cortado */
        .cpe-users-page .cpe-table-card {
            overflow: visible;
        }

        /* Dropdown inline */
        .cpe-dropdown {
            position: relative;
            display: inline-block;
        }

        .cpe-dropdown__trigger {
            cursor: pointer;
            border: 0;
            background: transparent;
        }

        .cpe-dropdown__menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            z-index: 100;
            min-width: 170px;
            background: #fff;
            border: 1px solid rgba(0,0,0,.08);
            border-radius: 6px;
            box-shadow: 0 12px 32px rgba(0,0,0,.14);
            padding: 4px;
        }

        .cpe-dropdown.is-open .cpe-dropdown__menu {
            display: block;
        }

        .cpe-dropdown__item {
            display: block;
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            border-radius: 5px;
            padding: 8px 10px;
            font-weight: 700;
            color: #333;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .cpe-dropdown__item:hover {
            color: var(--cpe-purple);
            background: var(--cpe-bg);
        }

        @media (max-width: 760px) {
            .cpe-users-shell {
                margin-top: 64px;
            }

            .cpe-users-header,
            .cpe-users-footer {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>

    <script>
        // Dropdown toggle
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.cpe-dropdown__trigger');
            // fechar todos os dropdowns abertos
            document.querySelectorAll('.cpe-dropdown.is-open').forEach(function(el) {
                if (!trigger || !el.contains(trigger)) el.classList.remove('is-open');
            });
            if (trigger) {
                e.preventDefault();
                trigger.closest('.cpe-dropdown').classList.toggle('is-open');
            }
        });

        // Modal open/close (reusa o padrão do Cartas _scripts)
        document.addEventListener('click', function(e) {
            const openBtn = e.target.closest('[data-modal-open]');
            if (openBtn) {
                e.preventDefault();
                const modal = document.getElementById(openBtn.dataset.modalOpen);
                if (modal) modal.classList.add('is-open');
                // fechar dropdown também
                const dd = openBtn.closest('.cpe-dropdown');
                if (dd) dd.classList.remove('is-open');
            }

            const closeBtn = e.target.closest('[data-modal-close]');
            if (closeBtn) {
                e.preventDefault();
                const modal = closeBtn.closest('.cpe-modal');
                if (modal) modal.classList.remove('is-open');
            }

            // fechar pelo backdrop
            if (e.target.classList.contains('cpe-modal__backdrop')) {
                const modal = e.target.closest('.cpe-modal');
                if (modal) modal.classList.remove('is-open');
            }
        });
    </script>
@endsection
