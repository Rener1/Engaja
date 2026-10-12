<?php

namespace App\Services;

class PresencaImportValidator extends ParticipanteImportValidator
{
    public function validate(array $rows, bool $requireStatus = false): array
    {
        return $this->validateDadosPessoais($rows, $requireStatus ? [
            'status' => 'required|string|in:presente,ausente,justificado',
        ] : [], [
            'status.required' => 'Selecione o status da presença.',
            'status.string' => 'Selecione o status da presença.',
            'status.in' => 'Selecione o status da presença.',
        ]);
    }
}
