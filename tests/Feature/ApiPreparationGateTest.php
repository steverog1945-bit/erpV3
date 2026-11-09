<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiPreparationGateTest extends TestCase
{
    public function test_api_eksternal_ditutup_secara_default(): void
    {
        config()->set('orion_api.enabled', false);

        $this->getJson('/api/konsumen')
            ->assertStatus(503)
            ->assertHeaderContains('Cache-Control', 'no-store');
        $this->postJson('/api/auth/token', [])
            ->assertStatus(503);
        $this->postJson('/api/transaksi', [])
            ->assertStatus(503);
    }

    public function test_api_tetap_meminta_autentikasi_saat_mode_uji_dibuka(): void
    {
        config()->set('orion_api.enabled', true);

        $this->getJson('/api/konsumen')->assertUnauthorized();
    }

    public function test_halaman_web_tetap_dapat_diakses_saat_api_nonaktif(): void
    {
        config()->set('orion_api.enabled', false);

        $this->get('/masuk')->assertOk();
        $this->get(route('pwa.manifest'))->assertOk();
        $this->get(route('pwa.sw'))->assertOk();
    }
}
