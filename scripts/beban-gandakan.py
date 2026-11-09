"""Menggandakan data transaksi di database BEBAN (bukan oriondb) untuk uji performa.
Setiap putaran: baris disalin dengan id digeser, FK ke tabel yang ikut digandakan ikut digeser,
kolom unik teks diberi akhiran, dan tanggal digeser acak sampai ~2 tahun ke belakang."""
import subprocess, sys

DB = 'percetakan_erp_beban'
assert DB != 'oriondb'
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
PUTARAN = int(sys.argv[1]) if len(sys.argv) > 1 else 10
TABEL = ['transaksi', 'transaksi_detail', 'transaksi_detail_bahan_pakai', 'pembayaran_riwayat', 'pengambilan',
         'jurnal_umum', 'kartu_stok', 'audit_logs', 'konsumen_deposit',
         'jersey_orders', 'jersey_order_lines', 'jersey_order_items', 'jersey_order_size_quantities',
         'jersey_order_line_materials', 'jersey_order_payments', 'kas_kecil', 'approval_requests']


def q(sql):
    r = subprocess.run([MYSQL, '-u', 'root', '-N', '-B', DB, '-e', sql], capture_output=True, text=True)
    if r.returncode:
        raise SystemExit(r.stderr[:2000])
    return [l.split('\t') for l in r.stdout.strip().split('\n') if l]


ada = {r[0] for r in q("select table_name from information_schema.tables where table_schema=database()")}
TABEL = [t for t in TABEL if t in ada]
kolom = {t: q(f"select column_name, data_type, extra from information_schema.columns where table_schema=database() and table_name='{t}' order by ordinal_position") for t in TABEL}
pk = {t: q(f"select column_name from information_schema.key_column_usage where table_schema=database() and table_name='{t}' and constraint_name='PRIMARY'")[0][0] for t in TABEL}
fk = {}
for t in TABEL:
    for c, rt in q(f"select column_name, referenced_table_name from information_schema.key_column_usage where table_schema=database() and table_name='{t}' and referenced_table_name is not null"):
        if rt in TABEL:
            fk.setdefault(t, {})[c] = rt
unik = {}
for t in TABEL:
    for (c,) in q(f"select s.column_name from information_schema.statistics s join information_schema.columns c on c.table_schema=s.table_schema and c.table_name=s.table_name and c.column_name=s.column_name where s.table_schema=database() and s.table_name='{t}' and s.non_unique=0 and s.index_name<>'PRIMARY' and c.data_type in ('varchar','char')"):
        unik.setdefault(t, set()).add(c)

TGL = ('date', 'datetime', 'timestamp')
for i in range(1, PUTARAN + 1):
    ofs = {t: int(q(f"select coalesce(max(`{pk[t]}`),0) from `{t}`")[0][0]) for t in TABEL}
    geser = f"@g := FLOOR(RAND({i})*700)"
    perintah = ['SET FOREIGN_KEY_CHECKS=0;']
    for t in TABEL:
        cols, sel = [], []
        for baris in kolom[t]:
            c, tipe, extra = (baris + ['', ''])[:3]
            if 'GENERATED' in extra.upper():
                continue
            cols.append(f'`{c}`')
            if c == pk[t]:
                sel.append(f'`{c}` + {ofs[t]}')
            elif c in fk.get(t, {}):
                sel.append(f'IF(`{c}` IS NULL, NULL, `{c}` + {ofs[fk[t][c]]})')
            elif c in unik.get(t, set()):
                sel.append(f"LEFT(CONCAT(`{c}`, '-G{i}'), 250)")
            elif c == 'no_referensi' or c == 'referensi':
                sel.append(f"IF(`{c}` IS NULL, NULL, CONCAT(`{c}`, '-G{i}'))")
            elif tipe in TGL:
                sel.append(f'IF(`{c}` IS NULL, NULL, `{c}` - INTERVAL ((`{pk[t]}` * 7 + {i} * 13) % 700) DAY)')
            else:
                sel.append(f'`{c}`')
        perintah.append(f"INSERT INTO `{t}` ({', '.join(cols)}) SELECT {', '.join(sel)} FROM `{t}` WHERE `{pk[t]}` <= {ofs[t]};")
    perintah.append('SET FOREIGN_KEY_CHECKS=1;')
    q('\n'.join(perintah))
    print('putaran', i, {t: q(f'select count(*) from `{t}`')[0][0] for t in ('transaksi', 'jurnal_umum', 'audit_logs', 'kartu_stok', 'jersey_orders')}, flush=True)
