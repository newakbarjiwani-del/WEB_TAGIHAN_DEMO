# Dokumentasi Sistem Pembayaran Tagihan

Dokumen ini menjelaskan secara lengkap cara kerja sistem pembayaran tagihan — mulai dari
database, web service (WS), Laravel controller, hingga tampilan UI. Tujuan: memudahkan
pemindahan ke project lain dengan konsep yang sama.

---

## 1. Arsitektur Keseluruhan

```
Browser (index3.blade.php)
       │
       │ POST form / AJAX fetch
       ▼
Laravel (routes/web.php + TagihanController)
       │
       │ HTTP POST JSON
       ▼
PHP Web Service (ws/index.php)
       │
       │ PDO Query
       ▼
MySQL Billing DB (sidoarjo_raudhatul_jannah)
```

Tidak ada sesi login di Laravel. Autentikasi dilakukan oleh WS (VA + password di-cek ke tabel
`sm_user`). Data siswa hanya ada di halaman selama satu request.

---

## 2. Tabel Database Billing (MySQL)

Semua tabel ada di database **billing WS** (bukan database Laravel lokal).

### 2.1 `sm_user` — Login / Autentikasi

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `userlogin` | VARCHAR | NIS / NOCUST (tanpa prefix bank) |
| `kunci` | VARCHAR | `sha1(password)` — hash plain text |

Query autentikasi:
```sql
SELECT userlogin, kunci FROM sm_user
WHERE userlogin = :nocust LIMIT 1;
-- cocokkan: $user['kunci'] === sha1($password)
```

### 2.2 `scctcust` — Data Siswa / Customer

| Kolom | Alias | Keterangan |
|-------|-------|-----------|
| `CUSTID` | `id` | Primary key internal |
| `NOCUST` | `no_cust` | NIS siswa (tanpa prefix bank) |
| `NUM2ND` | `num2nd` | Nomor alternatif |
| `NMCUST` | `nama` | Nama lengkap |
| `CODE02` | `jenjang` | Jenjang (SD/SMP/SMA) |
| `DESC02` | `kelas` | Nama kelas |
| `DESC03` | `jurusan` | Jurusan |
| `GENUS` | `orangtua` | Nama orang tua |

Query ambil siswa:
```sql
SELECT c.CUSTID AS id, c.NOCUST AS no_cust, c.NMCUST AS nama,
       c.CODE02 AS jenjang, c.DESC02 AS kelas, ...
FROM scctcust c
WHERE c.NOCUST = :nocust LIMIT 1;
```

### 2.3 `scctbill` — Tagihan

| Kolom | Keterangan |
|-------|-----------|
| `AA` | Primary key tagihan (dipakai sebagai ID di payload) |
| `CUSTID` | FK ke `scctcust.CUSTID` |
| `BILLCD` | Kode tagihan |
| `BILLNM` | Nama tagihan (misal: "SPP Agustus") |
| `BILLAM` | Nominal tagihan penuh (Rp) |
| `BILLPAID` | Sudah dibayar — dihitung dari saldo VA. `sudah_dibayar = BILLAM - BILLPAID` |
| `PAIDST` | Status: `'0'` = belum, `'1'` = lunas |
| `PAIDDT` | Tanggal bayar (NULL jika belum) |
| `BTA` | Tahun akademik (format `202409`) |
| `BILLAC` | Periode bulan (format `202409`) |
| `FURUTAN` | Urutan tampil |
| `isINSTALLABLE` | `1` = boleh cicil, `0` = harus lunas |
| `FSTSBolehBayar` | `1` = tagihan aktif bisa dibayar |
| `ExpDate` | Batas waktu bayar VA (opsional) |

**Query tagihan aktif (belum lunas):**
```sql
SELECT b.AA, b.BILLCD, b.BILLNM AS nama_tagihan, b.BILLAM AS total_tagihan,
       b.BILLPAID, b.BILLAC AS periode, b.BTA AS tahun_akademik_tagihan,
       b.FURUTAN, b.isINSTALLABLE, b.PAIDST, b.PAIDDT, b.ExpDate
FROM scctbill b
WHERE b.CUSTID = :custid
  AND b.PAIDST = '0'
  AND b.FSTSBolehBayar = '1'
  [AND b.BTA = :tahun_akademik]   -- opsional, jika filter tahun
ORDER BY b.FURUTAN ASC;
```

**Hitung sudah dibayar (dari BILLPAID):**
```php
$sudah = max(0, $total - (int)$row['BILLPAID']);
$sisa  = max(0, $total - $sudah);
```

### 2.4 `scctbill_detail` — Rincian Komponen Tagihan

| Kolom | Keterangan |
|-------|-----------|
| `CUSTID` | FK ke customer |
| `BILLCD` | Kode tagihan |
| `AA` | Nomor tagihan |
| `KodePost` | Kode akun komponen |
| `BILLAM` | Nominal komponen |
| `tahun`, `periode` | Tahun & bulan komponen |

Diquery saat user klik **Detail** tagihan. Di-join dengan `u_akun.NamaAkun`.

### 2.5 `scctva` — Virtual Account Pembayaran

Setiap kali user klik **Buat Nomor VA**, satu baris diinsert di sini.

| Kolom | Keterangan |
|-------|-----------|
| `CUSTID` | FK ke customer |
| `NOCUST` | NIS / nomor customer (= NOVA) |
| `NMCUST` | Nama customer |
| `NOVA` | Nomor VA yang ditampilkan ke user (= NOCUST) |
| `ArrayTagihan` | ID tagihan yang dibayar, dipisah koma: `"5,8,12"` |
| `BILLAM` | Nominal per tagihan, dipisah koma: `"250000,100000,150000"` |
| `BILLTOT` | Total nominal (jumlah BILLAM) |
| `STATUS` | `1` = aktif |
| `CREATED_AT` | Waktu buat |
| `ExpDate` | Batas waktu VA (ambil dari `MIN(scctbill.ExpDate)` tagihan terpilih) |

> **Catatan penting:** Kolom `NOVA` di tabel ini = `NOCUST` (bukan nomor unik per transaksi).
> Setiap pembayaran baru tetap insert baris baru dengan `NOVA = NOCUST`.
> Jika kolom `NOVA` memiliki constraint `UNIQUE`, insert kedua akan gagal.
> **Solusi:** hapus UNIQUE constraint di kolom `NOVA`, atau beri composite key.

### 2.6 `v_saldo_va` — View Saldo VA

| Kolom | Keterangan |
|-------|-----------|
| `NOCUST` | NIS |
| `SALDO` | Total saldo VA yang belum terpakai |

Diquery satu kali saat cek tagihan dan ditampilkan di Data Siswa.

### 2.7 `mst_thn_aka` — Master Tahun Akademik

| Kolom | Keterangan |
|-------|-----------|
| `thn_aka` | Nilai tahun akademik (misal `"2024/2025"`) |
| `urut` | Urutan tampil (DESC = terbaru di atas) |

Dipakai di dropdown **Tahun akademik** pada form Cek Tagihan.

### 2.8 `u_akun` — Master Akun/Komponen

| Kolom | Keterangan |
|-------|-----------|
| `KodeAkun` | Kode akun |
| `NamaAkun` | Label nama akun untuk rincian tagihan |

---

## 3. Web Service (WS) — `ws/`

File masuk: `ws/index.php`, routing via query param `?path=`.

### Endpoint

| `?path=` | Method | Fungsi |
|----------|--------|--------|
| `cek-tagihan` | POST | Cek tagihan siswa via VA saja (tanpa password) |
| `cek-tagihan-pw` | POST | Cek tagihan siswa via VA + password |
| `generate-va` | POST | Insert ke `scctva` & kembalikan nomor VA |
| `list-tahun-aka` | GET/POST | List tahun akademik dari `mst_thn_aka` |
| `multi-akun-list` | POST | List akun yang terhubung di satu grup |
| `multi-akun-tambah` | POST | Tambah / link akun ke grup multi akun |
| `multi-akun-switch` | POST | Beralih ke akun lain (tanpa password) |
| `multi-akun-hapus` | POST | Hapus satu akun dari grup multi akun |

### 3.1 `cek-tagihan-pw` — Request & Response

**Request (JSON):**
```json
{
  "va": "123456789",
  "password": "rahasia",
  "tahun_akademik": "2024/2025"
}
```

**Response (JSON):**
```json
{
  "status": true,
  "message": "Data ditemukan",
  "data": {
    "id": 42,
    "no_cust": "123456789",
    "nama": "NOVIA",
    "kelas": "ICT",
    "jenjang": "SD",
    "jurusan": "-",
    "va_number": "123456789",
    "saldo": 0,
    "tahun_dipilih": "Semua Tahun Akademik",
    "tagihan": [
      {
        "AA": 5,
        "BILLCD": "SPP",
        "nama_tagihan": "Spp Agustus",
        "total_tagihan": 250000,
        "sudah_dibayar": 20000,
        "sisa_tagihan": 230000,
        "isINSTALLABLE": 1,
        "is_installment": 1,
        "periode": "202408",
        "tahun_akademik_tagihan": "2024/2025",
        "PAIDST": "0",
        "ExpDate": "2025-01-31",
        "detail": [
          { "nominal_detail": 250000, "akun_detail": "SPP Reguler" }
        ]
      }
    ],
    "tagihan_lunas": [...]
  }
}
```

### 3.2 `generate-va` — Request & Response

**Request (JSON):**
```json
{
  "custid": 42,
  "nocust": "123456789",
  "namacust": "NOVIA",
  "array_tagihan": "5,8",
  "billam": "230000,100000",
  "total": 330000,
  "items": [
    { "AA": 5, "amount": 230000 },
    { "AA": 8, "amount": 100000 }
  ]
}
```

**Response sukses:**
```json
{
  "status": true,
  "message": "Nomor Virtual Account berhasil dibuat",
  "data": { "va_number": "123456789" }
}
```

**Response gagal (jika insert gagal):**
```json
{
  "status": false,
  "message": "Gagal menyimpan ke scctva. ..."
}
```

---

## 4. Laravel — Alur Server

### Routes (`routes/web.php`)

| Method | Path | Handler | Keterangan |
|--------|------|---------|-----------|
| GET | `/` | closure → `view('index3')` | Halaman utama kosong |
| POST | `/` | `TagihanController@cek2` | Proses cek tagihan + password |
| GET | `/list-tahun-akademik` | `TagihanController@listTahunAkademik` | Dropdown tahun |
| POST | `/generate-va` | `TagihanController@buatVA` | Proxy ke WS generate-va |
| POST | `/cek-tagihan` | closure | Proxy WS cek-tagihan (AJAX) |
| POST | `/multi-akun/tambah` | `MultiAccountController@tambah` | Tambah multi akun |
| POST | `/multi-akun/switch` | `MultiAccountController@switch` | Beralih akun |
| POST | `/multi-akun/hapus` | `MultiAccountController@hapus` | Hapus koneksi akun |

### `TagihanController::cek2` — Alur Login

```
1. Validasi: no_cust, password, academic_year (required)
2. normalizeVa(no_cust) → strip prefix 757777/797766/751000, ltrim('0')
3. POST ke WS cek-tagihan-pw dengan payload {va, password, tahun_akademik}
4. Jika WS return 404 → fallback ke cek-tagihan (tanpa password)
5. withNova($result) → formatNova(no_cust) → prefix '757777' + nocust
6. Jika status false → redirect back dengan flash error
7. MultiAccountService::syncMemberAfterLogin() → simpan session + update DB
8. MultiAccountService::listForNoCust() → ambil daftar multi akun dari WS
9. return view('index3', compact('result', 'multiAccounts'))
```

### `TagihanController::buatVA` — Alur Generate VA

```
1. Validasi: custid, nocust, namacust (required)
2. Parse items / array_tagihan + billam → pairs [{aa, amount}]
3. normalizeVa(nocust)
4. POST ke WS generate-va dengan payload lengkap
5. Jika JSON response gagal → retry dengan form-encoded
6. Cek result['status'] && va_number ada → return JSON sukses
7. Return JSON gagal dengan pesan WS
```

### Format VA ditampilkan ke user

```php
// TagihanController::formatNova()
'757777' . normalizeVa($nocust)
// contoh: '757777' + '123456789' = '757777123456789'
```

```js
// JS: formatNovaDisplay(va)
n = String(va).replace(/^(757777|797766|751000)/, '').replace(/^0+/, '');
return n ? ('757777' + n) : '-';
```

---

## 5. Tampilan UI (`resources/views/index3.blade.php`)

### 5.1 Form Cek Tagihan (Informasi Akun)

| Field | Tipe | Keterangan |
|-------|------|-----------|
| Nomor virtual account | `input[text]` | Placeholder `797766xxx` |
| Password | `input[password]` | Toggle show/hide |
| Tahun akademik | `select` | Di-load async dari `/list-tahun-akademik` |
| Cek tagihan | `button[submit]` | POST ke `/` |

### 5.2 Data Siswa (setelah login)

Kartu informasi di `.student-grid` (grid 2 kolom):

| Label | Sumber data |
|-------|------------|
| Nama | `$result['data']['nama']` |
| Kelas | `$result['data']['kelas']` |
| Angkatan | `$academic_year` |
| Saldo VA | `$result['data']['saldo']` |
| NOVA | `$result['data']['va_number']` (sudah `757777`+NIS) |
| Jenjang | `$result['data']['jenjang']` |

### 5.3 Tabel Tagihan Aktif

Kolom: `No`, `Nama tagihan`, `Nominal`, `Sudah dibayar`, `Dapat dicicil`, `Bayar`, `Detail`, `Exp Date`

| Kolom | Sumber | Keterangan |
|-------|--------|-----------|
| Nominal | `tagihan.total_tagihan` | Format Rp |
| Sudah dibayar | `tagihan.sudah_dibayar` | `BILLAM - BILLPAID` |
| Dapat dicicil | `tagihan.isINSTALLABLE` | Badge **Ya** / **Tidak** |
| Bayar | `input[number]` | Disable jika belum dipilih |
| Exp Date | `tagihan.ExpDate` | Format `YYYY-MM-DD` |

**Logika input Bayar:**
- `isINSTALLABLE = 0` → input readonly, value = sisa tagihan penuh
- `isINSTALLABLE = 1` → input bebas, max = sisa tagihan
- Tagihan dengan `sisa_tagihan <= 0` → checkbox disabled (tidak bisa dipilih)

### 5.4 Tabel Tagihan Lunas

Kolom: `No`, `Urutan`, `Nama tagihan`, `Nominal`, `Tgl bayar`, `Detail`

Sumber: `$result['data']['tagihan_lunas']` — `PAIDST = '1'`.

### 5.5 Modal Bayar Tagihan

Dipicu setelah user centang tagihan dan klik **Bayar tagihan**.

**Isi modal:**
- Ringkasan siswa (Nama, Kelas, NIS, Nomor VA dengan prefix `757777`, Exp Date VA)
- Tabel tagihan terpilih dengan kolom Nominal, Sudah dibayar, Bayar (input)
- Total pembayaran (update realtime)
- Tombol **+ Buat Nomor VA**

**Alur Buat Nomor VA:**
```
1. Kumpulkan selected tagihan → items [{AA, amount}]
2. Validasi: bayar > 0, bayar <= sisa, non-cicil harus = sisa
3. POST /generate-va (AJAX JSON) → TagihanController@buatVA → WS generate-va
4. WS insert ke scctva → return {va_number}
5. Tampilkan nomor VA: '757777' + nocust
6. Tombol berubah jadi "Tutup"
```

**Nomor VA yang ditampilkan** = `formatNovaDisplay(nocust)` = `757777123456789`

### 5.6 Modal Detail Tagihan

Klik **Lihat** → modal kecil, tampilkan:
- Nama tagihan
- Tahun akademik
- Tabel komponen rincian (`akun_detail` + `nominal_detail`)

---

## 6. Cara Adaptasi ke Project Lain

### Checklist perubahan wajib:

1. **`ws/config/database.php`** — Ganti `host`, `dbname`, `username`, `password` ke database billing project baru

2. **Kode bank (prefix VA)** — Cari-ganti `757777` (ada di 3 tempat):
   - `app/Http/Controllers/TagihanController.php` → `formatNova()` dan `normalizeVa()`
   - `resources/views/index3.blade.php` → fungsi JS `formatNovaDisplay()`
   - `ws/models/Tagihan.php` → `stripVa()`

3. **WS URL** — Update `.env`:
   ```
   WS_TAGIHAN_URL=https://domain-baru.com/path/ws/index.php
   ```

4. **Nama sekolah / brand** — Di `index3.blade.php`:
   - `<title>`, `.brand`, `h1`, footer copyright
   - `manifest.webmanifest` → `name`, `short_name`
   - File `public/icon-jannah.jpeg` → ganti icon baru + regenerasi `public/icons/icon-192.png` dan `icon-512.png` via `php public/icons/generate-icons.php`

5. **Multi akun** — Jalankan SQL di database billing baru:
   ```
   ws/sql/multi_account_tables.sql
   ```

6. **Database Laravel lokal** — Update `.env` lalu `php artisan migrate`

7. **Turnstile** (opsional) — Isi `TURNSTILE_SITE_KEY` dan `TURNSTILE_SECRET_KEY` jika perlu

### Yang **tidak perlu** diubah jika struktur tabel sama:
- `ws/index.php` (routing endpoint)
- `ws/models/Tagihan.php` (semua query pakai kolom standar)
- `app/Http/Controllers/TagihanController.php` (logika bisnis)
- `resources/views/index3.blade.php` (UI)
- `public/sw.js`, `public/manifest.webmanifest` (PWA)

---

## 7. Ringkasan Flow Lengkap

```
[User buka halaman]
  ↓ GET / → view index3 (form kosong)

[User isi VA + password + tahun, klik Cek Tagihan]
  ↓ POST / → TagihanController::cek2
  ↓ normalizeVa(VA) → strip prefix, strip leading zero
  ↓ POST ke WS ?path=cek-tagihan-pw
  ↓ WS cek sm_user (sha1 password) → getSiswaByVa → attachTagihan
  ↓ WS return {status, data: {siswa + tagihan + tagihan_lunas}}
  ↓ formatNova → '757777' + nocust
  ↓ syncMemberAfterLogin → session aktif
  ↓ return view index3 dengan $result + $multiAccounts

[User centang tagihan, set nominal bayar, klik Bayar Tagihan]
  ↓ JS: openPaymentModal() → tampil modal ringkasan
  
[User klik + Buat Nomor VA]
  ↓ POST /generate-va (AJAX) → TagihanController::buatVA
  ↓ parse pairs [{AA, amount}]
  ↓ POST ke WS ?path=generate-va
  ↓ WS insertVA → INSERT INTO scctva (CUSTID, NOCUST, NOVA, ArrayTagihan, BILLAM, BILLTOT, ExpDate)
  ↓ return {status:true, data:{va_number: nocust}}
  ↓ JS: tampilkan '757777' + nocust di modal
```

---

## 8. Catatan Penting

- **Password disimpan SHA1** (`sha1($plain_password)`) di tabel `sm_user` — ini standar sistem lama, tidak bisa diubah dari sisi WS tanpa migrasi password
- **`NOVA = NOCUST`** — nomor VA bukan unik per transaksi, tapi per siswa. Hapus UNIQUE constraint di kolom `NOVA` tabel `scctva` jika ada masalah insert kedua kali
- **Sesi tidak persisten** — user harus cek tagihan ulang jika refresh halaman; multi-akun switch tidak memerlukan password ulang (verifikasi dari DB grup)
- **WS harus HTTPS di produksi** — Laravel menggunakan `withoutVerifying()` untuk bypass SSL self-signed; di produksi sebaiknya pakai sertifikat valid
- **PWA hanya aktif di HTTPS** — install tombol tidak muncul di HTTP (kecuali `localhost`)
