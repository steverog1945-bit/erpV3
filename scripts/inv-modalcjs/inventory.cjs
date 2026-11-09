// PHASE 1 - INVENTORY modal & form (READ-ONLY).
// Sumber: semua blade view. Output: JSON inventory ke stdout.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..', 'resources', 'views');

function walk(d, o = []) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, o);
    else if (e.name.endsWith('.blade.php')) o.push(p);
  }
  return o;
}
const files = walk(root);
const rel = (f) => path.relative(root, f).replace(/\\/g, '/');

const hasil = { halaman: [], ajax: [], bootstrapModal: [] };

for (const f of files) {
  const s = fs.readFileSync(f, 'utf8');
  const hal = { file: rel(f), modal: [], formLuarModal: 0 };

  // --- x-modal blocks (paired tag) ---
  const reModal = /<x-modal\b([^>]*)>([\s\S]*?)(?:<\/x-modal>|$)/g;
  let m;
  while ((m = reModal.exec(s)) !== null) {
    const attrs = m[1];
    const body = m[2];
    const idM = (attrs.match(/\bid\s*=\s*"([^"]+)"/) || [])[1] || '(tanpa id)';
    const judul = (attrs.match(/\btitle\s*=\s*"([^"]+)"/) || [])[1] || '';
    const forms = [...body.matchAll(/<form\b([^>]*)>/g)].map(fm => {
      const fa = fm[1];
      const action = (fa.match(/\baction\s*=\s*"([^"]+)"/) || [])[1] || null;
      const method = (fa.match(/\bmethod\s*=\s*"([^"]+)"/) || [])[1] || 'GET';
      const fid = (fa.match(/\bid\s*=\s*"([^"]+)"/) || [])[1] || null;
      const spoofer = /@method\(['"](\w+)['"]\)/.test(body) ? 'spoofed:' + RegExp.$1 : null;
      return { formId: fid, method, action, spoofer };
    });
    // footer slot?
    const adaFooter = /@section\('footer'|footer=/.test(body) || /<x-slot:name="footer"/.test(body);
    hal.modal.push({ id: idM, judul, jumlahForm: forms.length, forms, footer: adaFooter });
  }

  // --- bootstrap modal ---
  if (/class="[^"]*\bmodal\b[^"]*"/.test(s)) {
    hasil.bootstrapModal.push(rel(f));
  }

  // --- fetch() mutasi ---
  const reFetch = /fetch\(([\s\S]*?)\)\s*\.then|fetch\(([\s\S]*?)\)\s*;/g;
  let fm;
  while ((fm = reFetch.exec(s)) !== null) {
    const arg = fm[1] || fm[2] || '';
    const jendela = s.slice(fm.index, fm.index + 1200);
    const isMutasi = /method\s*:\s*['"`](POST|PUT|PATCH|DELETE)['"`]/i.test(jendela.slice(0, 500));
    if (isMutasi) {
      const urlM = (arg.match(/['"]([^'"]+)['"]/) || [])[1] || '(dinamis)';
      const menangani = /\.ok\b|\.status\b|status\s*===|catch\s*\(|\.json\(\)\s*then|response\.ok/i.test(jendela);
      hasil.ajax.push({ file: rel(f), url: urlM, menangani });
    }
  }

  // --- form di luar modal ---
  const semuaForm = (s.match(/<form\b/g) || []).length;
  const formDiModal = hal.modal.reduce((a, x) => a + x.jumlahForm, 0);
  hal.formLuarModal = semuaForm - formDiModal;
  if (hal.modal.length || hal.formLuarModal) hasil.halaman.push(hal);
}

// --- route map (untuk mapping aksi -> controller) ---
const { execFileSync } = require('child_process');
let routes = [];
try {
  const out = execFileSync('php', ['artisan', 'route:list', '--except-vendor', '--json'],
    { cwd: path.join(__dirname, '..', '..'), encoding: 'utf8', maxBuffer: 1024 * 1024 * 32 });
  routes = JSON.parse(out);
} catch (e) { routes = []; }

const peta = {};
for (const r of routes) {
  if (!r.method) continue;
  for (const meth of r.method.split('|')) {
    if (['POST', 'PUT', 'PATCH', 'DELETE', 'GET'].includes(meth)) {
      peta[meth + ' ' + r.uri] = r.action || '';
    }
  }
}

fs.writeFileSync(path.join(__dirname, '..', '..', 'storage', 'logs', 'inv-modal.json'), JSON.stringify({
  ringkasan: {
    fileView: files.length,
    halamanPunyaModal: hasil.halaman.filter(h => h.modal.length).length,
    totalModal: hasil.halaman.reduce((a, h) => a + h.modal.length, 0),
    totalForm: hasil.halaman.reduce((a, h) => a + h.formLuarModal, 0) + hasil.halaman.reduce((a, h) => a + h.modal.reduce((b, x) => b + x.jumlahForm, 0), 0),
    ajaxMutasi: hasil.ajax.length,
    ajaxTanpaPenanganan: hasil.ajax.filter(a => !a.menangani).length,
    bootstrapModal: hasil.bootstrapModal.length,
  },
  halaman: hasil.halaman,
  ajax: hasil.ajax,
  bootstrapModal: hasil.bootstrapModal,
  ruteMutasi: Object.keys(peta).filter(k => !k.startsWith('GET ')).length,
}, null, 1), 'utf8');
console.log('inventory tertulis: storage/logs/inv-modal.json');
