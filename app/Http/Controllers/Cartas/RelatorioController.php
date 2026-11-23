<?php

namespace App\Http\Controllers\Cartas;

use App\Http\Controllers\Controller;
use App\Services\Cartas\RelatorioCartasService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RelatorioController extends Controller
{
    public function index(Request $request, RelatorioCartasService $relatorios): Response
    {
        $paginas = $request->validate([
            'pendentes_page' => ['sometimes', 'integer', 'min:1'],
            'sem_cartas_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->view('cartas.relatorios.index', [
            ...$relatorios->resumo(),
            'pendentes' => $relatorios->voluntariosComPendencias((int) ($paginas['pendentes_page'] ?? 1))->appends($paginas),
            'semCartas' => $relatorios->voluntariosSemCartas((int) ($paginas['sem_cartas_page'] ?? 1))->appends($paginas),
            'geradoEm' => now(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
