"""Elemen yang bisa diklik tetapi tidak bisa dicapai keyboard: onclick pada elemen non-interaktif tanpa tabindex/role."""
import glob, re, sys

hasil = {}
for p in glob.glob('resources/views/**/*.blade.php', recursive=True):
    if '.bck' in p:
        continue
    s = open(p, encoding='utf-8').read()
    for m in re.finditer(r'<(div|span|tr|td|li|i|img|label|p|h\d)\b([^>]*?)\bonclick=', s):
        tag = s[m.start():s.find('>', m.start()) + 1]
        if 'tabindex' in tag or 'role="button"' in tag:
            continue
        baris = s.count('\n', 0, m.start()) + 1
        hasil.setdefault(p.replace(chr(92), '/'), []).append((m.group(1), baris))
print(sum(len(v) for v in hasil.values()))
for k, v in sorted(hasil.items(), key=lambda x: -len(x[1])):
    print(len(v), k, [f'{t}:{b}' for t, b in v][:6])
