<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        $public = (string) config('services.webpush.public_key');
        $private = (string) config('services.webpush.private_key');

        return $public !== '' && $private !== '';
    }

    public function publicKey(): string
    {
        return (string) config('services.webpush.public_key');
    }

    /**
     * @param  array{endpoint:string,keys?:array,contentEncoding?:string}  $payload
     */
    public function subscribe(array $payload, ?string $nocust, ?string $vano, ?string $userAgent = null): PushSubscription
    {
        $endpoint = (string) ($payload['endpoint'] ?? '');
        if ($endpoint === '') {
            throw new \InvalidArgumentException('endpoint required');
        }

        $keys = $payload['keys'] ?? [];
        $p256dh = (string) ($keys['p256dh'] ?? $payload['public_key'] ?? '');
        $auth = (string) ($keys['auth'] ?? $payload['auth_token'] ?? '');
        $encoding = (string) ($payload['contentEncoding'] ?? $payload['content_encoding'] ?? 'aes128gcm');
        if (! in_array($encoding, ['aesgcm', 'aes128gcm'], true)) {
            $encoding = 'aes128gcm';
        }
        $hash = hash('sha256', $endpoint);

        return PushSubscription::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'endpoint' => $endpoint,
                'public_key' => $p256dh !== '' ? $p256dh : null,
                'auth_token' => $auth !== '' ? $auth : null,
                'content_encoding' => $encoding,
                'nocust' => $nocust !== null && $nocust !== '' ? $nocust : null,
                'vano' => $vano !== null && $vano !== '' ? $vano : null,
                'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
                'last_used_at' => now(),
            ]
        );
    }

    public function unsubscribe(string $endpoint): int
    {
        if ($endpoint === '') {
            return 0;
        }

        return PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }

    /**
     * Kirim notifikasi ke semua subscription yang cocok dengan vano/nocust.
     *
     * @return array{sent:int,failed:int,skipped:int}
     */
    public function notifyPaid(?string $vano, ?string $nocust, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $query = PushSubscription::query();
        $query->where(function ($q) use ($vano, $nocust) {
            $has = false;
            if ($vano) {
                $q->orWhere('vano', $vano);
                // match tanpa prefix bank
                $digits = preg_replace('/\D+/', '', $vano);
                if ($digits && strlen($digits) > 6) {
                    $q->orWhere('vano', $digits)
                        ->orWhere('nocust', ltrim(preg_replace('/^(751000|757777|797766)/', '', $digits), '0'));
                }
                $has = true;
            }
            if ($nocust) {
                $q->orWhere('nocust', $nocust);
                $q->orWhere('vano', 'like', '%'.$nocust);
                $has = true;
            }
            if (! $has) {
                $q->whereRaw('1=0');
            }
        });

        $subs = $query->get();
        if ($subs->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'matched' => 0];
        }

        $amount = isset($data['amount']) ? (float) $data['amount'] : null;
        $amountText = $amount !== null
            ? 'Rp '.number_format($amount, 0, ',', '.')
            : null;

        $title = (string) ($data['title'] ?? 'Pembayaran berhasil');
        $body = (string) ($data['body'] ?? (
            $amountText
                ? "Top up {$amountText} sudah masuk. Saldo VA diperbarui."
                : 'Pembayaran QRIS berhasil. Saldo VA diperbarui.'
        ));

        $iconPath = '/'.ltrim((string) config('brand.icon_192', 'icons/icon-192.png'), '/');
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $iconPath,
            'badge' => $iconPath,
            'tag' => $data['tag'] ?? ('qris-'.($data['qris_id'] ?? $data['transaction_id'] ?? time())),
            'url' => $data['url'] ?? '/',
            'data' => [
                'qris_id' => $data['qris_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'vano' => $vano,
                'amount' => $amount,
                'url' => $data['url'] ?? '/',
            ],
        ], JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('services.webpush.subject', 'mailto:admin@example.com'),
                    'publicKey' => $this->publicKey(),
                    'privateKey' => (string) config('services.webpush.private_key'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('webpush init failed', ['error' => $e->getMessage()]);

            return [
                'sent' => 0,
                'failed' => $subs->count(),
                'skipped' => 0,
                'matched' => $subs->count(),
                'error' => $e->getMessage(),
            ];
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($subs as $sub) {
            if (! $sub->endpoint || ! $sub->public_key || ! $sub->auth_token) {
                $failed++;
                $errors[] = 'sub#'.$sub->id.' incomplete keys';
                continue;
            }

            // Chrome modern = aes128gcm; coba encoding tersimpan, fallback aes128gcm
            $encodings = array_values(array_unique(array_filter([
                $sub->content_encoding ?: null,
                'aes128gcm',
                'aesgcm',
            ])));

            $queued = false;
            foreach ($encodings as $enc) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'publicKey' => $sub->public_key,
                        'authToken' => $sub->auth_token,
                        'contentEncoding' => $enc,
                    ]);
                    $webPush->queueNotification($subscription, $payload, [
                        'TTL' => 3600,
                        'urgency' => 'high',
                        'topic' => 'qris-paid',
                    ]);
                    if ($enc !== $sub->content_encoding) {
                        $sub->content_encoding = $enc;
                        $sub->save();
                    }
                    $queued = true;
                    break;
                } catch (\Throwable $e) {
                    $errors[] = 'sub#'.$sub->id.' enc='.$enc.': '.$e->getMessage();
                }
            }
            if (! $queued) {
                $failed++;
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                PushSubscription::where('endpoint', $report->getEndpoint())
                    ->update(['last_used_at' => now()]);
            } else {
                $failed++;
                $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                if (in_array($code, [404, 410], true)) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
                $errors[] = ($report->getReason() ?: 'send fail').' code='.$code;
                Log::info('webpush send failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                    'code' => $code,
                ]);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => 0,
            'matched' => $subs->count(),
            'errors' => array_slice($errors, 0, 5),
        ];
    }
}
