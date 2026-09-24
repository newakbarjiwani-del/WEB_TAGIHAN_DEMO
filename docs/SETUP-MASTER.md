# WEB_TAGIHAN — Master Project (Copy & Setup)

Dokumentasi lengkap untuk menyalin project ini sebagai **base** white-label tagihan PWA ke sekolah / client baru, sampai login, VA/QRIS, callback bayar, multi akun, dan **notifikasi Web Push** berjalan.

> File ini: `docs/SETUP-MASTER.md`  
> Notifikasi: **Web Push** (server kirim saat lunas) + **resume watch** saat PWA dibuka lagi (cadangan jika push tertunda di HP).  
> SQL tabel tambahan: lihat [§6b](#6b-query-sql--tabel-tambahan-pwa--multi-akun--notif) atau file `database/sql/pwa_extra_tables.sql`.

---

## Daftar isi

1. [Arsitektur singkat](#1-arsitektur-singkat)
2. [Yang perlu disiapkan](#2-yang-perlu-disiapkan)
3. [Cara copy project](#3-cara-copy-project)
4. [Setup Laravel (frontend PWA)](#4-setup-laravel-frontend-pwa)
5. [Setup white-label / brand](#5-setup-white-label--brand)
6. [Setup DEMO_WS (backend API)](#6-setup-demo_ws-backend-api)
6b. [Query SQL — tabel tambahan PWA / multi akun / notif](#6b-query-sql--tabel-tambahan-pwa--multi-akun--notif)
7. [Setup islamic_center (callback QRIS)](#7-setup-islamic_center-callback-qris)
8. [Notifikasi sistem (Web Push)](#8-notifikasi-sistem-web-push--resume-saat-buka-pwa)
9. [Checklist uji fungsi](#9-checklist-uji-fungsi)
10. [Troubleshooting](#10-troubleshooting)
11. [Peta file penting](#11-peta-file-penting)

---

## 1. Arsitektur singkat

```
┌─────────────────────┐     HTTP JSON      ┌──────────────────────────────┐
│  Laravel PWA        │ ◄────────────────► │  DEMO_WS_* / index.php       │
│  (browser / HP)     │   WS_TAGIHAN_URL   │  (cek tagihan, VA, QRIS)     │
└─────────┬───────────┘                    └──────────────┬───────────────┘
          │                                               │
          │ poll /cek-status-pembayaran                   │ tulis mst_qris
          │ (Fase 1 notif)                                ▼
          │                                    ┌──────────────────────────┐
          │                                    │  MySQL sekolah           │
          │                                    │  (mst_qris, tagihan, …)  │
          │                                    └────────────▲─────────────┘
          │                                                 │
          │                                    update paid  │
┌─────────▼───────────┐     callback bank     ┌────────────┴──────────────┐
│  User scan QRIS     │ ────────────────────► │  islamic_center/qris/     │
│  (e-wallet / bank)  │                       │  pushNotif.php + handler  │
└─────────────────────┘                       │  → forward DEMO_INSTALL…  │
                                              └───────────────────────────┘
```

**Tiga komponen yang harus ikut di-copy / di-deploy:**

| Komponen | Folder | Peran |
|----------|--------|--------|
| Frontend PWA | root Laravel (`app/`, `resources/`, `public/`, …) | UI login, bayar, PWA, poll status + notif |
| Web Service | `DEMO_WS_TAGIHAN_CICILAN/` (rename per client) | API cek tagihan, generate VA/QRIS |
| Callback QRIS | `islamic_center/qris/` (+ config DB) | Terima notif bayar dari bank, update DB, forward saldo |

Prefix VA contoh demo: **`751000`**. Setiap client baru biasanya punya prefix VA sendiri.

---

## 2. Yang perlu disiapkan

- PHP **8.1+**, Composer, ekstensi: `pdo_mysql`, `curl`, `openssl`, `mbstring`, `gd` (untuk generate icon PWA)
- MySQL/MariaDB sekolah (host, nama DB, user, password)
- Web server (Apache/Nginx) + HTTPS (PWA & Notification API butuh secure context; localhost OK untuk dev)
- URL publik untuk:
  - Laravel PWA
  - Folder WS (`…/DEMO_WS_…/index.php`)
  - `islamic_center/qris/pushNotif.php` (sudah terdaftar di sistem QRIS bank / Lazizmu)
- Data VA prefix, account QRIS, JWT secret QRIS (dari tim ICT / bank)

---

## 3. Cara copy project

### Opsi A — Copy folder (paling sederhana)

1. Salin seluruh folder `WEB_TAGIHAN_DEMO` → misalnya `WEB_TAGIHAN_NAMA_SEKOLAH`.
2. Rename folder WS di dalamnya:
   - `DEMO_WS_TAGIHAN_CICILAN` → `WS_TAGIHAN_NAMA_SEKOLAH` (atau pola yang dipakai di server `103.23.103.43`).
3. Deploy:
   - Laravel → document root / subdomain client
   - WS → path di server API (contoh `…/WEB_TAGIHAN_PROJECT/WS_…/`)
   - Handler callback → server `islamic_center` (atau salin file handler baru ke sana)

### Opsi B — Git

```bash
git clone <repo-master> WEB_TAGIHAN_NAMA_SEKOLAH
cd WEB_TAGIHAN_NAMA_SEKOLAH
cp .env.example .env
# lalu lanjut setup di bawah
```

Jangan commit `.env` berisi password/secret.

### Yang biasanya diganti per client

- Nama folder & `APP_NAME` / brand
- `WS_TAGIHAN_URL`
- Kredensial `TAGIHAN_DB_*` + `DEMO_WS_…/config/database.php`
- Prefix VA di `islamic_center` + cabang `pushNotif.php`
- URL forward ke `…/QRIS.php?token=` (jika endpoint installment berbeda)
- Logo, warna, `BRAND_PAYMENT_MODEL`, `BRAND_PAYMENT_QRIS`

---

## 4. Setup Laravel (frontend PWA)

Di root project:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Isi minimal di `.env`:

```env
APP_NAME="Nama Sekolah Tagihan"
APP_URL=https://tagihan-sekolah.example.com
APP_ENV=production
APP_DEBUG=false

# Arahkan ke WS client yang sudah di-deploy
WS_TAGIHAN_URL=http://103.23.103.43/WEB_TAGIHAN_PROJECT/WS_TAGIHAN_NAMA/index.php

# DB yang sama dengan DEMO_WS (mst_qris, dll.)
TAGIHAN_DB_HOST=103.23.103.36
TAGIHAN_DB_PORT=3306
TAGIHAN_DB_DATABASE=nama_database_sekolah
TAGIHAN_DB_USERNAME=...
TAGIHAN_DB_PASSWORD=...

# Brand — lihat bagian 5
BRAND_NAME="Nama Sekolah"
BRAND_SHORT_NAME="Sekolah"
BRAND_PAYMENT_MODEL=2
BRAND_PAYMENT_QRIS=true
```

Lalu:

```bash
php artisan config:clear
php artisan cache:clear
# pastikan storage & bootstrap/cache writable
chmod -R ug+rwx storage bootstrap/cache   # Linux
```

Document root web mengarah ke folder **`public/`**.

Pastikan file PWA ada:

- `public/sw.js`
- `public/offline.html`
- `public/icons/icon-192.png`, `icon-512.png` (atau path di `BRAND_ICON_*`)
- Route manifest: `/manifest.webmanifest` (sudah di `routes/web.php`)

---

## 5. Setup white-label / brand

Konfigurasi utama: **`config/brand.php`** (override lewat `.env`).

| Variabel | Fungsi |
|----------|--------|
| `BRAND_NAME` / `BRAND_SHORT_NAME` | Nama di UI & PWA |
| `BRAND_TAGLINE`, `BRAND_DESCRIPTION` | Teks pendukung |
| `BRAND_LOGO`, `BRAND_FAVICON` | File di `public/` |
| `BRAND_ICON_192`, `BRAND_ICON_512` | Icon PWA / notifikasi |
| `BRAND_PAYMENT_MODEL` | `1` / `bills` = pilih tagihan + bayar VA; `2` / `saldo` = tagihan info saja, VA kartu isi saldo (**default `2`**) |
| `BRAND_PAYMENT_QRIS` | `true` = tombol Top up saldo via QRIS |
| `BRAND_COLOR_*` | Tema (hex **wajib pakai tanda kutip** di `.env`, karena `#` = komentar) |
| `BRAND_RADIUS` | Radius UI (default 8) |
| `BRAND_PWA_ENABLED` | Register service worker |
| `QRIS_*` | Server generate QRIS, JWT, account, mitra |

Contoh warna aman di `.env`:

```env
BRAND_COLOR_PRIMARY="#0B7EB8"
BRAND_COLOR_HIGHLIGHT="#E89B0C"
```

### Ganti logo & icon PWA

1. Taruh logo di `public/` (mis. `public/logo-sekolah.png`).
2. Set `BRAND_LOGO=logo-sekolah.png`.
3. Generate icon (butuh GD):

```bash
php public/icons/generate-icons.php
```

4. Atau copy manual PNG 192/512 ke `public/icons/` dan set `BRAND_ICON_192` / `BRAND_ICON_512`.

Setelah ganti brand/icon, minta user **uninstall PWA lama** atau clear site data agar manifest & cache SW terbarui (`sw.js` versi cache: `tagihan-pwa-v7`).

---

## 6. Setup DEMO_WS (backend API)

Folder: `DEMO_WS_TAGIHAN_CICILAN/` (rename sesuai client).

### 6.1 Database

Edit **`DEMO_WS_TAGIHAN_CICILAN/config/database.php`**:

```php
$host = "...";
$dbname = "...";
$username = "...";
$password = "...";
```

Samakan dengan `TAGIHAN_DB_*` di Laravel `.env`.

Tabel penting (minimal untuk QRIS + PWA):

- Lihat **[§6b](#6b-query-sql--tabel-tambahan-pwa--multi-akun--notif)** / jalankan `database/sql/pwa_extra_tables.sql`
- `mst_qris` / `mst_qris_item` / `log_qris_push`
- `multi_account_*` (multi akun)
- `push_subscriptions` (Web Push)
- Tabel tagihan / saldo sesuai skema sekolah (sudah ada di DB billing)

### 6.2 Deploy WS

Upload folder ke path yang sama dengan `WS_TAGIHAN_URL`, contoh:

`http://103.23.103.43/WEB_TAGIHAN_PROJECT/WS_TAGIHAN_NAMA/index.php`

Uji cepat: POST ke `?path=cek-tagihan` dengan body JSON VA (atau lewat UI Laravel).

### 6.3 QRIS di WS

`DEMO_WS_TAGIHAN_CICILAN/config/qris.php` — samakan `QRIS_*` dengan Laravel bila dipakai di sisi WS.

Endpoint forward setelah bayar (di handler islamic_center) biasanya:

`http://…/sikeu_ws_mysql/DEMO_INSTALLMENT/QRIS.php?token=…`

Ganti path `DEMO_INSTALLMENT` jika client memakai endpoint installment lain.

---

## 6b. Query SQL — tabel tambahan PWA / multi akun / notif

Semua tabel di bawah dijalankan di **DB sekolah / WS / billing** (sama dengan `TAGIHAN_DB_*` dan `DEMO_WS_…/config/database.php`), **bukan** DB Laravel default (`DB_DATABASE`) kecuali memang digabung.

### Ringkasan tabel

| Tabel | Fitur | Wajib? |
|-------|--------|--------|
| `multi_account_groups` | Multi akun PWA (grup) | Ya, jika multi akun dipakai |
| `multi_account_members` | Multi akun PWA (anggota per NIS) | Ya, jika multi akun dipakai |
| `login_tokens` | Login lewat link `/{token}` dari admin | Opsional |
| `mst_qris` | Header generate QRIS / top up saldo | Ya, jika QRIS aktif |
| `mst_qris_item` | Detail tagihan di dalam 1 QRIS (kosong untuk top up murni) | Ya, jika QRIS aktif |
| `log_qris_push` | Audit callback `pushNotif` | Disarankan |
| `push_subscriptions` | Endpoint Web Push per browser/HP | Ya, untuk notifikasi sistem |

### Cara cepat (satu file)

```bash
# Ganti nama DB sesuai client
mysql -h HOST -u USER -p NAMA_DB_SEKOLAH < database/sql/pwa_extra_tables.sql
```

File alternatif (sama isinya, di folder WS):

```text
DEMO_WS_TAGIHAN_CICILAN/sql/install_missing_tables.sql
```

File terpisah per fitur:

| File | Isi |
|------|-----|
| `DEMO_WS_TAGIHAN_CICILAN/sql/multi_account_tables.sql` | Multi akun |
| `DEMO_WS_TAGIHAN_CICILAN/sql/login_tokens.sql` | Login token |
| `DEMO_WS_TAGIHAN_CICILAN/sql/qris_payment_tables.sql` | QRIS + log |
| `database/sql/push_subscriptions.sql` | Web Push saja |

Atau migrate Laravel (koneksi `tagihan`):

```bash
php artisan migrate --database=tagihan --path=database/migrations/2026_09_24_000001_create_push_subscriptions_table.php
```

### A) Multi akun PWA

```sql
CREATE TABLE IF NOT EXISTS multi_account_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS multi_account_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id BIGINT UNSIGNED NOT NULL,
  no_cust VARCHAR(50) NOT NULL COMMENT 'VA/NIS sudah dinormalisasi (normalizeVa)',
  va_display VARCHAR(80) NULL COMMENT 'VA asli yang diinput user',
  nama VARCHAR(150) NULL,
  kelas VARCHAR(100) NULL,
  jenjang VARCHAR(50) NULL,
  last_academic_year VARCHAR(50) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_no_cust (no_cust),
  KEY idx_members_group (group_id),
  CONSTRAINT fk_members_group
    FOREIGN KEY (group_id) REFERENCES multi_account_groups(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### B) Login token (opsional)

```sql
CREATE TABLE IF NOT EXISTS login_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(64) NOT NULL COMMENT 'Hex 64 karakter; path URL GET /{token}',
  no_cust VARCHAR(50) NOT NULL COMMENT 'NIS/VA sudah dinormalisasi (tanpa prefix bank)',
  custid INT NULL,
  tahun_akademik VARCHAR(50) NOT NULL DEFAULT 'all',
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_by VARCHAR(100) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_login_tokens_token (token),
  KEY idx_login_tokens_no_cust (no_cust),
  KEY idx_login_tokens_expires (expires_at),
  KEY idx_login_tokens_used (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### C) QRIS + audit callback

```sql
CREATE TABLE IF NOT EXISTS mst_qris (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  custid VARCHAR(64) NOT NULL,
  nocust VARCHAR(64) NOT NULL,
  namacust VARCHAR(191) NULL,
  vano VARCHAR(64) NULL COMMENT 'prefix bank + nocust (routing pushNotif)',
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  qris_id VARCHAR(128) NULL,
  qris_content TEXT NULL,
  transaction_id VARCHAR(64) NULL,
  account_no VARCHAR(64) NULL,
  mitra_customer_id VARCHAR(191) NULL,
  description VARCHAR(255) NULL,
  status ENUM('pending','paid','expired','failed') NOT NULL DEFAULT 'pending',
  paid_flag TINYINT(1) NOT NULL DEFAULT 0,
  request_payload MEDIUMTEXT NULL,
  response_payload MEDIUMTEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  paid_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_mst_qris_custid (custid),
  KEY idx_mst_qris_nocust (nocust),
  KEY idx_mst_qris_status (status),
  KEY idx_mst_qris_qris_id (qris_id),
  KEY idx_mst_qris_vano (vano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_qris_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  aa BIGINT UNSIGNED NOT NULL,
  billcd VARCHAR(64) NULL,
  nama_tagihan VARCHAR(255) NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_cicil TINYINT(1) NOT NULL DEFAULT 0,
  sisa_sebelum DECIMAL(18,2) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_mst_qris_item_payment (payment_id),
  KEY idx_mst_qris_item_aa (aa),
  CONSTRAINT fk_mst_qris_item_payment
    FOREIGN KEY (payment_id) REFERENCES mst_qris(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS log_qris_push (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(32) NOT NULL DEFAULT 'push_notif',
  qris_id VARCHAR(128) NULL,
  transaction_id VARCHAR(64) NULL,
  vano VARCHAR(64) NULL,
  custid VARCHAR(64) NULL,
  nocust VARCHAR(64) NULL,
  amount DECIMAL(18,2) NULL,
  paid_flag TINYINT(1) NULL,
  processed VARCHAR(32) NULL,
  scctva_status VARCHAR(32) NULL,
  response_code VARCHAR(8) NULL,
  response_message VARCHAR(255) NULL,
  http_code SMALLINT UNSIGNED NULL,
  request_payload MEDIUMTEXT NULL,
  response_payload MEDIUMTEXT NULL,
  source VARCHAR(128) NULL DEFAULT 'qris/pushNotif/demoInstallment.php',
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_log_qris_push_qris_id (qris_id),
  KEY idx_log_qris_push_vano (vano),
  KEY idx_log_qris_push_payment (payment_id),
  KEY idx_log_qris_push_custid (custid),
  KEY idx_log_qris_push_created (created_at),
  KEY idx_log_qris_push_event (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### D) Web Push — kirim notifikasi sistem

```sql
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  endpoint_hash VARCHAR(64) NOT NULL COMMENT 'sha256(endpoint) — unique pendek',
  endpoint TEXT NOT NULL COMMENT 'URL push service browser',
  public_key VARCHAR(255) NULL,
  auth_token VARCHAR(255) NULL,
  content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
  nocust VARCHAR(50) NULL COMMENT 'NIS/userlogin (normalizeVa)',
  vano VARCHAR(80) NULL,
  user_agent VARCHAR(255) NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY push_subscriptions_endpoint_hash_unique (endpoint_hash),
  KEY push_subscriptions_nocust_index (nocust),
  KEY push_subscriptions_vano_index (vano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Alur data notif:

1. Login PWA → browser subscribe → baris di `push_subscriptions` (`nocust` / `vano`).
2. QRIS lunas → `mst_qris.paid_flag = 1` + baris `log_qris_push`.
3. `demoInstallment.php` → `POST /push/notify-paid` → Laravel kirim Web Push ke endpoint yang cocok `nocust`/`vano`.

### Verifikasi setelah install

```sql
SHOW TABLES LIKE 'multi_account%';
SHOW TABLES LIKE 'login_tokens';
SHOW TABLES LIKE 'mst_qris%';
SHOW TABLES LIKE 'log_qris_push';
SHOW TABLES LIKE 'push_subscriptions';

SELECT COUNT(*) AS n FROM push_subscriptions;
SELECT COUNT(*) AS n FROM multi_account_members;
SELECT id, nocust, amount, status, paid_flag, paid_at
FROM mst_qris
ORDER BY id DESC
LIMIT 10;
```

---

## 7. Setup islamic_center (callback QRIS)

Folder referensi di repo: **`islamic_center/`**.

Di server produksi islamic_center biasanya sudah ada `qris/pushNotif.php`. Untuk client baru:

### 7.1 Config DB

Salin / edit misalnya `islamic_center/config/connectTagihanCicilan.php`:

- Host, DB, user, password
- `vano_prefix` / `nova_bank_prefix` (contoh demo: `751000`)

### 7.2 Handler per prefix VA

File contoh: `islamic_center/qris/pushNotif/demoInstallment.php`

Yang dilakukan:

1. Tandai QRIS paid di DB (`mst_qris`)
2. Log ke `log_qris_push` (jika ada)
3. Forward token ke endpoint installment WS
4. Panggil Web Push Laravel (`WEBPUSH_NOTIFY_URL` + secret) saat `newly_paid`

### 7.3 Daftarkan di `pushNotif.php`

Di `islamic_center/qris/pushNotif.php`, cabang 6 digit VA:

```php
} elseif ($enamDigitVano === '751000') {
    require_once __DIR__.'/pushNotif/demoInstallment.php';
}
```

Untuk client baru: ganti `'751000'` → prefix VA sekolah, dan `require` file handler baru (boleh copy dari `demoInstallment.php` lalu sesuaikan `$forwardBase` + config DB).

### 7.4 Pastikan bank / gateway

Callback QRIS harus mengarah ke URL `pushNotif.php` yang sama (sudah dikonfigurasi di sistem QRIS). Prefix VA baru harus dikenali di cabang `if` di atas.

---

## 8. Notifikasi sistem (Web Push + resume saat buka PWA)

### Cara kerja

1. Setelah login, PWA **subscribe** Web Push (VAPID) → endpoint disimpan di tabel `push_subscriptions`.
2. User generate QRIS/VA → status watch disimpan di `localStorage` (12 menit).
3. Saat QRIS lunas, `demoInstallment.php` memanggil `POST /push/notify-paid` → server kirim Web Push.
4. Service Worker (`public/sw.js`) menerima event `push` → tampilkan notifikasi sistem.
5. **Cadangan:** kalau PWA ditutup lalu dibuka lagi, watch di-resume dari `localStorage` → poll status → notif + toast (meski push tertunda di HP).

### Syarat

| Syarat | Keterangan |
|--------|------------|
| HTTPS | Wajib untuk Push + Notification |
| `VAPID_*` + `WEBPUSH_NOTIFY_SECRET` di `.env` Laravel | `php artisan webpush:vapid` atau `node scripts/generate-vapid.cjs` |
| `WEBPUSH_NOTIFY_URL` + `WEBPUSH_NOTIFY_SECRET` di `islamic_center/.env` | URL = `https://DOMAIN/push/notify-paid` |
| Tabel `push_subscriptions` di DB tagihan | `database/sql/push_subscriptions.sql` atau migrate |
| Izin notifikasi Granted | Satu kali di browser/HP |
| `composer require minishlink/web-push` | Package kirim push |

### File terkait

- `app/Services/WebPushService.php`, `WebPushController.php`
- `resources/views/index3.blade.php` — subscribe + paymentWatch + localStorage resume
- `public/sw.js` — listener `push` + `notificationclick`
- `islamic_center/qris/pushNotif/demoInstallment.php` — trigger notify saat `newly_paid`

---

## 9. Checklist uji fungsi

Centang berurutan setelah deploy:

### A. Dasar

- [ ] Buka `APP_URL` — halaman login tampil, logo & warna brand benar
- [ ] `composer install` & `APP_KEY` sudah ada
- [ ] Tidak ada error 500 di `storage/logs/laravel.log`

### B. Login & tagihan

- [ ] Login dengan NIS/VA valid + tahun akademik
- [ ] Daftar tagihan / saldo tampil
- [ ] Multi-akun (jika dipakai) tambah & switch OK

### C. VA

- [ ] Generate VA berhasil
- [ ] Setelah bayar VA (sandbox/bank), status / saldo berubah saat refresh atau lewat watch

### D. QRIS (jika `BRAND_PAYMENT_QRIS=true`)

- [ ] Tombol Top up QRIS muncul
- [ ] QR tampil, bisa unduh gambar
- [ ] Scan & bayar (nominal kecil)
- [ ] Di DB: `mst_qris` jadi paid/success
- [ ] Ada baris log di `log_qris_push` (jika tabel ada)
- [ ] Forward ke `…/QRIS.php?token=` sukses (cek log PHP islamic_center)

### E. Notifikasi Web Push

- [ ] Tabel `push_subscriptions` ada di DB tagihan
- [ ] `VAPID_*` + `WEBPUSH_NOTIFY_SECRET` di `.env` Laravel
- [ ] `WEBPUSH_NOTIFY_URL` + secret di `islamic_center/.env`
- [ ] Setelah login, browser minta izin notifikasi → **Allow**
- [ ] Ada baris di `push_subscriptions` untuk `nocust` user
- [ ] Setelah QRIS paid: notifikasi sistem muncul (atau saat buka PWA lagi)
- [ ] Install “Add to Home Screen” — icon & nama short_name benar

### F. Multi akun

- [ ] Tabel `multi_account_groups` + `multi_account_members` ada
- [ ] Tambah akun kedua → switch → hapus OK

### G. PWA manifest

- [ ] Buka `/manifest.webmanifest` — JSON valid, icon 200
- [ ] `theme_color` sesuai brand

---

## 10. Troubleshooting

| Gejala | Cek |
|--------|-----|
| Login gagal / timeout | `WS_TAGIHAN_URL`, firewall, log WS & Laravel |
| QRIS generate error | `QRIS_*`, JWT secret, account_no, koneksi ke server QRIS |
| Bayar sukses di e-wallet tapi saldo tidak berubah | Cabang prefix di `pushNotif.php`, DB connect, `$forwardBase` di handler, log `php_errors.log` islamic_center |
| Notif tidak muncul | Izin Notification, PWA/tab terbuka, SW di Application tab DevTools, poll `/cek-status-pembayaran` return `paid: true` |
| Warna brand aneh / kosong | Hex di `.env` tanpa kutip (`#` terpotong) — pakai `"#0B7EB8"` |
| Icon PWA lama | Naikkan `CACHE_VERSION` di `sw.js`, clear site data, reinstall PWA |
| CSRF 419 | Pastikan meta csrf & session cookie domain/`APP_URL` benar |

---

## 11. Peta file penting

```
WEB_TAGIHAN_*/
├── docs/SETUP-MASTER.md          ← dokumentasi ini
├── .env.example                  ← template env Laravel
├── database/sql/
│   ├── pwa_extra_tables.sql      ← SEMUA tabel tambahan (disarankan)
│   ├── push_subscriptions.sql
│   └── multi_account_tables.sql  ← deprecated; pakai file WS
├── config/brand.php
├── config/services.php           ← WS_TAGIHAN_URL, TAGIHAN_DB_*
├── routes/web.php
├── app/Http/Controllers/
│   ├── TagihanController.php
│   ├── MultiAccountController.php
│   └── WebPushController.php
├── app/Services/
│   ├── QrisGenerateService.php
│   ├── MultiAccountService.php
│   └── WebPushService.php
├── resources/views/index3.blade.php
├── public/sw.js
├── DEMO_WS_TAGIHAN_CICILAN/
│   ├── sql/
│   │   ├── install_missing_tables.sql  ← multi akun + QRIS + push
│   │   ├── multi_account_tables.sql
│   │   ├── login_tokens.sql
│   │   └── qris_payment_tables.sql
│   ├── config/database.php
│   └── …
└── islamic_center/
    ├── .env                      ← WEBPUSH_NOTIFY_URL + SECRET
    ├── config/connectTagihanCicilan.php
    └── qris/pushNotif/…
```

---

## Ringkasan cepat copy client baru

1. Copy repo → rename WS folder.  
2. `composer install` + `.env` (APP_URL, WS, DB, brand, QRIS, VAPID, SESSION_LIFETIME).  
3. Jalankan SQL: `database/sql/pwa_extra_tables.sql` di DB sekolah.  
4. Samakan DB di WS `database.php` + islamic_center connect.  
5. Daftarkan prefix VA di `pushNotif.php` + handler forward + env Web Push.  
6. Deploy Laravel `public/`, WS, handler callback.  
7. Uji login → multi akun → QRIS → paid → notifikasi + history top up.  

Selesai: base master siap dipakai ulang untuk project berikutnya.
