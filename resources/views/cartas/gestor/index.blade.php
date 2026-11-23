@extends('cartas.layouts.app')

@section('title', 'Cadastro de cartas - Cartas para Esperançar')

@section('body')
    @include('cartas.shared._styles')

    <main class="cpe-page cpe-manager">
        <section class="cpe-manager__left">
        @include('cartas.shared._logo')

            <div class="cpe-manager__form-wrap">
                <h1 class="cpe-title">Cadastro de cartas</h1>

                @if ($errors->any())
                    <div class="cpe-alert cpe-alert--error">{{ $errors->first() }}</div>
                @endif

                @if (session('status'))
                    <div class="cpe-alert">{{ session('status') }}</div>
                @endif

                <form id="gestorCartaForm" method="POST" action="{{ route('cartas.cartas.store') }}" enctype="multipart/form-data" class="cpe-manager-form">
                    @csrf
                    <input type="hidden" name="envio_token" value="{{ $errors->has('envio_token') ? (string) \Illuminate\Support\Str::uuid() : old('envio_token', (string) \Illuminate\Support\Str::uuid()) }}">
                    <label for="gestorCartaFluxo">Forma de envio</label>
                    <select id="gestorCartaFluxo" name="fluxo" class="cpe-field">
                        <option value="automatico" @selected(old('fluxo') !== 'manual')>Distribuição automática</option>
                        <option value="manual" @selected(old('fluxo') === 'manual')>Conversa com voluntário escolhido</option>
                    </select>
                    <label class="cpe-upload">
                        <input type="file" name="arquivo" required accept=".pdf,application/pdf">
                        <span>
                            <span class="cpe-upload__icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 16V4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 9l5-5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 20h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
                            <span class="cpe-upload__link">Clique para selecionar o arquivo</span>
                            <span class="cpe-upload__hint">PDF (máx. 10MB)</span>
                        </span>
                    </label>

                    @php
                        $remetenteSelecionado = $engajaUsers->firstWhere('id', (int) old('remetente_user_id'));
                    @endphp
                    <div class="cpe-combobox" data-combobox>
                        <input type="hidden" name="remetente_user_id" value="{{ old('remetente_user_id') }}" data-combobox-value>
                        <input type="text" class="cpe-field cpe-combobox__input" placeholder="Nome ou CPF do remetente" id="remetente_user_id" name="remetente_user_id_input"
                            autocomplete="off" role="combobox" aria-expanded="false"
                            value="{{ $remetenteSelecionado?->nome_com_localidade }}" data-combobox-input>
                        <ul class="cpe-combobox__list" role="listbox" data-combobox-list>
                            @foreach($engajaUsers as $engajaUser)
                                <?php $cpfDigits = preg_replace('/\D/', '', (string) $engajaUser->participante?->cpf); ?>
                                <li class="cpe-combobox__option" role="option"
                                    data-value="{{ $engajaUser->id }}" data-cpf="{{ $cpfDigits }}" data-label="{{ $engajaUser->nome_com_localidade }}">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%;">
                                        <span>{{ $engajaUser->nome_com_localidade }}</span>
                                    </div>
                                </li>
                            @endforeach
                            <li class="cpe-combobox__empty" data-combobox-empty hidden>Nenhum participante encontrado.</li>
                        </ul>
                    </div>

                    <div id="gestorConversaCampos" @if(old('fluxo') !== 'manual') hidden @endif>
                        <label for="gestorVoluntario">Voluntário que responde</label>
                        <select id="gestorVoluntario" name="voluntario_user_id" class="cpe-field" @if(old('fluxo') !== 'manual') disabled @endif @required(old('fluxo') === 'manual')>
                            <option value="">Selecione o voluntário</option>
                            @foreach($voluntarios as $voluntario)
                                <option value="{{ $voluntario->id }}" @selected((string) old('voluntario_user_id') === (string) $voluntario->id)>{{ $voluntario->nome_com_localidade }}</option>
                            @endforeach
                        </select>
                        <label class="cpe-upload">
                            <input type="file" name="arquivo_resposta" accept=".pdf,application/pdf" @if(old('fluxo') !== 'manual') disabled @endif>
                            <span>
                                <span class="cpe-upload__link">Anexar resposta do voluntário (opcional)</span>
                                <span class="cpe-upload__hint">PDF (máx. 10MB). Você também pode adicioná-la depois na conversa.</span>
                            </span>
                        </label>
                    </div>

                    <button type="button" class="cpe-button" id="gestorCartaSubmitBtn">Enviar carta</button>
                </form>
            </div>
        </section>

        @php
            $hasActiveFilter = filled($municipioId) || filled($statusFilter) || ($search !== '');
        @endphp

        <section class="cpe-manager__right">
            <div class="cpe-manager__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
                <div class="cpe-manager__titleline" style="margin: 0; display: flex; align-items: center; gap: 12px;">
                    <h2 style="margin: 0; padding: 0; font-size: 26px; font-weight: 600; color: #111;">Todas as cartas</h2>
                    <span style="margin: 0; font-size: 16px; font-weight: 500; color: #222; background: #f4f4f4; padding: 4px 16px; border-radius: 999px;">{{ $cartas->total() }} cartas</span>
                </div>

                <form id="filterForm" method="GET" action="{{ route('cartas.dashboard') }}" style="display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; background: #fff; padding: 16px 20px; border-radius: 8px; border: 1px solid #eaeaea; box-shadow: 0 2px 4px rgba(0,0,0,0.02); width: 100%; justify-content: space-between;">

                    @php
                        $municipioFiltroSelecionado = $municipioId ? $municipios->firstWhere('id', (int) $municipioId) : null;
                        $municipioFiltroLabel = $municipioFiltroSelecionado
                            ? $municipioFiltroSelecionado->nome . ($municipioFiltroSelecionado->estado?->sigla ? ' - '.$municipioFiltroSelecionado->estado->sigla : '')
                            : 'Todos os municípios';
                    @endphp
                    <div style="display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 180px;">
                        <label for="municipioSelectTrigger" style="font-size: 13px; font-weight: 600; color: #111; height: 20px; display: flex; align-items: center; white-space: nowrap;">Município do educando:</label>
                        <div class="cpe-municipio-select" id="municipioSelectDropdown">
                            <input type="hidden" id="municipio_id" name="municipio_id" value="{{ $municipioId }}">
                            <button type="button" id="municipioSelectTrigger" class="cpe-municipio-select__trigger" aria-haspopup="listbox" aria-expanded="false">
                                <span id="municipioSelectLabel" class="cpe-municipio-select__label">{{ $municipioFiltroLabel }}</span>
                                <svg class="cpe-municipio-select__chevron" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <div class="cpe-municipio-select__panel" role="dialog" aria-label="Filtrar município">
                                <div class="cpe-municipio-select__search-wrap">
                                    <input type="search" id="municipio_search_input" class="cpe-municipio-select__search" placeholder="Digite o nome do município..." autocomplete="off" aria-label="Digite o nome do município">
                                </div>
                                <ul class="cpe-municipio-select__list" role="listbox" id="municipioSelectList">
                                    <li class="cpe-municipio-select__option {{ empty($municipioId) ? 'is-selected' : '' }}" role="option" data-value="" data-label="Todos os municípios" aria-selected="{{ empty($municipioId) ? 'true' : 'false' }}">
                                        Todos os municípios
                                    </li>
                                    @foreach($municipios as $mun)
                                        @php
                                            $munLabel = $mun->nome . ($mun->estado?->sigla ? ' - '.$mun->estado->sigla : '');
                                            $isMunSelected = (string) $municipioId === (string) $mun->id;
                                        @endphp
                                        <li class="cpe-municipio-select__option {{ $isMunSelected ? 'is-selected' : '' }}" role="option" data-value="{{ $mun->id }}" data-label="{{ $munLabel }}" aria-selected="{{ $isMunSelected ? 'true' : 'false' }}">
                                            {{ $munLabel }}
                                        </li>
                                    @endforeach
                                    <li class="cpe-municipio-select__empty" id="municipioSelectEmpty" hidden>Nenhum município encontrado.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; max-width: 180px;">
                        <label for="statusFilter" style="font-size: 13px; font-weight: 600; color: #111; height: 20px; display: flex; align-items: center; white-space: nowrap;">Status:</label>
                        <select id="statusFilter" name="status" style="height: 40px; box-sizing: border-box; padding: 0 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; background: #fff; color: #111;">
                            <option value="">Todos</option>
                            <option value="respondida" @selected(($statusFilter ?? '') === 'respondida')>Respondida</option>
                            <option value="ajuste_solicitado" @selected(($statusFilter ?? '') === 'ajuste_solicitado')>Ajuste solicitado</option>
                            <option value="pendente" @selected(($statusFilter ?? '') === 'pendente')>Em preparação</option>
                            <option value="enviada" @selected(($statusFilter ?? '') === 'enviada')>Enviada</option>
                        </select>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 6px; flex: 2; min-width: 220px;">
                        <label for="search_input" style="font-size: 13px; font-weight: 600; color: #111; height: 20px; display: flex; align-items: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Pesquisar por nome ou CPF do remetente ou destinatário">Buscar por nome ou CPF:</label>
                        <div style="position: relative; height: 40px; display: flex; align-items: center;">
                            <input id="search_input" type="search" name="q" value="{{ $search }}" placeholder="Nome ou CPF do remetente ou destinatário..." style="height: 100%; width: 100%; box-sizing: border-box; padding: 0 36px 0 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; color: #111;">
                            <button type="submit" aria-label="Pesquisar" style="position: absolute; right: 8px; background: none; border: none; color: #888; cursor: pointer; display: flex; align-items: center; justify-content: center; height: 100%;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            </button>
                        </div>
                        <span style="font-size: 11px; color: #666;">Instantâneo (digite 2 letras)</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 180px; justify-content: flex-start;">
                        <label style="font-size: 13px; font-weight: 600; color: #111; height: 20px; display: flex; align-items: center; white-space: nowrap;">Ações:</label>
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <button type="submit" id="btnDownloadBatch" form="filterForm" formaction="{{ route('cartas.download-batch') }}" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; padding: 6px 12px; border-radius: 6px; min-height: 40px; box-sizing: border-box; text-decoration: none; background-color: var(--cartas-purple, #6a1b9a); color: white; font-weight: 500; font-size: 14px; line-height: 1.2; border: none; cursor: pointer; transition: opacity 0.2s;">
                                <span>Baixar cartas PDF</span>
                                <small style="font-size: 11px;">(todas as páginas)</small>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            @include('cartas.gestor._approval')
            @include('cartas.gestor._table')

        </section>

        @include('cartas.shared._user-menu')

        @foreach($cartas as $cartaModal)
            <div class="cpe-modal" id="deleteCarta-{{ $cartaModal->id }}">
                <div class="cpe-modal__backdrop"></div>
                <div class="cpe-modal__dialog">
                    <h2>Excluir carta</h2>
                    <p>Tem certeza que deseja excluir a carta {{ $cartaModal->codigo }}? Esta ação removerá a carta da listagem.</p>

                    <div class="cpe-modal-actions">
                        <button type="button" class="cpe-button cpe-button--ghost" data-modal-close>Cancelar</button>
                        <form method="POST" action="{{ route('cartas.cartas.destroy', $cartaModal) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="cpe-button cpe-button--danger">Excluir</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </main>

    @include('cartas.shared._scripts')

    <style>
        .cpe-batch-approval {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            padding: 16px 28px;
        }

        .cpe-batch-approval p {
            margin: 0 0 4px;
        }

        #selectedCountBadge {
            font-size: 13px;
            color: #666;
        }

        #batchApprovalFeedback {
            margin-top: 8px;
            color: #b42318;
        }

        .cpe-approval-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .cpe-approval-checkbox:disabled {
            cursor: not-allowed;
            opacity: 0.45;
        }

        #btnApproveBatch:disabled,
        #confirmApproveBatch:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .cpe-manager {
            display: grid;
            grid-template-columns: minmax(320px, 26%) minmax(0, 1fr);
        }

        .cpe-manager__left {
            min-height: 100%;
            padding: 0 36px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #dfd7d2;
        }

        .cpe-manager__form-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 20px;
            padding-top: 260px;
            padding-bottom: 110px;
        }

        #gestorConversaCampos:not([hidden]) {
            display: grid;
            gap: 14px;
        }

        .cpe-manager-form {
            display: grid;
            gap: 14px;
        }

        .cpe-manager__right {
            min-height: 100%;
            background: #fbfbfb;
            display: flex;
            flex-direction: column;
            padding-top: 76px;
            padding-bottom: 56px;
            box-sizing: border-box;
        }

        .cpe-manager__header {
            min-height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 28px;
            border-bottom: 1px solid #ededed;
        }

        .cpe-manager__titleline {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cpe-manager__titleline h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .cpe-manager__titleline span {
            background: #f1f1f1;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 11px;
            color: #555;
        }

        #search_input::placeholder {
            color: #333;
            opacity: 1;
        }

        .cpe-municipio-select {
            position: relative;
            width: 100%;
        }

        .cpe-municipio-select__trigger {
            width: 100%;
            height: 40px;
            box-sizing: border-box;
            padding: 0 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            background: #fff;
            color: #111;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            cursor: pointer;
            text-align: left;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .cpe-municipio-select__trigger:hover,
        .cpe-municipio-select.is-open .cpe-municipio-select__trigger,
        .cpe-municipio-select__trigger:focus {
            border-color: var(--cpe-purple, #a900d9);
            box-shadow: 0 0 0 3px rgba(169, 0, 217, 0.12);
        }

        .cpe-municipio-select__label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .cpe-municipio-select__chevron {
            flex-shrink: 0;
            color: #555;
            transition: transform 0.15s ease;
        }

        .cpe-municipio-select.is-open .cpe-municipio-select__chevron {
            transform: rotate(180deg);
        }

        .cpe-municipio-select__panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            min-width: 250px;
            z-index: 120;
            display: none;
            flex-direction: column;
            background: #fff;
            border: 1px solid #d6d6d6;
            border-radius: 6px;
            box-shadow: 0 18px 38px rgba(0, 0, 0, 0.14);
            overflow: hidden;
        }

        .cpe-municipio-select.is-open .cpe-municipio-select__panel {
            display: flex;
        }

        .cpe-municipio-select__search-wrap {
            padding: 8px;
            border-bottom: 1px solid #ececec;
            background: #fafafa;
        }

        .cpe-municipio-select__search {
            width: 100%;
            height: 36px;
            box-sizing: border-box;
            padding: 0 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 13px;
            font-family: inherit;
            color: #111;
            background: #fff;
            outline: none;
        }

        .cpe-municipio-select__search::placeholder {
            color: #555;
            opacity: 1;
        }

        .cpe-municipio-select__search:focus {
            border-color: var(--cpe-purple, #a900d9);
            box-shadow: 0 0 0 2px rgba(169, 0, 217, 0.12);
        }

        .cpe-municipio-select__list {
            max-height: 240px;
            overflow-y: auto;
            margin: 0;
            padding: 4px;
            list-style: none;
        }

        .cpe-municipio-select__option {
            padding: 8px 10px;
            border-radius: 5px;
            font-size: 13px;
            color: #222;
            cursor: pointer;
        }

        .cpe-municipio-select__option[hidden] {
            display: none;
        }

        .cpe-municipio-select__option:hover,
        .cpe-municipio-select__option.is-active {
            background: var(--cpe-bg, #f4f0ec);
            color: var(--cpe-purple, #a900d9);
        }

        .cpe-municipio-select__option.is-selected {
            font-weight: 700;
            color: var(--cpe-purple, #a900d9);
        }

        .cpe-municipio-select__empty {
            padding: 10px;
            font-size: 12px;
            color: #777;
            text-align: center;
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

        .cpe-manager-table {
            border-radius: 0;
            box-shadow: none;
        }

        .cpe-manager-table .cpe-table th,
        .cpe-manager-table .cpe-table td {
            color: #0f0f0f;
        }

        .cpe-combobox__input::placeholder {
            color: #333;
            opacity: 1;
        }

        .cpe-icon-button,
        .cpe-trash-button {
            width: 28px;
            height: 28px;
            border: 0;
            background: transparent;
            color: #555;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            cursor: pointer;
            text-decoration: none;
        }

        .cpe-icon-button:hover,
        .cpe-icon-button:focus {
            color: var(--cartas-purple);
            outline: none;
        }

        .cpe-trash-button:hover,
        .cpe-trash-button:focus {
            color: #9f1d1d;
            outline: none;
        }

        .cpe-empty {
            text-align: center;
            padding: 42px !important;
        }

        .cpe-table-card.cpe-manager-table {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            box-shadow: none;
            border-bottom: 0;
        }

        .cpe-pagination {
            background: #fff;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .05);
            margin-top: 0;
        }

        .cpe-custom-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 16px 24px;
            border-top: 1px solid #eaecf0;
            gap: 12px;
        }

        .cpe-page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 14px;
            height: 38px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            background: #fff;
            color: #344054;
            font-size: 14px;
            font-weight: 600;
            line-height: 20px;
            text-decoration: none;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            cursor: pointer;
        }

        .cpe-page-btn:hover:not(.cpe-page-btn--disabled) {
            background: #f9fafb;
            border-color: #d0d5dd;
            color: #1d2939;
        }

        .cpe-page-btn--disabled {
            opacity: 0.5;
            cursor: default;
            pointer-events: none;
            background: #f9fafb;
            color: #98a2b3;
        }

        .cpe-page-numbers {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .cpe-page-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 8px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            line-height: 20px;
            color: #667085;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        a.cpe-page-num:hover {
            background: #f9fafb;
            color: #1d2939;
        }

        .cpe-page-num--active {
            background: #f4ebfa;
            color: #a800d6;
            font-weight: 700;
            cursor: default;
            pointer-events: none;
        }

        .cpe-page-num--dots {
            color: #667085;
            cursor: default;
            pointer-events: none;
            min-width: 38px;
            height: 38px;
        }

        @media (max-width: 640px) {
            .cpe-custom-pagination {
                flex-direction: column;
                gap: 16px;
            }

            .cpe-page-numbers {
                order: -1;
            }
        }

        @media (max-width: 980px) {
            .cpe-manager {
                grid-template-columns: 1fr;
            }

            .cpe-manager__left {
                min-height: auto;
                padding: 72px 24px 40px;
                border-right: 0;
            }

            .cpe-manager__right {
                min-height: auto;
                padding-top: 0;
            }

            .cpe-manager__form-wrap {
                padding-top: 0;
                padding-bottom: 0;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search_input');
            const filterForm = document.getElementById('filterForm');
            const municipioSelect = document.getElementById('municipio_id');
            const tableContainer = document.querySelector('.cpe-manager-table');
            const paginationContainer = document.querySelector('.cpe-pagination');
            const approvalForm = document.getElementById('approveBatchForm');
            const approveButton = document.getElementById('btnApproveBatch');
            const approvalModal = document.getElementById('approveBatchModal');
            const confirmApproval = document.getElementById('confirmApproveBatch');
            const approvalFeedback = document.getElementById('batchApprovalFeedback');
            let debounceTimer;
            let resultsRequest = 0;
            let resultsController;
            let loadingResults = false;
            let approving = false;

            function selectedResponses() {
                return Array.from(tableContainer.querySelectorAll('.carta-checkbox:checked:not(:disabled)'));
            }

            function updateSelectedBadge() {
                const eligible = tableContainer.querySelectorAll('.carta-checkbox:not(:disabled)');
                const count = selectedResponses().length;
                const selectAll = document.getElementById('selectAllApprovals');
                document.getElementById('selectedCountBadge').textContent =
                    count + (count === 1 ? ' resposta selecionada' : ' respostas selecionadas') + ' nesta página';
                approveButton.textContent = 'Aprovar selecionadas (' + count + ')';
                approveButton.disabled = approving || loadingResults || count === 0;
                confirmApproval.disabled = approving || loadingResults || count === 0;

                if (selectAll) {
                    selectAll.disabled = approving || loadingResults || eligible.length === 0;
                    selectAll.checked = eligible.length > 0 && count === eligible.length;
                    selectAll.indeterminate = count > 0 && count < eligible.length;
                }
            }

            document.addEventListener('change', function(e) {
                if (e.target.id === 'selectAllApprovals') {
                    tableContainer.querySelectorAll('.carta-checkbox:not(:disabled)').forEach(function(checkbox) {
                        checkbox.checked = e.target.checked;
                    });
                    updateSelectedBadge();
                } else if (e.target.classList.contains('carta-checkbox')) {
                    updateSelectedBadge();
                }
            });

            approveButton.addEventListener('click', function() {
                const count = selectedResponses().length;
                if (approving || loadingResults || count === 0) return;
                document.getElementById('approveBatchDescription').textContent = count === 1
                    ? 'Você está aprovando 1 resposta. A carta passará para “Respondida”. Deseja continuar?'
                    : 'Você está aprovando ' + count + ' respostas. As cartas passarão para “Respondida”. Deseja continuar?';
                approvalModal.classList.add('is-open');
                confirmApproval.focus();
            });

            approvalForm.addEventListener('submit', function(e) {
                if (approving || loadingResults || selectedResponses().length === 0
                    || !approvalModal.classList.contains('is-open')) {
                    e.preventDefault();
                    return;
                }

                approving = true;
                confirmApproval.textContent = 'Aprovando...';
                updateSelectedBadge();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && approvalModal.classList.contains('is-open') && !approving) {
                    approvalModal.classList.remove('is-open');
                    approveButton.focus();
                }
            });

            updateSelectedBadge();

            function fetchResults(url) {
                if (approving) return;
                const request = ++resultsRequest;
                resultsController?.abort();
                resultsController = new AbortController();
                loadingResults = true;
                approvalFeedback.hidden = true;
                approvalModal.classList.remove('is-open');
                tableContainer.querySelectorAll('.carta-checkbox').forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                tableContainer.inert = true;
                tableContainer.style.opacity = '0.5';
                updateSelectedBadge();

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: resultsController.signal
                })
                .then(response => {
                    if (!response.ok) throw new Error('Falha ao atualizar a listagem');
                    return response.text();
                })
                .then(html => {
                    if (request !== resultsRequest) return;
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const table = doc.querySelector('.cpe-manager-table');
                    const pagination = doc.querySelector('.cpe-pagination');
                    if (!table || !pagination) throw new Error('Listagem indisponível');

                    tableContainer.innerHTML = table.innerHTML;
                    paginationContainer.innerHTML = pagination.innerHTML;
                })
                .catch(error => {
                    if (error.name === 'AbortError' || request !== resultsRequest) return;
                    approvalFeedback.textContent = 'Não foi possível atualizar a lista. Tente novamente.';
                    approvalFeedback.hidden = false;
                })
                .finally(() => {
                    if (request !== resultsRequest) return;
                    loadingResults = false;
                    tableContainer.inert = false;
                    tableContainer.style.opacity = '1';
                    updateSelectedBadge();
                });
            }

            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const submitter = e.submitter;
                    // Ignora a submissão via botão de download de PDF
                    if (submitter && submitter.getAttribute('formaction')) {
                        return;
                    }

                    e.preventDefault();

                    const url = new URL(filterForm.action);
                    const formData = new FormData(filterForm);
                    const searchParams = new URLSearchParams(formData);

                    url.search = searchParams.toString();
                    fetchResults(url);

                    window.history.pushState({}, '', url);
                });
            }

            if (searchInput && filterForm) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);

                    debounceTimer = setTimeout(function() {
                        const val = searchInput.value.trim();
                        if (val === '' || val.length >= 2) {
                            filterForm.dispatchEvent(new Event('submit', { cancelable: true }));
                        }
                    }, 500);
                });
            }

            if (municipioSelect && filterForm) {
                municipioSelect.addEventListener('change', function() {
                    filterForm.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            }

            const municipioDropdown = document.getElementById('municipioSelectDropdown');
            const municipioTrigger = document.getElementById('municipioSelectTrigger');
            const municipioLabel = document.getElementById('municipioSelectLabel');
            const municipioSearchInput = document.getElementById('municipio_search_input');
            const municipioEmpty = document.getElementById('municipioSelectEmpty');

            if (municipioDropdown && municipioTrigger && municipioSelect && municipioSearchInput) {
                const normalizeText = function(val) {
                    return (val || '')
                        .toString()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase()
                        .trim();
                };

                const municipioOptions = Array.from(municipioDropdown.querySelectorAll('.cpe-municipio-select__option')).map(function(el) {
                    el.dataset.normalized = normalizeText(el.dataset.label);
                    return el;
                });

                function setActiveMunicipioOption(option) {
                    municipioOptions.forEach(function(item) {
                        item.classList.remove('is-active');
                    });
                    if (option) {
                        option.classList.add('is-active');
                        option.scrollIntoView({ block: 'nearest' });
                    }
                }

                function filterMunicipioOptions() {
                    const term = normalizeText(municipioSearchInput.value);
                    let visibleCount = 0;

                    municipioOptions.forEach(function(option) {
                        const match = term === '' || option.dataset.normalized.includes(term);
                        option.hidden = !match;
                        if (match) {
                            visibleCount++;
                        }
                    });

                    if (municipioEmpty) {
                        municipioEmpty.hidden = visibleCount > 0;
                    }
                }

                function openMunicipioDropdown() {
                    municipioDropdown.classList.add('is-open');
                    municipioTrigger.setAttribute('aria-expanded', 'true');
                    municipioSearchInput.value = '';
                    filterMunicipioOptions();
                    const selectedOption = municipioDropdown.querySelector('.cpe-municipio-select__option.is-selected');
                    setActiveMunicipioOption(selectedOption);
                    requestAnimationFrame(function() {
                        municipioSearchInput.focus();
                    });
                }

                function closeMunicipioDropdown() {
                    municipioDropdown.classList.remove('is-open');
                    municipioTrigger.setAttribute('aria-expanded', 'false');
                    setActiveMunicipioOption(null);
                }

                function selectMunicipioOption(option) {
                    const newValue = option.dataset.value || '';
                    const newLabel = option.dataset.label || 'Todos os municípios';
                    const changed = String(municipioSelect.value) !== String(newValue);

                    municipioSelect.value = newValue;
                    if (municipioLabel) {
                        municipioLabel.textContent = newLabel;
                    }

                    municipioOptions.forEach(function(item) {
                        const isSel = item === option;
                        item.classList.toggle('is-selected', isSel);
                        item.setAttribute('aria-selected', isSel ? 'true' : 'false');
                    });

                    closeMunicipioDropdown();

                    if (changed) {
                        municipioSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }

                municipioTrigger.addEventListener('click', function() {
                    if (municipioDropdown.classList.contains('is-open')) {
                        closeMunicipioDropdown();
                    } else {
                        openMunicipioDropdown();
                    }
                });

                municipioSearchInput.addEventListener('input', function() {
                    filterMunicipioOptions();
                    const firstVisible = municipioOptions.find(function(opt) { return !opt.hidden; });
                    setActiveMunicipioOption(firstVisible || null);
                });

                municipioSearchInput.addEventListener('keydown', function(e) {
                    const visibleOptions = municipioOptions.filter(function(opt) { return !opt.hidden; });
                    const current = municipioDropdown.querySelector('.cpe-municipio-select__option.is-active');
                    const index = visibleOptions.indexOf(current);

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setActiveMunicipioOption(visibleOptions[Math.min(index + 1, visibleOptions.length - 1)] || visibleOptions[0]);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setActiveMunicipioOption(visibleOptions[Math.max(index - 1, 0)]);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const target = current || visibleOptions[0];
                        if (target) {
                            selectMunicipioOption(target);
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeMunicipioDropdown();
                        municipioTrigger.focus();
                    }
                });

                municipioOptions.forEach(function(option) {
                    option.addEventListener('click', function() {
                        selectMunicipioOption(option);
                    });
                });

                document.addEventListener('click', function(e) {
                    if (!municipioDropdown.contains(e.target)) {
                        closeMunicipioDropdown();
                    }
                });
            }

            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter && filterForm) {
                statusFilter.addEventListener('change', function() {
                    filterForm.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            }

            document.addEventListener('click', function(e) {
                const paginationLink = e.target.closest('.cpe-pagination a');
                if (paginationLink) {
                    e.preventDefault();
                    const url = paginationLink.href;
                    fetchResults(url);
                    window.history.pushState({}, '', url);
                }
            });

            window.addEventListener('popstate', function() {
                fetchResults(window.location.href);
            });
            //logica do modal de confirmação do gestor
            const gestorSubmitBtn = document.getElementById('gestorCartaSubmitBtn');
            const gestorForm = document.getElementById('gestorCartaForm');
            const fluxoSelect = document.getElementById('gestorCartaFluxo');
            const conversaCampos = document.getElementById('gestorConversaCampos');
            const voluntarioSelect = document.getElementById('gestorVoluntario');
            function atualizarFluxoCarta() {
                const manual = fluxoSelect.value === 'manual';
                conversaCampos.hidden = !manual;
                conversaCampos.querySelectorAll('input, select').forEach(campo => campo.disabled = !manual);
                voluntarioSelect.required = manual;
            }
            fluxoSelect.addEventListener('change', atualizarFluxoCarta);
            atualizarFluxoCarta();
            const gestorConfirmModal = document.getElementById('gestorConfirmModal');
            const gestorConfirmOk = document.getElementById('gestorConfirmOk');
            const gestorConfirmCancel = document.querySelectorAll('[data-gestor-confirm-close]');

            if (gestorSubmitBtn && gestorForm && gestorConfirmModal && gestorConfirmOk) {
                let envioConfirmado = false;
                let envioEmAndamento = false;
                const submitLabel = gestorSubmitBtn.textContent;
                const confirmLabel = gestorConfirmOk.textContent;

                function fecharConfirmacao() {
                    if (!envioEmAndamento) {
                        gestorConfirmModal.classList.remove('is-open');
                    }
                }

                gestorForm.addEventListener('submit', function(event) {
                    if (envioEmAndamento) {
                        event.preventDefault();
                        return;
                    }

                    if (!envioConfirmado) {
                        event.preventDefault();
                        gestorConfirmModal.classList.add('is-open');
                        gestorConfirmOk.focus();
                        return;
                    }

                    envioEmAndamento = true;
                    gestorForm.setAttribute('aria-busy', 'true');
                    gestorSubmitBtn.disabled = true;
                    gestorConfirmOk.disabled = true;
                    gestorSubmitBtn.textContent = 'Enviando...';
                    gestorConfirmOk.textContent = 'Enviando...';
                    gestorConfirmCancel.forEach(function(button) {
                        button.disabled = true;
                    });
                });

                gestorSubmitBtn.addEventListener('click', function() {
                    if (!envioEmAndamento) {
                        gestorForm.requestSubmit();
                    }
                });

                gestorConfirmOk.addEventListener('click', function() {
                    if (envioEmAndamento || !gestorForm.reportValidity()) {
                        return;
                    }

                    envioConfirmado = true;
                    gestorForm.requestSubmit();
                    envioConfirmado = false;
                });

                gestorConfirmCancel.forEach(function(button) {
                    button.addEventListener('click', fecharConfirmacao);
                });
                gestorConfirmModal.querySelector('.cpe-modal__backdrop').addEventListener('click', fecharConfirmacao);

                window.addEventListener('pageshow', function(event) {
                    if (!event.persisted) return;

                    envioEmAndamento = false;
                    envioConfirmado = false;
                    gestorForm.removeAttribute('aria-busy');
                    gestorSubmitBtn.disabled = false;
                    gestorConfirmOk.disabled = false;
                    gestorSubmitBtn.textContent = submitLabel;
                    gestorConfirmOk.textContent = confirmLabel;
                    gestorConfirmCancel.forEach(function(button) {
                        button.disabled = false;
                    });
                    fecharConfirmacao();
                });
            }
        });
    </script>

    <div class="cpe-modal" id="gestorConfirmModal">
        <div class="cpe-modal__backdrop"></div>
        <div class="cpe-modal__dialog">
            <h2>Confirmar envio</h2>
            <p>Você tem certeza que deseja enviar esta carta?</p>
            <div class="cpe-modal-actions">
                <button type="button" class="cpe-button cpe-button--ghost" data-gestor-confirm-close>Cancelar</button>
                <button type="button" class="cpe-button" id="gestorConfirmOk">Confirmar envio</button>
            </div>
        </div>
    </div>
@endsection
