<?php

namespace App\Http\Controllers\Cartas;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use App\Models\User;
use App\Notifications\Cartas\AvaliacaoEnviadaNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const ROLES = [
        'cartas_admin' => 'Administrador',
        'cartas_gestao' => 'Gestor',
        'cartas_voluntario' => 'Voluntário',
    ];

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->with(['roles', 'participante.municipio.estado'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $avaliacoes = Avaliacao::where('is_cartas', true)
            ->whereNull('atividade_id')
            ->where('formulario_aberto', true)
            ->with('templateAvaliacao')
            ->orderBy('id', 'desc')
            ->get();

        return view('cartas.usuarios.index', [
            'users' => $users,
            'roles' => self::ROLES,
            'search' => $search,
            'avaliacoes' => $avaliacoes,
        ]);
    }

    public function edit(Request $request, User $managedUser): View
    {
        $this->authorizeAdmin($request);
        $this->ensureCartasUser($managedUser);

        return view('cartas.usuarios.edit', [
            'managedUser' => $managedUser->load('roles'),
            'roles' => self::ROLES,
        ]);
    }

    public function update(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->ensureCartasUser($managedUser);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('sistema_origem', User::SISTEMA_CARTAS)
                    ->ignore($managedUser->id),
            ],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'cartas_limite_respostas' => ['required', 'integer', 'min:1', 'max:5'],
            'cartas_tipo_vinculo' => ['nullable', Rule::in(array_keys(User::VINCULOS_CARTAS))],
        ], [
            'name.required' => 'Informe o nome do usuário.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso no Cartas.',
            'role.required' => 'Selecione o perfil de acesso.',
            'role.in' => 'Selecione um perfil válido.',
            'cartas_limite_respostas.required' => 'Informe o limite de cartas.',
            'cartas_limite_respostas.min' => 'O limite mínimo é 1.',
            'cartas_limite_respostas.max' => 'O limite máximo é 5.',
            'cartas_tipo_vinculo.in' => 'Selecione um tipo de vínculo válido.',
        ]);

        if ($request->user()->is($managedUser) && $data['role'] !== 'cartas_admin') {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Você não pode remover o próprio acesso de administrador.']);
        }

        $managedUser->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'cartas_limite_respostas' => $data['cartas_limite_respostas'],
            'cartas_tipo_vinculo' => $data['cartas_tipo_vinculo'],
        ]);

        $managedUser->syncRoles([$data['role']]);

        return redirect()
            ->route('cartas.usuarios.index')
            ->with('status', 'Usuário atualizado.');
    }

    public function enviarAvaliacao(Request $request, User $managedUser): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $this->ensureCartasUser($managedUser);

        $data = $request->validate([
            'avaliacao_id' => ['required', 'integer', 'exists:avaliacaos,id'],
        ], [
            'avaliacao_id.required' => 'Selecione uma avaliação.',
            'avaliacao_id.exists' => 'Avaliação não encontrada.',
        ]);

        $avaliacao = Avaliacao::where('is_cartas', true)
            ->whereNull('atividade_id')
            ->where('formulario_aberto', true)
            ->findOrFail($data['avaliacao_id']);

        $avaliacao->load('templateAvaliacao');

        $managedUser->notify(new AvaliacaoEnviadaNotification($avaliacao));

        $titulo = $avaliacao->descricao_universal
            ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação');

        return redirect()
            ->route('cartas.usuarios.index')
            ->with('status', "Avaliação \"{$titulo}\" enviada para {$managedUser->email}.");
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('cartas_admin'), 403);
    }

    private function ensureCartasUser(User $user): void
    {
        abort_unless($user->sistema_origem === User::SISTEMA_CARTAS, 404);
    }
}
