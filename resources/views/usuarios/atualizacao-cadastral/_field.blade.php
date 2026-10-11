@php
    $value = (string) old("rows.$index.$field", $row[$field] ?? '');
    if (in_array($field, ['data_nascimento'], true)) {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date && $date->format('Y-m-d') === $value) {
            $value = $date->format('d/m/Y');
        }
    }
    $definition = config("engaja.demograficos.$field", []);
    $other = $definition['campo_outro'] ?? null;
    $options = $selectOptions[$field] ?? null;
@endphp
<div class="w-100">
    @if($field === 'municipio')
        @php
            $state = (string) old("rows.$index.estado", $row['estado'] ?? '');
            $normalize = fn ($text) => mb_strtolower(trim(\Illuminate\Support\Str::ascii($text ?? '')));
            $matches = $municipios->filter(fn ($m) => $normalize($m->nome) === $normalize($value) && ($state === '' || in_array($normalize($state), [$normalize($m->estado?->sigla), $normalize($m->estado?->nome)], true)));
            $selectedId = $matches->count() === 1 ? $matches->first()->id : null;
        @endphp
        <select class="form-select form-select-sm" name="rows[{{ $index }}][{{ $field }}]" form="editar-cadastro" aria-label="{{ $label }} da linha {{ $row['linha_original'] }}" data-municipio="{{ $index }}">
            <option value="" data-uf="">Selecione...</option>
            @if($value !== '' && $selectedId === null)
                <option value="{{ $value }}" selected>{{ $value }} — {{ $state }} (valor da planilha; confira)</option>
            @endif
            @if($selectedId !== null)
                @php
                    $municipio = $matches->first();
                @endphp
                <option value="{{ $municipio->nome }}" data-uf="{{ $municipio->estado?->sigla }}" data-id="{{ $municipio->id }}" selected>{{ $municipio->nome_com_estado }}</option>
            @endif
        </select>
    @elseif($options !== null)
        <select class="form-select form-select-sm" name="rows[{{ $index }}][{{ $field }}]" form="editar-cadastro" aria-label="{{ $label }} da linha {{ $row['linha_original'] }}" @if($other) data-outro-valor="{{ $definition['valor_outro'] }}" @endif>
            <option value="">Selecione...</option>
            @if($value !== '' && !in_array($value, $options, true))
                <option value="{{ $value }}" selected>{{ $value }} (valor da planilha; confira)</option>
            @endif
            @foreach($options as $option)
                <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @if($other)
            <input data-outro-input @if($value !== $definition['valor_outro'] && !filled($row[$other] ?? null)) hidden @endif class="form-control form-control-sm mt-1" name="rows[{{ $index }}][{{ $other }}]" value="{{ old("rows.$index.$other", $row[$other] ?? '') }}" placeholder="Especifique..." aria-label="Especifique {{ $label }} da linha {{ $row['linha_original'] }}" maxlength="255" form="editar-cadastro">
        @endif
    @else
        <input class="form-control form-control-sm" aria-label="{{ $label }} da linha {{ $row['linha_original'] }}" name="rows[{{ $index }}][{{ $field }}]" value="{{ $value }}" @if(in_array($field, ['data_nascimento'], true)) data-date-mask inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10" @else maxlength="255" @endif form="editar-cadastro">
    @endif
</div>
