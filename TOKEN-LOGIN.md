# Dokumentasi Login Otomatis via Token (Admin → Web Tagihan)

Dashboard admin adalah **project terpisah**. Admin membuat token di database billing
yang sama, lalu memberi URL ke siswa/orang tua. Sistem web tagihan ini **hanya
menerima** link itu dan menampilkan halaman tagihan tanpa password.

---

## 1. Bentuk URL

```
https://{DOMAIN_WEB_TAGIHAN}/{token}
```

Contoh:

```
https://tagihan.raudhatuljannah.sch.id/a8f3c91e0123456789abcdef0123456789abcdef0123456789abcdef01234567
```

- `{token}` = hex **64 karakter** (bukan NIS, bukan nomor VA, bukan prefix bank `757777`).
- Route Laravel: `GET /{token}` dengan constraint `[A-Fa-f0-9]{64}` (paling bawah di `routes/web.php`).

---

## 2. Tabel yang dipakai

Jalankan di **database billing WS** (bukan database Laravel):

[`ws/sql/sm_user_credential_link.sql`](ws/sql/sm_user_credential_link.sql)

Siswa tetap diambil dari tabel yang sudah ada:

| Tabel | Peran |
|-------|--------|
| `sm_user_credential_link` | Token URL + NIS tujuan + kadaluarsa + sekali pakai |
| `scctcust` | Validasi `no_cust` saat **buat** token |
| `scctbill` + view terkait | Isi tagihan saat **pakai** token (sama seperti cek tagihan biasa) |
| `sm_user` | **Tidak** dipakai. Login token tidak cek password |

### 2.1 Kolom `sm_user_credential_link`

| Kolom | Jadi apa di URL / login? |
|-------|--------------------------|
| `token` | **Path URL.** Ini satu-satunya yang tampil di link. |
| `no_cust` | NIS sudah dinormalisasi (tanpa `757777`). Dipakai untuk load tagihan. **Tidak** ada di URL. |
| `custid` | `scctcust.CUSTID` (opsional, diisi otomatis saat `token-buat`). |
| `tahun_akademik` | Filter tagihan; default `all`. |
| `expires_at` | Setelah waktu ini link ditolak. |
| `used_at` | `NULL` = belum dipakai. Diisi `NOW()` saat login sukses (**sekali pakai**). |
| `created_by` | Id/nama admin dari dashboard admin. |
| `created_at` / `updated_at` | Audit. |

Token **bukan** `no_cust`. NIS hanya disimpan di kolom `no_cust`.

---

## 3. Cara admin membuat link

Pilih salah satu.

### A. SQL langsung (dashboard admin insert ke DB billing)

```sql
INSERT INTO sm_user_credential_link (token, no_cust, custid, tahun_akademik, expires_at, created_by)
VALUES (
  LOWER(HEX(RANDOM_BYTES(32))),  -- MySQL 8+: 64 hex
  '123456789',                   -- NIS tanpa prefix bank
  42,                            -- CUSTID (boleh NULL)
  'all',
  DATE_ADD(NOW(), INTERVAL 24 HOUR),
  'admin-12'
);
```

Lalu bangun URL:

```
{APP_URL_WEB_TAGIHAN}/{token}
```

PHP (project admin):

```php
$token = bin2hex(random_bytes(32));
// INSERT ... token = $token, no_cust = $nisNormalized ...
$url = rtrim($appUrlTagihan, '/') . '/' . $token;
```

### B. Endpoint WS `token-buat` (tanpa INSERT manual)

`POST {WS_TAGIHAN_URL}?path=token-buat`

```json
{
  "no_cust": "123456789",
  "expires_hours": 24,
  "created_by": "admin-12",
  "tahun_akademik": "all"
}
```

`no_cust` boleh berisi prefix bank; WS akan menormalisasi.

Response:

```json
{
  "status": true,
  "message": "Token login berhasil dibuat",
  "data": {
    "token": "a8f3c91e...",
    "url_path": "a8f3c91e...",
    "no_cust": "123456789",
    "custid": 42,
    "tahun_akademik": "all",
    "expires_at": "2026-09-04 23:00:00",
    "expires_hours": 24,
    "created_by": "admin-12"
  }
}
```

URL untuk user: `{APP_URL_WEB_TAGIHAN}/{data.url_path}`

Batas `expires_hours`: 1–168 (default 24). Siswa harus ada di `scctcust`.

---

## 4. Fungsi login dari link (sistem web tagihan ini)

```
Browser  GET /{token}
    → Laravel TagihanController::loginByToken
    → POST WS ?path=token-login  { "token": "..." }
    → WS cek token, used_at, expires_at
    → UPDATE used_at = NOW()
    → cekTagihanByVA(no_cust) tanpa password
    → view index3 (sama seperti Cek tagihan)
```

Jika token salah, kadaluarsa, atau sudah dipakai: redirect `/` + pesan error (form login biasa tetap ada).

Endpoint WS `token-login` **hanya** dipanggil Laravel, bukan oleh user.

---

## 5. File yang harus di-deploy

| File | Keterangan |
|------|------------|
| `ws/sql/sm_user_credential_link.sql` | Jalankan sekali di DB billing |
| `ws/index.php` | Path `token-buat`, `token-login` |
| `ws/models/LoginToken.php` | Logika token |
| `ws/controllers/TagihanController.php` | `tokenBuat()`, `tokenLogin()` |
| `routes/web.php` | `GET /{token}` |
| `app/Http/Controllers/TagihanController.php` | `loginByToken()` |

---

## 6. Copy ke project lain (konsep sama)

1. Jalankan `ws/sql/sm_user_credential_link.sql` di DB billing project baru.
2. Upload file WS di atas ke server WS.
3. Pastikan `WS_TAGIHAN_URL` di `.env` Laravel mengarah ke WS yang sudah di-update.
4. Prefix bank **tidak** masuk ke token. Prefix hanya untuk tampilan NOVA setelah login (lihat `PEMBAYARAN.md`).
5. Di dashboard admin: simpan `{APP_URL}` web tagihan, lalu `url = APP_URL + '/' + token`.

Alur bayar (`scctva`, generate-va) tidak berubah.
