<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use App\Notifications\ReativacaoCadastroNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $municipios = Municipio::with('estado')
            ->orderBy('nome')
            ->get(['id', 'nome', 'estado_id']);

        $participanteTags = config('engaja.participante_tags', Participante::TAGS);

        return view('auth.register', compact('municipios', 'participanteTags'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($this->prepareData($request), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->where('sistema_origem', User::SISTEMA_ENGAJA)->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cpf' => ['required', 'digits:11'],
            'telefone' => ['nullable', 'regex:/^\d{10,11}$/'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'escola_unidade' => ['nullable', 'string', 'max:255'],
            'tipo_organizacao' => ['nullable', 'string', 'max:255', Rule::in(config('engaja.organizacoes', []))],
            'tag' => ['nullable', Rule::in(Participante::TAGS)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'identidade_genero' => ['required', 'string'],
            'identidade_genero_outro' => ['nullable', 'string', 'max:255', 'required_if:identidade_genero,Outro'],
            'raca_cor' => ['required', 'string'],
            'comunidade_tradicional' => ['required', 'string'],
            'comunidade_tradicional_outro' => ['nullable', 'string', 'max:255', 'required_if:comunidade_tradicional,Outro'],
            'faixa_etaria' => ['required', 'string'],
            'pcd' => ['required', 'string'],
            'orientacao_sexual' => ['required', 'string'],
            'orientacao_sexual_outra' => ['nullable', 'string', 'max:255', 'required_if:orientacao_sexual,Outra'],
        ], [
            'cpf.required' => 'O campo CPF é obrigatório.',
            'cpf.digits' => 'CPF deve conter 11 dígitos.',
            'telefone.regex' => 'Telefone deve ter DDD e 10 ou 11 dígitos.',
            'municipio_id.exists' => 'Município inválido.',
            'tipo_organizacao.in' => 'Selecione um tipo de organização válido.',
            'tag.in' => 'Selecione uma tag válida.',
            'profile_photo.image' => 'Envie um arquivo de imagem válido.',
            'profile_photo.mimes' => 'A foto deve estar em JPG, JPEG, PNG, GIF ou WEBP.',
            'profile_photo.max' => 'A foto deve ter no máximo 5 MB.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $cpf = $this->normalizeCpf($request->input('cpf'));
            if ($cpf && ! $this->isValidCpf($cpf)) {
                $validator->errors()->add('cpf', 'CPF inválido.');

                return;
            }

            if ($cpf && $this->cpfDuplicado($cpf)) {
                $validator->errors()->add('cpf', 'Este CPF já possui cadastro no sistema.');
            }
        });

        $data = $validator->validate();

        $trashedUser = User::onlyTrashed()
            ->where('email', $data['email'])
            ->where('sistema_origem', User::SISTEMA_ENGAJA)
            ->first();

        if ($trashedUser) {
            return $this->requestReactivation($request, $trashedUser, $data);
        }

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create($this->buildUserAttributes($data, Hash::make($data['password'])));

            $user->participante()->updateOrCreate(
                ['user_id' => $user->id],
                $this->buildParticipanteAttributes($data)
            );

            $user->assignRole('participante');

            if ($request->hasFile('profile_photo')) {
                $user->forceFill([
                    'profile_photo_path' => $this->storeProfilePhoto($request, $user),
                ])->save();
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect('/');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserAttributes(array $data, string $hashedPassword): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $hashedPassword,
            'sistema_origem' => User::SISTEMA_ENGAJA,
            'identidade_genero' => $data['identidade_genero'],
            'identidade_genero_outro' => $data['identidade_genero_outro'] ?? null,
            'raca_cor' => $data['raca_cor'],
            'comunidade_tradicional' => $data['comunidade_tradicional'],
            'comunidade_tradicional_outro' => $data['comunidade_tradicional_outro'] ?? null,
            'faixa_etaria' => $data['faixa_etaria'],
            'pcd' => $data['pcd'],
            'orientacao_sexual' => $data['orientacao_sexual'],
            'orientacao_sexual_outra' => $data['orientacao_sexual_outra'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParticipanteAttributes(array $data): array
    {
        return [
            'cpf' => $data['cpf'],
            'telefone' => $data['telefone'] ?? null,
            'municipio_id' => $data['municipio_id'] ?? null,
            'escola_unidade' => $data['escola_unidade'] ?? null,
            'tipo_organizacao' => $data['tipo_organizacao'] ?? null,
            'tag' => $data['tag'] ?? null,
        ];
    }

    /**
     * Conta desativada com este e-mail: não recria/loga nada agora. Os dados
     * enviados ficam pendentes até a pessoa provar (clicando no link enviado
     * por e-mail) que controla essa caixa de entrada — sem isso, qualquer um
     * que soubesse o e-mail de uma conta desativada poderia sequestrá-la.
     */
    private function requestReactivation(Request $request, User $trashedUser, array $data): RedirectResponse
    {
        $pending = $this->buildUserAttributes($data, Hash::make($data['password']));
        $pending['participante'] = $this->buildParticipanteAttributes($data);

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
            $pending['pending_photo_path'] = $file->storeAs(
                "pending-cadastros/{$trashedUser->id}",
                "perfil.{$extension}",
                'public'
            );
        }

        Cache::put("reactivation-pending:{$trashedUser->id}", $pending, now()->addMinutes(60));

        $url = URL::temporarySignedRoute(
            'register.reactivate',
            now()->addMinutes(60),
            ['user' => $trashedUser->id]
        );

        $trashedUser->notify(new ReativacaoCadastroNotification($url));

        return redirect()->route('register.reactivate.pending');
    }

    /**
     * Tela exibida após o pedido de reativação: aguarda o clique no link
     * enviado por e-mail (ver requestReactivation()).
     */
    public function reactivationPending(): View
    {
        return view('auth.reactivation-pending');
    }

    /**
     * Confirmação do link assinado enviado por requestReactivation(): só aqui
     * a conta é de fato restaurada e atualizada com os dados pendentes.
     */
    public function confirmReactivation(int $user): RedirectResponse
    {
        $trashedUser = User::onlyTrashed()->find($user);

        if (! $trashedUser) {
            return redirect()->route('login')
                ->with('status', 'Esta conta já está ativa. Faça login normalmente.');
        }

        $pending = Cache::pull("reactivation-pending:{$trashedUser->id}");

        if (! $pending) {
            return redirect()->route('register')
                ->withErrors(['email' => 'O link de confirmação expirou. Refaça o cadastro.']);
        }

        $participanteAttributes = $pending['participante'];
        $pendingPhotoPath = $pending['pending_photo_path'] ?? null;
        unset($pending['participante'], $pending['pending_photo_path']);

        $trashedUser = DB::transaction(function () use ($trashedUser, $pending, $participanteAttributes, $pendingPhotoPath) {
            $trashedUser->restore();
            $trashedUser->update($pending);

            $participante = $trashedUser->participante()->withTrashed()->first();
            $participante?->restore();
            $trashedUser->participante()->updateOrCreate(['user_id' => $trashedUser->id], $participanteAttributes);

            if (! $trashedUser->hasRole('participante')) {
                $trashedUser->assignRole('participante');
            }

            if ($pendingPhotoPath) {
                $extension = pathinfo($pendingPhotoPath, PATHINFO_EXTENSION);
                $finalPath = "users/{$trashedUser->id}/perfil.{$extension}";

                if ($trashedUser->profile_photo_path) {
                    Storage::disk('public')->delete($trashedUser->profile_photo_path);
                }

                Storage::disk('public')->move($pendingPhotoPath, $finalPath);
                $trashedUser->forceFill(['profile_photo_path' => $finalPath])->save();
            }

            return $trashedUser;
        });

        Auth::login($trashedUser);

        return redirect('/');
    }

    private function prepareData(Request $request): array
    {
        $toNull = fn ($value) => ($value === '' || $value === null) ? null : $value;

        return array_merge($request->all(), [
            'name' => isset($request->name) ? trim((string) $request->name) : null,
            'email' => isset($request->email) ? trim((string) $request->email) : null,
            'cpf' => $toNull(preg_replace('/\D+/', '', (string) ($request->cpf ?? '')) ?: null),
            'telefone' => $toNull(preg_replace('/\D+/', '', (string) ($request->telefone ?? '')) ?: null),
            'municipio_id' => $toNull($request->municipio_id ?? null),
            'escola_unidade' => $toNull(isset($request->escola_unidade) ? trim((string) $request->escola_unidade) : null),
            'tipo_organizacao' => $toNull(isset($request->tipo_organizacao) ? trim((string) $request->tipo_organizacao) : null),
            'tag' => $toNull(isset($request->tag) ? trim((string) $request->tag) : null),
            'profile_photo' => $request->file('profile_photo'),
        ]);
    }

    private function storeProfilePhoto(Request $request, User $user): string
    {
        $file = $request->file('profile_photo');
        $directory = "users/{$user->id}/perfil";
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = "perfil.{$extension}";

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        return $file->storeAs($directory, $filename, 'public');
    }

    private function normalizeCpf(?string $cpf): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($cpf ?? ''));

        return $digits !== '' ? $digits : null;
    }

    private function cpfDuplicado(string $cpf): bool
    {
        $cpf = $this->normalizeCpf($cpf);
        if (! $cpf) {
            return false;
        }

        return Participante::query()
            ->whereNotNull('cpf')
            ->whereHas('user', fn ($query) => $query->where('sistema_origem', User::SISTEMA_ENGAJA))
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
        for ($i = 0, $weight = 10; $i < 9; $i++, $weight--) {
            $sum += (int) $cpf[$i] * $weight;
        }
        $remainder = $sum % 11;
        $digitOne = ($remainder < 2) ? 0 : 11 - $remainder;

        $sum = 0;
        for ($i = 0, $weight = 11; $i < 10; $i++, $weight--) {
            $sum += (int) $cpf[$i] * $weight;
        }
        $remainder = $sum % 11;
        $digitTwo = ($remainder < 2) ? 0 : 11 - $remainder;

        return ($cpf[9] == $digitOne) && ($cpf[10] == $digitTwo);
    }
}
