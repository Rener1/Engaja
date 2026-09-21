<?php

namespace Tests\Feature\Auth;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\Regiao;
use App\Models\User;
use App\Notifications\ReativacaoCadastroNotification;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_reactivation_pending_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register.reactivate.pending'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Storage::fake('public');

        $regiao = Regiao::create(['nome' => 'Nordeste']);
        $estado = Estado::create([
            'nome' => 'Ceara',
            'sigla' => 'CE',
            'regiao_id' => $regiao->id,
        ]);
        $municipio = Municipio::create([
            'nome' => 'Fortaleza',
            'estado_id' => $estado->id,
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'cpf' => '390.533.447-05',
            'telefone' => '(85) 99999-0000',
            'municipio_id' => $municipio->id,
            'escola_unidade' => 'Escola Teste',
            'tipo_organizacao' => config('engaja.organizacoes')[0] ?? null,
            'tag' => Participante::TAGS[0],
            'identidade_genero' => 'Mulher Cisgênero',
            'raca_cor' => 'Parda',
            'comunidade_tradicional' => 'Não',
            'faixa_etaria' => 'Adulto (18 a 59 anos)',
            'pcd' => 'Não',
            'orientacao_sexual' => 'Heterossexual',
            'profile_photo' => UploadedFile::fake()->image('perfil.jpg'),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');

        $this->assertDatabaseHas('participantes', [
            'cpf' => '39053344705',
            'telefone' => '85999990000',
            'municipio_id' => $municipio->id,
            'escola_unidade' => 'Escola Teste',
            'tag' => Participante::TAGS[0],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Novo Nome',
            'password' => 'password',
            'password_confirmation' => 'password',
            'cpf' => '390.533.447-05',
            'telefone' => '(85) 99999-0000',
            'escola_unidade' => 'Escola Teste',
            'tipo_organizacao' => config('engaja.organizacoes')[0] ?? null,
            'tag' => Participante::TAGS[0],
            'identidade_genero' => 'Mulher Cisgênero',
            'raca_cor' => 'Parda',
            'comunidade_tradicional' => 'Não',
            'faixa_etaria' => 'Adulto (18 a 59 anos)',
            'pcd' => 'Não',
            'orientacao_sexual' => 'Heterossexual',
        ], $overrides);
    }

    public function test_registering_with_deactivated_account_email_sends_reactivation_link_without_logging_in(): void
    {
        Notification::fake();

        $regiao = Regiao::create(['nome' => 'Nordeste']);
        $estado = Estado::create(['nome' => 'Ceara', 'sigla' => 'CE', 'regiao_id' => $regiao->id]);
        $municipio = Municipio::create(['nome' => 'Fortaleza', 'estado_id' => $estado->id]);

        $user = User::factory()->create([
            'email' => 'desativado@example.com',
            'sistema_origem' => User::SISTEMA_ENGAJA,
        ]);
        $participante = $user->participante()->updateOrCreate(['user_id' => $user->id], [
            'cpf' => '11111111111',
            'municipio_id' => $municipio->id,
        ]);
        $participante->delete();
        $user->delete();

        $response = $this->post('/register', $this->registrationPayload([
            'email' => 'desativado@example.com',
            'municipio_id' => $municipio->id,
        ]));

        $this->assertGuest();
        $response->assertRedirect(route('register.reactivate.pending'));

        $this->assertSame(1, User::withTrashed()->where('email', 'desativado@example.com')->count());
        $this->assertNotNull($user->fresh()->deleted_at);

        Notification::assertSentTo($user, ReativacaoCadastroNotification::class);
    }

    public function test_confirming_reactivation_link_restores_and_updates_account(): void
    {
        Notification::fake();

        $regiao = Regiao::create(['nome' => 'Nordeste']);
        $estado = Estado::create(['nome' => 'Ceara', 'sigla' => 'CE', 'regiao_id' => $regiao->id]);
        $municipio = Municipio::create(['nome' => 'Fortaleza', 'estado_id' => $estado->id]);

        $user = User::factory()->create([
            'name' => 'Nome Antigo',
            'email' => 'desativado@example.com',
            'sistema_origem' => User::SISTEMA_ENGAJA,
        ]);
        $participante = $user->participante()->updateOrCreate(['user_id' => $user->id], [
            'cpf' => '11111111111',
            'escola_unidade' => 'Escola Antiga',
            'municipio_id' => $municipio->id,
        ]);
        $participante->delete();
        $user->delete();

        $this->post('/register', $this->registrationPayload([
            'name' => 'Nome Novo',
            'email' => 'desativado@example.com',
            'escola_unidade' => 'Escola Nova',
            'municipio_id' => $municipio->id,
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
        $response->assertRedirect('/');

        $user->refresh();
        $this->assertNull($user->deleted_at);
        $this->assertSame('Nome Novo', $user->name);

        $participante->refresh();
        $this->assertNull($participante->deleted_at);
        $this->assertSame('39053344705', $participante->cpf);
        $this->assertSame('Escola Nova', $participante->escola_unidade);

        $this->assertNull(Cache::get("reactivation-pending:{$user->id}"));
    }
}
