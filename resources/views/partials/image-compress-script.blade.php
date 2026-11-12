<script>
    // Kompres gambar lewat canvas SEBELUM diunggah - turunkan resolusi lalu
    // re-encode sebagai JPEG, dicoba bertahap (dimensi & kualitas makin
    // turun) sampai lolos target targetBytes. Dipakai di SEMUA tempat upload
    // gambar (thumbnail Input Pesanan, thumbnail Pesanan Jersey, bukti
    // pembayaran) supaya satu-satunya logika kompresi ada di sini - gambar
    // ASLI beresolusi penuh TIDAK PERNAH ikut terkirim ke server ataupun
    // tersimpan di disk manapun (termasuk di komputer development ini),
    // cuma hasil kompresnya. Dimensi ikut diperkecil (bukan cuma kualitas
    // JPEG-nya) karena foto dengan detail tinggi bisa tetap di atas target
    // walau kualitasnya sudah sangat rendah selama dimensinya masih besar.
    // Latar putih digambar dulu sebelum gambar asli supaya PNG transparan
    // tidak berubah jadi hitam saat di-flatten ke JPEG (JPEG tidak punya
    // kanal alpha).
    //
    // TARGET & RESOLUSI
    // -----------------
    // Dulu targetnya 10 KB dengan sisi terpanjang 480 px - hasilnya terlalu
    // kecil untuk dicetak di SPK: huruf dan detail di dalam desain jadi
    // pecah, jadi acuan warnanya sendiri tidak bisa dipercaya. Sekarang
    // targetnya 200 KB dengan sisi terpanjang 1600 px, jadi gambar tetap
    // jernih di kertas tanpa jadi millionair di server.
    //
    // Cara memilihnya: kombinasi dimensi (besar ke kecil) x kualitas (tinggi
    // ke rendah) dicoba berurutan, dan yang dipakai adalah kombinasi PERTAMA
    // yang ukurannya <= target - jadi yang dipilih selalu resolusi setinggi
    // mungkin yang masih muat, bukan kualitas paling rendah.
    const TARGET_BYTES_GAMBAR = 200 * 1024;
    const DIMENSI_GAMBAR = [1600, 1400, 1200, 1000, 800, 600, 450];
    const KUALITAS_GAMBAR = [0.85, 0.75, 0.6, 0.45, 0.3];

    // Format yang bisa dibuka peramban sebagai gambar, jadi bisa dikompres
    // lewat canvas. Format lain (PDF, CDR, AI, EPS, PSD, TIFF, HEIC)
    // TIDAK bisa dikompres di peramban - berkasnya dikirim apa adanya.
    function formatBisaDikompres(namaFile) {
        const ext = (String(namaFile || '').split('.').pop() || '').toLowerCase();
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'].indexOf(ext) >= 0;
    }

    // Peramban bisa menampilkan berkas ini sebagai gambar? Format desain
    // (PDF, CDR, AI, EPS) tidak bisa - kalau dipaksa jadi <img>, peramban
    // menampilkan gambar rusak, jadi berkas seperti itu ditampilkan sebagai
    // keterangan saja.
    function bisaDitampilkanBerkas(namaAtauPath) {
        const ext = (String(namaAtauPath || '').split('.').pop() || '').toLowerCase();
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'].indexOf(ext) >= 0;
    }

    // ==============================================================
    // PDF -> GAMBAR
    // --------------------------------------------------------------
    // PDF tidak bisa dibuka peramban sebagai gambar (dibutuhkan PDF viewer),
    // dan tidak ada konverter PDF di server ini. Tapi PDF bisa dirender
    // sendiri di peramban lewat pdf.js - hasilnya halaman pertama
    // digambar ke canvas, lalu diekspor sebagai JPEG.
    //
    // Efeknya untuk aplikasi: berkas yang diunggah berubah dari PDF jadi
    // GAMBAR (jpg), jadi tersimpan di server sebagai gambar - bisa
    // ditampilkan di SPK dan tidak perlu pdf.js saat pencetakan.
    //
    // pdf.js dimuat SEKALI saat dibutuhkan saja (bukan di setiap halaman),
    // dari public/vendor/pdfjs/pdf.min.js.
    // ==============================================================
    let pdfJsSiap = null;

    function muatPdfJs() {
        if (pdfJsSiap) return pdfJsSiap;

        pdfJsSiap = new Promise((resolve, reject) => {
            if (window.pdfjsLib) {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('vendor/pdfjs/pdf.worker.min.js') }}';
                resolve(window.pdfjsLib);
                return;
            }

            const script = document.createElement('script');
            script.src = '{{ asset('vendor/pdfjs/pdf.min.js') }}';
            script.onload = function () {
                if (!window.pdfjsLib) {
                    reject(new Error('pdf.js gagal dimuat.'));
                    return;
                }
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('vendor/pdfjs/pdf.worker.min.js') }}';
                resolve(window.pdfjsLib);
            };
            script.onerror = function () { reject(new Error('pdf.js gagal dimuat.')); };
            document.head.appendChild(script);
        });

        return pdfJsSiap;
    }

    /**
     * Render halaman pertama PDF menjadi blob JPEG.
     *
     * @param {File} file          berkas PDF
     * @param {number} lebarPx     lebar gambar hasil (tinggi ikut rasio)
     * @param {number} kualitas    kualitas JPEG 0..1
     * @param {number} batasMs     batas waktu render (ms)
     * @returns {Promise<Blob>}
     */
    async function rasterisasiPdf(file, lebarPx = 1600, kualitas = 0.85, batasMs = 25000) {
        const pdfjs = await muatPdfJs();
        const data = new Uint8Array(await file.arrayBuffer());

        const dokumen = await pdfjs.getDocument({ data: data }).promise;
        try {
            const halaman = await dokumen.getPage(1);
            const viewportAwal = halaman.getViewport({ scale: 1 });
            const skala = Math.min(lebarPx / viewportAwal.width, 4);
            const viewport = halaman.getViewport({ scale: skala });

            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.floor(viewport.width));
            canvas.height = Math.max(1, Math.floor(viewport.height));

            const konteks = canvas.getContext('2d');
            // Latar putih dulu: PDF bisa berisi area transparan, dan JPEG
            // tidak punya kanal alpha.
            konteks.fillStyle = '#ffffff';
            konteks.fillRect(0, 0, canvas.width, canvas.height);

            // pdf.js menggambar lewat requestAnimationFrame. Di tab yang
            // TIDAK terlihat (operator ganti tab selagi proses berjalan)
            // rAF tidak dijalankan sama sekali, sehingga render menggantung
            // selamanya dan unggahan tidak pernah selesai. Pakai setTimeout
            // selama render, lalu kembalikan yang asli.
            const rafaAsli = window.requestAnimationFrame;
            window.requestAnimationFrame = function (callback) {
                return setTimeout(function () { callback(Date.now()); }, 16);
            };

            try {
                await Promise.race([
                    halaman.render({ canvasContext: konteks, viewport: viewport }).promise,
                    new Promise(function (_, reject) {
                        setTimeout(function () { reject(new Error('Render PDF melebihi batas waktu.')); }, batasMs);
                    })
                ]);
            } finally {
                window.requestAnimationFrame = rafaAsli;
            }

            return await new Promise((res, rej) => {
                canvas.toBlob(function (blob) {
                    if (blob) res(blob);
                    else rej(new Error('Gagal membuat gambar dari PDF.'));
                }, 'image/jpeg', kualitas);
            });
        } finally {
            try { await dokumen.destroy(); } catch (e) { /* abaikan */ }
        }
    }

    function compressImage(file, targetBytes = TARGET_BYTES_GAMBAR, dims = DIMENSI_GAMBAR) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);

            img.onload = () => {
                URL.revokeObjectURL(objectUrl);

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                function renderAt(maxDim, quality) {
                    let { width, height } = img;
                    if (width > maxDim || height > maxDim) {
                        const scale = maxDim / Math.max(width, height);
                        width = Math.round(width * scale);
                        height = Math.round(height * scale);
                    }
                    canvas.width = width;
                    canvas.height = height;
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, width, height);
                    ctx.drawImage(img, 0, 0, width, height);
                    return new Promise((res) => canvas.toBlob(res, 'image/jpeg', quality));
                }

                (async () => {
                    let terkecil = null;

                    for (const dim of dims) {
                        for (const q of KUALITAS_GAMBAR) {
                            const blob = await renderAt(dim, q);
                            if (!blob) continue;
                            if (!terkecil || blob.size < terkecil.size) terkecil = blob;
                            if (blob.size <= targetBytes) { resolve(blob); return; }
                        }
                    }
                    // Tidak ada kombinasi yang lolos target (foto sangat kompleks) -
                    // pakai hasil terkecil yang berhasil didapat, jangan sampai
                    // gagal total/tidak ada gambar sama sekali.
                    if (terkecil) resolve(terkecil);
                    else reject(new Error('Gagal memproses gambar.'));
                })();
            };
            img.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('File bukan gambar yang valid.')); };
            img.src = objectUrl;
        });
    }

    // Nama file gambar -> teks deskripsi yang manusiawi: buang ekstensi,
    // ganti underscore/dash dengan spasi, rapikan spasi ganda. "banner_3x1-
    // meter_FINAL.jpg" jadi "banner 3x1 meter FINAL", bukan disalin mentah.
    function namaFileDariGambar(namaAsli) {
        return namaAsli
            .replace(/\.[^./]+$/, '')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /**
     * Siapkan berkas sebelum diunggah.
     *
     * - Gambar (jpg/png/webp/gif/bmp): dikompres sampai 200 KB dengan
     *   resolusi tinggi supaya tetap jernih saat dicetak di SPK.
     * - PDF: dirender jadi gambar (halaman pertama) oleh pdf.js, lalu
     *   dikirim sebagai JPEG. Jadi berkas yang sampai di server berupa
     *   GAMBAR - bisa langsung ditampilkan di SPK.
     * - Format lain (CDR, AI, EPS, PSD, TIFF, HEIC): tidak bisa dirender
     *   peramban, jadi dikirim apa adanya dan ditampilkan sebagai
     *   keterangan format di SPK.
     *
     * @returns {Promise<{file: File, gambar: boolean, dariPdf: boolean}>}
     */
    async function siapkanBerkasUntukUnggah(file, namaAsli) {
        if (formatBisaDikompres(namaAsli)) {
            const compressed = await compressImage(file);
            return {
                file: new File([compressed], namaFileDariGambar(namaAsli) + '.jpg', { type: 'image/jpeg' }),
                gambar: true,
                dariPdf: false
            };
        }

        if (String(namaAsli || '').toLowerCase().endsWith('.pdf')) {
            try {
                const gambar = await rasterisasiPdf(file);
                return {
                    file: new File([gambar], namaFileDariGambar(namaAsli) + '.jpg', { type: 'image/jpeg' }),
                    gambar: true,
                    dariPdf: true
                };
            } catch (e) {
                // PDF gagal dirender (pdf.js gagal dimuat, berkas rusak, atau
                // lewat batas waktu). Berkasnya tetap dikirim apa adanya
                // supaya desainnya tidak hilang - SPK nanti menampilkan
                // keterangan formatnya, bukan gambar rusak.
                console.warn('PDF tidak bisa dirender jadi gambar:', e);
                return { file: file, gambar: false, dariPdf: false, gagalRender: true };
            }
        }

        return { file: file, gambar: false, dariPdf: false };
    }
</script>
