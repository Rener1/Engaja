<?php

namespace App\Services;

use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AtualizacaoCadastralService
{
    public static function columns(): array
    {
        $fields = [
            'nome' => 'Nome', 'email' => 'E-mail', 'cpf' => 'CPF', 'telefone' => 'Telefone',
            'municipio' => 'Município', 'estado' => 'Estado/UF',
            'tipo_organizacao' => 'Tipo de organização', 'escola_unidade' => 'Organização', 'tag' => 'Tag',
        ];
        foreach (config('engaja.demograficos', []) as $field => $definition) {
            $fields[$field] = ucfirst(str_replace('_', ' ', $field));
            if ($definition['campo_outro'] ?? null) {
                $fields[$definition['campo_outro']] = ucfirst(str_replace('_', ' ', $definition['campo_outro']));
            }
        }

        return $fields + ['sexo' => 'Sexo', 'data_nascimento' => 'Data de nascimento'];
    }

    public function normalize(array $rows): array
    {
        foreach ($rows as $index => &$row) {
            $row['aba_original'] ??= 'Planilha';
            $row['linha_original'] ??= $index + 2;
            foreach (self::columns() as $field => $label) {
                $value = $row[$field] ?? null;
                $row[$field] = is_scalar($value) ? trim((string) $value) : null;
            }
            $row['email'] = mb_strtolower($row['email'] ?? '');
            foreach (Participante::SEXOS as $option) {
                if ($this->name($row['sexo'] ?? '') === $this->name($option)) {
                    $row['sexo'] = $option;
                    break;
                }
            }
            foreach (['cpf', 'telefone'] as $field) {
                $row[$field] = preg_replace('/\D+/', '', $row[$field] ?? '');
            }
            foreach (['data_nascimento'] as $field) {
                $row[$field] = $this->date($row[$field]);
            }
        }

        return $rows;
    }

    public function analyze(array $rows, bool $lock = false): array
    {
        $rows = $this->normalize($rows);
        $errors = [];
        $plans = [];
        $normalizer = new DemograficoNormalizerService;
        $municipios = Municipio::with('estado')->get();
        foreach ($rows as $index => $row) {
            $location = $this->location($row);
            try {
                $rules = array_fill_keys(array_keys(self::columns()), ['nullable', 'string', 'max:255']);
                $rules['email'][] = 'email:rfc';
                $rules['data_nascimento'] = ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
                Validator::make($row, $rules)->validate();
                if (filled($row['sexo']) && ! in_array($row['sexo'], Participante::SEXOS, true)) {
                    $this->fail('Sexo não reconhecido. Selecione Masculino, Feminino ou Não preenchido.');
                }
                if ($row['cpf'] === '' && $row['email'] === '') {
                    $this->fail('Informe CPF ou e-mail.');
                }
                if ($row['cpf'] !== '' && strlen($row['cpf']) !== 11) {
                    $this->fail('O CPF informado tem '.strlen($row['cpf']).' dígitos; precisa ter 11, incluindo zeros à esquerda. Confira o CPF no documento da pessoa e corrija a coluna CPF.');
                }
                $targets = $this->targets($row, $lock);
                if ($targets->isEmpty() && ! filled($row['nome'])) {
                    $this->fail('Informe o nome para criar um novo cadastro.');
                }
                $participantData = [];
                if (filled($row['cpf'])) {
                    $participantData['cpf'] = $row['cpf'];
                }
                foreach (['telefone', 'tipo_organizacao', 'escola_unidade', 'tag', 'sexo', 'data_nascimento'] as $field) {
                    if (filled($row[$field])) {
                        $participantData[$field] = $row[$field];
                    }
                }
                if (filled($row['municipio'])) {
                    $candidates = $municipios->filter(function ($municipio) use ($row) {
                        return $this->name($municipio->nome) === $this->name($row['municipio'])
                            && (! filled($row['estado']) || in_array($this->name($row['estado']), [
                                $this->name($municipio->estado?->nome ?? ''), $this->name($municipio->estado?->sigla ?? ''),
                            ], true));
                    });
                    if ($candidates->count() !== 1) {
                        $this->fail('Município inexistente ou ambíguo. Informe município e UF.');
                    }
                    $participantData['municipio_id'] = $candidates->first()->id;
                } elseif (filled($row['estado'])) {
                    $this->fail('Informe o município junto com o estado.');
                }
                $userData = [];
                if (filled($row['nome'])) {
                    $userData['name'] = $row['nome'];
                }
                foreach (config('engaja.demograficos', []) as $field => $definition) {
                    if (filled($row[$field])) {
                        $result = $normalizer->normalize($field, $row[$field]);
                        if (! $result['ok']) {
                            $this->fail('Valor inválido para '.self::columns()[$field].'.');
                        }
                        $userData[$field] = $result['campo'];
                        if (($definition['campo_outro'] ?? null) && filled($result['campo_outro'])) {
                            $userData[$definition['campo_outro']] = $result['campo_outro'];
                        }
                    }
                    $other = $definition['campo_outro'] ?? null;
                    if ($other && filled($row[$other])) {
                        $userData[$other] = $row[$other];
                    }
                }
                $plans[$index] = ['targets' => $targets, 'participante' => $participantData, 'usuario' => $userData];
            } catch (ValidationException $exception) {
                $errors[$index] = $location.': '.implode(' ', collect($exception->errors())->flatten()->all());
            }
        }

        $groups = $this->groupPlans($rows, $plans, $errors);

        return [
            'rows' => $rows, 'plans' => $plans, 'errors' => $errors, 'groups' => $groups,
            'newCount' => count(array_filter($groups, fn ($group) => $group['targets']->isEmpty())),
            'existingCount' => count(array_filter($groups, fn ($group) => $group['targets']->isNotEmpty())),
        ];
    }

    public function apply(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            app(ParticipanteImportIdentityResolver::class)->lock();
            $analysis = $this->analyze($rows, true);
            if ($analysis['errors'] !== []) {
                throw ValidationException::withMessages(['rows' => array_values($analysis['errors'])]);
            }
            $resolver = app(ParticipanteImportIdentityResolver::class);
            $resolver->prepare($analysis['rows']);
            $result = ['updated' => 0, 'created' => 0, 'unchanged' => 0, 'updatedRecords' => 0];
            foreach ($analysis['groups'] as $plan) {
                $new = $plan['targets']->isEmpty();
                if ($new) {
                    $target = $resolver->resolve($plan['identity']);
                    $plan['targets'] = collect([$target]);
                    $result['created']++;
                }
                $changed = false;
                foreach ($plan['targets'] as $target) {
                    $target->fill($plan['participante']);
                    $target->user->fill($plan['usuario']);
                    if ($target->isDirty() || $target->user->isDirty()) {
                        $target->save();
                        $target->user->save();
                        $changed = true;
                        if (! $new) {
                            $result['updatedRecords']++;
                        }
                    }
                }
                if (! $new) {
                    $result[$changed ? 'updated' : 'unchanged']++;
                }
            }

            return $result;
        });
    }

    /** Agrupa linhas conectadas por CPF, e-mail ou cadastro, sem duplicar pessoas no lote. */
    private function groupPlans(array $rows, array &$plans, array &$errors): array
    {
        $parents = array_combine(array_keys($plans), array_keys($plans));
        $root = function (int $index) use (&$parents): int {
            while ($parents[$index] !== $index) {
                $index = $parents[$index];
            }

            return $index;
        };
        $owners = [];
        foreach ($plans as $index => $plan) {
            $tokens = [];
            foreach (['email', 'cpf'] as $field) {
                if (filled($rows[$index][$field])) {
                    $tokens[] = $field.':'.$rows[$index][$field];
                }
            }
            foreach ($plan['targets'] as $target) {
                $tokens[] = 'id:'.$target->id;
                $cpf = preg_replace('/\D+/', '', $target->cpf ?? '');
                if ($cpf !== '') {
                    $tokens[] = 'cpf:'.$cpf;
                }
            }
            foreach ($tokens as $token) {
                if (isset($owners[$token])) {
                    $parents[$root($index)] = $root($owners[$token]);
                }
                $owners[$token] = $index;
            }
        }
        $members = [];
        foreach (array_keys($plans) as $index) {
            $members[$root($index)][] = $index;
        }
        $groups = [];
        foreach ($members as $indexes) {
            $group = ['targets' => collect(), 'participante' => [], 'usuario' => [], 'identity' => ['email' => '', 'cpf' => null, 'nome' => '']];
            try {
                $names = [];
                $cpfs = [];
                $sources = [];
                foreach ($indexes as $index) {
                    $row = $rows[$index];
                    $group['targets'] = $group['targets']->concat($plans[$index]['targets'])->unique('id')->values();
                    foreach (['email', 'cpf', 'nome'] as $field) {
                        if (! filled($group['identity'][$field]) && filled($row[$field])) {
                            $group['identity'][$field] = $row[$field];
                        }
                    }
                    if (filled($row['nome'])) {
                        $names[] = $this->name($row['nome']);
                    }
                    if (filled($row['cpf'])) {
                        $cpfs[] = $row['cpf'];
                    }
                    foreach (['participante', 'usuario'] as $kind) {
                        foreach ($plans[$index][$kind] as $field => $value) {
                            $previous = $group[$kind][$field] ?? $value;
                            $equal = $field === 'name' ? $this->name($previous) === $this->name($value) : $previous === $value;
                            if (! $equal) {
                                $source = $sources[$kind][$field];
                                $column = match ($field) {
                                    'name' => 'nome',
                                    'municipio_id' => 'municipio',
                                    default => $field,
                                };
                                $label = self::columns()[$column] ?? ucfirst(str_replace('_', ' ', $column));
                                $before = $rows[$source][$column] ?? $previous;
                                $after = $row[$column] ?? $value;
                                if ($column === 'municipio') {
                                    $before .= ' / '.($rows[$source]['estado'] ?? '');
                                    $after .= ' / '.($row['estado'] ?? '');
                                }
                                $this->fail($label.' diferente entre linhas vinculadas pelo CPF, e-mail ou cadastro: '
                                    .$this->location($rows[$source]).' informa “'.$before.'”; '
                                    .$this->location($row).' informa “'.$after.'”. '
                                    .'Se forem a mesma pessoa, informe o mesmo valor correto nas duas linhas. '
                                    .'Se forem pessoas diferentes, corrija o CPF ou e-mail que as vinculou.');
                            }
                            $group[$kind][$field] ??= $value;
                            $sources[$kind][$field] ??= $index;
                        }
                    }
                }
                foreach ($group['targets'] as $target) {
                    if (count($indexes) > 1 || $group['targets']->count() > 1) {
                        $names[] = $this->name($target->user->name);
                    }
                    if (filled($target->cpf)) {
                        $cpfs[] = preg_replace('/\D+/', '', $target->cpf);
                    }
                }
                if (count(array_unique($names)) > 1 || count(array_unique($cpfs)) > 1) {
                    $this->fail('Nomes ou CPFs divergentes em linhas da mesma pessoa. Revise o grupo.');
                }
                foreach ($indexes as $index) {
                    $plans[$index]['targets'] = $group['targets'];
                    $plans[$index]['newEmail'] = $group['identity']['email'];
                }
                $groups[] = $group;
            } catch (ValidationException $exception) {
                foreach ($indexes as $index) {
                    $errors[$index] = $this->location($rows[$index]).': '.implode(' ', collect($exception->errors())->flatten()->all());
                }
            }
        }

        return $groups;
    }

    private function targets(array $row, bool $lock): Collection
    {
        $query = Participante::query()->with('user')->whereHas('user', fn ($q) => $q->where('sistema_origem', User::SISTEMA_ENGAJA));
        $emailMatches = $row['email'] !== ''
            ? (clone $query)->whereHas('user', fn ($q) => $q->whereRaw('LOWER(TRIM(email)) = ?', [$row['email']]))->get()
            : collect();
        if ($emailMatches->count() > 1) {
            $this->fail('E-mail ambíguo. Regularize os cadastros.');
        }
        $cpf = $row['cpf'];
        if ($emailMatches->isNotEmpty()) {
            $existingCpf = preg_replace('/\D+/', '', $emailMatches->first()->cpf ?? '');
            if ($cpf !== '' && $existingCpf !== '' && $existingCpf !== $cpf) {
                $this->fail('CPF e e-mail não identificam o mesmo cadastro.');
            }
            $cpf = $cpf ?: $existingCpf;
        }
        if ($cpf !== '') {
            $query->where(fn ($q) => $q->whereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [$cpf])
                ->orWhereIn('id', $emailMatches->pluck('id')));
        } else {
            $query->whereIn('id', $emailMatches->pluck('id'));
        }
        $targets = $query->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get();
        if ($lock && $targets->isNotEmpty()) {
            $users = User::whereIn('id', $targets->pluck('user_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($targets as $target) {
                $target->setRelation('user', $users[$target->user_id]);
            }
        }
        if ($targets->isEmpty()) {
            $emailExists = $row['email'] !== '' && User::withTrashed()
                ->where('sistema_origem', User::SISTEMA_ENGAJA)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$row['email']])->exists();
            $cpfExists = $cpf !== '' && Participante::withTrashed()
                ->whereHas('user', fn ($q) => $q->withTrashed()->where('sistema_origem', User::SISTEMA_ENGAJA))
                ->whereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') = ?", [$cpf])->exists();
            if ($emailExists || $cpfExists) {
                $this->fail('Já existe cadastro com este CPF ou e-mail, excluído ou sem participante ativo. Regularize-o antes de importar.');
            }

            return $targets;
        }
        if ($row['email'] !== '' && $emailMatches->isEmpty() && User::withTrashed()
            ->where('sistema_origem', User::SISTEMA_ENGAJA)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$row['email']])->exists()) {
            $this->fail('O e-mail pertence a outro cadastro excluído ou sem participante ativo.');
        }
        $names = $targets->map(fn ($p) => $this->name($p->user->name))->unique();
        if ($names->count() !== 1 || $names->first() === '') {
            $this->fail('Há nomes divergentes nos cadastros deste CPF. Revise a identidade antes de atualizar.');
        }
        if ($targets->count() > 1 && filled($row['nome']) && $this->name($row['nome']) !== $names->first()) {
            $this->fail('O nome da planilha diverge do grupo identificado pelo CPF. Revise a identidade.');
        }

        return $targets;
    }

    private function location(array $row): string
    {
        return 'Linha '.$row['linha_original'];
    }

    private function name(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower(Str::ascii($value))));
    }

    private function date(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['rows' => $message]);
    }
}
