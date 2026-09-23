<?php
date_default_timezone_set("Asia/Bangkok");
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '0');
ini_set('error_log', 'php_errors.log');

require_once '../config/connectQrDummy.php';

class JWT
{
    public static function decode($jwt, $key = null, $verify = true)
    {
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
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
        $header = array('typ' => 'JWT', 'alg' => $algo);
        $segments = array();
        $segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($header));
        $segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($payload));
        $signing_input = implode('.', $segments);
        $signature = JWT::sign($signing_input, $key, $algo);
        $segments[] = JWT::urlsafeB64Encode($signature);
        return implode('.', $segments);
    }

    public static function sign($msg, $key, $method = 'HS256')
    {
        $methods = array(
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
        );
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
        } else if ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }
        return $obj;
    }

    public static function jsonEncode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            JWT::_handleJsonError($errno);
        } else if ($json === 'null' && $input !== null) {
            throw new DomainException('Null result with non-null input');
        }
        return $json;
    }

    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    public static function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    private static function _handleJsonError($errno)
    {
        $messages = array(
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
        );
        throw new DomainException(
            isset($messages[$errno])
            ? $messages[$errno]
            : 'Unknown JSON error: ' . $errno
        );
    }
}

header('Content-Type: application/json');

if (!isset($dbhandle) || !$dbhandle) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal koneksi database dummy']);
    exit;
}

$id = null;
$qrisId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $id = isset($data['id']) ? (int)$data['id'] : null;
    $qrisId = isset($data['qris_id']) ? trim((string)$data['qris_id']) : '';
    if ($qrisId === '' && isset($data['transactionQrId'])) {
        $qrisId = trim((string)$data['transactionQrId']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $qrisId = isset($_GET['qris_id']) ? trim((string)$_GET['qris_id']) : '';
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET or POST request.']);
    exit;
}

if (empty($id) && $qrisId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'id atau qris_id wajib diisi']);
    exit;
}

try {
    if (!empty($id)) {
        $stmt = $dbhandle->prepare('SELECT * FROM qr_dummy WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $dbhandle->prepare('SELECT * FROM qr_dummy WHERE qris_id = ? LIMIT 1');
        $stmt->bind_param('s', $qrisId);
    }

    if (!$stmt) {
        throw new Exception('Failed to prepare status lookup: ' . mysqli_error($dbhandle));
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Data QR dummy tidak ditemukan']);
        exit;
    }

    $transactionId = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    $payload = [
        "accountNo" => $row['account_no'] ? $row['account_no'] : "5080010295",
        "amount" => strval((int)$row['amount']),
        "mitraCustomerId" => $row['mitra_customer_id'] ? $row['mitra_customer_id'] : "ISLAMIC CENTER SMG451061",
        "transactionId" => $transactionId,
        "tipeTransaksi" => "MTR-CHECKSTATUS-QRIS-DYNAMIC",
        "vano" => strval($row['vano']),
        "transactionQrId" => strval($row['qris_id']),
    ];

    $secretKey = 'TokenJWT_BMI_ICT';
    $jwtToken = JWT::encode($payload, $secretKey);

    $url = 'http://103.23.103.43/qris/lazizmu_diy/server.php?token=' . urlencode($jwtToken);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'cURL Error: ' . $error,
            'local' => $row,
        ]);
        exit;
    }

    curl_close($ch);

    $serverResponse = json_decode($response, true);
    if (!$serverResponse) {
        $serverResponse = array('raw' => $response);
    }

    $paidFromServer = false;
    $statusText = '';
    if (isset($serverResponse['transactionDetail']['status'])) {
        $statusText = strtolower((string)$serverResponse['transactionDetail']['status']);
    } elseif (isset($serverResponse['status'])) {
        $statusText = strtolower((string)$serverResponse['status']);
    } elseif (isset($serverResponse['responseMessage'])) {
        $statusText = strtolower((string)$serverResponse['responseMessage']);
    }

    if (
        strpos($statusText, 'paid') !== false
        || strpos($statusText, 'success') !== false
        || strpos($statusText, 'settlement') !== false
    ) {
        $paidFromServer = true;
    }

    if ($paidFromServer && (int)$row['paid_flag'] !== 1) {
        $paymentTime = date('Y-m-d H:i:s');
        $updateStmt = $dbhandle->prepare("UPDATE qr_dummy
            SET status = 'paid', paid_flag = 1, paid_at = ?, updated_at = NOW()
            WHERE id = ? AND paid_flag = 0");
        $billingId = (int)$row['id'];
        $updateStmt->bind_param('si', $paymentTime, $billingId);
        $updateStmt->execute();
        $updateStmt->close();

        $row['status'] = 'paid';
        $row['paid_flag'] = 1;
        $row['paid_at'] = $paymentTime;
    }

    echo json_encode(array(
        'success' => true,
        'id' => (int)$row['id'],
        'vano' => $row['vano'],
        'amount' => $row['amount'],
        'qris_id' => $row['qris_id'],
        'transactionQrId' => $row['qris_id'],
        'qris_content' => $row['qris_content'],
        'rawQrData' => $row['qris_content'],
        'status' => $row['status'],
        'paid_flag' => (int)$row['paid_flag'],
        'paid_at' => $row['paid_at'],
        'serverResponse' => $serverResponse,
    ));
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    exit;
} finally {
    if (isset($dbhandle)) {
        mysqli_close($dbhandle);
    }
}
