<?php

namespace Tests\Feature\Cartas;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Regiao;
use App\Models\User;
use App\Notifications\Cartas\ReativacaoCadastroNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CartasRegistrationReactivationTest extends TestCase
{
    use RefreshDatabase;

    private Estado $estado;

    private Municipio $municipio;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'cartas_voluntario', 'guard_name' => 'web']);

        $regiao = Regiao::create(['nome' => 'Sudeste']);
        $this->estado = Estado::create(['nome' => 'São Paulo', 'sigla' => 'SP', 'regiao_id' => $regiao->id]);
        $this->municipio = Municipio::create([
            'nome' => 'São Paulo',
            'estado_id' => $this->estado->id,
            'regiao_id' => $regiao->id,
        ]);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Voluntário Novo',
            'password' => 'Senha123!@#',
            'password_confirmation' => 'Senha123!@#',
            'cpf' => '529.982.247-25',
            'telefone' => '(11) 99999-9999',
            'estado_id' => $this->estado->id,
            'municipio_id' => $this->municipio->id,
            'termos_aceitos' => '1',
            'cartas_tipo_vinculo' => 'petrobras',
            'cartas_limite_respostas' => 3,
        ], $overrides);
    }

    public function test_reactivation_pending_screen_can_be_rendered(): void
    {
        $response = $this->get(route('cartas.register.reactivate.pending'));

        $response->assertStatus(200);
    }

    public function test_registering_with_deactivated_account_email_sends_reactivation_link_without_logging_in(): void
    {
        $user = User::factory()->create([
            'email' => 'desativado@exemplo.com',
            'sistema_origem' => User::SISTEMA_CARTAS,
        ]);
        $participante = $user->participante()->updateOrCreate(['user_id' => $user->id], [
            'cpf' => '11111111111',
            'municipio_id' => $this->municipio->id,
        ]);
        $participante->delete();
        $user->delete();

        Notification::fake();

        $response = $this->post(route('cartas.register.store'), $this->registrationPayload([
            'email' => 'desativado@exemplo.com',
        ]));

        $this->assertGuest();
        $response->assertRedirect(route('cartas.register.reactivate.pending'));

        $this->assertSame(1, User::withTrashed()->where('email', 'desativado@exemplo.com')->count());
        $this->assertNotNull($user->fresh()->deleted_at);

        Notification::assertSentTo($user, ReativacaoCadastroNotification::class);
    }

    public function test_confirming_reactivation_link_restores_and_updates_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Nome Antigo',
            'email' => 'desativado@exemplo.com',
            'sistema_origem' => User::SISTEMA_CARTAS,
        ]);
        $participante = $user->participante()->updateOrCreate(['user_id' => $user->id], [
            'cpf' => '11111111111',
            'municipio_id' => $this->municipio->id,
        ]);
        $participante->delete();
        $user->delete();

        Notification::fake();

        $this->post(route('cartas.register.store'), $this->registrationPayload([
            'name' => 'Nome Novo',
            'email' => 'desativado@exemplo.com',
        ]));

        $confirmationUrl = null;
        Notification::assertSentTo(
            $user,
            ReativacaoCadastroNotification::class,
            function (ReativacaoCadastroNotification $notification) use (&$confirmationUrl) {
                $confirmationUrl = $notification->url;

                return true;
            }
        );
        $this->assertNotNull($confirmationUrl);

        $response = $this->get($confirmationUrl);

        $this->assertAuthenticated();
        $response->assertRedirect(route('cartas.dashboard'));

        $user->refresh();
        $this->assertNull($user->deleted_at);
        $this->assertSame('Nome Novo', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole('cartas_voluntario'));

        $participante->refresh();
        $this->assertNull($participante->deleted_at);
        $this->assertSame('52998224725', $participante->cpf);

        $this->assertNull(Cache::get("reactivation-pending:{$user->id}"));
    }
}
