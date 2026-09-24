# WEB_TAGIHAN — Master White-Label

Project master PWA cek & bayar tagihan (Laravel 10) + Web Service + callback QRIS.

## Dokumentasi setup / copy project

Baca lengkap: **[docs/SETUP-MASTER.md](docs/SETUP-MASTER.md)**

Isi tutorial:

- Cara copy sebagai base client baru
- Setup Laravel, brand, DEMO_WS, islamic_center
- **Query SQL tabel tambahan** (multi akun, QRIS, Web Push) — `database/sql/pwa_extra_tables.sql`
- Notifikasi: Web Push + resume saat PWA dibuka lagi
- Checklist uji & troubleshooting

## Stack singkat

| Layer | Teknologi |
|-------|-----------|
| Frontend | Laravel Blade PWA (`index3`), Service Worker |
| API tagihan | `DEMO_WS_TAGIHAN_CICILAN/` |
| Callback bayar QRIS | `islamic_center/qris/pushNotif.php` |

## Quick start (dev)

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: WS_TAGIHAN_URL, TAGIHAN_DB_*, BRAND_*
php artisan serve
```

Buka `APP_URL`, login dengan VA demo, uji generate QRIS jika `BRAND_PAYMENT_QRIS=true`.
