<?php

namespace App\Imports;

use App\Services\AtualizacaoCadastralService;
use App\Services\DemograficoNormalizerService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AtualizacaoCadastralImport
{
    public function read(string $path): array
    {
        $book = IOFactory::load($path);
        $rows = [];
        $aliases = array_merge([
            'nome' => ['nome', 'name'], 'email' => ['email', 'e_mail', 'mail'],
            'cpf' => ['cpf', 'documento'], 'telefone' => ['telefone', 'celular', 'fone'],
            'municipio' => ['municipio', 'cidade'], 'estado' => ['estado', 'uf'],
            'tipo_organizacao' => ['tipo_organizacao', 'tipo_de_organizacao'],
            'escola_unidade' => ['escola_unidade', 'organizacao', 'nome_da_organizacao'],
        ], DemograficoNormalizerService::headerAliases());
        foreach (AtualizacaoCadastralService::columns() as $field => $label) {
            $aliases[$field][] = $field;
        }
        $lookup = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $lookup[Str::slug($name, '_')] = $field;
            }
        }
        try {
            foreach ($book->getWorksheetIterator() as $sheet) {
                if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE || strtolower(trim($sheet->getTitle())) === '_valid') {
                    continue;
                }
                $headers = [];
                foreach ($sheet->getRowIterator() as $source) {
                    $number = $source->getRowIndex();
                    $cells = $source->getCellIterator();
                    $cells->setIterateOnlyExistingCells(true);
                    $values = [];
                    foreach ($cells as $cell) {
                        $values[$cell->getColumn()] = $cell->getValue();
                    }
                    if ($headers === []) {
                        if ($number > 30) {
                            break;
                        }
                        $candidate = [];
                        foreach ($values as $column => $value) {
                            $key = Str::slug((string) $value, '_');
                            if (isset($lookup[$key])) {
                                $candidate[$column] = $lookup[$key];
                            }
                        }
                        if (in_array('cpf', $candidate, true) || in_array('email', $candidate, true)) {
                            if (count($candidate) !== count(array_unique($candidate))) {
                                throw ValidationException::withMessages(['your_file' => 'Há colunas repetidas na aba '.$sheet->getTitle().'.']);
                            }
                            $headers = $candidate;
                        }

                        continue;
                    }
                    $row = array_fill_keys(array_keys(AtualizacaoCadastralService::columns()), null);
                    foreach ($headers as $column => $field) {
                        $row[$field] = $values[$column] ?? null;
                    }
                    if (! collect($row)->contains(fn ($value) => $value !== null && trim((string) $value) !== '')) {
                        continue;
                    }
                    $row['linha_original'] = $number;
                    $row['aba_original'] = $sheet->getTitle();
                    $rows[] = $row;
                    if (count($rows) > 5000) {
                        throw ValidationException::withMessages(['your_file' => 'Utilize no máximo 5.000 registros por arquivo.']);
                    }
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['your_file' => 'Nenhum registro encontrado. Use cabeçalhos com CPF ou e-mail nas primeiras 30 linhas.']);
        }

        return $rows;
    }
}
