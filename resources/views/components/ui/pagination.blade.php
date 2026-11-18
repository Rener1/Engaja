{{--
    Bloco de paginação padrão: contador de resultados + páginas.
    Uso: <x-ui.pagination :paginator="$users" />
         <x-ui.pagination :paginator="$rows->appends(['session_key' => $key])" />
         <x-ui.pagination :paginator="$x" stacked />  (contador acima, páginas abaixo, centralizado — colunas estreitas)
    Não renderiza nada quando há uma única página.
--}}
@props(['paginator', 'stacked' => false])

@if ($paginator->hasPages())
    <div {{ $attributes->class(['eg-pagination-wrap', 'eg-pagination-wrap--stacked' => $stacked]) }}>
        {{ $paginator->links() }}
    </div>
@endif
