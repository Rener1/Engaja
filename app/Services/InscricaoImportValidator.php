<?php

namespace App\Services;

use App\Models\Participante;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class InscricaoImportValidator extends ParticipanteImportValidator
{
    public function validate(array $rows): array
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

        $rows = $this->validateRows($rows, [
            'data_nascimento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sexo' => ['nullable', 'string', Rule::in(Participante::SEXOS)],
        ], [
            'data_nascimento.required' => 'Informe a data de nascimento.',
            'data_nascimento.date_format' => 'Informe uma data de nascimento válida, com ano de quatro dígitos (DD/MM/AAAA ou AAAA-MM-DD).',
            'data_nascimento.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
            'sexo.string' => 'Informe um sexo válido.',
            'sexo.in' => 'Sexo não reconhecido. Selecione Masculino, Feminino ou Não preenchido.',
        ]);
        $errors = [];
        $groups = [];
        foreach ($rows as $index => $row) {
            foreach (['email', 'cpf'] as $field) {
                $value = $row[$field];
                if ($value !== '' && $value !== null) {
                    $groups[$field][$value][] = $index;
                }
            }
        }
        foreach ($groups as $field => $values) {
            foreach ($values as $indexes) {
                $this->addDuplicateErrors($rows, $indexes, $field, $field === 'email' ? 'E-mail' : 'CPF', $errors);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    public function validateProfiles(array $rows, ParticipanteImportIdentityResolver $resolver): void
    {
        // E-mail e CPF diferentes também podem apontar para o mesmo perfil existente.
        $resolver->prepare($rows);
        $errors = [];
        $profiles = [];
        foreach ($rows as $index => $row) {
            try {
                $user = $resolver->findUser($row);
                if ($user) {
                    $profiles[$user->id][] = $index;
                }
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    $errors['rows.'.$index.'.identificacao'] = $messages;
                }
            }
        }
        foreach ($profiles as $indexes) {
            $this->addDuplicateErrors($rows, $indexes, 'identificacao', 'Perfil', $errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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

    private function addDuplicateErrors(array $rows, array $indexes, string $field, string $label, array &$errors): void
    {
        if (count($indexes) < 2) {
            return;
        }

        foreach ($indexes as $index) {
            $other = $index === $indexes[0] ? $indexes[1] : $indexes[0];
            $errors['rows.'.$index.'.'.$field][] = $label.' repetido na planilha. Registros: '
                .self::location($rows[$index], $index).'; '.self::location($rows[$other], $other).'.';
        }
    }
}
