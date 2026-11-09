<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Akses Ditolak</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <div class="text-7xl font-bold text-rose-500 mb-4">403</div>
        <h1 class="text-xl font-semibold text-slate-800 mb-2">Akses Ditolak</h1>
        <p class="text-sm text-slate-500 mb-6">Anda tidak memiliki hak untuk membuka halaman ini. Hubungi Administrator bila perlu akses tambahan.</p>
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">Kembali ke Beranda</a>
    </div>
</body>
</html>