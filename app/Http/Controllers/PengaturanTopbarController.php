<?php

namespace App\Http\Controllers;

use App\Models\TopbarTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PengaturanTopbarController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdministrator($request);

        return view('pengaturan.topbar', [
            'tabs' => TopbarTab::orderBy('urutan')->get(),
            'defaults' => TopbarTab::DEFAULTS,
            'icons' => TopbarTab::ICONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);

        $rows = (array) $request->input('rows', []);
        $hapusIds = [];
        $simpan = [];

        foreach ($rows as $row) {
            if (is_array($row) && ! empty($row['hapus']) && ! empty($row['id'])) {
                $hapusIds[] = (int) $row['id'];

                continue;
            }
            $simpan[] = $row;
        }

        $validated = Validator::make(['rows' => $simpan], [
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer', 'exists:topbar_tabs,id'],
            'rows.*.label' => ['required', 'string', 'max:30'],
            'rows.*.url' => ['required', 'string', 'max:255', 'regex:/^\/[A-Za-z0-9_\-\/]*$/'],
            'rows.*.icon' => ['required', Rule::in(array_keys(TopbarTab::ICONS))],
            'rows.*.urutan' => ['required', 'integer', 'min:0', 'max:9999'],
            'rows.*.aktif' => ['sometimes', 'boolean'],
        ])->validate();

        if ($hapusIds !== []) {
            TopbarTab::whereIn('id', $hapusIds)->delete();
        }

        foreach ($validated['rows'] ?? [] as $row) {
            $attributes = [
                'urutan' => (int) $row['urutan'],
                'label' => $row['label'],
                'url' => '/' . ltrim((string) $row['url'], '/'),
                'icon' => $row['icon'],
                'aktif' => (bool) ($row['aktif'] ?? false),
            ];

            if (! empty($row['id'])) {
                TopbarTab::whereKey($row['id'])->update($attributes);
            } else {
                TopbarTab::create($attributes);
            }
        }

        return redirect()
            ->route('pengaturan-aplikasi.topbar.index')
            ->with('success', 'Topbar berhasil disimpan.');
    }

    /** Hapus semua tab kustom sehingga kembali ke bawaan. */
    public function reset(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);

        TopbarTab::query()->delete();

        return redirect()
            ->route('pengaturan-aplikasi.topbar.index')
            ->with('success', 'Topbar dikembalikan ke bawaan.');
    }

    private function authorizeAdministrator(Request $request): void
    {
        $roleName = (string) (optional($request->user()?->role)->nama_role ?? '');

        abort_unless(
            str_starts_with($roleName, 'Administrator'),
            403,
            'Hanya Administrator yang boleh mengubah topbar.',
        );
    }
}
