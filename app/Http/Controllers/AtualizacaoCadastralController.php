<?php

namespace App\Http\Controllers;

use App\Imports\AtualizacaoCadastralImport;
use App\Models\Municipio;
use App\Models\Participante;
use App\Services\AtualizacaoCadastralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AtualizacaoCadastralController extends Controller
{
    public function index(): View
    {
        return view('usuarios.atualizacao-cadastral.import', ['columns' => AtualizacaoCadastralService::columns()]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Dados cadastrais');
            $sheet->fromArray(array_keys(AtualizacaoCadastralService::columns()), null, 'A1');
            $sheet->freezePane('A2');
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            $sheet->getStyle('A2:'.$sheet->getHighestColumn().'5001')->getNumberFormat()->setFormatCode('@');
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'modelo_atualizacao_cadastral.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function upload(Request $request, AtualizacaoCadastralImport $import, AtualizacaoCadastralService $service): RedirectResponse
    {
        $request->validate(['your_file' => 'required|file|mimes:xlsx,xls,csv|max:20480']);
        try {
            $rows = $import->read($request->file('your_file')->getRealPath());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['your_file' => 'Não foi possível ler a planilha. Confira o formato e tente novamente.']);
        }
        $key = 'atualizacao_cadastral_'.Str::uuid();
        session([$key => ['user_id' => $request->user()->id, 'rows' => $service->normalize($rows)]]);

        return redirect()->route('usuarios.atualizacao-cadastral.preview', ['session_key' => $key]);
    }

    public function preview(Request $request, AtualizacaoCadastralService $service): View
    {
        [$key, $payload] = $this->payload($request);
        $analysis = $service->analyze($payload['rows']);
        $perPage = max(1, min(100, $request->integer('per_page', 50)));
        $page = max(1, $request->integer('page', 1));
        $rows = new LengthAwarePaginator(array_slice($analysis['rows'], ($page - 1) * $perPage, $perPage, true), count($analysis['rows']), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('usuarios.atualizacao-cadastral.preview', [
            'rows' => $rows, 'analysis' => $analysis, 'sessionKey' => $key,
            'columns' => $service::columns(),
            'municipios' => Municipio::with('estado')->orderBy('nome')->get(),
            'selectOptions' => [
                'sexo' => Participante::SEXOS,
                'tipo_organizacao' => config('engaja.organizacoes', []),
                'tag' => config('engaja.participante_tags', Participante::TAGS),
            ] + collect(config('engaja.demograficos', []))->map(fn ($definition) => $definition['opcoes'])->all(),
        ]);
    }

    public function savePage(Request $request, AtualizacaoCadastralService $service): RedirectResponse
    {
        [$key, $payload] = $this->payload($request);
        $fields = array_keys($service::columns());
        $rules = ['rows' => 'required|array', 'rows.*' => 'array:'.implode(',', $fields)];
        foreach ($fields as $field) {
            $rules['rows.*.'.$field] = 'nullable|string|max:255';
        }
        $data = $request->validate($rules);
        foreach ($data['rows'] as $index => $row) {
            if (! ctype_digit((string) $index) || ! array_key_exists($index, $payload['rows'])) {
                throw ValidationException::withMessages(['rows' => 'Linha inválida nesta prévia.']);
            }
            $payload['rows'][$index] = array_merge($payload['rows'][$index], $row);
        }
        $payload['rows'] = $service->normalize($payload['rows']);
        session([$key => $payload]);

        return redirect()->route('usuarios.atualizacao-cadastral.preview', [
            'session_key' => $key, 'page' => max(1, $request->integer('page', 1)),
            'per_page' => max(1, min(100, $request->integer('per_page', 50))),
        ])->withFragment('pendencias-cadastrais')->with('success', 'Alterações da página salvas. Confira abaixo as pendências de todo o arquivo antes de confirmar.');
    }

    public function confirm(Request $request, AtualizacaoCadastralService $service): RedirectResponse
    {
        [$key, $payload] = $this->payload($request);
        $result = $service->apply($payload['rows']);
        session()->forget($key);

        return redirect()->route('usuarios.atualizacao-cadastral.index')->with('success',
            $result['updated'].' pessoa(s) com dados atualizados e '.$result['created'].' nova(s) cadastrada(s). '
            .$result['updatedRecords'].' cadastro(s) existente(s) alterado(s); '.$result['unchanged'].' pessoa(s) sem alterações.');
    }

    private function payload(Request $request): array
    {
        $request->validate(['session_key' => 'required|string|max:100']);
        $key = $request->string('session_key')->toString();
        $payload = str_starts_with($key, 'atualizacao_cadastral_') ? session($key) : null;
        if (! is_array($payload) || ($payload['user_id'] ?? null) !== $request->user()->id || empty($payload['rows'])) {
            throw ValidationException::withMessages(['session_key' => 'Prévia inválida ou expirada. Envie a planilha novamente.']);
        }

        return [$key, $payload];
    }
}
