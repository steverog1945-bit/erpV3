<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Halaman Tidak Ditemukan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <div class="text-7xl font-bold text-indigo-500 mb-4">404</div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Halaman Tidak Ditemukan</h1>
        <p class="text-sm text-slate-500 mb-6">Alamat yang Anda buka tidak ada atau sudah dipindahkan. Periksa kembali tautan atau kembali ke beranda.</p>
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">Kembali ke Beranda</a>
    </div>
</body>
</html>