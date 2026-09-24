<?php

date_default_timezone_set('Asia/Bangkok');
ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/php_errors.log');

class JWT
{
    public static function decode($jwt, $key = null, $verify = true)
    {
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        [$headb64, $bodyb64, $cryptob64] = $tks;
        if (null === ($header = JWT::jsonDecode(JWT::urlsafeB64Decode($headb64)))) {
            throw new UnexpectedValueException('Invalid segment encoding');
        }
        if (null === $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64))) {
            throw new UnexpectedValueException('Invalid segment encoding');
        }
        $sig = JWT::urlsafeB64Decode($cryptob64);
        if ($verify) {
            if (empty($header->alg)) {
                throw new DomainException('Empty algorithm');
            }
            if ($sig != JWT::sign("$headb64.$bodyb64", $key, $header->alg)) {
                throw new UnexpectedValueException('Signature verification failed');
            }
        }

        return $payload;
    }

    public static function encode($payload, $key, $algo = 'HS256')
    {
        $header = ['typ' => 'JWT', 'alg' => $algo];
        $segments = [];
        $segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($header));
        $segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($payload));
        $signing_input = implode('.', $segments);
        $signature = JWT::sign($signing_input, $key, $algo);
        $segments[] = JWT::urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    public static function sign($msg, $key, $method = 'HS256')
    {
        $methods = ['HS256' => 'sha256', 'HS384' => 'sha384', 'HS512' => 'sha512'];
        if (empty($methods[$method])) {
            throw new DomainException('Algorithm not supported');
        }

        return hash_hmac($methods[$method], $msg, $key, true);
    }

    public static function jsonDecode($input)
    {
        $obj = json_decode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            JWT::_handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }

        return $obj;
    }

    public static function jsonEncode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            JWT::_handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            throw new DomainException('Null result with non-null input');
        }

        return $json;
    }

    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }

    public static function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    private static function _handleJsonError($errno)
    {
        $messages = [
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
        ];
        throw new DomainException(isset($messages[$errno]) ? $messages[$errno] : 'Unknown JSON error: '.$errno);
    }
}

header('Content-Type: application/json');
$token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $token = $data['token'] ?? null;

    if (empty($token)) {
        $token = $_GET['token'] ?? null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? null;
}
if (empty($token)) {
    http_response_code(400);
    echo json_encode([
        'responseCode' => '01',
        'responseMessage' => 'Token tidak ditemukan',
        'responseTimestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

$secretKey = 'TokenJWT_BMI_ICT';

try {
    $decoded = JWT::decode($token, $secretKey, ['HS256']);

    $responseCode = $decoded->responseCode;
    $responseMessage = $decoded->responseMessage;
    $transactionId = $decoded->transactionId;
    $responseTimestamp = $decoded->responseTimestamp;
    $data = $decoded->data;
    if ($responseCode === '00') {
        $vano = $data->vano1;
        $amount = $data->amount;
        $accountNo = $data->accountNo;
        $transactionQrId = $data->transactionQrId;
        $description = $data->description ?? '';
        $enamDigitVano = substr($vano, 0, 6);

        if ($enamDigitVano === '508001') {
            require_once __DIR__.'/../config/connectWalisongo.php';
            require_once __DIR__.'/pushNotif/walisongo.php';
        } elseif ($enamDigitVano === '070240') {
            require_once __DIR__.'/../config/connectAlberr.php';
            require_once __DIR__.'/pushNotif/walisongo.php';
        } elseif ($enamDigitVano === '880088') {
            require_once __DIR__.'/../config/connectMarkaz.php';
            require_once __DIR__.'/pushNotif/markaz.php';
        } elseif ($enamDigitVano === '111111') {
            require_once __DIR__.'/../config/connectQrDummy.php';
            require_once __DIR__.'/pushNotif/dummy.php';
        } elseif ($enamDigitVano === '751000') {
            // WEB_TAGIHAN_DEMO → forward DEMO_INSTALLMENT (103.23.103.43), log DB (103.23.103.36)
            require_once __DIR__.'/pushNotif/demoInstallment.php';
        } elseif ($enamDigitVano === '757777' || $enamDigitVano === '222222') {
            require_once __DIR__.'/../config/connectTagihanCicilan.php';
            require_once __DIR__.'/pushNotif/tagihanCicilan.php';
        } else {
            require_once __DIR__.'/../config/connectMetaschool.php';
            require_once __DIR__.'/pushNotif/metaschool.php';
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'responseCode' => '01',
            'responseMessage' => $responseMessage,
            'responseTimestamp' => date('Y-m-d H:i:s'),
        ]);
        exit;
    }
} catch (Throwable $th) {
    error_log('pushNotif.php exception: '.$th->getMessage().' @ '.$th->getFile().':'.$th->getLine());
    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode([
        'responseCode' => '01',
        'responseMessage' => 'pushNotif error: '.$th->getMessage(),
        'responseTimestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}
