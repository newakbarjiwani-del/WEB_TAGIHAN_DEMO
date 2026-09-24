<?php

namespace App\Http\Controllers;

use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebPushController extends Controller
{
    public function vapidPublicKey(WebPushService $push)
    {
        if (! $push->isConfigured()) {
            return response()->json([
                'status' => false,
                'message' => 'Web Push belum dikonfigurasi',
            ], 503);
        }

        return response()->json([
            'status' => true,
            'publicKey' => $push->publicKey(),
        ]);
    }

    public function subscribe(Request $request, WebPushService $push)
    {
        if (! $push->isConfigured()) {
            return response()->json(['status' => false, 'message' => 'Web Push nonaktif'], 503);
        }

        $endpoint = (string) $request->input('endpoint', '');
        if ($endpoint === '') {
            return response()->json(['status' => false, 'message' => 'endpoint wajib'], 422);
        }

        $sess = session('tagihan');
        $nocust = TagihanController::normalizeVa(
            $request->input('nocust')
            ?: ($sess['active_no_cust'] ?? '')
        );
        $vano = (string) (
            $request->input('vano')
            ?: ($sess['va_display'] ?? '')
            ?: ($nocust !== '' ? TagihanController::formatNova($nocust) : '')
        );

        try {
            $sub = $push->subscribe($request->all(), $nocust !== '' ? $nocust : null, $vano !== '' && $vano !== '-' ? $vano : null, $request->userAgent());
        } catch (\Throwable $e) {
            Log::warning('push subscribe failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'Gagal menyimpan subscription'], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notifikasi push aktif',
            'id' => $sub->id,
        ]);
    }

    public function unsubscribe(Request $request, WebPushService $push)
    {
        $endpoint = (string) $request->input('endpoint', '');
        $n = $push->unsubscribe($endpoint);

        return response()->json([
            'status' => true,
            'removed' => $n,
        ]);
    }

    /**
     * Dipanggil dari islamic_center pushNotif setelah QRIS marked paid.
     * Auth: header X-WebPush-Secret atau query/body secret.
     */
    public function notifyPaid(Request $request, WebPushService $push)
    {
        $secret = (string) config('services.webpush.notify_secret');
        $given = (string) (
            $request->header('X-WebPush-Secret')
            ?: $request->input('secret', '')
        );

        if ($secret === '' || ! hash_equals($secret, $given)) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $vano = (string) $request->input('vano', '');
        $nocust = TagihanController::normalizeVa($request->input('nocust') ?: $vano);
        $amount = $request->input('amount');
        $qrisId = $request->input('qris_id');
        $trxId = $request->input('transaction_id');

        $result = $push->notifyPaid($vano !== '' ? $vano : null, $nocust !== '' ? $nocust : null, [
            'amount' => $amount,
            'qris_id' => $qrisId,
            'transaction_id' => $trxId,
            'title' => $request->input('title', 'Pembayaran berhasil'),
            'body' => $request->input('body'),
        ]);

        Log::info('webpush notifyPaid', [
            'vano' => $vano,
            'nocust' => $nocust,
            'result' => $result,
        ]);

        return response()->json([
            'status' => true,
            'result' => $result,
        ]);
    }
}
