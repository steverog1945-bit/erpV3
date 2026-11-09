/* AUDIT KEAMANAN
   Empat bidang yang diminta: otorisasi, rate limiting, validasi &
   sanitasi input, dan proteksi CSRF. Semua dibaca dari kode, bukan
   ditebak dari DOCUMENTASI.
*/

const fs = require('fs');
const path = require('path');

const root = __dirname + '/..';
const baca = (p) => fs.readFileSync(path.join(root, p), 'utf8');

const temuan = [];
const add = (bidang, level, ket, bukti) => temuan.push({ bidang, level, ket, bukti });

/* ---------------------------------------------------------------- 1. CSRF */
{
  const web = baca('routes/web.php');
  const boot = baca('bootstrap/app.php');
  const views = [];

  (function walk(d) {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('.blade.php')) views.push(p);
    }
  })(path.join(root, 'resources', 'views'));

  // 1a. Form POST/PUT/PATCH/DELETE tanpa @csrf
  let tanpaCsrf = 0, totalForm = 0;
  const contohTanpa = [];
  for (const v of views) {
    const c = fs.readFileSync(v, 'utf8');
    for (const m of c.matchAll(/<form\b([^>]*)>([\s\S]*?)<\/form>/g)) {
      const attrs = m[1], body = m[2];
      const metode = ((attrs.match(/method\s*=\s*["']?(get|post|put|patch|delete)/i) || [])[1] || 'get').toLowerCase();
      if (metode === 'get') continue;
      totalForm++;
      if (!/@csrf/.test(body) && !/csrf_token\(\)/.test(body) && !/\$errors/.test(body)) {
        tanpaCsrf++;
        if (contohTanpa.length < 6) {
          contohTanpa.push(path.relative(root, v).replace(/\\/g, '/') + ' (method=' + metode + ')');
        }
      }
    }
  }
  if (tanpaCsrf > 0) {
    add('CSRF', 'tinggi', 'Ada ' + tanpaCsrf + ' form non-GET tanpa @csrf dari total ' + totalForm,
      contohTanpa.join('; '));
  }

  // 1b. Route POST/PUT/PATCH/DELETE yang di-exclude dari CSRF
  const exclude = boot.match(/validateCsrfTokens\s*\(\s*except\s*:\s*\[([^\]]*)\]/);
  if (exclude) {
    const isinya = exclude[1].trim();
    if (isinya) {
      add('CSRF', 'tinggi', 'Ada route yang dikecualikan dari proteksi CSRF: ' + isinya, 'bootstrap/app.php');
    }
  } else {
    // Tidak ada pengecualian = seluruh route web (termasuk POST) dilindungi.
  }

  // 1c. Token CSRF di response JSON / AJAX
  const ajaxTanpaToken = [];
  for (const v of views) {
    const c = fs.readFileSync(v, 'utf8');
    for (const m of c.matchAll(/fetch\(([\s\S]{0,400}?)\)/g)) {
      if (/method\s*:\s*['"](POST|PUT|PATCH|DELETE)/i.test(m[1]) && !/csrf/i.test(m[1])) {
        ajaxTanpaToken.push(path.relative(root, v).replace(/\\/g, '/'));
      }
    }
  }
  if (ajaxTanpaToken.length) {
    add('CSRF', 'sedang', ajaxTanpaToken.length + ' pemanggilan fetch dengan method tulis tanpa header CSRF di bloknya',
      Array.from(new Set(ajaxTanpaToken)).slice(0, 6).join('; '));
  }
}

/* -------------------------------------------------------- 2. RATE LIMITING */
{
  const web = baca('routes/web.php');
  const boot = baca('bootstrap/app.php');

  // Route yang memakai rate limiter
  const throttle = Array.from(web.matchAll(/middleware\(\s*['"]([^'"]*throttle[^'"]*)['"]/g)).map(m => m[1]);
  const limiterDidefinisikan = /RateLimiter::for/.test(baca('app/Providers/AppServiceProvider.php') || '');
  const limiterDiWeb = /RateLimiter::for/.test(web) || limiterDidefinisikan;

  // Semua endpoint tulis
  const tulis = Array.from(web.matchAll(/Route::(post|put|patch|delete)\s*\(\s*['"]([^'"]*)['"]/g)).map(m => m[2]);
  const adaAuth = /middleware\(\s*\[?\s*['"]auth['"]/.test(web);

  if (tulis.length > 0 && throttle.length === 0 && !limiterDiWeb) {
    add('Rate limiting', 'tinggi',
      'Ada ' + tulis.length + ' endpoint tulis tapi tidak satu pun memakai middleware throttle',
      'contoh: ' + tulis.slice(0, 5).join(', '));
  } else if (throttle.length > 0) {
    // Hanya sebagian. Hitung endpoint tulis yang berada di route tanpa throttle.
    add('Rate limiting', 'info',
      'Rate limiter hanya pada sebagian route (' + throttle.join(', ') + ')',
      'endpoint tulis total: ' + tulis.length);
  }

  // Rate limiter login
  const loginThrottle = /post\(\s*['"]\/login['"][\s\S]{0,200}?throttle:([a-z0-9_]+)/i.test(web);
  if (!loginThrottle) {
    add('Rate limiting', 'tinggi', 'Endpoint /login tidak dibatasi percobaan (brute force)', 'routes/web.php');
  }

  // Rate limiter default global
  if (!/->withMiddleware[\s\S]{0,400}?throttleApi|throttleWithRedis|ThrottleRequests/.test(boot)) {
    add('Rate limiting', 'info',
      'Tidak ada throttle default di withMiddleware() - hanya throttle yang ditulis eksplisit per route', 'bootstrap/app.php');
  }
}

/* --------------------------------------------------------- 3. OTORISASI */
{
  const web = baca('routes/web.php');
  const lines = web.split(/\r?\n/);

  // Cari blok route yang TIDAK punya auth.
  // Lacak kedalaman kurung kurawal: Route::middleware([...])->group(function () {
  // membuka satu blok, dan penutupnya '});' - bukan '}' polos. Menghitung
  // hanya baris yang persis '}' akan salah membacanya sebagai blok baru,
  // sehingga seluruh route di dalam group ikut terlihat "di luar auth".
  const tanpaAuth = [];
  let depth = 0, i = 0;
  const dalamAuth = [];
  while (i < lines.length) {
    const L = lines[i];

    if (/Route::middleware\(/.test(L) && /auth/.test(L)) {
      dalamAuth.push(depth);
    }

    // Hitung pembuka/penutup kurung kurawal pada baris ini
    for (const ch of L) {
      if (ch === '{') depth++;
      else if (ch === '}') depth--;
    }

    const m = L.match(/Route::(get|post|put|patch|delete|resource|apiResource)\s*\(\s*['"]([^'"]*)['"]/);
    if (m) {
      // Route ini berada di dalam blok auth kalau ada entri auth dengan
      // kedalaman <= depth saat baris ini dibaca.
      const diAuth = dalamAuth.some(d => d <= depth);
      if (!diAuth && !m[2].startsWith('login') && m[2] !== '/' && m[2] !== 'up' && m[2] !== 'storage') {
        tanpaAuth.push(m[2] + ' (' + m[1] + ')');
      }
    }
    i++;
  }
  if (tanpaAuth.length) {
    add('Otorisasi', 'tinggi', tanpaAuth.length + ' route di luar blok auth: ' + tanpaAuth.slice(0, 10).join(', '),
      'routes/web.php');
  }

  // Autorisasi di level objek (policy/gate) - apakah ada?
  let adaPolicy = false;
  const appDir = path.join(root, 'app', 'Policies');
  if (fs.existsSync(appDir)) adaPolicy = fs.readdirSync(appDir).some(f => f.endsWith('.php'));

  // Meghitung pemakaian authorize()/Gate::/abort_if() di SELURUH controller,
  // bukan hanya satu. Menghitung satu file akan menyimpulkan "tidak ada"
  // padahal ada di file lain.
  let authorize = 0, contohOtorisasi = [];
  (function walk(d) {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('.php')) {
        const src = fs.readFileSync(p, 'utf8');
        const n = (src.match(/->authorize\(|Gate::|->can\(|->cannot\(|abort_if\(|abort_unless\(/g) || []).length;
        if (n) { authorize += n; contohOtorisasi.push(path.relative(root, p).replace(/\\/g, '/')); }
      }
    }
  })(path.join(root, 'app'));

  if (!adaPolicy && authorize === 0) {
    add('Otorisasi', 'tinggi',
      'Tidak ada Policy/Gate di app/Policies, dan tidak ada authorize()/abort_if() memverifikasi kepemilikan data',
      'middleware menu.access hanya mengatur akses menu, bukan data milik user');
  } else if (!adaPolicy) {
    add('Otorisasi', 'sedang',
      'Ada pemeriksaan otorisasi (' + authorize + 'x) tapi tidak ada Policy kelas yang terpusat',
      'disebutkan di: ' + Array.from(new Set(contohOtorisasi)).slice(0, 5).join('; '));
  }
}

/* --------------------------------------- 4. VALIDASI & SANITASI INPUT */
{
  const ctrls = [];
  (function walk(d) {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('.php') && e.name.endsWith('Controller.php')) ctrls.push(p);
    }
  })(path.join(root, 'app', 'Http', 'Controllers'));

  let tanpaValidasi = 0, contoh = [], totalTulis = 0;
  for (const c of ctrls) {
    const src = fs.readFileSync(c, 'utf8');
    for (const m of src.matchAll(/public function (store|update)\s*\([^)]*\)\s*\{([\s\S]*?)\n    \}/g)) {
      totalTulis++;
      const body = m[2];
      const adaValidasi = /\$request->validate\(|\$request->input\([^)]*\)\s*\)?\s*;?\s*$|Validator::make|->validate\(/m.test(body)
        || /\$request->validate\(/.test(body);
      if (!adaValidasi) {
        tanpaValidasi++;
        if (contoh.length < 8) contoh.push(path.relative(root, c).replace(/\\/g, '/') + '::' + m[1]);
      }
    }
  }
  if (tanpaValidasi > 0) {
    add('Validasi', 'sedang',
      tanpaValidasi + ' dari ' + totalTulis + ' method store/update tidak memanggil $request->validate()',
      contoh.join('; '));
  }

  // Mass assignment
  let adaMassAssign = 0, contohMA = [];
  for (const c of ctrls) {
    const src = fs.readFileSync(c, 'utf8');
    for (const m of src.matchAll(/->create\(\s*\$request->all\(\)/g)) { adaMassAssign++; contohMA.push(path.relative(root, c)); }
    for (const m of src.matchAll(/->update\(\s*\$request->all\(\)/g)) { adaMassAssign++; contohMA.push(path.relative(root, c)); }
  }
  if (adaMassAssign) {
    add('Validasi', 'tinggi', adaMassAssign + ' kali memakai $request->all() untuk create/update (mass assignment)',
      Array.from(new Set(contohMA)).slice(0, 6).join('; '));
  }

  // Query mentah
  let mentah = 0, contohMentah = [];
  for (const c of ctrls) {
    const src = fs.readFileSync(c, 'utf8');
    for (const m of src.matchAll(/DB::(?:select|statement|raw|unprepared)\s*\(\s*([^,\)]+)/g)) {
      if (/\$/.test(m[1])) { mentah++; contohMentah.push(path.relative(root, c).replace(/\\/g, '/')); }
    }
  }
  if (mentah) {
    add('Validasi', 'sedang', mentah + ' query DBmentah dengan variabel yang diinterpolasi (perlu diperiksa: apakah pakai binding)',
      Array.from(new Set(contohMentah)).slice(0, 6).join('; '));
  }
}

/* --------------------------------------------------- 5. KONFIGURASI .ENV */
{
  const env = baca('.env');
  if (/^APP_DEBUG=true/m.test(env)) {
    add('Konfigurasi', 'tinggi', 'APP_DEBUG=true - halaman error menampilkan stack trace, path file, dan kredensial env',
      '.env');
  }
  if (/^SESSION_ENCRYPT=false/m.test(env)) {
    add('Konfigurasi', 'sedang', 'SESSION_ENCRYPT=false - isi session tersimpan di database tanpa enkripsi', '.env');
  }
  if (/^SESSION_SECURE_COOKIE\s*=\s*false/m.test(env)) {
    add('Konfigurasi', 'sedang', 'SESSION_SECURE_COOKIE=false - cookie session bisa terkirim lewat HTTP', '.env');
  }
}

/* ------------------------------------------------------------------ LAPOR */
const urut = { tinggi: 0, sedang: 1, info: 2, info2: 3 };
temuan.sort((a, b) => urut[a.level] - urut[b.level]);

console.log('='.repeat(72));
console.log('AUDIT KEAMANAN - ' + temuan.length + ' temuan');
console.log('='.repeat(72));
console.log('');

let prev = null;
for (const t of temuan) {
  if (t.bidang !== prev) { console.log('## ' + t.bidang); prev = t.bidang; }
  const tag = t.level === 'tinggi' ? '!! TINGGI ' : t.level === 'sedang' ? ' !  SEDANG ' : ' -  INFO  ';
  console.log('  ' + tag + t.ket);
  if (t.bukti) console.log('          -> ' + t.bukti);
  console.log('');
}
