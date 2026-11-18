{{--
    Moldura de tabela HTML (<table>) com a paginação do servidor como rodapé —
    mesmo visual do <x-data-table :paginator="..."> e do painel de paginação do AG Grid.
    Uso: <x-ui.table-frame :paginator="$rows"> <div class="table-responsive">…</div> </x-ui.table-frame>
    Props: paginator (opcional), bordered (padrão true; false quando já está dentro de um .card).
--}}
@props(['paginator' => null, 'bordered' => true])

<div {{ $attributes->class(['dt-frame', 'dt-frame--bordered' => $bordered]) }}>
    {{ $slot }}
    @if ($paginator && $paginator->hasPages())
        <div class="dt-footer">
            <x-ui.pagination :paginator="$paginator" />
        </div>
    @endif
</div>
