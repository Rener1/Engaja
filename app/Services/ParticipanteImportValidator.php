<?php

namespace App\Services;

use App\Models\Participante;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ParticipanteImportValidator
{
    public static function normalize(array $row): array
    {
        foreach (['nome', 'email', 'cpf', 'telefone'] as $field) {
            if (is_scalar($row[$field] ?? null)) {
                $row[$field] = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', (string) $row[$field]);
            }
        }

        if (is_string($row['email'] ?? null)) {
            $row['email'] = mb_strtolower($row['email']);
        }

        foreach (['cpf', 'telefone'] as $field) {
            if (is_string($row[$field] ?? null)) {
                $digits = preg_replace('/\D+/', '', $row[$field]);
                $row[$field] = $digits === '' ? null : $digits;
            }
        }

        $row['nome'] ??= '';
        $row['email'] ??= '';
        $row['cpf'] ??= null;
        $row['telefone'] ??= null;

        return $row;
    }

    public function validate(array $rows): array
    {
        return $this->validateRows($rows);
    }

    protected function validateDadosPessoais(array $rows, array $additionalRules = [], array $additionalMessages = []): array
    {
        foreach ($rows as &$row) {
            $row['data_nascimento'] = $this->normalizeDataNascimento($row['data_nascimento'] ?? null);
            $sexo = $row['sexo'] ?? null;
            if (is_scalar($sexo)) {
                $sexo = trim((string) $sexo);
                foreach (Participante::SEXOS as $option) {
                    if (Str::lower(Str::ascii($sexo)) === Str::lower(Str::ascii($option))) {
                        $sexo = $option;
                        break;
                    }
                }
            }
            $row['sexo'] = $sexo === '' ? null : $sexo;
        }
        unset($row);

        return $this->validateRows($rows, array_merge([
            'data_nascimento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sexo' => ['nullable', 'string', Rule::in(Participante::SEXOS)],
        ], $additionalRules), array_merge([
            'data_nascimento.required' => 'Informe a data de nascimento.',
            'data_nascimento.date_format' => 'Informe uma data de nascimento válida, com ano de quatro dígitos (DD/MM/AAAA ou AAAA-MM-DD).',
            'data_nascimento.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
            'sexo.string' => 'Informe um sexo válido.',
            'sexo.in' => 'Sexo não reconhecido. Selecione Masculino, Feminino ou Não preenchido.',
        ], $additionalMessages));
    }

    protected function validateRows(array $rows, array $additionalRules = [], array $additionalMessages = []): array
    {
        $errors = [];

        $rules = [
            'nome' => 'required|string|max:255',
            'email' => 'nullable|string|email:rfc|max:255',
            'cpf' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:255',
        ];
        $rules = array_merge($rules, $additionalRules);

        foreach ($rows as $index => &$row) {
            $row = self::normalize($row);
            $validator = Validator::make($row, $rules, array_merge([
                'nome.required' => 'Informe o nome.',
                'nome.string' => 'Informe um nome válido.',
                'nome.max' => 'O nome deve ter no máximo 255 caracteres.',
                'email.email' => 'Informe um e-mail válido.',
                'email.string' => 'Informe um e-mail válido.',
                'email.max' => 'O e-mail deve ter no máximo 255 caracteres.',
                'cpf.string' => 'Informe um CPF em formato de texto.',
                'cpf.max' => 'O CPF excede o tamanho permitido.',
                'telefone.string' => 'Informe um telefone em formato de texto.',
                'telefone.max' => 'O telefone excede o tamanho permitido.',
            ], $additionalMessages));

            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors["rows.{$index}.{$field}"] = array_map(
                    fn (string $message) => self::location($row, $index).': '.$message,
                    $messages
                );
            }

            if ($row['email'] === '' && $row['cpf'] === null) {
                $errors["rows.{$index}.identificacao"] = self::location($row, $index)
                    .': informe e-mail ou CPF.';
            }
        }
        unset($row);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    private function normalizeDataNascimento(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    public static function location(array $row, int $index = 0): string
    {
        $line = $row['linha_original'] ?? $index + 2;

        return 'Linha '.$line;
    }
}
