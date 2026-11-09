// PHASE 1/2 - ekstrak validate() dari tiap controller (ground truth).
// READ-ONLY. Output: daftar method + aturan validasi.
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..', '..', 'app', 'Http', 'Controllers');

function walk(d, o = []) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p, o);
    else if (e.name.endsWith('.php')) o.push(p);
  }
  return o;
}

const files = walk(root);
const out = [];
for (const f of files) {
  const s = fs.readFileSync(f, 'utf8');
  // temukan method public function xxx( ... ) yang berisi validate(
  const reMethod = /public\s+function\s+(\w+)\s*\(([\s\S]*?)\)\s*\{([\s\S]*?)(?=\n    public|\n    protected|\n    private|\n    \})/g;
  let m;
  while ((m = reMethod.exec(s)) !== null) {
    const body = m[3];
    const vIdx = body.indexOf('validate(');
    if (vIdx < 0) continue;
    // ambil array validasi sampai );
    const mulai = body.indexOf('[', vIdx);
    if (mulai < 0) continue;
    let dalam = 0, akhir = -1;
    for (let i = mulai; i < body.length; i++) {
      if (body[i] === '[') dalam++;
      else if (body[i] === ']') { dalam--; if (dalam === 0) { akhir = i; break; } }
    }
    if (akhir < 0) continue;
    const aturan = body.slice(mulai, akhir + 1)
      .replace(/\s+/g, ' ')
      .replace(/=>/g, '=>')
      .slice(0, 600);
    out.push({
      file: path.relative(root, f).replace(/\\/g, '/'),
      method: m[1],
      aturan: aturan,
    });
  }
}

// kelompokkan per controller
const perCtrl = {};
for (const o of out) {
  perCtrl[o.file] = perCtrl[o.file] || [];
  perCtrl[o.file].push({ method: o.method, aturan: o.aturan });
}

let total = 0;
const baris = [];
for (const [ctrl, methods] of Object.entries(perCtrl)) {
  baris.push('=== ' + ctrl + ' ===');
  for (const mm of methods) {
    total++;
    baris.push('  ' + mm.method + '(): ' + mm.aturan);
  }
}
fs.writeFileSync(path.join(__dirname, '..', '..', 'storage', 'logs', 'validasi-controller.txt'), baris.join('\n'), 'utf8');
console.log('controller dengan validasi: ' + Object.keys(perCtrl).length);
console.log('method dengan validate(): ' + total);
