<?php

namespace App\Http\Controllers;

use App\Imports\PresencasPreviewImport;
use App\Models\Atividade;
use App\Models\Inscricao;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\Presenca;
use App\Services\ParticipanteImportIdentityResolver;
use App\Services\PresencaImportValidator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PresencaImportController extends Controller
{
    public function import(Atividade $atividade)
    {
        $evento = $atividade->evento;

        // $this->authorize('update', $evento); // opcional, remova se não usa policy
        return view('presencas.import', compact('evento', 'atividade'));
    }

    public function cadastro(Request $request, Atividade $atividade, PresencaImportValidator $validator)
    {
        $evento = $atividade->evento;
        // $this->authorize('update', $evento);

        $request->validate([
            'your_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $import = new PresencasPreviewImport;
        Excel::import($import, $request->file('your_file'));

        $rows = $validator->validate($import->rows->values()->all());
        if ($rows === []) {
            return back()->withErrors(['your_file' => 'O arquivo não contém registros para importar.']);
        }

        $sessionKey = "presenca_import_preview_atividade_{$atividade->id}_".Str::uuid();
        session([$sessionKey => [
            'atividade_id' => $atividade->id,
            'user_id' => $request->user()->id,
            'rows' => $rows,
        ]]);

        return redirect()->route('atividades.presencas.preview', [
            'atividade' => $atividade,
            'session_key' => $sessionKey,
        ]);
    }

    public function preview(Request $request, Atividade $atividade)
    {
        $evento = $atividade->evento;
        // $this->authorize('update', $evento);

        $request->validate(['session_key' => 'required|string|max:150']);
        $sessionKey = $request->query('session_key');
        $preview = $this->previewData($request, $atividade, $sessionKey);
        $allRows = collect($preview['rows'] ?? []);

        if ($allRows->isEmpty()) {
            return redirect()->route('atividades.presencas.import', $atividade)
                ->withErrors(['your_file' => 'Sessão vazia/expirada. Envie o arquivo novamente.']);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $page = (int) max(1, $request->query('page', 1));
        $total = $allRows->count();

        $slice = $allRows->slice(($page - 1) * $perPage, $perPage)->values();

        $rowsPaginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => route('atividades.presencas.preview', $atividade),
                'query' => ['session_key' => $sessionKey, 'per_page' => $perPage],
            ]
        );

        $globalOffset = ($page - 1) * $perPage;

        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $organizacoes = config('engaja.organizacoes', []);
        $participanteTags = config('engaja.participante_tags', Participante::TAGS);

        return view('presencas.preview', [
            'evento' => $evento,
            'atividade' => $atividade,
            'rows' => $rowsPaginator,
            'globalOffset' => $globalOffset,
            'sessionKey' => $sessionKey,
            'municipios' => $municipios,
            'organizacoes' => $organizacoes,
            'participanteTags' => $participanteTags,
        ]);
    }

    public function savePage(Request $request, Atividade $atividade, PresencaImportValidator $validator)
    {
        $data = $request->validate([
            'session_key' => 'required|string|max:150',
            'rows' => 'required|array',
            'rows.*' => 'array:nome,email,cpf,telefone,municipio_id,tipo_organizacao,escola_unidade,tag,data_nascimento,sexo,status,justificativa,data_entrada',
            'rows.*.nome' => 'sometimes|nullable|string|max:255',
            'rows.*.email' => 'sometimes|nullable|string|max:255',
            'rows.*.cpf' => 'sometimes|nullable|string|max:255',
            'rows.*.telefone' => 'sometimes|nullable|string|max:255',
            'rows.*.municipio_id' => 'sometimes|nullable|integer',
            'rows.*.tipo_organizacao' => 'sometimes|nullable|string|max:255',
            'rows.*.escola_unidade' => 'sometimes|nullable|string|max:255',
            'rows.*.tag' => 'sometimes|nullable|string|max:255',
            'rows.*.data_nascimento' => 'sometimes|nullable|string|max:255',
            'rows.*.sexo' => 'sometimes|nullable|string|max:255',
            'rows.*.status' => 'sometimes|nullable|in:presente,ausente,justificado',
            'rows.*.justificativa' => 'sometimes|nullable|string',
            'rows.*.data_entrada' => 'sometimes|nullable|date_format:Y-m-d',
        ]);

        $sessionKey = $data['session_key'];
        $preview = $this->previewData($request, $atividade, $sessionKey);
        $allRows = $preview['rows'] ?? [];

        if ($allRows === []) {
            return back()->withErrors(['rows' => 'Sessão expirada. Reenvie o arquivo.']);
        }

        foreach ($data['rows'] as $globalIndex => $row) {
            if (! ctype_digit((string) $globalIndex) || ! array_key_exists($globalIndex, $allRows)) {
                throw ValidationException::withMessages(['rows' => 'A linha editada não pertence a esta importação.']);
            }
            $allRows[$globalIndex] = array_merge($allRows[$globalIndex], $row);
        }

        $preview['rows'] = $validator->validate($allRows);
        session([$sessionKey => $preview]);

        return back()->with('success', 'Alterações desta página salvas.');
    }

    public function confirmar(Request $request, Atividade $atividade, PresencaImportValidator $validator, ParticipanteImportIdentityResolver $resolver)
    {
        $evento = $atividade->evento;
        // $this->authorize('update', $evento);

        $request->validate([
            'session_key' => 'required|string|max:150',
        ]);

        $sessionKey = $request->input('session_key');
        $preview = $this->previewData($request, $atividade, $sessionKey);
        $rows = collect($preview['rows'] ?? []);

        if ($rows->isEmpty()) {
            return back()->withErrors(['rows' => 'Sessão vazia/expirada. Reenvie o arquivo.']);
        }

        $rows = $validator->validate($rows->all(), requireStatus: true);

        DB::transaction(function () use ($rows, $evento, $atividade, $resolver) {
            $resolver->lock();

            $resolver->prepare($rows);
            $munCache = Municipio::pluck('id', 'nome')
                ->mapWithKeys(fn ($id, $nome) => [mb_strtolower(trim($nome)) => $id])
                ->toArray();

            $tagOptions = config('engaja.participante_tags', Participante::TAGS);
            $tagLookup = array_fill_keys($tagOptions, true);

            foreach ($rows as $row) {
                $cpf = $row['cpf'];
                $tel = $row['telefone'];

                // tipo da organização pode vir da nova coluna ou da antiga "organizacao"
                $tipoOrganizacao = $row['tipo_organizacao'] ?? $row['organizacao'] ?? $row['escola_unidade'] ?? null;
                $organizacaoLivre = $row['escola_unidade'] ?? $row['organizacao_nome'] ?? null;
                if ($organizacaoLivre === null && ! isset($row['tipo_organizacao'])) {
                    $organizacaoLivre = $row['organizacao'] ?? null;
                }

                $munId = null;
                if (! empty($row['municipio'])) {
                    $key = mb_strtolower(trim($row['municipio']));
                    $munId = $munCache[$key] ?? null;
                }

                // 1) Resolve o perfil por e-mail ou, na ausência dele, por CPF.
                $participante = $resolver->resolve($row);

                // 2) Participante
                $tag = isset($row['tag']) ? trim((string) $row['tag']) : null;
                if ($tag === '') {
                    $tag = null;
                } elseif (! isset($tagLookup[$tag])) {
                    $tag = null;
                }

                $dados = [
                    'municipio_id' => $munId,
                    'cpf' => $cpf,
                    'telefone' => $tel,
                    'escola_unidade' => $organizacaoLivre,
                    'tipo_organizacao' => $tipoOrganizacao,
                    'tag' => $tag,
                    'data_nascimento' => $row['data_nascimento'],
                    'sexo' => $row['sexo'],
                    'data_entrada' => $row['data_entrada'] ?? null,
                ];

                // Campos opcionais vazios não apagam informações já cadastradas.
                $participante->fill(array_filter($dados, fn ($value) => filled($value)));
                if ($participante->isDirty()) {
                    $participante->save();
                }

                $resolver->remember($participante);

                // 3) Inscrição no momento
                $inscricao = Inscricao::withTrashed()
                    ->where('participante_id', $participante->id)
                    ->where('atividade_id', $atividade->id)
                    ->first();

                if (! $inscricao) {
                    $inscricao = Inscricao::withTrashed()
                        ->where('participante_id', $participante->id)
                        ->where('evento_id', $evento->id)
                        ->whereNull('atividade_id')
                        ->first();
                }

                if ($inscricao) {
                    $inscricao->fill([
                        'evento_id' => $evento->id,
                        'atividade_id' => $atividade->id,
                        'participante_id' => $participante->id,
                        'ouvinte' => false,
                    ]);
                    $inscricao->deleted_at = null;
                    $inscricao->save();
                } else {
                    $inscricao = Inscricao::create([
                        'evento_id' => $evento->id,
                        'atividade_id' => $atividade->id,
                        'participante_id' => $participante->id,
                        'ouvinte' => false,
                    ]);
                }
                // 4) Presenca
                $status = $row['status'] ?? null;
                $just = $row['justificativa'] ?? null;

                Presenca::updateOrCreate(
                    ['inscricao_id' => $inscricao->id, 'atividade_id' => $atividade->id],
                    ['status' => $status, 'justificativa' => $just]
                );
            }
        });

        session()->forget($sessionKey);

        return redirect()->route('eventos.show', $evento)
            ->with('success', 'Presenças importadas/atualizadas para a atividade com sucesso!');
    }

    private function previewData(Request $request, Atividade $atividade, string $sessionKey): array
    {
        if (! Str::startsWith($sessionKey, "presenca_import_preview_atividade_{$atividade->id}_")) {
            return [];
        }

        $preview = session($sessionKey, []);
        if (! is_array($preview)
            || ($preview['atividade_id'] ?? null) !== $atividade->id
            || ($preview['user_id'] ?? null) !== $request->user()->id
            || ! is_array($preview['rows'] ?? null)) {
            return [];
        }

        return $preview;
    }
}
