<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Jaring pengaman statis untuk input angka di seluruh view: min harus
 * kelipatan step (step bawaan HTML = 1). Contoh bug yang dijaga: min="0.01"
 * dengan step="1" membuat browser hanya menerima 0.01, 1.01, 2.01 dst -
 * angka 1 (Pcs) ditolak dengan "two nearest valid values are 0.01 and 1.01".
 * Untuk step dinamis (Blade/JS), min wajib memakai ekspresi yang sama dengan step.
 */
class InputNumberAuditTest extends TestCase
{
    public function test_min_selalu_kelipatan_step_pada_semua_input_angka(): void
    {
        $root = resource_path('views');
        $temuan = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            preg_match_all('/<(?:x-form\.input|input)\b(?:[^>]|->)*?type=["\']number["\'](?:[^>]|->)*>/s', $src, $m, PREG_OFFSET_CAPTURE);

            foreach ($m[0] as [$tag, $offset]) {
                $ambil = fn (string $atribut) => preg_match('/\b' . $atribut . '=(?:"([^"]*)"|\'([^\']*)\')/', $tag, $x) ? ($x[1] !== '' ? $x[1] : ($x[2] ?? '')) : null;
                $min = $ambil('min');
                $step = $ambil('step');
                $lokasi = str_replace($root, '', $file->getPathname()) . ':' . (substr_count(substr($src, 0, $offset), "\n") + 1);

                if ($min === null || $step === 'any') {
                    // Tanpa min: aman. min tanpa step: step bawaan 1.
                    if ($min !== null && is_numeric($min) && fmod((float) $min, 1.0) !== 0.0) {
                        $temuan[] = "{$lokasi}: min={$min} tanpa step (bawaan 1)";
                    }
                    continue;
                }

                if ($step !== null && preg_match('/[{$]/', $step)) {
                    if ($min !== $step) {
                        $temuan[] = "{$lokasi}: step dinamis '{$step}' tetapi min='{$min}' (harus memakai ekspresi yang sama)";
                    }
                    continue;
                }

                if (is_numeric($min)) {
                    $stepAngka = $step === null ? 1.0 : (float) $step;
                    $rasio = $stepAngka > 0 ? (float) $min / $stepAngka : 0;
                    if (abs($rasio - round($rasio)) > 1e-9) {
                        $temuan[] = "{$lokasi}: min={$min} bukan kelipatan step=" . ($step ?? '1 (bawaan)');
                    }
                }
            }
        }

        $this->assertSame([], $temuan, "Input angka dengan min/step tidak cocok:\n" . implode("\n", $temuan));
    }
}
