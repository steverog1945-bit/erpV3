<?php

namespace Tests\Feature;

use App\Services\BomCalculationService as Bom;
use Tests\TestCase;

class BomRumusTest extends TestCase
{
    public function test_rumus_tetap_tidak_berubah(): void
    {
        $this->assertEqualsWithDelta(3.0, Bom::hitungQtyPakai('(P*L)/10000', 300, 100, 1), 1e-9);
        $this->assertEqualsWithDelta(6.0, Bom::hitungQtyPakai('(P*L)/10000*2', 300, 100, 1), 1e-9);
        $this->assertEqualsWithDelta(8.0, Bom::hitungQtyPakai('2*(P+L)/100', 300, 100, 1), 1e-9);
        $this->assertEqualsWithDelta(7.0, Bom::hitungQtyPakai('Q', 0, 0, 7), 1e-9);
    }

    public function test_rumus_berfaktor_bagi_dan_kali(): void
    {
        $this->assertEqualsWithDelta(2.5, Bom::hitungQtyPakai('Q/4', 0, 0, 10), 1e-9);       // 10 foto 6R, 4 per lembar
        $this->assertEqualsWithDelta(25.0, Bom::hitungQtyPakai('Q*5', 0, 0, 5), 1e-9);       // 5 box kartu nama x 5 lembar
        $this->assertEqualsWithDelta(0.44, Bom::hitungQtyPakai('Q*0.22', 0, 0, 2), 1e-9);    // 2 foto 16R x 0,22 m2
        $this->assertEqualsWithDelta(3.3, Bom::hitungQtyPakai('(P*L)/10000*1.1', 300, 100, 1), 1e-9);
        $this->assertEqualsWithDelta(0.5, Bom::hitungQtyPakai('Q / 2', 0, 0, 1), 1e-9);      // spasi diabaikan
    }

    public function test_rumus_tidak_dikenali_jatuh_ke_qty_dan_pembagian_nol_aman(): void
    {
        $this->assertEqualsWithDelta(3.0, Bom::hitungQtyPakai('system("x")', 0, 0, 3), 1e-9);
        $this->assertEqualsWithDelta(3.0, Bom::hitungQtyPakai('Q/0', 0, 0, 3), 1e-9);
        $this->assertFalse(Bom::rumusValid('Q*abc'));
        $this->assertTrue(Bom::rumusValid('Q/4'));
        $this->assertTrue(Bom::rumusValid('Q*0.22'));
        $this->assertTrue(Bom::rumusValid('(P*L)/10000'));
    }
}
