<?php

namespace App\Imports;

use App\Models\Municipio;
use App\Models\Participante;
use App\Services\DemograficoNormalizerService;
use App\Services\ParticipanteImportValidator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParticipantesPreviewImport implements OnEachRow, SkipsEmptyRows, WithHeadingRow, WithMultipleSheets
{
    /** @var Collection<array<string,mixed>> Linhas normalizadas para exibir na prévia */
    public Collection $rows;

    /** @var array<string, array<int, array{id: int, estado_nome: string, estado_sigla: string}>> */
    protected array $municipiosCache = [];

    /** @var array<int,string> */
    protected array $tiposOrganizacao = [];

    /** @var array<string,string> */
    protected array $tiposOrganizacaoMap = [];

    /** @var array<int,string> */
    protected array $tags = [];

    /** @var array<string,string> */
    protected array $tagsMap = [];

    protected DemograficoNormalizerService $demograficoNormalizer;

    protected int $headerRow = 1;

    public function __construct(int $headerRow = 1, private array $worksheetInfo = [])
    {
        $this->rows = collect();
        $this->headerRow = $headerRow > 0 ? $headerRow : 1;
        $this->demograficoNormalizer = new DemograficoNormalizerService;

        // Pré-carrega municípios para não consultar a cada linha
        $this->municipiosCache = Municipio::query()
            ->with('estado:id,nome,sigla')
            ->select('id', 'nome', 'estado_id')
            ->get()
            ->groupBy(fn ($m) => $this->slugify($m->nome))
            ->map(fn ($municipios) => $municipios->map(fn ($municipio) => [
                'id' => (int) $municipio->id,
                'estado_nome' => (string) $municipio->estado?->nome,
                'estado_sigla' => (string) $municipio->estado?->sigla,
            ])->values()->all())
            ->all();

        $this->tiposOrganizacao = config('engaja.organizacoes', []);
        $this->tiposOrganizacaoMap = collect($this->tiposOrganizacao)
            ->mapWithKeys(fn ($o) => [$this->slugify($o) => $o])
            ->all();

        $this->tags = config('engaja.participante_tags', Participante::TAGS);
        $this->tagsMap = collect($this->tags)
            ->mapWithKeys(fn ($t) => [$this->slugify($t) => $t])
            ->all();
    }

    /** Primeira linha contém os cabeçalhos */
    public function headingRow(): int
    {
        return $this->headerRow;
    }

    public function sheets(): array
    {
        if ($this->worksheetInfo === []) {
            return [0 => $this];
        }

        $sheets = [];
        foreach ($this->worksheetInfo as $info) {
            if ($info['totalRows'] > $this->headerRow
                && mb_strtolower(trim($info['worksheetName'])) !== '_valid') {
                $sheets[$info['worksheetName']] = $this;
            }
        }

        return $sheets;
    }

    public function withHeadingRow(int $headerRow): self
    {
        $import = clone $this;
        $import->headerRow = max(1, $headerRow);
        $import->rows = collect();

        return $import;
    }

    public function onRow(Row $source): void
    {
        $sheet = $source->getDelegate()->getWorksheet();
        if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE
            || mb_strtolower(trim($sheet->getTitle())) === '_valid') {
            return;
        }

        $raw = $source->toArray(null, false, false);
        if (! collect($raw)->contains(fn ($value) => is_scalar($value) && trim((string) $value) !== '')) {
            return;
        }

        $nome = $this->firstValue($raw, ['nome', 'name']) ?? '';
        $email = $this->firstValue($raw, ['email', 'e_mail', 'e-mail', 'mail']) ?? '';
        $cpfRaw = $this->firstValue($raw, ['cpf', 'documento']) ?? '';
        $telefoneRaw = $this->firstValue($raw, ['telefone', 'celular', 'fone', 'telefone_celular']) ?? '';

        // Resolve municipio_id via cache (se existir)
        $municipioNome = $this->firstValue($raw, ['municipio', 'município', 'cidade']) ?? '';
        $estado = $this->firstValue($raw, ['estado', 'uf', 'estado_sigla', 'sigla_estado']) ?? '';
        if (preg_match('/^(.+?)\s*(?:-|\/)\s*([A-Za-z]{2})$/u', $municipioNome, $matches)) {
            $municipioNome = trim($matches[1]);
            if ($estado === '') {
                $estado = mb_strtoupper($matches[2]);
            }
        }

        $municipioId = null;
        if ($municipioNome !== '') {
            $candidatos = collect($this->municipiosCache[$this->slugify($municipioNome)] ?? []);
            if ($estado !== '') {
                $estadoNormalizado = $this->slugify($estado);
                $candidatos = $candidatos->filter(fn (array $municipio) => $this->slugify($municipio['estado_nome']) === $estadoNormalizado
                    || $this->slugify($municipio['estado_sigla']) === $estadoNormalizado
                );
            }

            if ($candidatos->count() === 1) {
                $municipioId = $candidatos->first()['id'];
            }
        }

        $tipoColumnExists = false;
        $tipoRaw = $this->firstValue($raw, [
            'tipo_de_organizacao',
            'tipo_organizacao',
            'tipo-da-organizacao',
            'tipo_da_organizacao',
            'tipoorganizacao',
        ], $tipoColumnExists);
        if (! $tipoColumnExists) {
            $tipoRaw = $this->firstValue($raw, ['organizacao', 'escola_unidade']) ?? '';
        }
        $tipoCanon = $this->normalizeTipoOrganizacao($tipoRaw);
        $tipoOut = $tipoCanon ?? $tipoRaw;
        $tipoOk = ($tipoRaw === '') ? true : ($tipoCanon !== null);

        $organizacaoLivre = $this->firstValue(
            $raw,
            $tipoColumnExists
                ? ['organizacao', 'organizacao_nome', 'nome_da_organizacao', 'organizacao_livre', 'escola_unidade']
                : ['escola_unidade', 'organizacao']
        ) ?? '';

        $tagRaw = $this->firstValue($raw, ['tag']) ?? '';
        $tagCanon = $this->normalizeTag($tagRaw);
        $tagOut = $tagCanon;
        $tagOk = ($tagRaw === '') ? true : ($tagCanon !== null);

        $demograficosRaw = [];
        foreach (DemograficoNormalizerService::headerAliases() as $campo => $aliases) {
            $demograficosRaw[$campo] = $this->firstValue($raw, $aliases);
        }
        $demograficosNormalizados = $this->demograficoNormalizer->normalizeRow($demograficosRaw);

        $this->rows->push(ParticipanteImportValidator::normalize(array_merge([
            'linha_original' => $source->getIndex(),
            'aba_original' => $sheet->getTitle(),
            'nome' => (string) $nome,
            'email' => (string) $email,
            'cpf' => preg_replace('/\D+/', '', (string) $cpfRaw) ?: null,
            'telefone' => preg_replace('/\D+/', '', (string) $telefoneRaw) ?: null,
            'municipio' => $municipioNome,
            'municipio_id' => $municipioId,
            'estado' => $estado,
            'tipo_organizacao' => $tipoOut,
            'tipo_organizacao_ok' => $tipoOk,
            'escola_unidade' => $organizacaoLivre,
            'tag' => $tagOut,
            'tag_ok' => $tagOk,
            'data_nascimento' => $this->firstValue($raw, ['data_nascimento', 'data_de_nascimento', 'nascimento']) ?? '',
            'sexo' => $this->firstValue($raw, ['sexo']),
            'data_entrada' => $this->firstValue($raw, ['data_entrada', 'data entrada', 'data-de-entrada']) ?? '',
        ], $demograficosNormalizados)));
    }

    private function firstValue(array $row, array $keys, ?bool &$foundKey = null): ?string
    {
        $index = [];
        foreach ($row as $rowKey => $value) {
            if (! is_scalar($rowKey)) {
                continue;
            }
            $normalized = $this->slugify((string) $rowKey);
            if ($normalized !== '' && ! array_key_exists($normalized, $index)) {
                $index[$normalized] = $value;
            }
        }

        foreach ($keys as $key) {
            $normalizedKey = $this->slugify($key);
            if (array_key_exists($normalizedKey, $index)) {
                if ($foundKey !== null) {
                    $foundKey = true;
                }
                $value = $index[$normalizedKey];
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

    private function slugify(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($s, \Normalizer::NFD) ?: $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim((string) $s);
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
}
