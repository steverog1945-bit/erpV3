{{-- Tool import CSV Daftar Pemain: parser + validasi master ukuran/lengan/kerah
     lewat option dropdown template baris pemain, isi baris pemain form, unduh
     template CSV, dan panel hasil per baris. Satu include per halaman; tombol
     per kartu ada di _line-card-fields.blade.php. JANGAN di-double include --}}

{{-- Blok data template CSV (rendered server, dibaca JS lewat textContent). --}}
<script type="text/plain" id="csv-pemain-template-data">nama;nomor;ukuran;gender;lengan;kerah</script>

{{-- Input file CSV dibagi pakai satu elemen saja untuk seluruh halaman;
     tombol Import CSV per kartu barang (di _line-card-fields) yang
     memicunya. --}}
<input type="file" id="csv-pemain-file-input" accept=".csv,text/csv,text/plain" class="hidden">

<script>
// =====================================================================
// TEMPLATE CSV TETAP "DAFTAR PEMAIN JERSEY"
// ---------------------------------------------------------------------
// Satu baris = satu pemain, urutan kolom TETAP:
//   nama;nomor;ukuran;gender;lengan;kerah
// - Pemisah kolom: titik koma (;) - hasil simpanan default Excel Indonesia.
// - Baris pertama boleh berupa header (nama/nomor/...) - otomatis dilewati.
// - gender: L atau P (kosong = L).
// - ukuran/lengan/kerah: HARUS sama dengan nama di Data Master Jersey
//   (Ukuran / Tipe Lengan / Tipe Kerah) - pencocokan tidak peduli huruf
//   besar/kecil & spasi berlebih. Barang tanpa lengan/kerah (mis. Celana)
//   tetap WAJIB menuliskan master "Tanpa Lengan" / "Tanpa Kerah".
// - Semua baris divalidasi dulu; kalau ada yang gagal, TIDAK ADA baris yang
//   diisi (atomik) supaya form tidak pernah setengah jadi.
//
// Format ini juga menjadi PEDIOMAN MACRO .GMS CorelDRAW untuk otomasi
// layouting jersey (satu baris = satu sisi/gambar pemain): macro membaca
// nama, nomor, dan ukuran per baris dengan urutan kolom yang sama persis.
// =====================================================================

function csvPemainSplitBaris(text) {
    return text.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n').split('\n');
}

function csvPemainParseLine(line) {
    const out = [];
    let cur = '', inQ = false;
    for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (inQ) {
            if (ch === '"') {
                if (line[i + 1] === '"') { cur += '"'; i++; }
                else inQ = false;
            } else cur += ch;
        } else if (ch === '"') {
            inQ = true;
        } else if (ch === ';') {
            out.push(cur); cur = '';
        } else {
            cur += ch;
        }
    }
    out.push(cur);
    return out;
}

// Pencocokan nama master: tidak peduli huruf besar/kecil & spasi berlebih.
function csvPemainKey(s) {
    return String(s ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
}

function csvPemainBacaMaster(selectEl) {
    // PENTING: untuk lengan/kerah, option.value adalah ID angka - nama yang
    // ditulis di CSV harus dicocokkan ke option TEXT, bukan value. (Bug lama:
    // dipetakan dari value sehingga SEMUA nama lengan/kerah "tidak ada di
    // master" walau ditulis benar.) Ukuran memakai name sebagai value, jadi
    // tetap benar - kini seragam lewat text.
    const map = new Map();   // kunci nama (lowercase, spasi rapat) -> value option
    const label = new Map(); // kunci nama -> teks tampilan (untuk pesan error)
    selectEl.querySelectorAll('option').forEach(op => {
        const nama = (op.textContent || '').trim();
        const v = (op.value || '').trim();
        if (v && nama && !op.disabled) {
            map.set(csvPemainKey(nama), v);
            label.set(csvPemainKey(nama), nama);
        }
    });
    return { map, label };
}

function csvPemainMasterDariTemplate() {
    // PENTING: isi <template> berada di template.content (DocumentFragment
    // terpisah), BUKAN child DOM biasa - querySelector dijalankan langsung
    // pada elemen template SELALU null. Itulah sebabnya import CSV pernah
    // selalu gagal dengan "Template baris pemain tidak ditemukan".
    const tpl = document.getElementById('template_baris_pemain_jersey');
    const root = tpl ? (tpl.content || tpl) : null;
    if (!root) return null;
    const opsiSize = root.querySelector('select[name$="[size]"]');
    const opsiSleeve = root.querySelector('select[name$="[jersey_sleeve_type_id]"]');
    const opsiCollar = root.querySelector('select[name$="[jersey_collar_type_id]"]');
    if (!opsiSize || !opsiSleeve || !opsiCollar) return null;
    return {
        size: csvPemainBacaMaster(opsiSize),
        sleeve: csvPemainBacaMaster(opsiSleeve),
        collar: csvPemainBacaMaster(opsiCollar),
    };
}

// Kembalikan array {nama,nomor,ukuran,gender,lengan,kerah} atau {error:...}.
function csvPemainParse(text, master) {
    const barisSemua = csvPemainSplitBaris(text)
        .map(b => b.trim())
        .filter(b => b !== '');
    if (barisSemua.length === 0) {
        return { error: 'File CSV kosong.' };
    }

    let baris = barisSemua;
    const first = baris[0].toLowerCase();
    if (first.includes('nama') && (first.includes('ukuran') || first.includes('nomor'))) {
        baris = baris.slice(1);
    }
    if (baris.length === 0) {
        return { error: 'File CSV hanya berisi header, tidak ada baris pemain.' };
    }

    const hasil = [], errors = [];
    baris.forEach((line, i) => {
        const noBaris = i + 2; // +2: baris 1 header (jika ada)
        const kol = csvPemainParseLine(line);
        const gagal = (pesan) => errors.push(`Baris ${noBaris}: ${pesan}`);

        if (kol.length < 6) {
            gagal(`kolom kurang (dapat ${kol.length}, harus 6: nama;nomor;ukuran;gender;lengan;kerah).`);
            return;
        }
        const nama = kol[0].trim();
        const nomor = kol[1].trim();
        const ukuran = kol[2].trim();
        const genderRaw = kol[3].trim();
        const lenganRaw = kol[4].trim();
        const kerahRaw = kol[5].trim();

        if (nama === '') { gagal('nama pemain kosong.'); return; }
        if (nomor !== '' && !/^\d{1,3}$/.test(nomor)) { gagal(`nomor punggung "${nomor}" tidak valid (isi angka, mis. 10).`); return; }

        const ukuranFix = master.size.map.get(csvPemainKey(ukuran));
        if (!ukuranFix) {
            gagal(`ukuran "${ukuran}" tidak ada di Master Ukuran. Pilihan: ${[...master.size.label.values()].join(', ')}.`);
            return;
        }

        let gender = 'L';
        if (genderRaw !== '') {
            const g = csvPemainKey(genderRaw);
            if (g === 'p') gender = 'P';
            else if (g !== 'l') { gagal(`gender "${genderRaw}" harus L atau P.`); return; }
        }

        const lenganFix = master.sleeve.map.get(csvPemainKey(lenganRaw));
        if (!lenganFix) {
            gagal(`lengan "${lenganRaw}" tidak ada di Master Tipe Lengan. Pilihan: ${[...master.sleeve.label.values()].join(', ')}.`);
            return;
        }
        const kerahFix = master.collar.map.get(csvPemainKey(kerahRaw));
        if (!kerahFix) {
            gagal(`kerah "${kerahRaw}" tidak ada di Master Tipe Kerah. Pilihan: ${[...master.collar.label.values()].join(', ')}.`);
            return;
        }

        hasil.push({ nama, nomor, ukuran: ukuranFix, gender, lengan: lenganFix, kerah: kerahFix });
    });

    if (errors.length > 0) return { error: errors.join('\n'), jumlah: errors.length };
    if (hasil.length === 0) return { error: 'Tidak ada baris pemain yang valid.' };
    return { rows: hasil };
}

function csvPemainUnduhTemplate() {
    const blob = new Blob(['\uFEFF' + document.getElementById('csv-pemain-template-data').textContent.trim() + '\n'], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'template-daftar-pemain-jersey.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
}

// Isi hasil parse CSV ke baris pemain satu kartu barang (atomik: bila ada
// error, tidak ada baris yang diisi). Baris pemain lama pada kartu itu
// DIGANTI seluruhnya oleh isi CSV.
function csvPemainTerapkan(btn, rows) {
    const card = btn.closest('.jersey-line-card');
    // CSV = daftar pemain: pindahkan kartu ke mode Daftar Pemain lewat
    // radio ASLI form (dispatch change -> toggleLineType jalan normal).
    // Karena qty per ukuran dihapus dulu di bawah, pengunci mode tidak
    // menemukan data yang harus dibuang (tidak ada dialog), kartu hanya
    // berpindah mode - dan yang terkirim ke server murni daftar pemain.
    // Tanpa ini, kartu yang tadinya mode Qty per Ukuran mengirim qty lama
    // + pemain CSV sekaligus: harga "menumpuk" di perkiraan & saat disimpan.
    const radioPemain = card.querySelector('input[name$="[order_type]"][value="player_list"]');
    if (radioPemain && !radioPemain.checked) {
        card.querySelectorAll('.input-qty-ukuran').forEach(i => { i.value = 0; });
        if (typeof hitungUlangQtyUkuran === 'function') hitungUlangQtyUkuran();
        radioPemain.checked = true;
        radioPemain.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const tbody = card.querySelector('.tbody-pemain');
    tbody.querySelectorAll('.baris-pemain').forEach(tr => tr.remove());
    rows.forEach(r => {
        tambahBarisPemain(btn);
        const tr = tbody.lastElementChild;
        tr.querySelector('input[name$="[player_name]"]').value = r.nama;
        tr.querySelector('input[name$="[player_number]"]').value = r.nomor;
        const selSize = tr.querySelector('select[name$="[size]"]');
        selSize.value = r.ukuran;
        const selGender = tr.querySelector('select[name$="[gender]"]');
        selGender.value = r.gender;
        const selLengan = tr.querySelector('select[name$="[jersey_sleeve_type_id]"]');
        selLengan.value = r.lengan;
        const selKerah = tr.querySelector('select[name$="[jersey_collar_type_id]"]');
        selKerah.value = r.kerah;
    });
    perbaruiVisibilitasLenganKerah(card);
    if (typeof jadwalkanPreviewHarga === 'function') jadwalkanPreviewHarga();
    if (typeof jadwalkanPreviewHargaSusulan === 'function') jadwalkanPreviewHargaSusulan();
}

function csvPemainTampilkanHasil(card, pesan, adaGagal) {
    const panel = card.querySelector('.csv-pemain-hasil');
    if (!panel) return;
    if (!pesan) { panel.classList.add('hidden'); panel.innerHTML = ''; return; }
    panel.classList.remove('hidden');
    panel.innerHTML = pesan.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>');
    panel.classList.toggle('bg-rose-50', !!adaGagal);
    panel.classList.toggle('text-rose-600', !!adaGagal);
    panel.classList.toggle('border-rose-100', !!adaGagal);
    panel.classList.toggle('bg-emerald-50', !adaGagal);
    panel.classList.toggle('text-emerald-700', !adaGagal);
    panel.classList.toggle('border-emerald-100', !adaGagal);
}

// Entry point tombol "Import CSV" per kartu barang.
function csvPemainMulai(btn) {
    const input = document.getElementById('csv-pemain-file-input');
    input.value = '';
    input.dataset.targetBtn = '';
    // Tandai tombol pemanggil supaya onchange tahu kartu mana yang diisi.
    btn.dataset.csvAktif = '1';
    input.click();
}

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('csv-pemain-file-input');
    if (!input || input.dataset.csvTerpasang) return;
    input.dataset.csvTerpasang = '1';

    input.addEventListener('change', function () {
        const btn = document.querySelector('.btn-import-csv-pemain[data-csv-aktif="1"]');
        document.querySelectorAll('.btn-import-csv-pemain[data-csv-aktif]').forEach(b => delete b.dataset.csvAktif);
        if (!btn || !input.files || !input.files[0]) return;

        const card = btn.closest('.jersey-line-card');
        const master = csvPemainMasterDariTemplate();
        if (!master) {
            csvPemainTampilkanHasil(card, 'Template baris pemain tidak ditemukan di halaman ini.', true);
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const hasil = csvPemainParse(String(e.target.result || ''), master);
            if (hasil.error) {
                csvPemainTampilkanHasil(card, `Import CSV dibatalkan - tidak ada baris yang diubah.\n${hasil.error}`, true);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'CSV belum sesuai template', text: 'Perbaiki baris yang ditandai lalu import ulang. Detail per baris ada di bawah tabel pemain.', confirmButtonColor: '#006C7B' });
                }
                return;
            }
            csvPemainTerapkan(btn, hasil.rows);
            csvPemainTampilkanHasil(card, `CSV diimport: ${hasil.rows.length} pemain masuk ke Daftar Pemain kartu ini. Periksa kembali sebelum simpan.`, false);
            if (typeof Toast !== 'undefined') Toast.fire({ icon: 'success', title: `${hasil.rows.length} pemain diimport` });
        };
        reader.readAsText(input.files[0], 'utf-8');
    });
});
</script>
