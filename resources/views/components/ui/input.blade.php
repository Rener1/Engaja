{{-- Input no padrão visual dos filtros. Atributos são repassados ao <input>. --}}
@props(['type' => 'text'])

<input type="{{ $type }}" {{ $attributes->class(['eg-input']) }}>
