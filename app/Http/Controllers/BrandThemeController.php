<?php

namespace App\Http\Controllers;

use App\Services\BrandThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BrandThemeController extends Controller
{
    public function index(Request $request, BrandThemeService $themes)
    {
        $this->authorizeAdministrator($request);

        return view('pengaturan.tema', [
            'currentTheme' => $themes->current(),
            'presets' => $themes->presets(),
        ]);
    }

    public function update(Request $request, BrandThemeService $themes): RedirectResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'preset' => ['required', 'string', Rule::in(array_column($themes->presets(), 'id'))],
        ]);

        try {
            $theme = $themes->save($data['preset']);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Tema gagal disimpan. Periksa izin folder penyimpanan aplikasi lalu coba lagi.');
        }

        return redirect()
            ->route('pengaturan-aplikasi.tema.index')
            ->with('success', 'Tema ' . $theme['name'] . ' berhasil diterapkan untuk seluruh pengguna.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        $roleName = (string) (optional($request->user()?->role)->nama_role ?? '');
        abort_unless(
            str_starts_with($roleName, 'Administrator'),
            403,
            'Hanya Administrator yang boleh mengubah tema aplikasi.',
        );
    }
}
