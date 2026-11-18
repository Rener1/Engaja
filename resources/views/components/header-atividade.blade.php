{{--
    Cabeçalho de um momento (Atividade): título, dia/horário, município(s), duração, local etc.

    Props:
      - atividade: a Atividade exibida
      - hero:      renderiza dentro da barra roxa (<x-ui.page-hero>) em vez do bloco simples

    Slots (modo hero): default = conteúdo extra abaixo dos metadados (ex.: badge de status);
                       actions = botões à direita.
--}}
@props(['atividade', 'hero' => false])

@php
        $dia = \Carbon\Carbon::parse($atividade->dia)
            ->locale('pt_BR')
            ->translatedFormat('l, d \\d\\e F \\d\\e Y');

        $inicio = \Carbon\Carbon::parse($atividade->dia . ' ' . $atividade->hora_inicio);
        $fim = $atividade->hora_fim ? \Carbon\Carbon::parse($atividade->dia . ' ' . $atividade->hora_fim) : null;

        if ($fim && $fim->lessThanOrEqualTo($inicio)) {
            $fim->addDay();
        }

        $duracaoLabel = null;
        if ($fim) {
            $mins = $inicio->diffInMinutes($fim, false);
            if ($mins < 0) {
                $mins += 24 * 60;
            }
            $h = intdiv($mins, 60);
            $m = $mins % 60;
            $duracaoLabel = $h > 0 ? $h . 'h' . ($m ? ' ' . $m . 'min' : '') : $m . 'min';
        }

        $presencaPage = request()?->routeIs('presenca.confirmar');
        $munLabel = $atividade->municipios && $atividade->municipios->isNotEmpty()
            ? $atividade->municipios->map(fn($m) => $m->nome_com_estado ?? $m->nome)->join(', ')
            : null;
@endphp

@if ($hero)
    <x-ui.page-hero eyebrow="Momento" eyebrow-icon="calendar3"
        :title="$atividade->descricao ?? 'Momento'" {{ $attributes }}>

        <p class="text-muted mb-1">
            {{ $dia }}@unless($presencaPage) &bull; {{ $inicio->format('H:i') }}@if($fim) a {{ $fim->format('H:i') }}@endif @endunless
        </p>

        @if ($munLabel)
            <p class="text-muted mb-1">Município(s): {{ $munLabel }}</p>
        @endif

        @unless($presencaPage)
            @if ($duracaoLabel)
                <p class="text-muted mb-1">Duração: {{ $duracaoLabel }}</p>
            @endif

            @if ($atividade->local)
                <p class="text-muted mb-1">Local: {{ $atividade->local }}</p>
            @endif
        @endunless

        @if(!$presencaPage && !is_null($atividade->publico_esperado))
            <p class="text-muted mb-1">
                Público esperado: {{ number_format($atividade->publico_esperado, 0, ',', '.') }}
            </p>
        @endif

        @if(!$presencaPage && !is_null($atividade->carga_horaria))
            <p class="text-muted mb-1">Carga horária: {{ \App\Support\CargaHoraria::formatMinutos((int) $atividade->carga_horaria) }}</p>
        @endif

        {{ $slot }}
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
    </x-ui.page-hero>
@else
    <div {{ $attributes->merge(['class' => '']) }}>
        <h1 class="h4 fw-bold text-engaja mb-1">{{ $atividade->descricao ?? 'Momento' }}</h1>

        <p class="text-muted mb-1">
            {{ $dia }}@unless($presencaPage) &bull; {{ $inicio->format('H:i') }}@if($fim) a {{ $fim->format('H:i') }}@endif @endunless
        </p>

        @if ($munLabel)
            <p class="text-muted mb-1">Município(s): {{ $munLabel }}</p>
        @endif

        @unless($presencaPage)
            @if ($duracaoLabel)
                <p class="text-muted mb-1">Duração: {{ $duracaoLabel }}</p>
            @endif

            @if ($atividade->local)
                <p class="text-muted mb-1">Local: {{ $atividade->local }}</p>
            @endif
        @endunless

        @if(!$presencaPage && !is_null($atividade->publico_esperado))
            <p class="text-muted mb-1">
                Público esperado: {{ number_format($atividade->publico_esperado, 0, ',', '.') }}
            </p>
        @endif

        @if(!$presencaPage && !is_null($atividade->carga_horaria))
            <p class="text-muted mb-1">Carga horária: {{ \App\Support\CargaHoraria::formatMinutos((int) $atividade->carga_horaria) }}</p>
        @endif

    </div>
@endif
