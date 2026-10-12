<?php

namespace App\Http\Requests;

use App\Models\Participante;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserManagementRequest extends FormRequest
{
    private const LEGACY_ROLES = ['gestor', 'formador'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica', 'articulador']);
    }

    protected function prepareForValidation(): void
    {
        $toNull = fn ($v) => ($v === '' || $v === null) ? null : $v;

        $cpfDigits = preg_replace('/\D+/', '', (string) ($this->cpf ?? ''));
        $telDigits = preg_replace('/\D+/', '', (string) ($this->telefone ?? ''));

        $this->merge([
            'name' => isset($this->name) ? trim((string) $this->name) : null,
            'email' => isset($this->email) ? trim((string) $this->email) : null,
            'role' => isset($this->role) ? trim((string) $this->role) : null,
            'cpf' => $toNull($cpfDigits ?: null),
            'telefone' => $toNull($telDigits ?: null),
            'municipio_id' => $toNull($this->municipio_id ?? null),
            'escola_unidade' => $toNull(isset($this->escola_unidade) ? trim((string) $this->escola_unidade) : null),
            'tipo_organizacao' => $toNull(isset($this->tipo_organizacao) ? trim((string) $this->tipo_organizacao) : null),
            'tag' => $toNull(isset($this->tag) ? trim((string) $this->tag) : null),
            'sexo' => $toNull($this->sexo ?? null),
            'data_nascimento' => $toNull($this->data_nascimento ?? null),
            'autorizacao_imagem' => $this->boolean('autorizacao_imagem'),
        ]);
    }

    public function rules(): array
    {
        $managedUser = $this->route('managedUser');
        $managedUserId = $managedUser?->id;
        $isCreate = $managedUserId === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->where('sistema_origem', User::SISTEMA_ENGAJA)
                    ->ignore($managedUserId),
            ],
            'password' => [$isCreate ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'role' => ['nullable', 'string', Rule::in($this->assignableRoleNames())],

            'cpf' => ['nullable', 'digits:11'],
            'telefone' => ['nullable', 'regex:/^\\d{10,11}$/'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'escola_unidade' => ['nullable', 'string', 'max:255'],
            'tipo_organizacao' => ['nullable', 'string', 'max:255', Rule::in(config('engaja.organizacoes', []))],
            'tag' => ['nullable', Rule::in(Participante::TAGS)],
            'sexo' => ['nullable', 'string', Rule::in(Participante::SEXOS)],
            'data_nascimento' => ['nullable', 'date', 'before_or_equal:today'],
            'autorizacao_imagem' => ['boolean'],

            // campos demograficos
            'identidade_genero' => ['nullable', 'string', Rule::in(['Mulher Cisgênero', 'Mulher Transsexual', 'Homem Cisgênero', 'Homem Transsexual', 'Travesti', 'Não binárie', 'Prefiro não responder', 'Outro'])],
            'identidade_genero_outro' => ['nullable', 'string', 'max:255'],
            'raca_cor' => ['nullable', 'string', Rule::in(['Preta', 'Parda', 'Branca', 'Amarela', 'Indígena', 'Prefere não declarar'])],
            'comunidade_tradicional' => ['nullable', 'string', Rule::in(['Não', 'Povos indígenas', 'Comunidades Quilombolas', 'Povos Ciganos', 'Ribeirinhos', 'Extrativistas', 'Outro'])],
            'comunidade_tradicional_outro' => ['nullable', 'string', 'max:255'],
            'faixa_etaria' => ['nullable', 'string', Rule::in(['Primeira infância (0 a 6 anos)', 'Criança (7 a 11 anos)', 'Adolescente (12 a 17 anos)', 'Adulto (18 a 59 anos)', 'Idoso (a partir dos 60 anos)'])],
            'pcd' => ['nullable', 'string', Rule::in(['Não', 'Física', 'Auditiva', 'Visual', 'Intelectual', 'Múltipla'])],
            'orientacao_sexual' => ['nullable', 'string', Rule::in(['Lésbica', 'Gay', 'Bissexual', 'Heterossexual', 'Prefere não declarar', 'Outra'])],
            'orientacao_sexual_outra' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $cpf = $this->normalizeCpf($this->input('cpf'));
            if ($cpf) {
                if (! $this->isValidCpf($cpf)) {
                    $v->errors()->add('cpf', 'CPF invalido.');
                } else {
                    $managedUser = $this->route('managedUser');
                    $ignoreParticipanteId = $managedUser?->participante?->id;
                    if ($this->cpfDuplicado($cpf, $ignoreParticipanteId)) {
                        $v->errors()->add('cpf', 'Este CPF já possui cadastro no sistema.');
                    }
                }
            }

            $tel = (string) ($this->input('telefone'));
            if ($tel && ! preg_match('/^\\d{10,11}$/', $tel)) {
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

        return Participante::query()
            ->whereNotNull('cpf')
            ->whereHas('user', fn ($query) => $query->where('sistema_origem', User::SISTEMA_ENGAJA))
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
        if (preg_match('/^(\\d)\\1{10}$/', $cpf)) {
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
            'name.required' => 'Informe o nome do usuario.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail valido.',
            'email.unique' => 'Este e-mail ja esta em uso.',
            'password.required' => 'Informe a senha do usuario.',
            'password.confirmed' => 'A confirmacao da senha nao confere.',
            'role.in' => 'O papel selecionado nao e permitido.',
            'cpf.required' => 'CPF e obrigatorio.',
            'cpf.digits' => 'CPF deve conter 11 digitos.',
            'telefone.regex' => 'Telefone deve ter DDD e 10 ou 11 digitos.',
            'municipio_id.exists' => 'Municipio invalido.',
            'tipo_organizacao.in' => 'Selecione um tipo de organizacao valido.',
            'tag.in' => 'Selecione uma tag valida.',
        ];
    }

    private function assignableRoleNames(): array
    {

        $rolesToExclude = self::LEGACY_ROLES;

        return Role::whereNotIn('name', $rolesToExclude)
            ->pluck('name')
            ->toArray();
    }
}
