<?php

namespace App\Http\Controllers\Cartas;

use App\Http\Controllers\Controller;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use App\Notifications\Cartas\ReativacaoCadastroNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    private const PENDING_REGISTRATION_SESSION_KEY = 'cartas.pending_registration';

    public function apresentacao(): View
    {
        return view('cartas.apresentacao');
    }

    public function login(): View
    {
        return view('cartas.auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe sua senha.',
        ]);

        $user = User::where('email', $credentials['email'])
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->first();

        if (! $user || ! $user->isCartasUser()) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'sistema_origem' => User::SISTEMA_CARTAS,
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        $request->session()->regenerate();

        if (! $request->user()->cartas_terms_accepted_at) {
            return redirect()->route('cartas.terms');
        }

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('cartas.verification.notice');
        }

        return redirect()->intended(route('cartas.dashboard'));
    }

    public function register(): View
    {
        return view('cartas.auth.register');
    }

    public function estados(): JsonResponse
    {
        return response()->json(Estado::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'sigla']));
    }

    public function municipios(int $estadoId): JsonResponse
    {
        return response()->json(Municipio::query()
            ->where('estado_id', $estadoId)
            ->orderBy('nome')
            ->get(['id', 'nome']));
    }

    public function storeRegister(Request $request): RedirectResponse
    {
        $data = $this->validatedRegistrationData($request);

        $municipio = Municipio::query()
            ->whereKey($data['municipio_id'])
            ->where('estado_id', $data['estado_id'])
            ->firstOrFail();

        $trashedUser = User::onlyTrashed()
            ->where('email', $data['email'])
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->first();

        if ($trashedUser) {
            return $this->requestReactivation($trashedUser, $data, $municipio);
        }

        $user = DB::transaction(function () use ($data, $municipio) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'sistema_origem' => User::SISTEMA_CARTAS,
                'cartas_tipo_vinculo' => $data['cartas_tipo_vinculo'],
                'cartas_limite_respostas' => $data['cartas_limite_respostas'],
                'cartas_terms_accepted_at' => now(),
            ]);

            $user->participante()->updateOrCreate(['user_id' => $user->id], [
                'cpf' => $data['cpf'],
                'telefone' => $data['telefone'],
                'municipio_id' => $municipio->id,
            ]);

            if ($role = Role::where('name', 'cartas_voluntario')->where('guard_name', 'web')->first()) {
                $user->assignRole($role);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        return redirect()->route('cartas.verification.notice');
    }

    /**
     * Conta desativada com este e-mail: não recria/loga nada agora. Os dados
     * enviados ficam pendentes até a pessoa provar (clicando no link enviado
     * por e-mail) que controla essa caixa de entrada — sem isso, qualquer um
     * que soubesse o e-mail de uma conta desativada poderia sequestrá-la (o
     * Cartas já loga o usuário antes de checar e-mail verificado, então
     * reenviar a verificação depois não fecharia essa janela).
     */
    private function requestReactivation(User $trashedUser, array $data, Municipio $municipio): RedirectResponse
    {
        $pending = [
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
            'cartas_tipo_vinculo' => $data['cartas_tipo_vinculo'],
            'cartas_limite_respostas' => $data['cartas_limite_respostas'],
            'cpf' => $data['cpf'],
            'telefone' => $data['telefone'],
            'municipio_id' => $municipio->id,
        ];

        Cache::put("reactivation-pending:{$trashedUser->id}", $pending, now()->addMinutes(60));

        $url = URL::temporarySignedRoute(
            'cartas.register.reactivate',
            now()->addMinutes(60),
            ['user' => $trashedUser->id]
        );

        $trashedUser->notify(new ReativacaoCadastroNotification($url));

        return redirect()->route('cartas.register.reactivate.pending');
    }

    /**
     * Tela exibida após o pedido de reativação: aguarda o clique no link
     * enviado por e-mail (ver requestReactivation()).
     */
    public function reactivationPending(): View
    {
        return view('cartas.auth.reactivation-pending');
    }

    /**
     * Confirmação do link assinado enviado por requestReactivation(): só aqui
     * a conta é de fato restaurada e atualizada com os dados pendentes.
     */
    public function confirmReactivation(int $user): RedirectResponse
    {
        $trashedUser = User::onlyTrashed()->find($user);

        if (! $trashedUser) {
            return redirect()->route('cartas.login')
                ->with('status', 'Esta conta já está ativa. Faça login normalmente.');
        }

        $pending = Cache::pull("reactivation-pending:{$trashedUser->id}");

        if (! $pending) {
            return redirect()->route('cartas.register')
                ->withErrors(['email' => 'O link de confirmação expirou. Refaça o cadastro.']);
        }

        $trashedUser = DB::transaction(function () use ($trashedUser, $pending) {
            $trashedUser->restore();
            $trashedUser->update([
                'name' => $pending['name'],
                'password' => $pending['password'],
                'cartas_tipo_vinculo' => $pending['cartas_tipo_vinculo'],
                'cartas_limite_respostas' => $pending['cartas_limite_respostas'],
                'cartas_terms_accepted_at' => now(),
            ]);
            // Clicar no link já prova o e-mail: evita reenviar outra verificação.
            $trashedUser->markEmailAsVerified();

            $participante = $trashedUser->participante()->withTrashed()->first();
            $participante?->restore();
            $trashedUser->participante()->updateOrCreate(['user_id' => $trashedUser->id], [
                'cpf' => $pending['cpf'],
                'telefone' => $pending['telefone'],
                'municipio_id' => $pending['municipio_id'],
            ]);

            if ($role = Role::where('name', 'cartas_voluntario')->where('guard_name', 'web')->first()) {
                if (! $trashedUser->hasRole($role)) {
                    $trashedUser->assignRole($role);
                }
            }

            return $trashedUser;
        });

        Auth::login($trashedUser);

        return redirect()->route('cartas.dashboard');
    }

    public function terms(Request $request): RedirectResponse|View
    {
        if ($request->user()) {
            if (! $request->user()->isCartasUser()) {
                abort(403);
            }

            return view('cartas.auth.terms');
        }

        if (! $request->session()->has(self::PENDING_REGISTRATION_SESSION_KEY)) {
            return redirect()->route('cartas.register');
        }

        return view('cartas.auth.terms');
    }

    public function acceptTerms(Request $request): RedirectResponse
    {
        if (! $request->user() && $request->session()->has(self::PENDING_REGISTRATION_SESSION_KEY)) {
            return $this->completePendingRegistration($request);
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('cartas.register');
        }

        if (! $user->isCartasUser()) {
            abort(403);
        }

        $user->forceFill(['cartas_terms_accepted_at' => now()])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('cartas.verification.notice');
    }

    public function verificationNotice(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('cartas.dashboard')
            : view('cartas.auth.verify-email');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('cartas.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    public function forgotPassword(): View
    {
        return view('cartas.auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        $user = User::where('email', $request->email)
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->first();

        if (! $user || ! $user->isCartasUser()) {
            return back()->with('status', 'Se o e-mail estiver cadastrado, enviaremos as instruções de recuperação.');
        }

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        return back()->with('status', 'Enviamos o link de redefinição de senha para seu e-mail.');
    }

    public function resetPassword(Request $request, string $token): View
    {
        return view('cartas.auth.reset-password', [
            'request' => $request,
            'token' => $token,
        ]);
    }

    public function storeNewPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'token.required' => 'O token de redefinição é obrigatório.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
        ]);

        $user = User::where('email', $request->email)
            ->where('sistema_origem', User::SISTEMA_CARTAS)
            ->first();

        if (! $user?->isCartasUser()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Não foi possível redefinir a senha para este e-mail.']);
        }

        if (! Password::broker()->tokenExists($user, $request->token)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Este link de redefinição de senha é inválido ou expirou.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();
        Password::broker()->deleteToken($user);

        return redirect()->route('cartas.login')->with('status', 'Sua senha foi redefinida com sucesso.');
    }

    private function prepareRegistrationData(Request $request): array
    {
        $toNull = fn ($value) => $value === '' || $value === null ? null : $value;

        return array_merge($request->all(), [
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'cpf' => $toNull(preg_replace('/\D+/', '', (string) $request->input('cpf'))),
            'telefone' => $toNull(preg_replace('/\D+/', '', (string) $request->input('telefone'))),
            'estado_id' => $toNull($request->input('estado_id')),
            'municipio_id' => $toNull($request->input('municipio_id')),
            'cartas_tipo_vinculo' => $toNull($request->input('cartas_tipo_vinculo')),
            'cartas_limite_respostas' => $toNull($request->input('cartas_limite_respostas')),
        ]);
    }

    private function validatedRegistrationData(Request $request): array
    {
        $data = $this->prepareRegistrationData($request);
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->where('sistema_origem', User::SISTEMA_CARTAS)->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cpf' => ['required', 'digits:11'],
            'telefone' => ['required', 'regex:/^\d{10,11}$/'],
            'estado_id' => ['required', 'integer', Rule::exists('estados', 'id')],
            'municipio_id' => ['required', 'integer', Rule::exists('municipios', 'id')],
            'cartas_tipo_vinculo' => ['required', 'string', Rule::in([User::VINCULO_PETROBRAS, User::VINCULO_COMUNIDADE])],
            'cartas_limite_respostas' => ['required', 'integer', 'min:1', 'max:5'],
            'termos_aceitos' => ['accepted'],
        ], [
            'cpf.required' => 'Informe seu CPF.',
            'cpf.digits' => 'CPF deve conter 11 dígitos.',
            'telefone.required' => 'Informe seu telefone.',
            'telefone.regex' => 'Telefone deve ter DDD e 10 ou 11 dígitos.',
            'estado_id.required' => 'Selecione seu estado.',
            'municipio_id.required' => 'Selecione seu município.',
            'cartas_tipo_vinculo.required' => 'Informe se você é funcionário da Petrobras.',
            'cartas_tipo_vinculo.in' => 'Opção inválida para o vínculo com a Petrobras.',
            'cartas_limite_respostas.required' => 'Informe o limite de respostas.',
            'cartas_limite_respostas.integer' => 'O limite de respostas deve ser um número.',
            'cartas_limite_respostas.min' => 'O limite mínimo de respostas é 1.',
            'cartas_limite_respostas.max' => 'O limite máximo de respostas é 5.',
            'termos_aceitos.accepted' => 'Você precisa aceitar os termos de uso para continuar.',
        ]);

        $validator->after(function ($validator) use ($data) {
            if (isset($data['estado_id'], $data['municipio_id']) && ! Municipio::query()
                ->whereKey($data['municipio_id'])
                ->where('estado_id', $data['estado_id'])
                ->exists()) {
                $validator->errors()->add('municipio_id', 'O município selecionado não pertence ao estado informado.');
            }

            if (! $this->isValidCpf($data['cpf'] ?? '')) {
                $validator->errors()->add('cpf', 'CPF inválido.');
            } elseif ($this->cpfDuplicado($data['cpf'])) {
                $validator->errors()->add('cpf', 'Este CPF já possui cadastro no sistema.');
            }
        });

        return $validator->validate();
    }

    private function completePendingRegistration(Request $request): RedirectResponse
    {
        $pendingRegistration = $request->session()->get(self::PENDING_REGISTRATION_SESSION_KEY);
        $data = Validator::make($pendingRegistration, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->where('sistema_origem', User::SISTEMA_CARTAS)->whereNull('deleted_at')],
            'password' => ['required', 'string'],
            'cpf' => ['required', 'digits:11'],
            'telefone' => ['required', 'regex:/^\d{10,11}$/'],
            'estado_id' => ['required', 'integer', Rule::exists('estados', 'id')],
            'municipio_id' => ['required', 'integer', Rule::exists('municipios', 'id')],
            'cartas_tipo_vinculo' => ['required', 'string', Rule::in([User::VINCULO_PETROBRAS, User::VINCULO_COMUNIDADE])],
            'cartas_limite_respostas' => ['required', 'integer', 'min:1', 'max:5'],
        ], [
            'telefone.required' => 'Informe seu telefone.',
            'telefone.regex' => 'Telefone deve ter DDD e 10 ou 11 dígitos.',
            'cartas_tipo_vinculo.required' => 'Informe se você é funcionário da Petrobras.',
            'cartas_tipo_vinculo.in' => 'Opção inválida para o vínculo com a Petrobras.',
            'cartas_limite_respostas.required' => 'Informe o limite de respostas.',
            'cartas_limite_respostas.integer' => 'O limite de respostas deve ser um número.',
            'cartas_limite_respostas.min' => 'O limite mínimo de respostas é 1.',
            'cartas_limite_respostas.max' => 'O limite máximo de respostas é 5.',
        ])->after(function ($validator) use ($pendingRegistration) {
            if (isset($pendingRegistration['estado_id'], $pendingRegistration['municipio_id']) && ! Municipio::query()
                ->whereKey($pendingRegistration['municipio_id'])
                ->where('estado_id', $pendingRegistration['estado_id'])
                ->exists()) {
                $validator->errors()->add('municipio_id', 'O município selecionado não pertence ao estado informado.');
            }

            $cpf = $pendingRegistration['cpf'] ?? '';
            if (! $this->isValidCpf($cpf)) {
                $validator->errors()->add('cpf', 'CPF inválido.');
            } elseif ($this->cpfDuplicado($cpf)) {
                $validator->errors()->add('cpf', 'Este CPF já possui cadastro no sistema.');
            }
        })->validate();

        $municipio = Municipio::query()
            ->whereKey($data['municipio_id'])
            ->where('estado_id', $data['estado_id'])
            ->firstOrFail();

        $user = DB::transaction(function () use ($data, $municipio) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'sistema_origem' => User::SISTEMA_CARTAS,
                'cartas_tipo_vinculo' => $data['cartas_tipo_vinculo'],
                'cartas_limite_respostas' => $data['cartas_limite_respostas'],
                'cartas_terms_accepted_at' => now(),
            ]);

            $user->participante()->updateOrCreate(['user_id' => $user->id], [
                'cpf' => $data['cpf'],
                'telefone' => $data['telefone'] ?? null,
                'municipio_id' => $municipio->id,
            ]);

            if ($role = Role::where('name', 'cartas_voluntario')->where('guard_name', 'web')->first()) {
                $user->assignRole($role);
            }

            return $user;
        });

        $request->session()->forget(self::PENDING_REGISTRATION_SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();

        return redirect()->route('cartas.verification.notice');
    }

    private function cpfDuplicado(string $cpf): bool
    {
        return Participante::query()->whereNotNull('cpf')
            ->whereHas('user', fn ($query) => $query->where('sistema_origem', User::SISTEMA_CARTAS))
            ->whereRaw("regexp_replace(cpf, '[^0-9]', '', 'g') = ?", [$cpf])
            ->exists();
    }

    public function markWelcomeSeen(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isCartasUser() && is_null($user->cartas_welcome_seen_at)) {
            $user->forceFill(['cartas_welcome_seen_at' => now()])->save();
        }

        return response()->json(['success' => true]);
    }

    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D+/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $position) {
            $sum = 0;
            for ($index = 0, $weight = $position + 1; $index < $position; $index++, $weight--) {
                $sum += (int) $cpf[$index] * $weight;
            }
            $digit = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
