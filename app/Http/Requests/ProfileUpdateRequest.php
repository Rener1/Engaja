<?php

namespace App\Http\Requests;

use App\Models\Participante;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $toNull = fn ($v) => ($v === '' || $v === null) ? null : $v;
        $cpfDigits = preg_replace('/\D+/', '', (string) ($this->cpf ?? ''));
        $telDigits = preg_replace('/\D+/', '', (string) ($this->telefone ?? ''));

        $this->merge([
            'name' => isset($this->name) ? trim((string) $this->name) : null,
            'email' => isset($this->email) ? trim((string) $this->email) : null,
            'cpf' => $toNull($cpfDigits ?: null),
            'telefone' => $toNull($telDigits ?: null),
            'municipio_id' => $toNull($this->municipio_id ?? null),
            'sexo' => $toNull($this->sexo ?? null),
            'data_nascimento' => $toNull($this->data_nascimento ?? null),
            'remove_profile_photo' => $this->boolean('remove_profile_photo'),
            'autorizacao_imagem' => $this->boolean('autorizacao_imagem'),
        ]);
    }

    public function rules(): array
    {
        $userId = $this->user()->id ?? null;
        $sistemaOrigem = $this->user()->sistema_origem ?? User::SISTEMA_ENGAJA;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->where('sistema_origem', $sistemaOrigem)
                    ->ignore($userId),
            ],
            'cpf' => ['required', 'digits:11'],
            'telefone' => ['nullable', 'regex:/^\d{10,11}$/'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'escola_unidade' => ['nullable', 'string', 'max:255'],
            'tipo_organizacao' => ['nullable', 'string', 'max:255', Rule::in(config('engaja.organizacoes', []))],
            'tag' => ['nullable', Rule::in(Participante::TAGS)],
            'sexo' => ['nullable', 'string', Rule::in(Participante::SEXOS)],
            'data_nascimento' => ['nullable', 'date', 'before_or_equal:today'],
            'autorizacao_imagem' => ['boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $cpf = preg_replace('/\D+/', '', (string) ($this->input('cpf')));
            if (! $this->isValidCpf($cpf)) {
                $v->errors()->add('cpf', 'CPF invalido.');
            } else {
                $meuParticipanteId = $this->user()?->participante?->id;
                $duplicado = Participante::where('cpf', $cpf)
                    ->when($meuParticipanteId, fn ($q) => $q->where('id', '!=', $meuParticipanteId))
                    ->exists();
                if ($duplicado) {
                    $v->errors()->add('cpf', 'Este CPF já possui cadastro no sistema.');
                }
            }

            $tel = (string) ($this->input('telefone'));
            if ($tel && ! preg_match('/^\d{10,11}$/', $tel)) {
                $v->errors()->add('telefone', 'Telefone invalido. Use DDD + numero (10 ou 11 digitos).');
            }
        });
    }

    private function normalizeCpf(?string $cpf): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($cpf ?? ''));

        return $digits !== '' ? $digits : null;
    }

    private function cpfDuplicado(string $cpf, ?int $ignorarId = null): bool
    {
        $cpf = $this->normalizeCpf($cpf);
        if (! $cpf) {
            return false;
        }

        $sistemaOrigem = $this->user()?->sistema_origem ?? User::SISTEMA_ENGAJA;

        return Participante::query()
            ->whereNotNull('cpf')
            ->whereHas('user', fn ($query) => $query->where('sistema_origem', $sistemaOrigem))
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$cpf])
            ->exists();
    }

    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D+/', '', $cpf ?? '');
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum += (int) $cpf[$i] * $w;
        }
        $r = $sum % 11;
        $dv1 = ($r < 2) ? 0 : 11 - $r;

        $sum = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum += (int) $cpf[$i] * $w;
        }
        $r = $sum % 11;
        $dv2 = ($r < 2) ? 0 : 11 - $r;

        return ($cpf[9] == $dv1) && ($cpf[10] == $dv2);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome é obrigatório.',
            'email.required' => 'E-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'cpf.required' => 'CPF e obrigatorio.',
            'cpf.digits' => 'CPF deve conter 11 digitos.',
            'telefone.regex' => 'Telefone deve ter DDD e 10 ou 11 digitos.',
            'municipio_id.exists' => 'Municipio invalido.',
            'profile_photo.image' => 'Envie um arquivo de imagem valido.',
            'profile_photo.mimes' => 'A foto deve estar em JPG, JPEG, PNG, GIF ou WEBP.',
            'profile_photo.max' => 'A foto deve ter no maximo 5 MB.',
        ];
    }
}
