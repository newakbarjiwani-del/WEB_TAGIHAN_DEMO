<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagihanController;
use App\Http\Controllers\MultiAccountController;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

Route::get('/manifest.webmanifest', function () {
    $brand = config('brand');
    $colors = $brand['colors'];
    $pwa = $brand['pwa'];

    return response()->json([
        'id' => '/',
        'name' => $brand['tagline'] . ' - ' . $brand['name'],
        'short_name' => $brand['short_name'],
        'description' => $brand['description'],
        'start_url' => $pwa['start_url'] ?? '/?source=pwa',
        'scope' => '/',
        'display' => $pwa['display'] ?? 'standalone',
        'orientation' => $pwa['orientation'] ?? 'portrait-primary',
        'background_color' => $pwa['background_color'] ?? $colors['dark_bg'],
        'theme_color' => $colors['theme'],
        'lang' => 'id',
        'dir' => 'ltr',
        'categories' => ['finance', 'education', 'utilities'],
        'prefer_related_applications' => false,
        // Wajib untuk Chrome/FCM Web Push di Android
        'gcm_sender_id' => '103953800507',
        'icons' => [
            [
                'src' => asset($brand['icon_192']),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset($brand['icon_512']),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => asset($brand['icon_192']),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => asset($brand['icon_512']),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
    ], 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
});

Route::get('/', [TagihanController::class, 'home'])->name('home');

Route::post('/logout', [TagihanController::class, 'logout'])->name('logout');

Route::get('/dua', function () {
    return view('index');
});


Route::get('/list-tahun-akademik', [TagihanController::class, 'listTahunAkademik']);

Route::post('/cek-tagihan', function (Request $request) {
    try {
        $payload = [
            'va' => TagihanController::normalizeVa($request->input('va')),
            'tahun_akademik' => $request->input('tahun_akademik')
        ];

        $path = 'cek-tagihan';
        if ($request->filled('password')) {
            $payload['password'] = $request->input('password');
            $path = 'cek-tagihan-pw';
        }

        $response = Http::timeout(30)
            ->withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])
            ->post(config('services.tagihan_ws.url').'?path='.$path, $payload);

        \Log::info('WS cek-tagihan response', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json([
            'status' => false,
            'message' => 'Gagal mengecek tagihan'
        ], 500);
    } catch (\Exception $e) {
        \Log::error('Error cek-tagihan', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage()
        ], 500);
    }
})->name('cek-tagihan');

Route::post('/generate-va', [TagihanController::class, 'buatVA'])->name('generate-va');
Route::post('/generate-qris', [TagihanController::class, 'buatQRIS'])->name('generate-qris');
Route::match(['get', 'post'], '/cek-status-pembayaran', [TagihanController::class, 'cekStatusPembayaran'])
    ->name('cek-status-pembayaran');

Route::post('/dua', [TagihanController::class, 'cek'])->name('tagihan.cek');

Route::post('/', [TagihanController::class, 'cek2'])->name('tagihan.cek2');

Route::post('/multi-akun/tambah', [MultiAccountController::class, 'tambah'])->name('multi-akun.tambah');
Route::post('/multi-akun/switch', [MultiAccountController::class, 'switch'])->name('multi-akun.switch');
Route::post('/multi-akun/hapus', [MultiAccountController::class, 'hapus'])->name('multi-akun.hapus');

Route::get('/push/vapid-public-key', [\App\Http\Controllers\WebPushController::class, 'vapidPublicKey'])
    ->name('push.vapid');
Route::post('/push/subscribe', [\App\Http\Controllers\WebPushController::class, 'subscribe'])
    ->name('push.subscribe');
Route::post('/push/unsubscribe', [\App\Http\Controllers\WebPushController::class, 'unsubscribe'])
    ->name('push.unsubscribe');
Route::post('/push/notify-paid', [\App\Http\Controllers\WebPushController::class, 'notifyPaid'])
    ->name('push.notify-paid');

Route::get('/tagihan/view', [TagihanController::class, 'tagihanView'])->name('tagihan.view');

Route::post('/pembayaran/buat-va', [TagihanController::class, 'buatVA'])->name('pembayaran.buatva');

Route::get('/{token}', [TagihanController::class, 'loginByToken'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('tagihan.token-login');
