<?php

namespace App\Support;

/**
 * Menyaring HTML isi postingan (hasil editor) dengan daftar putih: hanya tag pemformatan dasar, gambar, dan tautan yang lolos;
 * semua atribut lain, skrip, gaya inline, dan pengendali kejadian (onclick, dst.) dibuang. Aman dicetak sebagai HTML mentah.
 */
class HtmlBersih
{
    private const TAG = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'pre', 'code', 'hr', 'figure', 'figcaption', 'span', 'sub', 'sup'];

    private const TAG_BLOK = ['p', 'h2', 'h3', 'h4', 'li', 'ul', 'ol', 'blockquote', 'span'];

    public static function bersihkan(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="akar">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $akar = $dom->getElementById('akar');
        if (!$akar) {
            return e(strip_tags($html));
        }
        self::saring($akar);

        $hasil = '';
        foreach ($akar->childNodes as $anak) {
            $hasil .= $dom->saveHTML($anak);
        }

        return trim($hasil);
    }

    private static function saring(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $anak) {
            if ($anak instanceof \DOMText) {
                continue;
            }
            if (!($anak instanceof \DOMElement)) {
                $node->removeChild($anak); // komentar, CDATA, dst.

                continue;
            }

            $tag = strtolower($anak->tagName);
            if (!in_array($tag, self::TAG, true)) {
                // Tag tak dikenal: buang tag-nya, pertahankan isi teks/anak yang aman (skrip & gaya dibuang beserta isinya).
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'svg'], true)) {
                    $node->removeChild($anak);

                    continue;
                }
                self::saring($anak);
                while ($anak->firstChild) {
                    $node->insertBefore($anak->firstChild, $anak);
                }
                $node->removeChild($anak);

                continue;
            }

            $kelasAsal = (string) $anak->getAttribute('class');
            foreach (iterator_to_array($anak->attributes) as $atr) {
                $nama = strtolower($atr->name);
                $nilai = trim($atr->value);
                $boleh = ($tag === 'a' && $nama === 'href' && self::urlAman($nilai, false))
                    || ($tag === 'img' && $nama === 'src' && self::urlAman($nilai, true))
                    || ($tag === 'img' && $nama === 'alt');
                if (!$boleh) {
                    $anak->removeAttribute($atr->name);
                }
            }
            // Kelas tata letak dari editor (rata teks, indentasi, jarak) - hanya yang ada di daftar putih.
            if (in_array($tag, self::TAG_BLOK, true) && ($kelas = self::kelasAman($kelasAsal)) !== '') {
                $anak->setAttribute('class', $kelas);
            }
            if ($tag === 'a') {
                $anak->setAttribute('target', '_blank');
                $anak->setAttribute('rel', 'noopener noreferrer');
            }
            if ($tag === 'img') {
                $anak->setAttribute('loading', 'lazy');
                if (!$anak->hasAttribute('src')) {
                    $node->removeChild($anak);

                    continue;
                }
            }
            self::saring($anak);
        }
    }

    private static function kelasAman(string $kelas): string
    {
        $lolos = array_filter(preg_split('/\s+/', trim($kelas)) ?: [], fn ($k) => preg_match('/^ql-(align-(center|right|justify)|indent-[1-8]|spasi-(rapat|longgar|lega)|font-(nunito|inter|lora|playfair|mono)|size-(small|large|huge)|color-(red|orange|green|blue|purple|gray)|bg-(yellow|lightgreen|lightblue|pink))$/', $k));

        return implode(' ', $lolos);
    }

    private static function urlAman(string $url, bool $gambar): bool
    {
        if ($url === '' || preg_match('/^\s*(javascript|vbscript|data):/i', $url)) {
            return false;
        }

        return (bool) preg_match($gambar ? '#^(https?:)?//|^/#i' : '#^(https?://|mailto:|/|\#)#i', $url);
    }

    /** Perkiraan menit baca (± 200 kata per menit). */
    public static function menitBaca(string $html): int
    {
        $kata = str_word_count(strip_tags($html));

        return max(1, (int) ceil($kata / 200));
    }
}
