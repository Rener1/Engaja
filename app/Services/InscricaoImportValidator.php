<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class InscricaoImportValidator extends ParticipanteImportValidator
{
    public function validate(array $rows): array
    {
        $rows = $this->validateDadosPessoais($rows);
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
