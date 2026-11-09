// PHASE 1 - ringkasan inventory untuk laporan.
const fs = require('fs');
const path = require('path');
const inv = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'storage', 'logs', 'inv-modal.json'), 'utf8'));

const bersih = (s) => (s || '').replace(/{{ route\('|'\) }}|{{ url\('|'\) }}|{{.*?}}/g, '').trim();

console.log('=== MODAL (x-modal) PER HALAMAN ===');
let nModal = 0;
for (const h of inv.halaman) {
  if (!h.modal.length) continue;
  console.log('[' + h.file + ']  form-luar-modal=' + h.formLuarModal);
  for (const m of h.modal) {
    nModal++;
    const list = m.forms.map(f => {
      const meth = f.method + (f.spoofer ? '/' + f.spoofer.replace('spoofed:', '') : '');
      const act = f.action ? bersih(f.action) : '(JS/dinamis)';
      return meth + ' -> ' + act;
    }).join('  |  ');
    console.log('   #' + m.id + '  "' + m.judul + '"  form=' + m.jumlahForm + (m.footer ? '  [footer]' : '') + (list ? '  ' + list : ''));
  }
}
console.log('\n=== AJAX MUTASI (fetch) ===');
for (const a of inv.ajax) {
  console.log('   ' + a.file + '  ->  ' + a.url + '  menangani=' + a.menangani);
}
console.log('\n=== BOOTSTRAP MODAL ===');
for (const b of inv.bootstrapModal) console.log('   ' + b);
console.log('\ntotal modal tercetak: ' + nModal);
