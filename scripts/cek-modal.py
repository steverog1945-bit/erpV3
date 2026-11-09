"""Audit statis modal: (1) tag <div>/<form> tidak seimbang di dalam <x-modal> (membuat modal berikutnya
bersarang di dalam modal ini), (2) tombol submit di slot footer yang berada di luar <form> tanpa atribut form=."""
import re, glob, sys

VOID = set('area base br col embed hr img input link meta param source track wbr'.split())
temuan = []
for p in sorted(glob.glob('resources/views/**/*.blade.php', recursive=True)):
    s = open(p, encoding='utf-8').read()
    s_nc = re.sub(r'\{\{--.*?--\}\}', lambda m: '\n' * m.group(0).count('\n'), s, flags=re.S)
    for m in re.finditer(r'<x-modal\b([^>]*)>(.*?)</x-modal>', s_nc, flags=re.S):
        atr, isi = m.group(1), m.group(2)
        baris = s_nc.count('\n', 0, m.start()) + 1
        mid = re.search(r'\bid="([^"]+)"', atr)
        nama = mid.group(1) if mid else '?'
        # pisahkan footer
        f = re.search(r'<x-slot:footer>(.*?)</x-slot:footer>|<x-slot name="footer">(.*?)</x-slot>', isi, flags=re.S)
        footer = (f.group(1) or f.group(2)) if f else ''
        badan = isi.replace(f.group(0), '') if f else isi
        for tag in ('div', 'form', 'table', 'tbody', 'ul', 'label', 'span', 'p'):
            buka = len(re.findall(r'<' + tag + r'\b[^>]*?(?<!/)>', badan))
            tutup = len(re.findall(r'</' + tag + r'\s*>', badan))
            if buka != tutup and '@if' not in badan[:0]:
                temuan.append((p, baris, nama, f'<{tag}> buka {buka} tutup {tutup}'))
        if footer and re.search(r'<form\b', badan):
            # footer yang ditulis DI DALAM <form> dirender di luar form oleh komponen
            posisi_form_tutup = badan.rfind('</form>')
            ditulis_di_dalam = f and isi.find(f.group(0)) < isi.rfind('</form>')
            for t in re.finditer(r'<(x-button|button)\b([^>]*)>', footer):
                a = t.group(2)
                if 'type="submit"' in a and 'form=' not in a:
                    temuan.append((p, baris, nama, 'tombol submit di footer tanpa form= (berada di luar <form>)'))
for t in temuan:
    print(' | '.join(map(str, t)))
print('total', len(temuan))
