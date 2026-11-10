/* COMMAND PALETTE (Ctrl+K) — Visual Refresh v2.
 *
 * Port dari implementasi vanilla yang lama, kini memakai framer-motion.
 * Sumber data tetap DOM: tautan yang dirender sidebar (sudah ikut
 * permission $cekAkses dan status menu aktif), jadi palette selalu
 * mengikuti hak akses yang sama, tanpa daftar menu kedua.
 */
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { AnimatePresence, motion } from 'framer-motion';

const kumpulkan = () => {
    const nav = document.getElementById('mainSidebar');
    if (!nav) return [];
    const hasil = [];
    const isBadge = (el) =>
        el.classList.contains('notif-badge') ||
        /(^|[\s-])badge([\s-]|$)/.test(el.className || '') ||
        el.hasAttribute('data-badge');

    nav.querySelectorAll('a[href]').forEach((a) => {
        const href = a.getAttribute('href');
        if (!href || href === '#' || href === 'javascript:void(0)' || a.target === '_blank') return;

        const spanLabel = [...a.querySelectorAll('span')].find((s) => !isBadge(s) && s.textContent.trim());
        const label = (spanLabel || a).textContent.trim();
        if (!label) return;

        const namaGrupDari = (btn) => {
            const s = btn.querySelector('span:not(.notif-badge)');
            return s ? s.textContent.trim() : '';
        };
        let grup = '';
        let n = a.previousElementSibling;
        while (n) {
            if (n.classList && n.classList.contains('menu-group-toggle')) { grup = namaGrupDari(n); break; }
            if (n.classList && n.classList.contains('menu-group-items')) {
                const btn = n.previousElementSibling;
                if (btn && btn.classList.contains('menu-group-toggle')) { grup = namaGrupDari(btn); break; }
            }
            n = n.previousElementSibling;
        }

        const svg = a.querySelector('svg');
        hasil.push({
            teks: label,
            grup,
            href: a.href,
            path: (a.pathname || '').replace(/^\/+|\/+$/g, ''),
            ikon: svg ? svg.outerHTML : '',
            aktif: a.classList.contains('sb-active'),
        });
    });
    return hasil;
};

/* Skor tiga lapis: nama menu, grup, lalu path URL. Kecil = lebih cocok. */
const skor = (teks, kueri) => {
    const t = teks.toLowerCase();
    const k = kueri.toLowerCase().replace(/\s+/g, '');
    if (!k) return { cocok: true, nilai: 0 };

    const kataOf = new Array(t.length).fill(0);
    let wi = 0;
    for (let i = 0; i < t.length; i++) {
        if (t[i] === ' ' || t[i] === '-' || t[i] === '&' || t[i] === '/') { wi += 1; continue; }
        kataOf[i] = wi;
    }

    const tRapis = t.replace(/[\s\-&/]/g, '');
    let pos = 0, berurutan = true, awal = -1;
    const kataPemakai = new Set();
    for (let i = 0; i < k.length; i++) {
        const d = tRapis.indexOf(k[i], pos);
        if (d < 0) return { cocok: false };
        if (awal < 0) awal = d;
        if (d !== pos) berurutan = false;
        kataPemakai.add(kataOf[d]);
        pos = d + 1;
    }
    let nilai = 0;
    if (kataPemakai.size > 1) nilai += 25 * (kataPemakai.size - 1);
    if (!berurutan) nilai += 40;
    if (awal > 0) nilai += awal * 3;
    nilai += teks.length / 100;
    return { cocok: true, nilai };
};

const filterMenu = (semua, kueri) => {
    const hasil = [];
    for (const m of semua) {
        const byName = skor(m.teks, kueri);
        if (byName.cocok) {
            let nilai = byName.nilai;
            if (kueri && m.grup) {
                const sg = skor(m.grup, kueri);
                if (sg.cocok) nilai += 40;
            }
            hasil.push({ m, nilai });
            continue;
        }
        if (!kueri) continue;
        let nilai = null;
        if (m.grup) {
            const sg = skor(m.grup, kueri);
            if (sg.cocok) nilai = sg.nilai + 40;
        }
        if (nilai === null) {
            const su = skor(m.path, kueri);
            if (su.cocok) nilai = su.nilai + 50;
        }
        if (nilai !== null) hasil.push({ m, nilai });
    }
    hasil.sort((a, b) => a.nilai - b.nilai || a.m.teks.localeCompare(b.m.teks));
    return hasil;
};

function CommandPalette() {
    const [terbuka, setTerbuka] = useState(false);
    const [semua, setSemua] = useState([]);
    const [kueri, setKueri] = useState('');
    const [indeks, setIndeks] = useState(0);
    const inputRef = useRef(null);

    const buka = useCallback(() => {
        setSemua(kumpulkan());
        setKueri('');
        setIndeks(0);
        setTerbuka(true);
    }, []);
    const tutup = useCallback(() => setTerbuka(false), []);

    useEffect(() => {
        window.bukaPalette = buka;
        window.tutupPalette = tutup;
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (terbuka) tutup(); else buka();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [terbuka, buka, tutup]);

    useEffect(() => { if (terbuka && inputRef.current) inputRef.current.focus(); }, [terbuka]);
    useEffect(() => { setIndeks(0); }, [kueri]);

    const cocok = useMemo(() => filterMenu(semua, kueri), [semua, kueri]);
    const pilih = (m) => { window.location.href = m.href; };

    const onInputKey = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setIndeks((i) => (cocok.length ? (i + 1) % cocok.length : 0)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setIndeks((i) => (cocok.length ? (i - 1 + cocok.length) % cocok.length : 0)); }
        else if (e.key === 'Enter') { e.preventDefault(); if (cocok[indeks]) pilih(cocok[indeks].m); }
        else if (e.key === 'Escape') { e.preventDefault(); tutup(); }
    };

    let grupSebelumnya = null;
    const baris = [];
    cocok.forEach((c, i) => {
        if (c.m.grup !== grupSebelumnya) {
            grupSebelumnya = c.m.grup;
            if (grupSebelumnya) baris.push({ jenis: 'grup', kunci: 'g-' + grupSebelumnya, grup: grupSebelumnya });
        }
        baris.push({ jenis: 'item', kunci: 'i-' + i, c, i });
    });

    return (
        <AnimatePresence>
            {terbuka && (
                <div className="cmdk" role="dialog" aria-modal="true" aria-label="Cari menu">
                    <motion.div className="cmdk-backdrop" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={tutup} />
                    <motion.div
                        className="cmdk-panel"
                        initial={{ opacity: 0, scale: 0.96, y: -12 }}
                        animate={{ opacity: 1, scale: 1, y: 0, transition: { type: 'spring', stiffness: 340, damping: 27 } }}
                        exit={{ opacity: 0, scale: 0.97, y: -8, transition: { duration: 0.15 } }}
                    >
                        <div className="cmdk-input-row">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                            <input ref={inputRef} type="text" value={kueri} onChange={(e) => setKueri(e.target.value)} onKeyDown={onInputKey}
                                placeholder="Cari menu..." role="combobox" aria-expanded="true" aria-controls="cmdk-list" aria-autocomplete="list" autoComplete="off" />
                            <kbd>Ctrl K</kbd>
                        </div>
                        <div id="cmdk-list" role="listbox" className="cmdk-list">
                            {baris.length === 0 && (
                                <div className="cmdk-empty">
                                    <p>Tidak ada menu yang cocok</p>
                                    <small>{kueri.trim() ? 'Coba kata yang lebih pendek.' : 'Panel menu sedang tidak menampilkan menu.'}</small>
                                </div>
                            )}
                            {baris.map((baris) => baris.jenis === 'grup' ? (
                                <div key={baris.kunci} className="cmdk-group">{baris.grup}</div>
                            ) : (
                                <motion.button
                                    layout
                                    key={baris.kunci}
                                    type="button"
                                    role="option"
                                    aria-selected={baris.i === indeks}
                                    className={'cmdk-item' + (baris.i === indeks ? ' aktif' : '')}
                                    onMouseEnter={() => setIndeks(baris.i)}
                                    onClick={() => pilih(baris.c.m)}
                                >
                                    <span className="cmdk-item-icon" dangerouslySetInnerHTML={{ __html: baris.c.m.ikon }} />
                                    <span className="cmdk-item-label">{baris.c.m.teks}</span>
                                    {baris.c.m.aktif && <span className="cmdk-item-page">halaman ini</span>}
                                </motion.button>
                            ))}
                        </div>
                        <div className="cmdk-kaki">
                            <span><kbd>↑↓</kbd> pindah</span>
                            <span><kbd>Enter</kbd> buka</span>
                            <span><kbd>Esc</kbd> tutup</span>
                            <span className="cmdk-hitung">{cocok.length} menu</span>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}

const mountEl = document.getElementById('cmdk-react');
if (mountEl) createRoot(mountEl).render(<CommandPalette />);
