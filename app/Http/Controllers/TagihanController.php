<?php

namespace App\Http\Controllers;

use App\Services\MultiAccountService;
use App\Services\QrisGenerateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TagihanController extends Controller
{
    private function wsUrl(?string $path = null): string
    {
        $url = rtrim(config('services.tagihan_ws.url'), '?&');

        if ($path) {
            $url .= (str_contains($url, '?') ? '&' : '?').'path='.$path;
        }

        return $url;
    }

    public static function normalizeVa(?string $va): string
    {
        $va = preg_replace('/\s+/', '', (string) $va);
        // Buang pemisah umum: 797766-1234567801 → 7977661234567801
        $va = preg_replace('/[-_.\/]/', '', $va);

        if (preg_match('/^(751000|757777|797766)(\d+)$/', $va, $m)) {
            $va = $m[2];
        }

        $nocust = ltrim($va, '0');

        return $nocust !== '' ? $nocust : $va;
    }

    public static function formatNova(?string $nocust): string
    {
        $n = self::normalizeVa($nocust);

        if ($n === '' || $n === '-') {
            return '-';
        }

        return '751000'.$n;
    }

    /**
     * Halaman utama: jika sesi login masih ada, tampilkan data tagihan (bukan form login).
     */
    public function home()
    {
        $sess = session('tagihan');
        $noCust = self::normalizeVa($sess['active_no_cust'] ?? '');

        if ($noCust === '') {
            return response()
                ->view('index3')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $academicYear = $sess['academic_year'] ?? 'all';
        $vaDisplay = $sess['va_display'] ?? $noCust;

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($this->wsUrl('cek-tagihan'), [
                    'va' => $noCust,
                    'tahun_akademik' => $academicYear,
                ]);

            $result = $this->withNova($response->json(), $vaDisplay);

            if (!empty($result['status']) && !empty($result['data'])) {
                $multiAccounts = collect();
                try {
                    $multiAccounts = MultiAccountService::listForNoCust(
                        $result['data']['no_cust'] ?? $noCust,
                        $noCust
                    );
                } catch (\Throwable $e) {
                    // ignore
                }

                return response()
                    ->view('index3', [
                        'result' => $result,
                        'multiAccounts' => $multiAccounts,
                        'va' => $vaDisplay,
                        'academic_year' => $academicYear,
                    ])
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->header('Pragma', 'no-cache');
            }
        } catch (\Throwable $e) {
            Log::warning('home restore session failed', ['error' => $e->getMessage()]);
        }

        // Sesi ada tapi data gagal dimuat: hapus sesi agar user login ulang
        session()->forget('tagihan');

        return redirect()
            ->route('home')
            ->with('error', 'Sesi login kedaluwarsa. Silakan masuk lagi.');
    }

    public function cek(Request $request)
    {
        $request->validate([
            'no_cust' => 'required|string',
            'academic_year' => 'required|string'
        ]);

        $response = Http::timeout(30)
            ->withoutVerifying()
            ->post($this->wsUrl('cek-tagihan'), [
                'va' => self::normalizeVa($request->no_cust),
                'tahun_akademik' => $request->academic_year
            ]);

        $result = $response->json();
        $result = $this->withNova($result, $request->no_cust);

        return view('index', compact('result'))
            ->with([
                'va' => $request->no_cust,
                'academic_year' => $request->academic_year
            ]);
    }

    public function cek2(Request $request)
    {
        $request->validate([
            'no_cust' => 'required|string',
            'password' => 'required|string',
            'academic_year' => 'nullable|string'
        ]);

        $academicYear = 'all';

        $payload = [
            'va' => self::normalizeVa($request->no_cust),
            'password' => $request->password,
            'tahun_akademik' => $academicYear
        ];

        $response = Http::timeout(30)
            ->withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->post($this->wsUrl('cek-tagihan-pw'), $payload);

        if ($response->status() === 404) {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($this->wsUrl('cek-tagihan'), $payload);
        }

        $json = $response->json();
        $result = $this->withNova($json, $request->no_cust);

        if (empty($result['status'])) {
            $message = $result['message'] ?? 'VA atau password salah, atau data tidak ditemukan';

            // WS sering return HTML/fatal error → json() null → pesan generik
            if (!is_array($json) || ($message === 'Terjadi kesalahan' && $response->body() && !str_starts_with(ltrim($response->body()), '{'))) {
                Log::error('WS cek-tagihan-pw non-JSON response', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                    'url' => $this->wsUrl('cek-tagihan-pw'),
                ]);
                $body = (string) $response->body();
                if (stripos($body, 'Unknown column') !== false && preg_match("/Unknown column '([^']+)'/", $body, $m)) {
                    $message = 'Web service error: kolom DB '.$m[1].' tidak ada. Cek models/Tagihan.php di server WS.';
                } elseif (stripos($body, 'Fatal error') !== false) {
                    $message = 'Web service PHP fatal error. Cek laravel.log / response WS.';
                } else {
                    $message = 'Web service tagihan error. Pastikan folder controllers/TagihanController.php ada di server WS.';
                }
            }

            return back()->with([
                'error' => $message,
                'va' => $request->no_cust,
                'academic_year' => $academicYear
            ])->withInput($request->except('password'));
        }

        return $this->renderIndex3AfterLogin($result, self::normalizeVa($request->no_cust), $academicYear);
    }

    public function loginByToken(string $token)
    {
        $token = strtolower(trim($token));

        $response = Http::timeout(30)
            ->withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->post($this->wsUrl('token-login'), [
                'token' => $token,
            ]);

        $json = $response->json();
        $result = $this->withNova(is_array($json) ? $json : null, null);

        if (empty($result['status']) || empty($result['data'])) {
            $message = $result['message'] ?? 'Link login tidak valid, sudah kadaluarsa, atau sudah dipakai';
            if ($response->failed() && empty($json)) {
                $message = 'Gagal menghubungi web service login token';
            }

            return redirect('/')
                ->with('error', $message);
        }

        $noCust = $result['data']['no_cust'] ?? '';
        $academicYear = $result['data']['tahun_dipilih'] ?? 'all';
        if (!is_string($academicYear) || $academicYear === '' || stripos($academicYear, 'Semua') !== false) {
            $academicYear = 'all';
        }

        $va = self::formatNova($noCust);

        return $this->renderIndex3AfterLogin($result, $va, $academicYear);
    }

    private function renderIndex3AfterLogin(array $result, string $vaDisplay, string $academicYear)
    {
        $multiAccounts = collect();
        try {
            MultiAccountService::syncMemberAfterLogin(
                $result['data'],
                $vaDisplay,
                $academicYear
            );

            $multiAccounts = MultiAccountService::listForNoCust(
                $result['data']['no_cust'] ?? $vaDisplay,
                self::normalizeVa($vaDisplay)
            );
        } catch (\Throwable $e) {
            Log::warning('multi-akun sync after login failed', [
                'error' => $e->getMessage(),
            ]);
            session([
                'tagihan' => [
                    'active_no_cust' => self::normalizeVa($vaDisplay),
                    'va_display' => $vaDisplay,
                    'academic_year' => $academicYear,
                    'group_id' => null,
                ],
            ]);
        }

        return view('index3', compact('result', 'multiAccounts'))
            ->with([
                'va' => self::normalizeVa($vaDisplay),
                'academic_year' => $academicYear
            ]);
    }

    private function withNova($result, ?string $fallback = null): array
    {
        if (!is_array($result)) {
            return ['status' => false, 'message' => 'Terjadi kesalahan'];
        }

        if (!empty($result['data'])) {
            $nocust = $result['data']['no_cust'] ?? $result['data']['va_number'] ?? $fallback;
            $result['data']['va_number'] = self::formatNova($nocust);
            $result = self::normalizeBillAmounts($result);
        }

        return $result;
    }

    private function extractVa($result, $fallback = null)
    {
        if (!is_array($result)) {
            return $fallback;
        }

        $data = $result['data'] ?? null;
        if (is_array($data)) {
            foreach (['va_number', 'NOVA', 'nova', 'NOCUST', 'nocust'] as $key) {
                if (array_key_exists($key, $data)) {
                    return $data[$key];
                }
            }
        }

        if (is_string($data) || is_numeric($data)) {
            return $data;
        }

        foreach (['va_number', 'nova', 'NOVA'] as $key) {
            if (array_key_exists($key, $result)) {
                return $result[$key];
            }
        }

        return $fallback;
    }

    public static function normalizeBillAmounts(array $result): array
    {
        foreach (['tagihan', 'tagihan_lunas'] as $key) {
            if (empty($result['data'][$key]) || !is_array($result['data'][$key])) {
                continue;
            }

            foreach ($result['data'][$key] as &$item) {
                if (!is_array($item)) {
                    continue;
                }

                $total = (int) ($item['total_tagihan'] ?? 0);
                $hasPaymentLeft = array_key_exists('paymentleft', $item) || array_key_exists('PAYMENTLEFT', $item);
                $hasBillPaid = array_key_exists('billpaid', $item) || array_key_exists('BILLPAID', $item);

                if ($hasPaymentLeft) {
                    $sisa = max(0, (int) ($item['paymentleft'] ?? $item['PAYMENTLEFT']));
                    $paid = $hasBillPaid
                        ? max(0, (int) ($item['billpaid'] ?? $item['BILLPAID']))
                        : max(0, $total - $sisa);
                } elseif ($hasBillPaid) {
                    $paid = max(0, (int) ($item['billpaid'] ?? $item['BILLPAID']));
                    $sisa = max(0, $total - $paid);
                } else {
                    $paid = max(0, (int) ($item['sisa_tagihan'] ?? 0));
                    $sisa = max(0, (int) ($item['sudah_dibayar'] ?? max(0, $total - $paid)));
                }

                $item['sudah_dibayar'] = $paid;
                $item['sisa_tagihan'] = $sisa;
            }
            unset($item);
        }

        return $result;
    }

    public function tagihanView()
    {
        return view('tagihan');
    }

    public function buatVA(Request $request)
    {
        $request->validate([
            'custid' => 'required',
            'nocust' => 'required|string',
            'namacust' => 'required|string',
        ]);

        $pairs = [];
        $items = $request->input('items');
        if (is_array($items) && count($items)) {
            foreach ($items as $item) {
                $aa = (int) ($item['AA'] ?? $item['aa'] ?? 0);
                $amount = (int) ($item['amount'] ?? $item['billam'] ?? 0);
                if ($aa > 0 && $amount > 0) {
                    $pairs[] = ['aa' => $aa, 'amount' => $amount];
                }
            }
        } else {
            $arrayTagihan = $request->input('array_tagihan', $request->input('arrayTagihan', ''));
            if (is_array($arrayTagihan)) {
                $arrayTagihan = implode(',', $arrayTagihan);
            }
            $ids = collect(explode(',', (string) $arrayTagihan))
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => $id > 0)
                ->values();

            $billamRaw = $request->input('billam', $request->input('total', ''));
            if (is_array($billamRaw)) {
                $amounts = array_map('intval', $billamRaw);
            } else {
                $amounts = collect(explode(',', (string) $billamRaw))
                    ->map(fn ($n) => (int) trim($n))
                    ->values()
                    ->all();
            }

            foreach ($ids as $i => $aa) {
                $amount = (int) ($amounts[$i] ?? 0);
                if ($aa > 0 && $amount > 0) {
                    $pairs[] = ['aa' => $aa, 'amount' => $amount];
                }
            }
        }

        if (empty($pairs)) {
            return response()->json([
                'status' => false,
                'message' => 'Tagihan yang dipilih tidak valid',
            ], 422);
        }

        $idsCsv = collect($pairs)->pluck('aa')->implode(',');
        $billamCsv = collect($pairs)->pluck('amount')->implode(',');
        $total = (int) collect($pairs)->sum('amount');
        $nocust = self::normalizeVa($request->nocust);

        $payload = [
            'custid' => $request->custid,
            'nocust' => $nocust,
            'namacust' => $request->namacust,
            'array_tagihan' => $idsCsv,
            'arrayTagihan' => $idsCsv,
            'billam' => $billamCsv,
            'total' => $total,
            'billtot' => $total,
            'bank' => $request->input('bank', 'Muamalat'),
            'items' => $pairs,
        ];

        try {
            $wsUrl = $this->wsUrl('generate-va');
            Log::info('WS generate-va incoming', [
                'url' => $wsUrl,
                'request' => $request->all(),
                'payload' => $payload,
                'payload_types' => collect($payload)->map(fn ($v) => gettype($v) . ':' . json_encode($v))->all(),
                'empty_fields' => collect($payload)->filter(fn ($v) => $v === null || $v === '' || $v === false)->keys()->values()->all(),
            ]);

            $response = Http::timeout(30)
                ->withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($wsUrl, $payload);

            Log::info('WS generate-va json', [
                'http' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);

            if ($response->failed() || empty($response->json()['status'])) {
                $formResponse = Http::timeout(30)
                    ->withoutVerifying()
                    ->asForm()
                    ->post($wsUrl, $payload);
                Log::info('WS generate-va form', [
                    'http' => $formResponse->status(),
                    'body' => $formResponse->body(),
                    'json' => $formResponse->json(),
                ]);
                if ($formResponse->successful()) {
                    $response = $formResponse;
                }
            }

            Log::info('WS generate-va response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $result = $response->json();
            if (!is_array($result)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Gagal membuat nomor VA',
                ], 500);
            }

            $va = $this->extractVa($result, null);
            $vaOk = $va !== false && $va !== null && $va !== '' && $va !== 'false';

            if (!empty($result['status']) && $vaOk) {
                $result['data'] = array_merge(
                    is_array($result['data'] ?? null) ? $result['data'] : [],
                    ['va_number' => $nocust]
                );

                return response()->json($result);
            }

            $wsMessage = (string) ($result['message'] ?? '');
            $looksSuccess = stripos($wsMessage, 'berhasil') !== false;
            $message = ($wsMessage !== '' && !$looksSuccess)
                ? $wsMessage
                : 'Gagal menyimpan ke scctva. insertVA return false. Kalau NIS '.$nocust.' sudah ada di kolom NOVA, lepas UNIQUE di NOVA supaya tiap bayar bisa baris baru dengan nomor yang sama.';

            Log::warning('WS generate-va insert failed', [
                'ws_status' => $result['status'] ?? null,
                'ws_message' => $wsMessage,
                'va' => $va,
                'nocust' => $nocust,
            ]);

            return response()->json([
                'status' => false,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error generate-va', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat membuat nomor VA',
            ], 500);
        }
    }

    public function buatQRIS(Request $request)
    {
        if (!config('brand.payment_qris')) {
            return response()->json([
                'status' => false,
                'message' => 'Metode QRIS tidak aktif',
            ], 403);
        }

        $request->validate([
            'custid' => 'required',
            'nocust' => 'required|string',
            'namacust' => 'required|string',
            'amount' => 'required|integer|min:1000',
        ]);

        $amount = (int) $request->input('amount');
        $nocust = self::normalizeVa($request->nocust);
        $description = (string) $request->input(
            'description',
            'Top up VA '.$request->namacust
        );

        $payload = [
            'custid' => $request->custid,
            'nocust' => $nocust,
            'namacust' => $request->namacust,
            'amount' => $amount,
            'total' => $amount,
            'description' => $description,
            'payment_type' => 'topup',
            'items' => [],
        ];

        try {
            $svc = new QrisGenerateService();
            $data = $svc->generate([
                'custid' => $request->custid,
                'nocust' => $nocust,
                'namacust' => $request->namacust,
                'description' => $description,
                'amount' => $amount,
            ], []);

            try {
                Http::timeout(3)
                    ->connectTimeout(2)
                    ->withoutVerifying()
                    ->acceptJson()
                    ->asJson()
                    ->post($this->wsUrl('save-qris'), array_merge($payload, [
                        'qris' => $data,
                    ]));
            } catch (\Throwable $e) {
                Log::info('WS save-qris skip', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'status' => true,
                'message' => 'QRIS top up berhasil dibuat',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generate-qris topup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Terjadi kesalahan saat membuat QRIS',
            ], 500);
        }
    }

    /**
     * Polling status pembayaran (QRIS / snapshot tagihan VA).
     */
    public function cekStatusPembayaran(Request $request)
    {
        $qrisId = trim((string) $request->input('qris_id', ''));
        $trxId = trim((string) $request->input('transaction_id', ''));
        $vano = trim((string) $request->input('vano', ''));

        if ($qrisId !== '' || $trxId !== '' || $vano !== '') {
            // 1) Langsung ke DB tagihan (lebih andal)
            $direct = $this->qrisStatusFromDb($qrisId, $trxId, $vano);
            if ($direct !== null) {
                return response()->json($direct);
            }

            // 2) Fallback WS
            try {
                $response = Http::timeout(12)
                    ->connectTimeout(4)
                    ->withoutVerifying()
                    ->acceptJson()
                    ->get($this->wsUrl('qris-status'), array_filter([
                        'qris_id' => $qrisId !== '' ? $qrisId : null,
                        'transaction_id' => $trxId !== '' ? $trxId : null,
                        'vano' => $vano !== '' ? $vano : null,
                    ]));

                $json = $response->json();
                if (!is_array($json)) {
                    return response()->json([
                        'status' => false,
                        'paid' => false,
                        'message' => 'Response status tidak valid',
                    ], 502);
                }

                $data = is_array($json['data'] ?? null) ? $json['data'] : [];
                $paid = !empty($data['paid']) || !empty($json['paid']);

                return response()->json([
                    'status' => (bool) ($json['status'] ?? false),
                    'paid' => $paid,
                    'message' => $json['message'] ?? ($paid ? 'Pembayaran berhasil' : 'Menunggu pembayaran'),
                    'data' => $data,
                    'type' => 'qris',
                ]);
            } catch (\Throwable $e) {
                Log::warning('cekStatusPembayaran qris', ['error' => $e->getMessage()]);

                return response()->json([
                    'status' => false,
                    'paid' => false,
                    'message' => 'Gagal cek status QRIS',
                ], 500);
            }
        }

        // Snapshot tagihan untuk deteksi VA lunas (bandingkan di client)
        $sess = session('tagihan');
        $noCust = self::normalizeVa($sess['active_no_cust'] ?? $request->input('nocust'));
        if ($noCust === '') {
            return response()->json([
                'status' => false,
                'paid' => false,
                'message' => 'Sesi tidak valid',
            ], 401);
        }

        $academicYear = $sess['academic_year'] ?? $request->input('tahun_akademik', 'all');
        $aaList = $request->input('aa', []);
        if (is_string($aaList)) {
            $aaList = array_filter(array_map('intval', explode(',', $aaList)));
        }
        if (!is_array($aaList)) {
            $aaList = [];
        }
        $aaList = array_values(array_unique(array_map('intval', $aaList)));

        try {
            $response = Http::timeout(25)
                ->withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($this->wsUrl('cek-tagihan'), [
                    'va' => $noCust,
                    'tahun_akademik' => $academicYear,
                ]);

            $result = $response->json();
            if (empty($result['status']) || empty($result['data'])) {
                return response()->json([
                    'status' => false,
                    'paid' => false,
                    'message' => $result['message'] ?? 'Gagal memuat tagihan',
                ], 502);
            }

            $data = $result['data'];
            $tagihan = is_array($data['tagihan'] ?? null) ? $data['tagihan'] : [];
            $unpaid = 0;
            $matched = 0;
            foreach ($tagihan as $item) {
                $aa = (int) ($item['AA'] ?? $item['aa'] ?? 0);
                if ($aaList && !in_array($aa, $aaList, true)) {
                    continue;
                }
                $matched++;
                $sisa = (int) ($item['sisa_tagihan'] ?? $item['SISA'] ?? 0);
                if ($sisa <= 0 && isset($item['total_tagihan'], $item['sudah_dibayar'])) {
                    $sisa = max(0, (int) $item['total_tagihan'] - (int) $item['sudah_dibayar']);
                }
                $unpaid += max(0, $sisa);
            }

            return response()->json([
                'status' => true,
                'paid' => false,
                'type' => 'va',
                'message' => 'Snapshot tagihan',
                'data' => [
                    'saldo' => (float) ($data['saldo'] ?? 0),
                    'unpaid_total' => $unpaid,
                    'matched' => $matched,
                    'aa' => $aaList,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('cekStatusPembayaran va', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'paid' => false,
                'message' => 'Gagal cek status VA',
            ], 500);
        }
    }

    /**
     * Baca status QRIS langsung dari DB sekolah (services.tagihan_db).
     *
     * @return array<string,mixed>|null null jika koneksi/query gagal total
     */
    private function qrisStatusFromDb(string $qrisId, string $trxId, string $vano): ?array
    {
        $cfg = config('services.tagihan_db');
        if (empty($cfg['host']) || empty($cfg['database'])) {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $cfg['host'],
                $cfg['port'] ?? 3306,
                $cfg['database']
            );
            $pdo = new \PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 4,
            ]);

            $parts = [];
            $params = [];
            if ($qrisId !== '') {
                $parts[] = 'qris_id = ?';
                $params[] = $qrisId;
            }
            if ($trxId !== '') {
                $parts[] = 'transaction_id = ?';
                $params[] = $trxId;
            }
            if ($vano !== '') {
                $parts[] = 'vano = ?';
                $params[] = $vano;
            }
            if (!$parts) {
                return null;
            }

            $sql = 'SELECT id, qris_id, transaction_id, vano, amount, status, paid_flag, paid_at
                    FROM mst_qris WHERE ('.implode(' OR ', $parts).')
                    ORDER BY id DESC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'status' => false,
                    'paid' => false,
                    'message' => 'Transaksi QRIS tidak ditemukan',
                    'type' => 'qris',
                    'data' => [
                        'paid' => false,
                        'qris_id' => $qrisId,
                        'transaction_id' => $trxId,
                        'vano' => $vano,
                    ],
                ];
            }

            $paid = ((int) ($row['paid_flag'] ?? 0) === 1)
                || strtolower((string) ($row['status'] ?? '')) === 'paid';

            return [
                'status' => true,
                'paid' => $paid,
                'message' => $paid ? 'Pembayaran berhasil' : 'Menunggu pembayaran',
                'type' => 'qris',
                'data' => [
                    'paid' => $paid,
                    'status' => $row['status'] ?? 'pending',
                    'paid_flag' => (int) ($row['paid_flag'] ?? 0),
                    'qris_id' => $row['qris_id'] ?? null,
                    'transaction_id' => $row['transaction_id'] ?? null,
                    'amount' => isset($row['amount']) ? (float) $row['amount'] : null,
                    'vano' => $row['vano'] ?? null,
                    'paid_at' => $row['paid_at'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::info('qrisStatusFromDb skip', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function listTahunAkademik()
    {
        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->get(config('services.tagihan_ws.url'), [
                    'path' => 'list-tahun-aka'
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'status' => false,
                'message' => 'API tidak memberikan response yang valid'
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error fetching tahun akademik', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil data tahun akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->session()->forget('tagihan');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
