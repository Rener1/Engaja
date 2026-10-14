<?php

namespace App\Imports;

use App\Models\Municipio;
use App\Models\Participante;
use App\Services\ParticipanteImportValidator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PresencasPreviewImport implements OnEachRow, SkipsEmptyRows, WithHeadingRow
{
    public Collection $rows;

    /** @var array<string,int> nome (lower) => municipio_id */
    protected array $municipiosCache = [];

    /** @var array<int,string> lista can├┤nica */
    protected array $tiposOrganizacao = [];

    /** @var array<string,string> mapa lower => can├┤nico */
    protected array $tiposOrganizacaoMap = [];

    /** @var array<int,string> */
    protected array $tags = [];

    /** @var array<string,string> */
    protected array $tagsMap = [];

    public function __construct()
    {
        $this->rows = collect();

        $this->municipiosCache = Municipio::with('estado')
            ->get(['id', 'nome', 'estado_id'])
            ->mapWithKeys(function ($m) {
                return [mb_strtolower(trim($m->nome)) => (int) $m->id];
            })
            ->toArray();
        $this->tiposOrganizacao = config('engaja.organizacoes', []);
        $this->tiposOrganizacaoMap = collect($this->tiposOrganizacao)
            ->mapWithKeys(function ($o) {
                return [mb_strtolower($this->slugify($o)) => $o];
            })->toArray();

        $this->tags = config('engaja.participante_tags', Participante::TAGS);
        $this->tagsMap = collect($this->tags)
            ->mapWithKeys(fn ($tag) => [mb_strtolower($this->slugify($tag)) => $tag])
            ->toArray();
    }

    private function slugify(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim($s);
    }

    private function normalizeTipoOrganizacao(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $key = $this->slugify($raw);

        return $this->tiposOrganizacaoMap[$key] ?? null;
    }

    private function normalizeTag(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $key = $this->slugify($raw);

        return $this->tagsMap[$key] ?? null;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $source): void
    {
        $sheet = $source->getDelegate()->getWorksheet();
        if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE
            || mb_strtolower(trim($sheet->getTitle())) === '_valid') {
            return;
        }

        $row = $source->toArray(null, false, false);

        // Os mesmos identificadores são normalizados na edição e confirmação.
        $identity = ParticipanteImportValidator::normalize($row);
        $nome = $identity['nome'];
        $email = $identity['email'];
        $cpf = $identity['cpf'];
        $telefone = $identity['telefone'];

        $munNome = trim((string) ($row['municipio'] ?? ''));
        $tagRaw = trim((string) ($row['tag'] ?? ''));
        $status = $this->mapStatus((string) ($row['status'] ?? ''));
        $justif = trim((string) ($row['justificativa'] ?? '')) ?: null;
        $entrada = $this->parseDate($row['data_entrada'] ?? null);

        $tipoColumnExists = false;
        $tipoRaw = $this->firstValue($row, [
            'tipo_de_organizacao',
            'tipo_organizacao',
            'tipo-da-organizacao',
            'tipo_da_organizacao',
            'tipoorganizacao',
        ], $tipoColumnExists);
        if (! $tipoColumnExists) {
            $tipoRaw = $tipoRaw ?? $this->firstValue($row, ['organizacao', 'escola_unidade']);
        }
        $tipoRaw = $tipoRaw ?? '';

        $organizacaoLivre = $this->firstValue(
            $row,
            $tipoColumnExists
                ? ['organizacao', 'organizacao_nome', 'nome_da_organizacao', 'organizacao_livre', 'escola_unidade']
                : ['escola_unidade', 'organizacao']
        ) ?? '';

        // Resolve municipio_id (se nome existir)
        $municipioId = null;
        if ($munNome !== '') {
            $key = mb_strtolower($munNome);
            $municipioId = $this->municipiosCache[$key] ?? null;
        }

        $tipoCanon = $this->normalizeTipoOrganizacao($tipoRaw);
        $tipoOk = $tipoRaw === '' ? true : ($tipoCanon !== null);
        $tipoOut = $tipoCanon ?? $tipoRaw;

        $tagCanon = $this->normalizeTag($tagRaw);
        $tagOk = $tagRaw === '' ? true : ($tagCanon !== null);
        $tagOut = $tagCanon ?? $tagRaw;

        $this->rows->push([
            'linha_original' => $source->getIndex(),
            'aba_original' => $sheet->getTitle(),
            'nome' => $nome,
            'email' => $email,
            'cpf' => $cpf,
            'telefone' => $telefone,
            'municipio' => $munNome,
            'municipio_id' => $municipioId,
            'tipo_organizacao' => $tipoOut,
            'tipo_organizacao_ok' => $tipoOk,
            'escola_unidade' => $organizacaoLivre,
            'tag' => $tagOut,
            'tag_ok' => $tagOk,
            'data_nascimento' => $this->firstValue($row, ['data_nascimento', 'data_de_nascimento', 'nascimento']) ?? '',
            'sexo' => $this->firstValue($row, ['sexo']),
            'status' => $status,
            'justificativa' => $justif,
            'data_entrada' => $entrada,
        ]);
    }

    private function firstValue(array $row, array $keys, ?bool &$foundKey = null): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                if ($foundKey !== null) {
                    $foundKey = true;
                }
                $value = $row[$key];
                if ($value === null) {
                    return null;
                }
                if (is_string($value)) {
                    return trim($value);
                }
                if (is_scalar($value)) {
                    return trim((string) $value);
                }

                return null;
            }
        }

        if ($foundKey !== null) {
            $foundKey = false;
        }

        return null;
    }

    private function mapStatus(string $raw): ?string
    {
        $s = mb_strtolower(trim($raw));

        return match ($s) {
            'presente', 'p', 'present' => 'presente',
            'ausente', 'a', 'absent' => 'ausente',
            'justificado', 'j', 'justify' => 'justificado',
            default => null,
        };
    }

    private function parseDate($v): ?string
    {
        if (is_string($v)) {
            $v = trim($v);
        }
        if (! $v) {
            return null;
        }
        try {
            if (is_numeric($v)) {
                return Date::excelToDateTimeObject($v)->format('Y-m-d');
            }

            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
