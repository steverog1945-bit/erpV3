<?php

/**
 * Asisten AI lokal (opsional).
 *
 * Semua kemampuan Asisten Orion berjalan TANPA AI (aturan frasa + query).
 * Bila Anda ingin narasi rekap laporan ditulis AI, pasang Ollama di mesin
 * ini (ollama.com), unduh model kecil (mis. `ollama pull qwen2.5:3b-instruct`),
 * lalu setel di .env:
 *   ASISTEN_AI_ENABLED=true
 *   OLLAMA_URL=http://127.0.0.1:11434
 *   OLLAMA_MODEL=qwen2.5:3b-instruct
 * Data tidak pernah keluar dari mesin ini. Tanpa pengaturan ini, aplikasi
 * tetap berjalan normal dan menampilkan ringkasan berbasis aturan.
 */
return [
    'enabled' => (bool) env('ASISTEN_AI_ENABLED', false),
    'ollama_url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
    'model' => env('OLLAMA_MODEL', 'qwen2.5:3b-instruct'),
    'timeout' => (int) env('ASISTEN_AI_TIMEOUT', 25),
];
