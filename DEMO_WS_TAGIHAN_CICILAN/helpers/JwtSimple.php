<?php

class JwtSimple
{
    public static function encode($payload, $key, $algo = 'HS256')
    {
        $header = ['typ' => 'JWT', 'alg' => $algo];
        $segments = [];
        $segments[] = self::urlsafeB64Encode(self::jsonEncode($header));
        $segments[] = self::urlsafeB64Encode(self::jsonEncode($payload));
        $signingInput = implode('.', $segments);
        $signature = self::sign($signingInput, $key, $algo);
        $segments[] = self::urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    public static function sign($msg, $key, $method = 'HS256')
    {
        $methods = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
        ];
        if (empty($methods[$method])) {
            throw new DomainException('Algorithm not supported');
        }

        return hash_hmac($methods[$method], $msg, $key, true);
    }

    public static function jsonEncode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && ($errno = json_last_error())) {
            throw new DomainException('JSON encode error: ' . $errno);
        }
        if ($json === 'null' && $input !== null) {
            throw new DomainException('Null result with non-null input');
        }

        return $json;
    }

    public static function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }
}
