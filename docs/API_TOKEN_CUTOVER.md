# API-5: Cutover Token Bearer (Hash SHA-256 + Kedaluwarsa)

## Ringkasan perubahan

| Aspek | Sebelum API-5 | Sesudah API-5 |
|---|---|---|
| Penyimpanan | plaintext di `users.api_token` | SHA-256 di `users.api_token_hash` (UNIQUE) |
| Entropi | 80 hex CSPRNG (320-bit) | 64 hex CSPRNG (256-bit), format `^[a-f0-9]{64}$` |
| Masa berlaku | ∞ | 24 jam (`ORION_API_TOKEN_MASA_AKTIF_JAM`, config `orion_api.token_masa_aktif_jam`) |
| Jalur kirim token | `Authorization: Bearer` **atau** `?api_token=` | hanya `Authorization: Bearer`; parameternya di query => 401 selalu |
| Rotasi | menimpa token lama tanpa jaminan atomik | transaksi + `lockForUpdate`: token lama dicabut pada commit yang sama |
| Token lama (plaintext) | sah | **tidak pernah dicocokkan lagi** — mati otomatis saat kode ini deploy |

Kolom `users.api_token` (plaintext) **tidak dihapus dan tidak di-backfill**;
penghapusannya adalah tahap pembersihan terpisah setelah cutover semua klien.

## migration

`2026_11_06_210000_api_token_hash_dan_kedaluwarsa.php` — ADDITIVE:
`api_token_hash` CHAR(64) NULL UNIQUE + `api_token_expires_at` TIMESTAMP NULL.
`down()` hanya membuang kedua kolom itu; migrasi ini tidak mengubah nilai apa pun.

## Rencana cutover (butuh persetujuan owner)

1. Deploy kode setelah merge. Mulai titik ini semua klien ber-token era lama
   langsung 401 pada request berikutnya — aman, fail-closed, tanpa perubahan data.
2. Setiap pemegang token lama menPOST `username`+`password` ke
   `POST /api/auth/token` dan menerima token baru + `expires_at` (sekali saja
   dalam respons; server hanya menyimpan hash-nya).
3. Simpan token plaintext baru di penyimpan aman klien (keychain/keystore);
   tidak pernah di URL, log, atau repo.
4. Pantau 401/429 di access log — lonjakan biasanya klien yang belum cutover.
5. Jalankan `POST /api/auth/logout` untuk mencabut token aktif saat klien
   tahu token telah bocor.

## Rollback yang aman

- Kode: `git revert` merge API-5 → jalur plaintext lama aktif kembali. Token
  plaintext yang ada di kolom `users.api_token` **tidak pernah disentuh**
  migrasi/idpl, jadi langsung berfungsi lagi tanpa pemulihan apa pun.
- Kolom hash boleh dibiarkan; ApiAuth lama mengabaikannya.
- Token baru era-hash tidak sah di kode lama — klien yang sudah cutover
  perlu token sekali lagi dari jalur lama; itu konsekuensi roll-back penuh.

## Yang tidak disetujui tanpa owner

- Menjalankan `migrate` di oriondb (operasional).
- Penghapusan/dinonaktifkan massal token operasional.
- Mengaktifkan `ORION_PUBLIC_API_ENABLED` / `ORION_API_CHECKOUT_ENABLED`.
