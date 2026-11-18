{{--
    Alternância em "pílula".

    Modo formulário: name + options (valor => rótulo) + value (+ inputId opcional p/ o hidden). Renderiza botões e um
    input hidden; o JS (resources/js/lib/segmented-control.js) atualiza o hidden,
    dispara "change" nele e, com autosubmit, submete o form.

    Modo links (abas): links (rótulo => url) + active (rótulo ativo).
--}}
@props([
    'name' => null,
    'options' => [],
    'value' => null,
    'autosubmit' => false,
    'links' => null,
    'active' => null,
    'auto' => false,
    'inputId' => null,
])

@if ($links !== null)
    <nav {{ $attributes->class(['eg-segmented', 'eg-segmented--auto' => $auto]) }}>
        @foreach ($links as $label => $href)
            <a href="{{ $href }}" @class(['eg-seg-btn', 'active' => $active === $label])
               @if ($active === $label) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
@else
    <div {{ $attributes->class(['eg-segmented', 'eg-segmented--auto' => $auto]) }}
         data-eg-segmented @if ($autosubmit) data-autosubmit @endif role="group">
        @foreach ($options as $optionValue => $optionLabel)
            <button type="button" @class(['eg-seg-btn', 'active' => (string) $value === (string) $optionValue])
                    data-value="{{ $optionValue }}"
                    aria-pressed="{{ (string) $value === (string) $optionValue ? 'true' : 'false' }}">
                {{ $optionLabel }}
            </button>
        @endforeach
        @if ($name)
            <input type="hidden" name="{{ $name }}" id="{{ $inputId ?? $name }}" value="{{ $value }}">
        @endif
    </div>
@endif
