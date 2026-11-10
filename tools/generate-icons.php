<?php
/**
 * Generator ikon PWA Command Center (2026-11).
 *
 * Brand mark: "tiga titik Orion" (cyan/rose/kuning) di atas ubin gradien
 * biru #3B82F6 -> #1D4ED8, latar navy #0F1E3E dengan glow radial halus.
 * Maskable: full-bleed navy + titik di zona aman (tanpa ubin, sudut
 * terpotong OS dengan aman).
 *
 * Pemakaian: php tools/generate-icons.php (menulis public/icons/*.png + public/favicon.ico)
 */

$ROOT = dirname(__DIR__);
$OUT = $ROOT . '/public/icons';
if (!is_dir($OUT)) {
    mkdir($OUT, 0755, true);
}

const NAVY_DEEP = [10, 20, 46];      // #0A142E
const NAVY_GLOW = [30, 42, 74];      // #1E2A4A
const BLUE_TOP = [59, 130, 246];     // #3B82F6
const BLUE_BOTTOM = [29, 78, 216];   // #1D4ED8
const DOT_CYAN = [34, 211, 238];     // #22D3EE
const DOT_ROSE = [244, 63, 94];      // #F43F5E
const DOT_YELLOW = [250, 204, 21];   // #FACC15

function lerpc(array $a, array $b, float $t): array {
    $t = max(0.0, min(1.0, $t));
    return [
        (int) round($a[0] + ($b[0] - $a[0]) * $t),
        (int) round($a[1] + ($b[1] - $a[1]) * $t),
        (int) round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

function smooth(float $t): float {
    // smoothstep
    return $t * $t * (3 - 2 * $t);
}

function drawBackground(GdImage $img, int $size): void {
    $cx = $size / 2;
    $cy = $size / 2;
    $maxD = $size * 0.72;
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $dx = $x - $cx;
            $dy = $y - $cy;
            $d = sqrt($dx * $dx + $dy * $dy) / $maxD;
            $t = smooth(min(1.0, $d));
            $c = lerpc(NAVY_GLOW, NAVY_DEEP, $t);
            imagesetpixel($img, $x, $y, imagecolorallocate($img, $c[0], $c[1], $c[2]));
        }
    }
}

function inRoundedRect(float $x, float $y, float $left, float $top, float $right, float $bottom, float $radius): bool {
    if ($x < $left || $x > $right || $y < $top || $y > $bottom) {
        return false;
    }
    $cx = null;
    $cy = null;
    if ($x < $left + $radius && $y < $top + $radius) { $cx = $left + $radius; $cy = $top + $radius; }
    elseif ($x > $right - $radius && $y < $top + $radius) { $cx = $right - $radius; $cy = $top + $radius; }
    elseif ($x < $left + $radius && $y > $bottom - $radius) { $cx = $left + $radius; $cy = $bottom - $radius; }
    elseif ($x > $right - $radius && $y > $bottom - $radius) { $cx = $right - $radius; $cy = $bottom - $radius; }
    if ($cx !== null) {
        $dx = $x - $cx;
        $dy = $y - $cy;
        return ($dx * $dx + $dy * $dy) <= $radius * $radius;
    }
    return true;
}

function drawTile(GdImage $img, int $size): void {
    $pad = (int) round($size * 0.145);
    $left = $pad;
    $top = $pad;
    $right = $size - $pad;
    $bottom = $size - $pad;
    $radius = (int) round($size * 0.175);
    for ($y = $top; $y <= $bottom; $y++) {
        for ($x = $left; $x <= $right; $x++) {
            if (!inRoundedRect($x + 0.5, $y + 0.5, $left, $top, $right, $bottom, $radius)) {
                continue;
            }
            $t = (($x - $left) + ($y - $top)) / (($right - $left) + ($bottom - $top));
            $t = smooth($t);
            $c = lerpc(BLUE_TOP, BLUE_BOTTOM, $t);
            imagesetpixel($img, $x, $y, imagecolorallocate($img, $c[0], $c[1], $c[2]));
        }
    }
    // Tepi ubin: stroke mengikuti sudut membulat supaya terlihat presisi
    $edge = imagecolorallocatealpha($img, 255, 255, 255, 105);
    for ($x = $left + $radius; $x <= $right - $radius; $x++) {
        imagesetpixel($img, $x, $top, $edge);
        imagesetpixel($img, $x, $bottom, $edge);
    }
    for ($y = $top + $radius; $y <= $bottom - $radius; $y++) {
        imagesetpixel($img, $left, $y, $edge);
        imagesetpixel($img, $right, $y, $edge);
    }
    imagearc($img, $left + $radius, $top + $radius, $radius * 2, $radius * 2, 180, 270, $edge);
    imagearc($img, $right - $radius, $top + $radius, $radius * 2, $radius * 2, 270, 360, $edge);
    imagearc($img, $left + $radius, $bottom - $radius, $radius * 2, $radius * 2, 90, 180, $edge);
    imagearc($img, $right - $radius, $bottom - $radius, $radius * 2, $radius * 2, 0, 90, $edge);
}

function drawDots(GdImage $img, int $size, float $scale = 1.0): void {
    $cx = $size / 2;
    $cy = $size / 2;
    $r = (int) round($size * 0.075 * $scale);
    $off = $size * 0.105 * $scale;
    $dy = $size * 0.062 * $scale;
    $dots = [
        [DOT_ROSE, 0.0, -$dy],
        [DOT_CYAN, -$off, $dy],
        [DOT_YELLOW, $off, $dy],
    ];
    foreach ($dots as [$rgb, $dx, $dyy]) {
        $c = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledellipse($img, (int) round($cx + $dx), (int) round($cy + $dyy), $r * 2, $r * 2, $c);
    }
}

function makeIcon(int $size, bool $maskable): GdImage {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    // Latar navy
    $navy = imagecolorallocate($img, NAVY_DEEP[0], NAVY_DEEP[1], NAVY_DEEP[2]);
    imagefilledrectangle($img, 0, 0, $size, $size, $navy);
    drawBackground($img, $size);
    if ($maskable) {
        // Full-bleed: tanpa ubin; titik di zona aman 70% tengah
        drawDots($img, $size, 0.82);
    } else {
        drawTile($img, $size);
        drawDots($img, $size, 1.0);
    }
    return $img;
}

function png(GdImage $img, string $path): void {
    imagepng($img, $path, 9);
    echo "ditulis: $path\n";
}

$i192 = makeIcon(192, false);
png($i192, $OUT . '/icon-192.png');
$i512 = makeIcon(512, false);
png($i512, $OUT . '/icon-512.png');
$mask = makeIcon(512, true);
png($mask, $OUT . '/icon-maskable-512.png');
$apple = makeIcon(180, false);
png($apple, $OUT . '/apple-touch-icon.png');
$fav = makeIcon(48, false);

// favicon.ico berisi PNG 48x48 (format ICO dengan payload PNG, didukung semua browser modern)
ob_start();
imagepng($fav);
$pngBytes = ob_get_clean();
$header = pack('vvv', 0, 1, 1);            // reserved=0, type=1 (icon), count=1
$entry = pack('CCCCvvVV', 48, 48, 0, 0, 1, 32, strlen($pngBytes), 6 + 16);
file_put_contents($ROOT . '/public/favicon.ico', $header . $entry . $pngBytes);
echo "ditulis: {$ROOT}/public/favicon.ico\n";

imagedestroy($i192);
imagedestroy($i512);
imagedestroy($mask);
imagedestroy($apple);
imagedestroy($fav);
echo "selesai.\n";
