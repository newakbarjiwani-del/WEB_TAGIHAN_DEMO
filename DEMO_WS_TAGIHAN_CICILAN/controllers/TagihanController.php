<?php
require_once __DIR__ . '/../models/Tagihan.php';
require_once __DIR__ . '/../models/MultiAkun.php';
require_once __DIR__ . '/../models/LoginToken.php';
require_once __DIR__ . '/../models/QrisPayment.php';
require_once __DIR__ . '/../helpers/response.php';

class TagihanController {
   private $tagihan;
   private $multiAkun;
   private $loginToken;

   public function __construct() {
        $this->tagihan = new Tagihan();
        $this->multiAkun = new MultiAkun();
        $this->loginToken = new LoginToken();
    }

    private function readJsonInput()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }

    public function cek() {
        $input = json_decode(file_get_contents("php://input"), true);
        $va = $input['va'] ?? null;
        $tahun_akademik = $input['tahun_akademik'] ?? null;

        if (empty($va) || empty($tahun_akademik)) {
            jsonResponse(false, "Nomor VA dan Tahun Akademik wajib diisi");
            return;
        }

        $data = $this->tagihan->cekTagihanByVA($va, $tahun_akademik);

        if ($data) {
            jsonResponse(true, "Data ditemukan", $data);
        } else {
            jsonResponse(false, "Data tidak ditemukan untuk VA dan Tahun Akademik tersebut");
        }
    }
public function cek2()
{
    $input = json_decode(file_get_contents("php://input"), true);
    $va = $input['va'] ?? null;
    $password = $input['password'] ?? null;
    $tahun_akademik = $input['tahun_akademik'] ?? null;

    if (empty($va) || empty($password) || empty($tahun_akademik)) {
        jsonResponse(false, "Nomor VA, Password, dan Tahun Akademik wajib diisi");
        return;
    }

    $data = $this->tagihan->cekTagihanByVAPw($va, $password, $tahun_akademik);

    if ($data) {
        jsonResponse(true, "Data ditemukan", $data);
    } else {
        jsonResponse(false, "VA atau Password salah, atau data tidak ditemukan");
    }
}

    public function generateVA() {
    $input = json_decode(file_get_contents("php://input"), true);
    $custid = $input['custid'] ?? null;
    $nocust = $input['nocust'] ?? null;
    $namacust = $input['namacust'] ?? null;
    $arrayTagihan = $input['array_tagihan'] ?? null;
    $billam = $input['total'] ?? null;

      if (is_null($custid) || is_null($nocust) || is_null($namacust) || is_null($arrayTagihan) || is_null($billam)) {
        jsonResponse(false, "Data tidak lengkap untuk membuat VA");
        return;
    }

    $model = new Tagihan();
    $va_number = $model->insertVA($custid, $nocust, $namacust, $arrayTagihan, $billam);

    jsonResponse(true, "Nomor Virtual Account berhasil dibuat", ['va_number' => $va_number]);
}

    public function generateQRIS()
    {
        $input = $this->readJsonInput();
        $custid = $input['custid'] ?? null;
        $nocust = $input['nocust'] ?? null;
        $namacust = $input['namacust'] ?? null;
        $amount = (int) ($input['amount'] ?? $input['total'] ?? 0);

        if ($custid === null || $nocust === null || $namacust === null) {
            jsonResponse(false, 'Data tidak lengkap untuk membuat QRIS');
            return;
        }

        $items = [];
        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $aa = (int) ($item['AA'] ?? $item['aa'] ?? 0);
                $amt = (int) ($item['amount'] ?? 0);
                if ($aa > 0 && $amt > 0) {
                    $items[] = [
                        'aa' => $aa,
                        'amount' => $amt,
                        'is_cicil' => !empty($item['is_cicil']) ? 1 : 0,
                        'sisa_sebelum' => $item['sisa_sebelum'] ?? null,
                        'nama_tagihan' => $item['nama_tagihan'] ?? '',
                        'billcd' => $item['billcd'] ?? $item['BILLCD'] ?? '',
                    ];
                }
            }
        }

        // Default: top-up VA (nominal saja, tanpa pilih tagihan)
        if (empty($items)) {
            if ($amount < 1000) {
                jsonResponse(false, 'Nominal top up minimal 1000');
                return;
            }
        }

        try {
            $qris = new QrisPayment();
            $result = $qris->generate([
                'custid' => $custid,
                'nocust' => $nocust,
                'namacust' => $namacust,
                'amount' => $amount,
                'description' => $input['description'] ?? ('Top up saldo ' . $namacust),
            ], $items);
            jsonResponse(true, empty($items) ? 'QRIS top up berhasil dibuat' : 'QRIS berhasil dibuat', $result);
        } catch (Exception $e) {
            jsonResponse(false, $e->getMessage());
        }
    }

    public function saveQRIS()
    {
        $input = $this->readJsonInput();
        $custid = $input['custid'] ?? null;
        $nocust = $input['nocust'] ?? null;
        $namacust = $input['namacust'] ?? null;
        $qris = $input['qris'] ?? null;

        if ($custid === null || $nocust === null || $namacust === null || !is_array($qris)) {
            jsonResponse(false, 'Data tidak lengkap untuk menyimpan QRIS');
            return;
        }

        $items = [];
        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $aa = (int) ($item['AA'] ?? $item['aa'] ?? 0);
                $amount = (int) ($item['amount'] ?? 0);
                if ($aa > 0 && $amount > 0) {
                    $items[] = $item;
                }
            }
        }

        $amount = (int) ($input['amount'] ?? $input['total'] ?? ($qris['amount'] ?? 0));
        $description = (string) ($input['description'] ?? ('Top up saldo ' . $namacust));
        if (empty($items) && stripos($description, 'top') === false) {
            $description = 'TOPUP|'.$description;
        }

        try {
            $model = new QrisPayment();
            $saved = $model->saveGenerated([
                'custid' => $custid,
                'nocust' => $nocust,
                'namacust' => $namacust,
                'amount' => $amount,
                'description' => $description,
            ], $items, $qris);
            jsonResponse(true, 'QRIS tersimpan', $saved);
        } catch (Exception $e) {
            jsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Polling status QRIS — GET/POST ?path=qris-status&qris_id=...&transaction_id=...&vano=...
     */
    public function statusQRIS()
    {
        $input = $this->readJsonInput();
        $qrisId = trim((string) ($input['qris_id'] ?? $_GET['qris_id'] ?? ''));
        $trxId = trim((string) ($input['transaction_id'] ?? $_GET['transaction_id'] ?? ''));
        $vano = trim((string) ($input['vano'] ?? $_GET['vano'] ?? ''));

        if ($qrisId === '' && $trxId === '' && $vano === '') {
            jsonResponse(false, 'qris_id / transaction_id / vano wajib');
            return;
        }

        try {
            $model = new QrisPayment();
            $row = $model->findStatus([
                'qris_id' => $qrisId,
                'transaction_id' => $trxId,
                'vano' => $vano,
            ]);
            if (!$row) {
                jsonResponse(false, 'Transaksi QRIS tidak ditemukan', [
                    'paid' => false,
                    'qris_id' => $qrisId,
                    'transaction_id' => $trxId,
                    'vano' => $vano,
                ]);
                return;
            }

            $paid = ((int) ($row['paid_flag'] ?? 0) === 1)
                || strtolower((string) ($row['status'] ?? '')) === 'paid';

            jsonResponse(true, $paid ? 'Pembayaran berhasil' : 'Menunggu pembayaran', [
                'paid' => $paid,
                'status' => $row['status'] ?? 'pending',
                'paid_flag' => (int) ($row['paid_flag'] ?? 0),
                'qris_id' => $row['qris_id'] ?? $qrisId,
                'transaction_id' => $row['transaction_id'] ?? $trxId,
                'amount' => isset($row['amount']) ? (float) $row['amount'] : null,
                'vano' => $row['vano'] ?? $vano,
            ]);
        } catch (Exception $e) {
            jsonResponse(false, $e->getMessage());
        }
    }

public function getTahunAkademik() {
        header('Content-Type: application/json');
        try {
            $data = $this->tagihan->getAllTahunAkademik();
            echo json_encode([
                'status' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => false,
                'message' => 'Gagal mengambil data tahun akademik: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * List akun multi yang terhubung dengan VA aktif.
     * Body JSON: { "va": "...", "active_va": "..." }
     */
    public function multiAkunList()
    {
        $input = $this->readJsonInput();
        $va = $input['va'] ?? $input['active_va'] ?? null;

        if (empty($va)) {
            jsonResponse(false, 'VA wajib diisi');
            return;
        }

        try {
            $active = $input['active_va'] ?? $va;
            $accounts = $this->multiAkun->listAccounts($va, $active);
            jsonResponse(true, 'OK', [
                'accounts' => $accounts,
                'active_no_cust' => $this->multiAkun->normalizeNoCust($active),
            ]);
        } catch (Exception $e) {
            jsonResponse(false, 'Gagal memuat daftar multi akun: ' . $e->getMessage());
        }
    }

    /**
     * Tambah / link akun baru ke grup multi akun.
     * Body JSON: {
     *   "active_va": "...",
     *   "va": "...",
     *   "password": "...",
     *   "tahun_akademik": "all"
     * }
     */
    public function multiAkunTambah()
    {
        $input = $this->readJsonInput();
        $activeVa = $input['active_va'] ?? null;
        $va = $input['va'] ?? $input['no_cust'] ?? null;
        $password = $input['password'] ?? null;
        $tahun = $input['tahun_akademik'] ?? 'all';
        $activeTahun = $input['active_tahun_akademik'] ?? $tahun;

        if (empty($activeVa) || empty($va) || empty($password)) {
            jsonResponse(false, 'active_va, va, dan password wajib diisi');
            return;
        }

        try {
            $activeData = $this->multiAkun->getTagihanByVa($activeVa, $activeTahun);
            if (!$activeData) {
                jsonResponse(false, 'Akun aktif tidak ditemukan');
                return;
            }

            $newData = $this->multiAkun->getTagihanByVaPw($va, $password, $tahun);
            if (!$newData) {
                jsonResponse(false, 'VA atau Password akun baru salah, atau data tidak ditemukan');
                return;
            }

            $linked = $this->multiAkun->linkAccounts(
                $activeData,
                $activeVa,
                $activeTahun,
                $newData,
                $va,
                $tahun
            );

            jsonResponse(true, 'Akun berhasil ditambahkan', $linked);
        } catch (InvalidArgumentException $e) {
            jsonResponse(false, $e->getMessage());
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'multi_account_') !== false || stripos($msg, "doesn't exist") !== false) {
                jsonResponse(false, 'Tabel multi akun belum ada di database WS. Jalankan ws/sql/multi_account_tables.sql');
                return;
            }
            jsonResponse(false, 'Gagal menambahkan multi akun: ' . $msg);
        }
    }

    /**
     * Switch ke akun lain dalam grup yang sama (tanpa password).
     * Body JSON: {
     *   "active_va": "...",
     *   "target_va": "...",
     *   "tahun_akademik": "all"
     * }
     * Response data = struktur sama seperti cek-tagihan + accounts[]
     */
    public function multiAkunSwitch()
    {
        $input = $this->readJsonInput();
        $activeVa = $input['active_va'] ?? null;
        $targetVa = $input['target_va'] ?? $input['va'] ?? $input['no_cust'] ?? null;
        $tahun = $input['tahun_akademik'] ?? 'all';

        if (empty($activeVa) || empty($targetVa)) {
            jsonResponse(false, 'active_va dan target_va wajib diisi');
            return;
        }

        try {
            if (!$this->multiAkun->sameGroup($activeVa, $targetVa)) {
                jsonResponse(false, 'Akun tujuan tidak terhubung dengan multi akun aktif');
                return;
            }

            $member = $this->multiAkun->findMember($targetVa);
            $vaDisplay = $member['va_display'] ?? $targetVa;

            $data = $this->multiAkun->getTagihanByVa($targetVa, $tahun);
            if (!$data) {
                jsonResponse(false, 'Data akun tujuan tidak ditemukan');
                return;
            }

            $this->multiAkun->syncMemberMeta($data, $vaDisplay, $tahun);
            $accounts = $this->multiAkun->listAccounts($targetVa, $targetVa);

            $data['accounts'] = $accounts;
            $data['active_no_cust'] = $this->multiAkun->normalizeNoCust($targetVa);
            $data['group_id'] = $member['group_id'] ?? null;

            jsonResponse(true, 'Berhasil beralih akun', $data);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'multi_account_') !== false || stripos($msg, "doesn't exist") !== false) {
                jsonResponse(false, 'Tabel multi akun belum ada di database WS. Jalankan ws/sql/multi_account_tables.sql');
                return;
            }
            jsonResponse(false, 'Gagal beralih akun: ' . $msg);
        }
    }

    /**
     * Hapus akun dari grup multi akun.
     * Body JSON: {
     *   "active_va": "...",
     *   "target_va": "..."
     * }
     */
    public function multiAkunHapus()
    {
        $input = $this->readJsonInput();
        $activeVa = $input['active_va'] ?? null;
        $targetVa = $input['target_va'] ?? $input['va'] ?? $input['no_cust'] ?? null;

        if (empty($activeVa) || empty($targetVa)) {
            jsonResponse(false, 'active_va dan target_va wajib diisi');
            return;
        }

        try {
            $result = $this->multiAkun->removeMember($activeVa, $targetVa);
            jsonResponse(true, 'Akun berhasil dihapus dari multi akun', $result);
        } catch (InvalidArgumentException $e) {
            jsonResponse(false, $e->getMessage());
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'multi_account_') !== false || stripos($msg, "doesn't exist") !== false) {
                jsonResponse(false, 'Tabel multi akun belum ada di database WS. Jalankan ws/sql/multi_account_tables.sql');
                return;
            }
            jsonResponse(false, 'Gagal menghapus multi akun: ' . $msg);
        }
    }

    /**
     * Buat token login sekali pakai (dipakai dashboard admin).
     * Body JSON: { "no_cust": "...", "expires_hours": 24, "created_by": "...", "tahun_akademik": "all" }
     */
    public function tokenBuat()
    {
        $input = $this->readJsonInput();
        $noCust = $input['no_cust'] ?? $input['va'] ?? null;
        $expiresHours = $input['expires_hours'] ?? 24;
        $createdBy = $input['created_by'] ?? null;
        $tahun = $input['tahun_akademik'] ?? 'all';
        $custid = $input['custid'] ?? null;

        if (empty($noCust)) {
            jsonResponse(false, 'no_cust wajib diisi');
            return;
        }

        try {
            $created = $this->loginToken->create($noCust, $expiresHours, $createdBy, $tahun, $custid);
            jsonResponse(true, 'Token login berhasil dibuat', $created);
        } catch (InvalidArgumentException $e) {
            jsonResponse(false, $e->getMessage());
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'sm_user_credential_link') !== false || stripos($msg, "doesn't exist") !== false) {
                jsonResponse(false, 'Tabel sm_user_credential_link belum ada di database WS. Jalankan ws/sql/sm_user_credential_link.sql');
                return;
            }
            jsonResponse(false, 'Gagal membuat token login: ' . $msg);
        }
    }

    /**
     * Tukar token URL menjadi sesi tagihan (tanpa password).
     * Body JSON: { "token": "..." }
     */
    public function tokenLogin()
    {
        $input = $this->readJsonInput();
        $token = $input['token'] ?? null;

        if (empty($token)) {
            jsonResponse(false, 'token wajib diisi');
            return;
        }

        try {
            $data = $this->loginToken->consume($token);
            jsonResponse(true, 'Data ditemukan', $data);
        } catch (InvalidArgumentException $e) {
            jsonResponse(false, $e->getMessage());
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'sm_user_credential_link') !== false || stripos($msg, "doesn't exist") !== false) {
                jsonResponse(false, 'Tabel sm_user_credential_link belum ada di database WS. Jalankan ws/sql/sm_user_credential_link.sql');
                return;
            }
            jsonResponse(false, 'Gagal login dengan token: ' . $msg);
        }
    }

}
