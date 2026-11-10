<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

class BrandThemeService
{
    public const DEFAULT_PRESET = 'deep-ocean';

    private const PRESETS = [
        'copilot-light' => [
            'name' => 'Copilot Light',
            'description' => 'Palet putih, abu-abu lembut, charcoal, dan slate yang jernih.',
            'font_family' => "'Segoe UI', ui-sans-serif, system-ui",
            'swatches' => ['#475569', '#64748B', '#E2E8F0', '#CBD5E1'],
            'neutralRamp' => [
                '50' => '#FFFFFF', '100' => '#F8FAFC', '200' => '#E2E8F0',
                '300' => '#CBD5E1', '400' => '#64748B', '500' => '#475569',
                '600' => '#334155', '700' => '#334155', '800' => '#1F2937',
                '900' => '#111827', '950' => '#0B1120',
            ],
            'surfaces' => [
                'page' => '#F8FAFC', 'primary' => '#FFFFFF', 'secondary' => '#F8FAFC',
                'tertiary' => '#F1F5F9', 'border' => '#E2E8F0', 'border_strong' => '#CBD5E1',
                'text' => '#111827', 'text_secondary' => '#334155', 'text_tertiary' => '#64748B',
                'dark_primary' => '#111827', 'dark_secondary' => '#1F2937',
                'dark_tertiary' => '#334155', 'dark_border' => '#475569',
                'dark_text' => '#F8FAFC', 'dark_text_secondary' => '#CBD5E1', 'dark_text_tertiary' => '#94A3B8',
            ],
            'sidebar' => [
                'deep' => '#0B1120', 'base' => '#1F2937', 'raised' => '#334155',
                'border' => '#475569', 'text' => '#E2E8F0', 'muted' => '#CBD5E1',
                'title' => '#FFFFFF', 'accent' => '#CBD5E1', 'warning' => '#F0A882',
                'active' => '#334155', 'hover' => '#334155', 'active_mark' => '#CBD5E1',
            ],
            'ramp' => [
                '50' => '#FFFFFF', '100' => '#F8FAFC', '200' => '#E2E8F0',
                '300' => '#CBD5E1', '400' => '#94A3B8', '500' => '#64748B',
                '600' => '#475569', '700' => '#334155', '800' => '#1F2937',
                '900' => '#111827', '950' => '#0B1120',
            ],
            'accent' => [
                'light' => '#475569', 'hover_light' => '#334155', 'surface_light' => '#F1F5F9',
                'dark' => '#CBD5E1', 'hover_dark' => '#E2E8F0', 'dark_hover' => '#94A3B8', 'surface_dark' => '#334155',
            ],
            'mobileNavigation' => [
                'inactive_light' => '#64748B', 'active_light' => '#475569',
                'inactive_dark' => '#94A3B8', 'active_dark' => '#CBD5E1',
            ],
            'typography' => [
                'font_family' => "'Segoe UI', Inter, ui-sans-serif, system-ui, sans-serif",
                'body_size' => '14px', 'secondary_size' => '13px', 'label_size' => '12px',
                'heading_size' => '18px', 'page_title_size' => '24px',
                'weight_regular' => '400', 'weight_medium' => '500', 'weight_strong' => '600',
                'line_height' => '1.45', 'line_height_compact' => '1.25', 'letter_spacing' => '0',
            ],
        ],
        'coastal-slate' => [
            'name' => 'Coastal Slate',
            'description' => 'Biru slate yang lembut, terinspirasi warna pesisir.',
            'swatches' => ['#3F6578', '#6E98AA', '#DCE9ED', '#8CBAC9'],
            'neutralRamp' => [
                '50' => '#F7FAFB', '100' => '#EDF4F6', '200' => '#DCE9ED',
                '300' => '#BDD5DC', '400' => '#6E8791', '500' => '#526A74',
                '600' => '#49616B', '700' => '#3D535D', '800' => '#2B424E',
                '900' => '#1F323B', '950' => '#15232A',
            ],
            'surfaces' => [
                'page' => '#F7FAFB', 'primary' => '#FFFFFF', 'secondary' => '#F7FAFB',
                'tertiary' => '#EDF4F6', 'border' => '#DCE9ED', 'border_strong' => '#BDD5DC',
                'text' => '#1F323B', 'text_secondary' => '#3D535D', 'text_tertiary' => '#526A74',
                'dark_primary' => '#1F323B', 'dark_secondary' => '#2B424E',
                'dark_tertiary' => '#3D535D', 'dark_border' => '#49616B',
                'dark_text' => '#F7FAFB', 'dark_text_secondary' => '#DCE9ED', 'dark_text_tertiary' => '#BDD5DC',
            ],
            'sidebar' => [
                'deep' => '#15232A', 'base' => '#2B424E', 'raised' => '#3D535D',
                'border' => '#49616B', 'text' => '#DCE9ED', 'muted' => '#BDD5DC',
                'title' => '#FFFFFF', 'accent' => '#8CBAC9', 'warning' => '#F0A882',
                'active' => '#345261', 'hover' => '#2B424E', 'active_mark' => '#8CBAC9',
            ],
            'ramp' => [
                '50' => '#EDF4F6', '100' => '#DCE9ED', '200' => '#BDD5DC',
                '300' => '#97BBC8', '400' => '#6E98AA', '500' => '#4F788B',
                '600' => '#3F6578', '700' => '#345261', '800' => '#2B424E',
                '900' => '#1F323B', '950' => '#15232A',
            ],
            'accent' => [
                'light' => '#3F6578', 'hover_light' => '#345261', 'surface_light' => '#EDF4F6',
                'dark' => '#8CBAC9', 'hover_dark' => '#A6CCD8', 'dark_hover' => '#6E9CAC', 'surface_dark' => '#203944',
            ],
            'mobileNavigation' => [
                'inactive_light' => '#526A74', 'active_light' => '#3F6578',
                'inactive_dark' => '#BDD5DC', 'active_dark' => '#8CBAC9',
            ],
        ],
        'deep-ocean' => [
            'name' => 'Command Center',
            'description' => 'Biru tenang di atas latar ice, netral navy. Default ERP.',
            'swatches' => ['#2563EB', '#1E3A8A', '#EFF4FF', '#0F1E3E'],
            'neutralRamp' => [
                '50' => '#F7F9FE', '100' => '#EFF3FB', '200' => '#E3E9F6',
                '300' => '#CBD6EE', '400' => '#8A96B8', '500' => '#5D6A85',
                '600' => '#3E4C74', '700' => '#2B3654', '800' => '#1E2740',
                '900' => '#141A29', '950' => '#0D111F',
            ],
            'surfaces' => [
                'page' => '#E9EEFA', 'primary' => '#FFFFFF', 'secondary' => '#F5F8FF',
                'tertiary' => '#EFF4FF', 'border' => '#E3E9F6', 'border_strong' => '#CBD6EE',
                'text' => '#0F1E3E', 'text_secondary' => '#3E4C74', 'text_tertiary' => '#5D6A85',
                'dark_primary' => '#1C2436', 'dark_secondary' => '#222C44',
                'dark_tertiary' => '#2A3450', 'dark_border' => '#3A4767',
                'dark_text' => '#E8ECF8', 'dark_text_secondary' => '#B9C3DD', 'dark_text_tertiary' => '#8A96B8',
            ],
            'sidebar' => [
                'deep' => '#0D111F', 'base' => '#141A29', 'raised' => '#1C2436',
                'border' => '#2A3450', 'text' => '#B9C3DD', 'muted' => '#8A96B8',
                'title' => '#FFFFFF', 'accent' => '#5B8DEF', 'warning' => '#F0A882',
                'active' => '#22304F', 'hover' => '#1C2436', 'active_mark' => '#5B8DEF',
            ],
            'ramp' => [
                '50' => '#EFF4FF', '100' => '#DDE9FF', '200' => '#BDD3FF',
                '300' => '#8FB4F8', '400' => '#5B8DEF', '500' => '#2563EB',
                '600' => '#1D4ED8', '700' => '#1E40AF', '800' => '#1E3A8A',
                '900' => '#172554', '950' => '#0F1E3E',
            ],
            'accent' => [
                'light' => '#2563EB', 'hover_light' => '#1D4ED8', 'surface_light' => '#EFF4FF',
                'dark' => '#5B8DEF', 'hover_dark' => '#7FA6F5', 'dark_hover' => '#1E40AF', 'surface_dark' => '#22304F',
            ],
            'mobileNavigation' => [
                'inactive_light' => '#3E4C74', 'active_light' => '#2563EB',
                'inactive_dark' => '#8A96B8', 'active_dark' => '#5B8DEF',
            ],
        ],
        'obsidian-gold' => [
            'name' => 'Obsidian Gold (Premium)',
            'description' => 'Gelap mewah aksen emas - tema premium Orion.',
            'swatches' => ['#1A1730', '#C98A1B', '#F7CD6B', '#0F0B1E'],
            'neutralRamp' => [
                '50' => '#F5F3FF', '100' => '#E8E6F2', '200' => '#CFCADA',
                '300' => '#8E88A6', '400' => '#5F5980', '500' => '#3E3857',
                '600' => '#2E2943', '700' => '#221E33', '800' => '#181527',
                '900' => '#0F0B1E', '950' => '#080612',
            ],
            'surfaces' => [
                'page' => '#121020', 'primary' => '#1A1730', 'secondary' => '#16132A',
                'tertiary' => '#221F38', 'border' => '#2E2947', 'border_strong' => '#3E3857',
                'text' => '#F3EFFA', 'text_secondary' => '#C9C4DC', 'text_tertiary' => '#8E88A6',
                'dark_primary' => '#121020', 'dark_secondary' => '#1A1730',
                'dark_tertiary' => '#221F38', 'dark_border' => '#2E2947',
                'dark_text' => '#F3EFFA', 'dark_text_secondary' => '#C9C4DC', 'dark_text_tertiary' => '#8E88A6',
            ],
            'sidebar' => [
                'deep' => '#0F0B1E', 'base' => '#181527', 'raised' => '#221F38',
                'border' => '#2E2947', 'text' => '#C9C4DC', 'muted' => '#8E88A6',
                'title' => '#FFFFFF', 'accent' => '#F7CD6B', 'warning' => '#F0A882',
                'active' => '#2A2545', 'hover' => '#1F1C33', 'active_mark' => '#F7CD6B',
            ],
            'ramp' => [
                '50' => '#FBF3DE', '100' => '#F7E8BE', '200' => '#EFD489',
                '300' => '#E4BB55', '400' => '#D9A43A', '500' => '#C98A1B',
                '600' => '#8A560C', '700' => '#6E4309', '800' => '#523007',
                '900' => '#472C08', '950' => '#2B1C05',
            ],
            'accent' => [
                'light' => '#8A560C', 'hover_light' => '#6E4309', 'surface_light' => '#F7E8BE',
                'dark' => '#F7CD6B', 'hover_dark' => '#FFDF8F', 'dark_hover' => '#C98A1B', 'surface_dark' => '#2A2545',
            ],
            'mobileNavigation' => [
                'inactive_light' => '#5F5980', 'active_light' => '#A96E12',
                'inactive_dark' => '#8E88A6', 'active_dark' => '#F7CD6B',
            ],
        ],
        'porcelain-bronze' => [
            'name' => 'Porcelain Bronze (Premium)',
            'description' => 'Kertas hangat aksen perunggu - tema premium terang.',
            'swatches' => ['#FAF6EC', '#8C5A2B', '#F3EAD8', '#2E2418'],
            'neutralRamp' => [
                '50' => '#FAF6EC', '100' => '#F3ECDD', '200' => '#E7DCC6',
                '300' => '#CBB894', '400' => '#9A8463', '500' => '#6E5B41',
                '600' => '#54462F', '700' => '#3E3423', '800' => '#2E2718',
                '900' => '#1F1A10', '950' => '#141009',
            ],
            'surfaces' => [
                'page' => '#F7F2E7', 'primary' => '#FFFFFF', 'secondary' => '#FAF6EC',
                'tertiary' => '#F3ECDD', 'border' => '#E7DCC6', 'border_strong' => '#CBB894',
                'text' => '#2E2418', 'text_secondary' => '#54462F', 'text_tertiary' => '#6E5B41',
                'dark_primary' => '#241F14', 'dark_secondary' => '#2E2718',
                'dark_tertiary' => '#3E3423', 'dark_border' => '#54462F',
                'dark_text' => '#F3ECDD', 'dark_text_secondary' => '#E7DCC6', 'dark_text_tertiary' => '#CBB894',
            ],
            'sidebar' => [
                'deep' => '#141009', 'base' => '#1F1A10', 'raised' => '#2E2718',
                'border' => '#3E3423', 'text' => '#E7DCC6', 'muted' => '#CBB894',
                'title' => '#FFFFFF', 'accent' => '#D9A43A', 'warning' => '#F0A882',
                'active' => '#33291A', 'hover' => '#241F14', 'active_mark' => '#D9A43A',
            ],
            'ramp' => [
                '50' => '#FAF0DC', '100' => '#F0DFC0', '200' => '#E1C391',
                '300' => '#C9A45F', '400' => '#B08A47', '500' => '#8C5A2B',
                '600' => '#6F4520', '700' => '#55341A', '800' => '#3F2813',
                '900' => '#2A1B0D', '950' => '#1A1007',
            ],
            'accent' => [
                'light' => '#6F4520', 'hover_light' => '#55341A', 'surface_light' => '#F0DFC0',
                'dark' => '#E1C391', 'hover_dark' => '#F5E3C4', 'dark_hover' => '#8C5A2B', 'surface_dark' => '#33291A',
            ],
            'mobileNavigation' => [
                'inactive_light' => '#6E5B41', 'active_light' => '#6F4520',
                'inactive_dark' => '#CBB894', 'active_dark' => '#E1C391',
            ],
        ],
    ];

    public function __construct(private readonly ?string $path = null)
    {
    }

    public function presets(): array
    {
        return array_map(
            fn (string $id, array $preset): array => $this->withVariables($id, $preset),
            array_keys(self::PRESETS),
            array_values(self::PRESETS),
        );
    }

    public function current(): array
    {
        return $this->preset($this->readPresetId());
    }

    public function preset(string $id): array
    {
        if (! isset(self::PRESETS[$id])) {
            throw new InvalidArgumentException('Preset tema tidak dikenal.');
        }

        return $this->withVariables($id, self::PRESETS[$id]);
    }

    public function save(string $id): array
    {
        if (! isset(self::PRESETS[$id])) {
            throw new InvalidArgumentException('Preset tema tidak dikenal.');
        }

        $path = $this->filePath();
        $directory = dirname($path);
        $createdDirectory = false;

        if (! is_dir($directory)) {
            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Folder penyimpanan tema tidak dapat dibuat.');
            }
            $createdDirectory = true;
        }

        if ($createdDirectory && DIRECTORY_SEPARATOR !== '\\') {
            if (! chmod($directory, 0700)) {
                throw new RuntimeException('Izin privat folder tema tidak dapat diterapkan.');
            }
        }

        $temporaryPath = tempnam($directory, '.brand-theme-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Berkas sementara tema tidak dapat dibuat.');
        }

        try {
            $temporaryDirectory = realpath(dirname($temporaryPath));
            $storageDirectory = realpath($directory);
            $sameDirectory = $temporaryDirectory !== false
                && $storageDirectory !== false
                && (DIRECTORY_SEPARATOR === '\\'
                    ? strcasecmp($temporaryDirectory, $storageDirectory) === 0
                    : $temporaryDirectory === $storageDirectory);
            if (! $sameDirectory) {
                throw new RuntimeException('Berkas sementara tema harus berada di folder penyimpanan privat.');
            }

            $json = json_encode(['preset' => $id], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            $written = file_put_contents($temporaryPath, $json, LOCK_EX);
            if ($written !== strlen($json)) {
                throw new RuntimeException('Berkas tema tidak dapat ditulis dengan lengkap.');
            }

            if (DIRECTORY_SEPARATOR !== '\\' && ! chmod($temporaryPath, 0600)) {
                throw new RuntimeException('Izin privat berkas tema tidak dapat diterapkan.');
            }

            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException('Berkas tema tidak dapat diganti secara atomik.');
            }
        } catch (\Throwable $exception) {
            if (is_file($temporaryPath)) {
                if (! unlink($temporaryPath)) {
                    error_log('Temporary brand theme file could not be removed after a failed save.');
                }
            }

            throw $exception;
        }

        return $this->preset($id);
    }

    private function readPresetId(): string
    {
        $path = $this->filePath();
        if (! is_file($path)) {
            return self::DEFAULT_PRESET;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            error_log('Brand theme settings file could not be read; using the default preset.');

            return self::DEFAULT_PRESET;
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('Brand theme settings file is malformed; using the default preset.');

            return self::DEFAULT_PRESET;
        }

        if (! is_array($data) || array_keys($data) !== ['preset'] || ! is_string($data['preset'] ?? null)) {
            error_log('Brand theme settings file has an invalid shape; using the default preset.');

            return self::DEFAULT_PRESET;
        }

        if (! isset(self::PRESETS[$data['preset']])) {
            error_log('Brand theme settings file contains an unknown preset; using the default preset.');

            return self::DEFAULT_PRESET;
        }

        return $data['preset'];
    }

    private function withVariables(string $id, array $preset): array
    {
        $variables = [];
        foreach ($preset['neutralRamp'] as $shade => $color) {
            $variables["--color-slate-{$shade}"] = $color;
        }

        foreach ($preset['ramp'] as $shade => $color) {
            $variables["--color-indigo-{$shade}"] = $color;
        }

        if (isset($preset['typography'])) {
            foreach ($preset['typography'] as $name => $value) {
                $variables['--brand-' . str_replace('_', '-', $name)] = $value;
            }
        }

        $variables['--brand-accent-light'] = $preset['accent']['light'];
        $variables['--brand-accent-hover-light'] = $preset['accent']['hover_light'];
        $variables['--brand-accent-surface-light'] = $preset['accent']['surface_light'];
        $variables['--brand-accent-dark'] = $preset['accent']['dark'];
        $variables['--brand-accent-hover-dark'] = $preset['accent']['hover_dark'];
        $variables['--brand-dark-accent-hover'] = $preset['accent']['dark_hover'];
        $variables['--brand-accent-surface-dark'] = $preset['accent']['surface_dark'];

        foreach ($preset['surfaces'] as $name => $color) {
            $variables["--brand-surface-{$name}"] = $color;
        }

        foreach ($preset['sidebar'] as $name => $color) {
            $variables["--brand-sidebar-{$name}"] = $color;
        }

        foreach ($preset['mobileNavigation'] as $name => $color) {
            $variables['--brand-mobile-nav-' . str_replace('_', '-', $name)] = $color;
        }

        return [
            'id' => $id,
            'name' => $preset['name'],
            'description' => $preset['description'],
            'swatches' => $preset['swatches'],
            'ramp' => $preset['ramp'],
            'accent' => $preset['accent'],
            'surfaces' => $preset['surfaces'],
            'sidebar' => $preset['sidebar'],
            'variables' => $variables,
        ];
    }

    private function filePath(): string
    {
        return $this->path ?? storage_path('app/system/brand-theme.json');
    }
}
