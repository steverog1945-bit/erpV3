<?php

/**
 * Asisten Telegram remote-ops - HANYA untuk owner (chat_id di .env),
 * perintah whitelist, TANPA angka rupiah/data penjualan/pembelian/nota.
 *
 * Cara pakai:
 *   1. Buat bot via @BotFather -> salin token.
 *   2. Isi .env: ASISTEN_TELEGRAM_ENABLED=true
 *      ASISTEN_TELEGRAM_TOKEN=<token dari BotFather>
 *      ASISTEN_TELEGRAM_CHAT_ID=<chat id Anda - lihat @userinfobot>
 *   3. Jalankan: php artisan telegram:bot
 *
 * Keamanan:
 *   - Panjang tidak ada port publik (long polling, hanya koneksi keluar HTTPS).
 *   - chat_id asing DIBIARKAN TANPA JAWABAN (diam) + dicatat di audit_logs.
 *   - Semua perintah yang diproses dicatat di audit_logs.
 *   - Versi/status saja yang keluar - tidak ada omzet, piutang, nota, nama
 *     pelanggan, harga, atau data pembelian.
 */
return [
    'enabled' => filter_var(env('ASISTEN_TELEGRAM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'token' => env('ASISTEN_TELEGRAM_TOKEN'),
    'chat_id' => env('ASISTEN_TELEGRAM_CHAT_ID'),
    'long_poll_timeout' => (int) env('ASISTEN_TELEGRAM_POLL_TIMEOUT', 25),
];
