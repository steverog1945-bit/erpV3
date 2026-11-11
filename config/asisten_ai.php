<?php

/**
 * Asisten AI lokal (opsional - AKTIF sebagai default setelah pemilik
 * memasang Ollama di mesin ini; setel ASISTEN_AI_ENABLED=false di .env
 * untuk mematikan).
 *
 * Semua kemampuan Asisten Orion berjalan TANPA AI (aturan frasa + query).
 * AI hanya dipakai untuk MENARASI angka rekap menjadi kalimat eksekutif,
 * tidak pernah menghitung dan tidak pernah mengirim data ke internet.
 * Bila Ollama tidak merespons, kode otomatis menampilkan ringkasan angka
 * mentah dengan catatan (tidak pernah gagal total).
 *
 * Pemasangan (Windows): irm https://ollama.com/install.ps1 | iex
 * Lalu: ollama pull qwen2.5:3b-instruct (1.9 GB).
 */
return [
    'enabled' => filter_var(env('ASISTEN_AI_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'ollama_url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
    'model' => env('OLLAMA_MODEL', 'qwen2.5:3b-instruct'),
    'timeout' => (int) env('ASISTEN_AI_TIMEOUT', 25),
];
