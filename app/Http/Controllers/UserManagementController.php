<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Exports\UsuariosNaoCadastradosExport;
use App\Exports\UsuariosVerificacaoCompletaExport;
use App\Http\Requests\UserManagementRequest;
use App\Imports\ParticipantesPreviewImport;
use App\Models\Estado;
use App\Models\ModeloCertificado;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\Regiao;
use App\Models\User;
use App\Services\UserDeletionService;
use App\Word\WordDocument;
use App\Word\WordTableExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    private const PROTECTED_ROLES = ['administrador'];

    private const LEGACY_ROLES = ['gestor', 'formador'];

    private const CREATOR_ROLES = ['administrador', 'gerente', 'eq_pedagogica', 'articulador'];

    private const DELETER_ROLES = ['administrador', 'gerente'];

    private const EMAIL_SIMILARITY_THRESHOLD = 0.85;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $searchCpf = preg_replace('/\D+/', '', $search) ?: '';
        $regiaoId = $request->query('regiao');
        $estadoId = $request->query('estado');
        $municipioId = $request->query('municipio');

        $users = User::with([
            'roles',
            'participante.municipio.estado.regiao',
            'participante.inscricoes.evento',
            'participante.inscricoes.atividade.evento',
            'participante.inscricoes.presencas.atividade.evento',
        ])
            ->when(! auth()->user()->hasRole('administrador'), function ($q) {
                // nao sendo administrador, oculta os administradores
                $q->whereDoesntHave('roles', fn ($sub) => $sub->whereIn('name', self::PROTECTED_ROLES));
            })
            ->when($search !== '', function ($q) use ($search, $searchCpf) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->when($searchCpf !== '', function ($sub) use ($searchCpf) {
                        $sub->orWhereHas('participante', function ($participanteQuery) use ($searchCpf) {
                            $participanteQuery->whereRaw("regexp_replace(coalesce(cpf, ''), '[^0-9]', '', 'g') like ?", ["%{$searchCpf}%"]);
                        });
                    });
            })
            ->when($municipioId, function ($q) use ($municipioId) {
                $q->whereHas('participante', fn ($sub) => $sub->where('municipio_id', $municipioId));
            })
            ->when($estadoId && ! $municipioId, function ($q) use ($estadoId) {
                $q->whereHas('participante.municipio', fn ($sub) => $sub->where('estado_id', $estadoId));
            })
            ->when($regiaoId && ! $estadoId && ! $municipioId, function ($q) use ($regiaoId) {
                $q->whereHas('participante.municipio.estado', fn ($sub) => $sub->where('regiao_id', $regiaoId));
            })
            ->orderBy('name')
            ->paginate(12)
            ->appends([
                'q' => $search,
                'regiao' => $regiaoId,
                'estado' => $estadoId,
                'municipio' => $municipioId,
            ]);

        $regioes = Regiao::orderBy('nome')->get(['id', 'nome']);
        $estados = Estado::orderBy('nome')->get(['id', 'nome', 'regiao_id']);
        $municipios = Municipio::orderBy('nome')->get(['id', 'nome', 'estado_id']);

        return view('usuarios.index', [
            'users' => $users,
            'search' => $search,
            'regiao_id' => $regiaoId,
            'estado_id' => $estadoId,
            'municipio_id' => $municipioId,
            'regioes' => $regioes,
            'estados' => $estados,
            'municipios' => $municipios,
            'modelosCertificado' => ModeloCertificado::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->hasAnyRole(self::CREATOR_ROLES), 403);

        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $organizacoes = config('engaja.organizacoes', []);
        $participanteTags = config('engaja.participante_tags', Participante::TAGS);
        $roles = $this->assignableRoles();

        return view('usuarios.create', [
            'user' => new User,
            'municipios' => $municipios,
            'organizacoes' => $organizacoes,
            'participanteTags' => $participanteTags,
            'roles' => $roles,
            'currentRole' => 'participante',
        ]);
    }

    public function store(UserManagementRequest $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(self::CREATOR_ROLES), 403);

        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'identidade_genero' => $data['identidade_genero'] ?? null,
                'identidade_genero_outro' => $data['identidade_genero_outro'] ?? null,
                'raca_cor' => $data['raca_cor'] ?? null,
                'comunidade_tradicional' => $data['comunidade_tradicional'] ?? null,
                'comunidade_tradicional_outro' => $data['comunidade_tradicional_outro'] ?? null,
                'faixa_etaria' => $data['faixa_etaria'] ?? null,
                'pcd' => $data['pcd'] ?? null,
                'orientacao_sexual' => $data['orientacao_sexual'] ?? null,
                'orientacao_sexual_outra' => $data['orientacao_sexual_outra'] ?? null,
            ]);

            $user->participante()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'cpf' => $data['cpf'] ?? null,
                    'telefone' => $data['telefone'] ?? null,
                    'municipio_id' => $data['municipio_id'] ?? null,
                    'escola_unidade' => $data['escola_unidade'] ?? null,
                    'tipo_organizacao' => $data['tipo_organizacao'] ?? null,
                    'tag' => $data['tag'] ?? null,
                    'autorizacao_imagem' => $data['autorizacao_imagem'] ?? false,
                ]
            );

            $roleToApply = auth()->user()->hasRole('administrador')
                ? ($data['role'] ?? 'participante')
                : 'participante';

            $user->syncRoles([$roleToApply]);
        });

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario cadastrado com sucesso.');
    }

    public function edit(User $managedUser): View|RedirectResponse
    {
        abort_unless(auth()->user()?->can('user.editar'), 403);

        if ($this->isProtected($managedUser) && ! auth()->user()->hasRole('administrador')) {
            return redirect()
                ->route('usuarios.index')
                ->with('error', 'Este usuario nao pode ser editado.');
        }

        $managedUser->load(['participante.municipio.estado', 'roles']);

        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $organizacoes = config('engaja.organizacoes', []);
        $participanteTags = config('engaja.participante_tags', Participante::TAGS);
        $roles = $this->assignableRoles();

        return view('usuarios.edit', [
            'user' => $managedUser,
            'municipios' => $municipios,
            'organizacoes' => $organizacoes,
            'participanteTags' => $participanteTags,
            'roles' => $roles,
            'currentRole' => $managedUser->roles->first()?->name,
        ]);
    }

    public function update(UserManagementRequest $request, User $managedUser): RedirectResponse
    {
        abort_unless(auth()->user()?->can('user.editar'), 403);

        if ($this->isProtected($managedUser) && ! auth()->user()->hasRole('administrador')) {
            return redirect()
                ->route('usuarios.index')
                ->with('error', 'Este usuario nao pode ser editado.');
        }

        $data = $request->validated();

        $oldEmail = $managedUser->email;
        $managedUser->fill([
            'name' => $data['name'],
            'email' => $data['email'],

            // campos demograficos
            'identidade_genero' => $data['identidade_genero'] ?? null,
            'identidade_genero_outro' => $data['identidade_genero_outro'] ?? null,
            'raca_cor' => $data['raca_cor'] ?? null,
            'comunidade_tradicional' => $data['comunidade_tradicional'] ?? null,
            'comunidade_tradicional_outro' => $data['comunidade_tradicional_outro'] ?? null,
            'faixa_etaria' => $data['faixa_etaria'] ?? null,
            'pcd' => $data['pcd'] ?? null,
            'orientacao_sexual' => $data['orientacao_sexual'] ?? null,
            'orientacao_sexual_outra' => $data['orientacao_sexual_outra'] ?? null,
        ]);

        if ($oldEmail !== $data['email']) {
            $managedUser->email_verified_at = null;
        }

        $managedUser->save();

        $managedUser->participante()->updateOrCreate(
            ['user_id' => $managedUser->id],
            [
                'cpf' => $data['cpf'] ?? null,
                'telefone' => $data['telefone'] ?? null,
                'municipio_id' => $data['municipio_id'] ?? null,
                'escola_unidade' => $data['escola_unidade'] ?? null,
                'tipo_organizacao' => $data['tipo_organizacao'] ?? null,
                'tag' => $data['tag'] ?? null,
                'autorizacao_imagem' => $data['autorizacao_imagem'] ?? false,
            ]
        );

        if (auth()->user()->hasRole('administrador')) {
            // se a role vier preenchida no request, aplica. Se vier vazia, remove os acessos.
            $roleToApply = $data['role'] ?? null;

            if ($roleToApply) {
                $managedUser->syncRoles([$roleToApply]);
            } else {
                $managedUser->syncRoles([]);
            }
        }

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuario atualizado com sucesso.');
    }

    public function resetPassword(Request $request, User $managedUser): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('administrador'), 403);

        if ($this->isProtected($managedUser)) {
            return redirect()
                ->route('usuarios.index')
                ->with('error', 'Este usuario nao pode ter a senha redefinida por esta tela.');
        }

        $validator = Validator::make($request->all(), [
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ], [
            'password' => 'senha',
            'password_confirmation' => 'confirmação da senha',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('usuarios.index')
                ->withErrors($validator, 'resetPassword')
                ->with('reset_password_action', route('usuarios.password.reset', $managedUser))
                ->with('reset_password_user_name', $managedUser->name);
        }

        $data = $validator->validated();

        $managedUser->update([
            'password' => Hash::make($data['password']),
            'force_password_change' => true,
        ]);

        return redirect()
            ->route('usuarios.index')
            ->with('success', "Senha de {$managedUser->name} redefinida com sucesso. O usuario devera troca-la no proximo acesso.");
    }

    public function destroy(Request $request, User $managedUser, UserDeletionService $userDeletionService): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(self::DELETER_ROLES), 403);

        if ($managedUser->is($request->user())) {
            return redirect()
                ->route('usuarios.index')
                ->with('error', 'Você não pode excluir o próprio usuário.');
        }

        if ($this->isProtected($managedUser) && ! $request->user()->hasRole('administrador')) {
            return redirect()
                ->route('usuarios.index')
                ->with('error', 'Este usuário não pode ser excluído.');
        }

        $userName = $managedUser->name;

        $userDeletionService->delete($managedUser);

        return redirect()
            ->route('usuarios.index')
            ->with('success', "Usuário {$userName} excluído com sucesso.");
    }

    private function assignableRoles()
    {
        $rolesToExclude = self::LEGACY_ROLES;

        return Role::whereNotIn('name', $rolesToExclude)
            ->orderBy('name')
            ->get(['name']);
    }

    private function isProtected(User $user): bool
    {
        return $user->hasAnyRole(self::PROTECTED_ROLES);
    }

    public function export(Request $request)
    {
        $regiaoId = $request->query('regiao');
        $estadoId = $request->query('estado');
        $municipioId = $request->query('municipio');

        $export = new UsersExport($regiaoId, $estadoId, $municipioId);

        if ($request->query('formato') === 'docx') {
            $doc = new WordDocument;
            $doc->addTitle('Usuários cadastrados');
            WordTableExport::render($doc, $export);

            return $doc->download('usuarios.docx');
        }

        return Excel::download($export, 'usuarios.xlsx');
    }

    public function verificarIndex(Request $request): View|RedirectResponse
    {
        $sessionKey = (string) $request->query('session_key', '');
        $rows = collect();
        $resumo = null;
        $rowsPaginator = null;

        if ($sessionKey !== '') {
            $payload = session($sessionKey);
            if (! is_array($payload) || ! array_key_exists('rows', $payload)) {
                return redirect()
                    ->route('usuarios.verificar.index')
                    ->withErrors(['arquivo' => 'Sessao de verificacao expirada. Envie o arquivo novamente.']);
            }

            $rows = collect($payload['rows'] ?? [])->values();
            $perPage = (int) $request->query('per_page', 50);
            if (! in_array($perPage, [25, 50, 100, 200], true)) {
                $perPage = 50;
            }

            $page = (int) max(1, $request->query('page', 1));
            $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

            $rowsPaginator = new LengthAwarePaginator(
                $slice,
                $rows->count(),
                $perPage,
                $page,
                [
                    'path' => route('usuarios.verificar.index'),
                    'query' => [
                        'session_key' => $sessionKey,
                        'per_page' => $perPage,
                    ],
                ]
            );

            $resumo = [
                'total_importacao' => (int) ($payload['total_count'] ?? 0),
                'usuarios_existentes' => (int) ($payload['existing_count'] ?? 0),
                'usuarios_nao_cadastrados' => (int) ($payload['new_count'] ?? 0),
                'usuarios_duplicados' => (int) ($payload['duplicate_count'] ?? 0),
                'gerado_em' => $payload['generated_at'] ?? null,
            ];
        }

        return view('usuarios.verificar', [
            'sessionKey' => $sessionKey,
            'resumo' => $resumo,
            'rows' => $rowsPaginator,
        ]);
    }

    public function verificarProcessar(Request $request): RedirectResponse
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        try {
            $import = new ParticipantesPreviewImport;
            Excel::import($import, $request->file('arquivo'));

            $rows = collect($import->rows ?? [])->values();
            $resumo = $this->montarResumoVerificacao($rows);

            $sessionKey = 'user_verification_'.Str::uuid();
            session([$sessionKey => [
                'rows' => $resumo['rows_nao_cadastrados']->values()->all(),
                'rows_completos' => $resumo['rows_verificacao_completa']->values()->all(),
                'existing_count' => $resumo['usuarios_existentes'],
                'new_count' => $resumo['usuarios_nao_cadastrados'],
                'duplicate_count' => $resumo['usuarios_duplicados'],
                'total_count' => $resumo['total_importacao'],
                'generated_at' => now()->toDateTimeString(),
            ]]);

            return redirect()
                ->route('usuarios.verificar.index', ['session_key' => $sessionKey])
                ->with('success', 'Verificacao concluida com sucesso.');
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['arquivo' => 'Falha ao processar o arquivo: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function verificarExportar(Request $request, string $format)
    {
        if (! in_array($format, ['csv', 'xlsx', 'docx'], true)) {
            abort(404);
        }

        $sessionKey = (string) $request->query('session_key', '');
        $payload = session($sessionKey);

        if (! is_array($payload) || ! array_key_exists('rows', $payload)) {
            return redirect()
                ->route('usuarios.verificar.index')
                ->withErrors(['arquivo' => 'Sessao de verificacao expirada. Envie o arquivo novamente.']);
        }

        $modelo = (string) $request->query('modelo', 'nao_cadastrados');
        if (! in_array($modelo, ['nao_cadastrados', 'completo'], true)) {
            abort(404);
        }

        if ($modelo === 'completo') {
            $rows = collect($payload['rows_completos'] ?? [])->values();
            $export = new UsuariosVerificacaoCompletaExport($rows);
            $filename = 'usuarios-verificacao-completa-'.now()->format('Ymd_His').'.'.$format;
        } else {
            $rows = collect($payload['rows'] ?? [])->values();
            $export = new UsuariosNaoCadastradosExport($rows);
            $filename = 'usuarios-nao-cadastrados-'.now()->format('Ymd_His').'.'.$format;
        }

        if ($format === 'docx') {
            $doc = new WordDocument;
            $doc->addTitle($modelo === 'completo'
                ? 'Verificação de usuários — completa'
                : 'Usuários não cadastrados');
            WordTableExport::render($doc, $export);

            return $doc->download($filename);
        }

        $writerType = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download($export, $filename, $writerType);
    }

    private function montarResumoVerificacao(Collection $rows): array
    {
        $rowsNormalizados = $rows
            ->values()
            ->map(function ($row) {
                $emailNormalizado = $this->normalizarEmail((string) ($row['email'] ?? ''));
                $cpfNormalizado = $this->normalizarCpf($row['cpf'] ?? null);
                $nomeNormalizado = $this->normalizarNome($row['nome'] ?? null);

                return [
                    'row' => $row,
                    'nome_normalizado' => $nomeNormalizado,
                    'email_normalizado' => $emailNormalizado,
                    'cpf_normalizado' => $cpfNormalizado,
                ];
            })
            ->values();

        $usuariosBase = User::query()
            ->leftJoin('participantes', 'participantes.user_id', '=', 'users.id')
            ->select('users.name', 'users.email', 'participantes.cpf')
            ->get();

        $nomesExistentes = $usuariosBase
            ->pluck('name')
            ->map(fn ($nome) => $this->normalizarNome($nome))
            ->filter()
            ->unique()
            ->values();

        $emailsExistentes = $usuariosBase
            ->pluck('email')
            ->map(fn ($email) => $this->normalizarEmail((string) $email))
            ->filter()
            ->unique()
            ->values();

        $cpfsExistentes = $usuariosBase
            ->pluck('cpf')
            ->map(fn ($cpf) => $this->normalizarCpf($cpf))
            ->filter()
            ->unique()
            ->values();

        $nomesExistentesLookup = array_fill_keys($nomesExistentes->all(), true);
        $emailsExistentesLookup = array_fill_keys($emailsExistentes->all(), true);
        $cpfsExistentesLookup = array_fill_keys($cpfsExistentes->all(), true);

        $emailsPorDominio = [];
        foreach ($emailsExistentes as $emailExistente) {
            [$local, $dominio] = $this->splitEmail($emailExistente);
            if ($dominio === null) {
                continue;
            }
            if (! array_key_exists($dominio, $emailsPorDominio)) {
                $emailsPorDominio[$dominio] = [];
            }
            $emailsPorDominio[$dominio][] = $emailExistente;
        }

        $emailsExistentesArray = $emailsExistentes->all();

        $rowsNaoCadastrados = collect();
        $rowsVerificacaoCompleta = collect();
        $usuariosExistentes = 0;
        $usuariosDuplicados = 0;

        $nomesPlanilhaProcessados = [];
        $emailsPlanilhaProcessados = [];
        $cpfsPlanilhaProcessados = [];
        $emailsPlanilhaPorDominio = [];
        $emailsPlanilhaArray = [];

        foreach ($rowsNormalizados as $item) {
            $nomeNormalizado = $item['nome_normalizado'];
            $emailNormalizado = $item['email_normalizado'];
            $cpfNormalizado = $item['cpf_normalizado'];

            $matchDbPorNome = $nomeNormalizado && isset($nomesExistentesLookup[$nomeNormalizado]);
            $matchDbPorCpf = $cpfNormalizado && isset($cpfsExistentesLookup[$cpfNormalizado]);
            $matchDbPorEmailExato = $emailNormalizado && isset($emailsExistentesLookup[$emailNormalizado]);
            $matchDbPorEmailSimilar = false;

            if (! $matchDbPorNome && ! $matchDbPorCpf && ! $matchDbPorEmailExato && $emailNormalizado) {
                [, $dominio] = $this->splitEmail($emailNormalizado);
                $candidatos = ($dominio && isset($emailsPorDominio[$dominio]))
                    ? $emailsPorDominio[$dominio]
                    : $emailsExistentesArray;

                $melhorScore = 0.0;
                foreach ($candidatos as $emailExistente) {
                    $score = $this->similaridadeEmail($emailNormalizado, $emailExistente);
                    if ($score > $melhorScore) {
                        $melhorScore = $score;
                    }
                    if ($melhorScore >= self::EMAIL_SIMILARITY_THRESHOLD) {
                        break;
                    }
                }

                $matchDbPorEmailSimilar = $melhorScore >= self::EMAIL_SIMILARITY_THRESHOLD;
            }

            $duplicadoNaPlanilha = false;
            if (
                ($nomeNormalizado && isset($nomesPlanilhaProcessados[$nomeNormalizado])) ||
                ($cpfNormalizado && isset($cpfsPlanilhaProcessados[$cpfNormalizado])) ||
                ($emailNormalizado && isset($emailsPlanilhaProcessados[$emailNormalizado]))
            ) {
                $duplicadoNaPlanilha = true;
            } elseif ($emailNormalizado) {
                [, $dominioPlanilha] = $this->splitEmail($emailNormalizado);
                $candidatosPlanilha = ($dominioPlanilha && isset($emailsPlanilhaPorDominio[$dominioPlanilha]))
                    ? $emailsPlanilhaPorDominio[$dominioPlanilha]
                    : $emailsPlanilhaArray;

                $melhorScorePlanilha = 0.0;
                foreach ($candidatosPlanilha as $emailProcessado) {
                    $score = $this->similaridadeEmail($emailNormalizado, $emailProcessado);
                    if ($score > $melhorScorePlanilha) {
                        $melhorScorePlanilha = $score;
                    }
                    if ($melhorScorePlanilha >= self::EMAIL_SIMILARITY_THRESHOLD) {
                        break;
                    }
                }
                $duplicadoNaPlanilha = $melhorScorePlanilha >= self::EMAIL_SIMILARITY_THRESHOLD;
            }

            $jaExisteNoBd = $matchDbPorNome || $matchDbPorCpf || $matchDbPorEmailExato || $matchDbPorEmailSimilar;
            $rowCompleta = $item['row'];
            $rowCompleta['ja_existe'] = $jaExisteNoBd ? 'Sim' : 'Nao';
            $rowCompleta['duplicado_planilha'] = $duplicadoNaPlanilha ? 'Sim' : 'Nao';
            $rowsVerificacaoCompleta->push($rowCompleta);

            if ($duplicadoNaPlanilha) {
                $usuariosDuplicados++;
            } elseif ($jaExisteNoBd) {
                $usuariosExistentes++;
            } else {
                $rowsNaoCadastrados->push($item['row']);
            }

            if ($nomeNormalizado) {
                $nomesPlanilhaProcessados[$nomeNormalizado] = true;
            }
            if ($cpfNormalizado) {
                $cpfsPlanilhaProcessados[$cpfNormalizado] = true;
            }
            if ($emailNormalizado) {
                $emailsPlanilhaProcessados[$emailNormalizado] = true;
                $emailsPlanilhaArray[] = $emailNormalizado;
                [, $dominioEmail] = $this->splitEmail($emailNormalizado);
                if ($dominioEmail) {
                    if (! array_key_exists($dominioEmail, $emailsPlanilhaPorDominio)) {
                        $emailsPlanilhaPorDominio[$dominioEmail] = [];
                    }
                    $emailsPlanilhaPorDominio[$dominioEmail][] = $emailNormalizado;
                }
            }
        }

        return [
            'total_importacao' => $rowsNormalizados->count(),
            'usuarios_existentes' => $usuariosExistentes,
            'usuarios_nao_cadastrados' => $rowsNaoCadastrados->count(),
            'usuarios_duplicados' => $usuariosDuplicados,
            'rows_nao_cadastrados' => $rowsNaoCadastrados->values(),
            'rows_verificacao_completa' => $rowsVerificacaoCompleta->values(),
        ];
    }

    private function normalizarCpf(mixed $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $cpf) ?: '';
        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    private function normalizarEmail(string $email): ?string
    {
        $email = trim(mb_strtolower($email));
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $dominio] = array_pad(explode('@', $email, 2), 2, null);
        if (! $local || ! $dominio) {
            return null;
        }

        $dominio = str_replace(' ', '', $dominio);
        $dominio = $this->normalizarDominio($dominio);

        // Regras de canonicalizacao para Gmail/Googlemail.
        if (in_array($dominio, ['gmail.com', 'googlemail.com'], true)) {
            $local = explode('+', $local, 2)[0];
            $local = str_replace('.', '', $local);
            $dominio = 'gmail.com';
        }

        $local = trim($local);
        if ($local === '') {
            return null;
        }

        return "{$local}@{$dominio}";
    }

    private function normalizarNome(mixed $nome): ?string
    {
        if ($nome === null) {
            return null;
        }

        $nome = trim(mb_strtolower((string) $nome));
        if ($nome === '') {
            return null;
        }

        $nome = iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome;
        $nome = preg_replace('/[^a-z0-9\s]+/', ' ', $nome) ?? $nome;
        $nome = preg_replace('/\s+/', ' ', $nome) ?? $nome;
        $nome = trim($nome);

        return $nome !== '' ? $nome : null;
    }

    private function normalizarDominio(string $dominio): string
    {
        $dominio = trim(mb_strtolower($dominio));

        $mapaErrosComuns = [
            'gmail.com.br' => 'gmail.com',
            'gmial.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmal.com' => 'gmail.com',
            'hotmial.com' => 'hotmail.com',
            'outlok.com' => 'outlook.com',
            'outllok.com' => 'outlook.com',
            'yaho.com' => 'yahoo.com',
        ];

        if (isset($mapaErrosComuns[$dominio])) {
            return $mapaErrosComuns[$dominio];
        }

        $dominiosConhecidos = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'icloud.com'];
        foreach ($dominiosConhecidos as $dominioConhecido) {
            if (levenshtein($dominio, $dominioConhecido) <= 2) {
                return $dominioConhecido;
            }
        }

        return $dominio;
    }

    private function splitEmail(string $email): array
    {
        if (! str_contains($email, '@')) {
            return [null, null];
        }

        [$local, $dominio] = array_pad(explode('@', $email, 2), 2, null);

        return [$local, $dominio];
    }

    private function similaridadeEmail(string $emailA, string $emailB): float
    {
        if ($emailA === $emailB) {
            return 1.0;
        }

        [$localA, $dominioA] = $this->splitEmail($emailA);
        [$localB, $dominioB] = $this->splitEmail($emailB);

        $scoreCompleto = $this->similaridadeLevenshtein($emailA, $emailB);
        $scoreLocal = $this->similaridadeLevenshtein((string) $localA, (string) $localB);
        $scoreDominio = $this->similaridadeLevenshtein((string) $dominioA, (string) $dominioB);

        // Dominio pesa mais por reduzir falso positivo entre provedores distintos.
        $scorePonderado = ($scoreLocal * 0.4) + ($scoreDominio * 0.6);

        return max($scoreCompleto, $scorePonderado);
    }

    private function similaridadeLevenshtein(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distancia = levenshtein($a, $b);
        $score = 1 - ($distancia / $maxLen);

        return max(0.0, min(1.0, $score));
    }
}
