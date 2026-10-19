<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_ve_a_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('landing')
            ->assertSee('Bem-vindo ao', false)
            ->assertSee('Gestão de Inscrições e Presenças')
            ->assertSee('Sistema de Gestão de Participação e Engajamento.')
            ->assertSee('CNPJ 04.950.603/0001-05');
    }

    public function test_landing_page_aponta_para_login_e_cadastro(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_landing_page_usa_os_assets_locais(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['engaja-icone.png', 'hero-foto.jpg', 'instituto-paulo-freire.png', 'petrobras.png'] as $asset) {
            $response->assertSee(asset('images/landing/'.$asset), false);
            $this->assertFileExists(public_path('images/landing/'.$asset));
        }

        $response->assertDontSee('figma.com/api/mcp/asset', false);
    }

    public function test_usuario_autenticado_continua_na_pagina_inicial_da_aplicacao(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertViewIs('welcome');
    }
}
