@extends('layouts.app')

@section('content')
<style>
    .notif-toggle {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        border: 1px solid transparent;
        border-radius: 999px;
        padding: .4rem .9rem;
        font-size: .85rem;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease;
    }
    .notif-toggle .notif-dot {
        width: .55rem;
        height: .55rem;
        border-radius: 50%;
        background: currentColor;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, .35) inset;
    }
    .notif-toggle.is-on {
        background: #421944;
        border-color: #421944;
        color: #fff;
    }
    .notif-toggle.is-on:hover {
        background: #57215a;
        box-shadow: 0 2px 8px rgba(66, 25, 68, .3);
    }
    .notif-toggle.is-off {
        background: #f3f4f6;
        border-color: #d9dbe0;
        color: #6b7280;
    }
    .notif-toggle.is-off:hover {
        background: #e9eaee;
        color: #421944;
        border-color: #c3b0c4;
    }
    .notif-toggle .bi,
    .notif-toggle .notif-label {
        transition: opacity .15s ease;
    }
    .notif-toggle.is-loading {
        pointer-events: none;
        opacity: .65;
    }
    .notif-toggle.is-pulsing {
        animation: notif-pulse .35s ease;
    }
    @keyframes notif-pulse {
        0%   { transform: scale(1); }
        45%  { transform: scale(1.08); }
        100% { transform: scale(1); }
    }
</style>
<div class="mb-4">
    <x-ui.page-hero eyebrow="Administração" eyebrow-icon="people" title="Notificações de Agendamento">
        <p class="text-muted small mb-0">Selecione quais usuários devem receber e-mail quando um novo agendamento for criado.</p>
        <x-slot:actions>
            <a href="{{ route('usuarios.index') }}" class="eg-hero-btn border">Voltar</a>
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="eg-filter-card">
        <form action="{{ route('usuarios.notificacoes-agendamento.index') }}" method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-4">
                <x-ui.input type="text" name="q" placeholder="Buscar nome ou e-mail..." value="{{ $search }}" />
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-engaja w-100">Filtrar</button>
                @if($search)
                    <a href="{{ route('usuarios.notificacoes-agendamento.index') }}" class="btn btn-light border w-100">Limpar</a>
                @endif
            </div>
        </form>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($users->isEmpty())
    <x-ui.empty-state icon="inbox" muted>
        @if (!empty($search))
            Nenhum usuário encontrado para "{{ $search }}".
        @else
            Não há usuários cadastrados.
        @endif
    </x-ui.empty-state>
@else
    @php
        $columns = [
            ['field' => 'nome', 'headerName' => 'Nome', 'flex' => 2],
            ['field' => 'email', 'headerName' => 'E-mail', 'flex' => 2],
            ['field' => 'recebe', 'headerName' => 'Recebe notificação', 'minWidth' => 180, 'html' => true, 'align' => 'center'],
        ];

        $rows = $users->map(function ($user) use ($search) {
            $action = route('usuarios.notificacoes-agendamento.toggle', ['managedUser' => $user, 'q' => $search]);
            $ativo = $user->hasPermissionTo('agendamento.notificar');
            $recebeHtml = '<button type="button" class="notif-toggle js-notif-toggle ' . ($ativo ? 'is-on' : 'is-off') . '"'
                . ' data-action="' . e($action) . '" aria-pressed="' . ($ativo ? 'true' : 'false') . '">'
                . '<span class="notif-dot"></span>'
                . '<i class="bi ' . ($ativo ? 'bi-bell-fill' : 'bi-bell-slash') . '"></i>'
                . '<span class="notif-label">' . ($ativo ? 'Ativado' : 'Desativado') . '</span>'
                . '</button>';

            return [
                'id' => $user->id,
                'nome' => $user->name,
                'email' => $user->email,
                'recebe' => $recebeHtml,
            ];
        })->values();
    @endphp

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <x-data-table
                id="grid-notificacoes-agendamento"
                :columns="$columns"
                :rows="$rows"
                :pagination="false"
            :paginator="$users"
        />
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('.js-notif-toggle');
        if (!btn || btn.classList.contains('is-loading')) {
            return;
        }

        const url = btn.dataset.action;
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        btn.classList.add('is-loading');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Falha na requisição');
            }

            const data = await response.json();
            const ativo = !!data.ativo;

            btn.classList.toggle('is-on', ativo);
            btn.classList.toggle('is-off', !ativo);
            btn.setAttribute('aria-pressed', ativo ? 'true' : 'false');
            btn.querySelector('.bi').className = 'bi ' + (ativo ? 'bi-bell-fill' : 'bi-bell-slash');
            btn.querySelector('.notif-label').textContent = ativo ? 'Ativado' : 'Desativado';

            btn.classList.remove('is-pulsing');
            void btn.offsetWidth; // reinicia a animação
            btn.classList.add('is-pulsing');
        } catch (error) {
            alert('Não foi possível atualizar a preferência. Tente novamente.');
        } finally {
            btn.classList.remove('is-loading');
        }
    });
</script>
@endpush
