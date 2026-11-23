@php
    $situacao = \App\Enums\SituacaoAutorizacaoImagem::daResposta($resposta);
    [$cor, $icone] = match ($situacao) {
        \App\Enums\SituacaoAutorizacaoImagem::Autorizada => ['autorizada', 'check-circle-fill'],
        \App\Enums\SituacaoAutorizacaoImagem::Negada => ['negada', 'x-circle-fill'],
        \App\Enums\SituacaoAutorizacaoImagem::Pendente => ['pendente', 'hourglass-split'],
    };
@endphp
<span class="eg-situacao-badge eg-situacao-badge--{{ $cor }}"><i class="bi bi-{{ $icone }}"></i>{{ $situacao->rotulo() }}</span>
