<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SimpleSoftwareIO\QrCode\Generator;
use Tests\TestCase;

class GeradorQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_pode_gerar_qr_code_para_um_link(): void
    {
        $qrCodeMock = Mockery::mock(Generator::class);
        $qrCodeMock->shouldReceive('format', 'style', 'color', 'eye', 'eyeColor', 'size', 'margin', 'merge', 'errorCorrection')
            ->andReturnSelf();
        $qrCodeMock->shouldReceive('generate')->with('https://engaja.test/inscricao')->andReturn('qr-code-png');
        $this->app->instance(Generator::class, $qrCodeMock);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('qrcode.gerador', ['link' => 'https://engaja.test/inscricao']))
            ->assertOk()
            ->assertSee('Gerador de QR Code')
            ->assertSee('https://engaja.test/inscricao')
            ->assertSee(base64_encode('qr-code-png'));
    }

    public function test_link_invalido_nao_gera_qr_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('qrcode.gerador'))
            ->get(route('qrcode.gerador', ['link' => 'link-invalido']))
            ->assertRedirect(route('qrcode.gerador'))
            ->assertSessionHasErrors('link');
    }
}
