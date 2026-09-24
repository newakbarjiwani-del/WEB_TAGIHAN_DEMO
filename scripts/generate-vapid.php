<?php

$key = openssl_pkey_new([
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
]);

if ($key === false) {
    fwrite(STDERR, "pkey_new failed: ".openssl_error_string().PHP_EOL);
    exit(1);
}

if (! openssl_pkey_export($key, $out)) {
    fwrite(STDERR, "export failed: ".openssl_error_string().PHP_EOL);
    exit(1);
}

$details = openssl_pkey_get_details($key);
$x = $details['ec']['x'] ?? null;
$y = $details['ec']['y'] ?? null;
$d = $details['ec']['d'] ?? null;

if ($x === null || $y === null || $d === null) {
    fwrite(STDERR, "missing ec components\n");
    exit(1);
}

$b64url = static function (string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
};

// Uncompressed public key: 0x04 || x || y
$public = $b64url(chr(4).$x.$y);
$private = $b64url($d);
$secret = bin2hex(random_bytes(24));

echo "VAPID_PUBLIC_KEY={$public}\n";
echo "VAPID_PRIVATE_KEY={$private}\n";
echo "VAPID_SUBJECT=mailto:admin@ict.local\n";
echo "WEBPUSH_NOTIFY_SECRET={$secret}\n";
