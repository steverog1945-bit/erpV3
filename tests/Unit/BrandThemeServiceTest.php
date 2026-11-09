<?php

namespace Tests\Unit;

use App\Services\BrandThemeService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BrandThemeServiceTest extends TestCase
{
    private string $testRoot;

    private string $themePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brand-theme-' . bin2hex(random_bytes(8));
        $this->themePath = $this->testRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'brand-theme.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->themePath)) {
            unlink($this->themePath);
        }
        if (is_dir(dirname($this->themePath))) {
            rmdir(dirname($this->themePath));
        }
        if (is_dir(dirname(dirname($this->themePath)))) {
            rmdir(dirname(dirname($this->themePath)));
        }
        if (is_dir($this->testRoot)) {
            rmdir($this->testRoot);
        }

        parent::tearDown();
    }

    public function test_missing_or_invalid_state_falls_back_to_orion_classic(): void
    {
        $themes = new BrandThemeService($this->themePath);

        self::assertSame(BrandThemeService::DEFAULT_PRESET, $themes->current()['id']);

        mkdir(dirname($this->themePath), 0700, true);
        file_put_contents($this->themePath, '{"preset":"unknown"}');
        self::assertSame(BrandThemeService::DEFAULT_PRESET, $themes->current()['id']);

        file_put_contents($this->themePath, '{broken json');
        self::assertSame(BrandThemeService::DEFAULT_PRESET, $themes->current()['id']);
    }

    public function test_only_a_known_preset_id_is_persisted_and_restored(): void
    {
        $themes = new BrandThemeService($this->themePath);
        $saved = $themes->save('coastal-slate');

        self::assertSame('coastal-slate', $saved['id']);
        self::assertSame($saved['id'], $themes->current()['id']);
        self::assertSame(['preset' => 'coastal-slate'], json_decode((string) file_get_contents($this->themePath), true));
        self::assertSame(['orion-classic', 'copilot-light', 'coastal-slate', 'deep-ocean'], array_column($themes->presets(), 'id'));
        self::assertArrayHasKey('--brand-font-family', $themes->preset('copilot-light')['variables']);
        self::assertSame('14px', $themes->preset('copilot-light')['variables']['--brand-body-size']);
        self::assertSame('12px', $themes->preset('copilot-light')['variables']['--brand-label-size']);
        self::assertSame('400', $themes->preset('copilot-light')['variables']['--brand-weight-regular']);
        self::assertSame('0', $themes->preset('copilot-light')['variables']['--brand-letter-spacing']);
        self::assertSame('#0F6472', $themes->preset('orion-classic')['variables']['--brand-mobile-nav-active-light']);
        self::assertArrayNotHasKey('--brand-font-family', $themes->preset('orion-classic')['variables']);
        self::assertSame('#475569', $themes->preset('copilot-light')['accent']['light']);
        self::assertSame('#F8FAFC', $themes->preset('copilot-light')['surfaces']['page']);
        self::assertSame('#1F2937', $themes->preset('copilot-light')['sidebar']['base']);
        self::assertSame('#006C7B', $themes->preset('orion-classic')['accent']['light']);

        $this->expectException(InvalidArgumentException::class);
        $themes->save('--accent-primary: url(javascript:alert(1))');
    }

    public function test_primary_button_text_contrast_meets_wcag_aa_for_every_preset(): void
    {
        foreach ((new BrandThemeService($this->themePath))->presets() as $preset) {
            self::assertGreaterThanOrEqual(
                4.5,
                $this->contrastRatio('#FFFFFF', $preset['ramp']['600']),
                $preset['name'] . ' primary button contrast',
            );
            self::assertGreaterThanOrEqual(
                4.5,
                $this->contrastRatio($preset['accent']['dark'], $preset['surfaces']['dark_primary']),
                $preset['name'] . ' dark-mode accent contrast',
            );
            self::assertGreaterThanOrEqual(
                4.5,
                $this->contrastRatio($preset['surfaces']['text_tertiary'], $preset['surfaces']['page']),
                $preset['name'] . ' light-mode tertiary text contrast',
            );
            self::assertGreaterThanOrEqual(
                4.5,
                $this->contrastRatio($preset['surfaces']['dark_text_tertiary'], $preset['surfaces']['dark_primary']),
                $preset['name'] . ' dark-mode tertiary text contrast',
            );

            self::assertArrayNotHasKey('--color-emerald-600', $preset['variables']);
            self::assertArrayNotHasKey('--color-amber-600', $preset['variables']);
            self::assertArrayNotHasKey('--color-rose-600', $preset['variables']);
        }
    }

    private function contrastRatio(string $first, string $second): float
    {
        $luminances = [];
        foreach ([$first, $second] as $color) {
            $channels = array_map(
                static function (string $channel): float {
                    $value = hexdec($channel) / 255;

                    return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
                },
                str_split(substr($color, 1), 2),
            );
            $luminances[] = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        }

        return (max($luminances) + 0.05) / (min($luminances) + 0.05);
    }
}
