<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsiDataDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ditolak_di_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:isi --yes --tanpa-backup')->assertFailed();
    }

    public function test_ditolak_bila_tidak_ada_administrator(): void
    {
        $this->artisan('demo:isi --yes --tanpa-backup')->assertFailed();
    }
}
