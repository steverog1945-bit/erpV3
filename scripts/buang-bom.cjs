/* BUANG BOM UTF-8

   Set-Content -Encoding UTF8 di Windows PowerShell menulis BOM (EF BB BF)
   di awal file. Untuk .php itu fatal - PHP refuses to parse because the
   namespace declaration is no longer the first statement. For .css, .js,
   and .blade.php it shows up as a stray character in front of the first
   selector / statement / markup.

   Yang ditulis ulang di sesi ini dan bisa kena.
*/
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const raw = execSync('git status --short', { cwd: path.join(__dirname, '..'), encoding: 'utf8' });
const files = raw.split(/\r?\n/)
  .map(l => l.slice(3).trim())
  .filter(f => f && /\.(php|css|js|cjs|json|blade\.php)$/.test(f));

let dibersihkan = 0;
for (const f of files) {
  const p = path.join(__dirname, '..', f);
  if (!fs.existsSync(p)) continue;
  const buf = fs.readFileSync(p);
  if (buf.length < 3) continue;
  if (buf[0] !== 0xEF || buf[1] !== 0xBB || buf[2] !== 0xBF) continue;
  fs.writeFileSync(p, buf.slice(3));
  dibersihkan++;
  console.log('  ' + f);
}
console.log('BOM dibuang dari ' + dibersihkan + ' file');
