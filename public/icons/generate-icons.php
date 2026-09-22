<?php

/**
 * Generate PWA icons from brand logo (contain, not center-crop).
 * Usage: php public/icons/generate-icons.php
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension not available\n");
    exit(1);
}

$srcCandidates = [
    dirname(__DIR__) . '/logo.jpeg',
    dirname(__DIR__) . '/icon-jannah.jpeg',
];
$src = null;
foreach ($srcCandidates as $candidate) {
    if (is_file($candidate)) {
        $src = $candidate;
        break;
    }
}
if (!$src) {
    fwrite(STDERR, "No source logo found (logo.jpeg / icon-jannah.jpeg)\n");
    exit(1);
}
$dir = __DIR__;

echo "Source: {$src}\n";

if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$data = @file_get_contents($src);
$img = $data ? @imagecreatefromstring($data) : false;
if (!$img) {
    fwrite(STDERR, "Failed to load source icon\n");
    exit(1);
}

foreach ([192, 512] as $size) {
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    // White pad so logo stays fully visible (no crop)
    $bg = imagecolorallocate($out, 255, 255, 255);
    imagefilledrectangle($out, 0, 0, $size, $size, $bg);
    imagealphablending($out, true);

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min($size / $w, $size / $h);
    $dw = (int) max(1, round($w * $scale));
    $dh = (int) max(1, round($h * $scale));
    $dx = (int) (($size - $dw) / 2);
    $dy = (int) (($size - $dh) / 2);
    imagecopyresampled($out, $img, $dx, $dy, 0, 0, $dw, $dh, $w, $h);

    $path = $dir . "/icon-{$size}.png";
    imagepng($out, $path);
    imagedestroy($out);
    echo "OK {$size} => {$path}\n";
}

imagedestroy($img);
