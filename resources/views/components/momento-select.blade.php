@props([
    'momentos' => [],
    'name' => 'atividade_id',
    'id' => null,
    'label' => 'Momento',
    'value' => null,
    'placeholder' => 'Selecione...',
    'required' => false,
    'disabled' => false,
])

@php
    $inputId = $id ?? 'momento-'.\Illuminate\Support\Str::uuid();
    $selectedValue = old($name, $value);
    $selectedValue = is_scalar($selectedValue) ? (string) $selectedValue : '';
    $errorMessage = $errors->first($name);
@endphp

<div class="momento-select" data-momento-select>
    <label id="{{ $inputId }}-label" for="{{ $inputId }}" class="form-label" data-momento-label>{{ $label }}</label>
    <select
        {{ $attributes->class(['form-select', 'momento-select__native', 'is-invalid' => filled($errorMessage)]) }}
        id="{{ $inputId }}"
        name="{{ $name }}"
        data-momento-native
        @required($required)
        @disabled($disabled)
        @if($errorMessage) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
    >
        <option value="">{{ $placeholder }}</option>
        @foreach($momentos as $momento)
            @php
                $dataMomento = $momento->dia ? \Carbon\Carbon::parse($momento->dia) : null;
            @endphp
            <option value="{{ $momento->id }}" data-date="{{ $dataMomento?->format('Y-m-d') }}"
                @selected($selectedValue === (string) $momento->id)>{{ $momento->descricao }}@if($dataMomento) ({{ $dataMomento->format('d/m/Y') }})@endif - {{ $momento->evento->nome ?? 'Sem evento' }}</option>
        @endforeach
    </select>

    <div class="form-select momento-select__loading text-muted" role="status" aria-busy="true"
        data-momento-loading>Carregando momentos...</div>

    <div data-momento-ui hidden>
        <button type="button" id="{{ $inputId }}-trigger" class="form-select momento-select__trigger"
            aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $inputId }}-panel"
            aria-labelledby="{{ $inputId }}-label {{ $inputId }}-selected"
            data-momento-trigger @disabled($disabled)>
            <span id="{{ $inputId }}-selected" data-momento-selected>{{ $placeholder }}</span>
        </button>

        <div id="{{ $inputId }}-panel" class="momento-select__panel" role="dialog"
            aria-labelledby="{{ $inputId }}-label" data-momento-panel hidden>
            <div class="momento-select__filter">
                <label for="{{ $inputId }}-date" class="form-label small mb-1">Data do momento</label>
                <div class="d-flex gap-2">
                    <input type="date" form="" id="{{ $inputId }}-date" class="form-control" autocomplete="off" data-momento-date>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-momento-clear-date>Limpar</button>
                </div>
            </div>
            <ul id="{{ $inputId }}-list" class="momento-select__list" role="listbox" tabindex="0"
                aria-label="Momentos disponíveis" data-momento-list></ul>
            <p class="momento-select__status small text-muted mb-0" role="status" aria-live="polite"
                aria-atomic="true" data-momento-status></p>
        </div>
    </div>

    <div id="{{ $inputId }}-error" class="invalid-feedback" data-momento-error
        @if(!$errorMessage) hidden @endif>{{ $errorMessage }}</div>
</div>
