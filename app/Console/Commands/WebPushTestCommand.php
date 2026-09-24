<?php

namespace App\Console\Commands;

use App\Services\WebPushService;
use Illuminate\Console\Command;

class WebPushTestCommand extends Command
{
    protected $signature = 'webpush:test {nocust? : NIS/userlogin} {--vano=} {--body=Tes notifikasi Fase 2}';

    protected $description = 'Kirim tes Web Push ke subscription yang tersimpan';

    public function handle(WebPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->error('VAPID belum dikonfigurasi di .env');

            return self::FAILURE;
        }

        if (! class_exists(\Minishlink\WebPush\WebPush::class)) {
            $this->error('Package minishlink/web-push belum terpasang. Jalankan: composer install');

            return self::FAILURE;
        }

        $nocust = $this->argument('nocust');
        $vano = $this->option('vano');
        if (! $nocust && ! $vano) {
            $this->error('Isi nocust atau --vano=');

            return self::FAILURE;
        }

        $this->info('Mengirim...');
        $result = $push->notifyPaid($vano ?: null, $nocust ?: null, [
            'title' => 'Tes Web Push',
            'body' => (string) $this->option('body'),
            'tag' => 'webpush-test-'.time(),
        ]);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (($result['sent'] ?? 0) > 0) {
            $this->info('Berhasil. Tutup PWA — notif harus tetap muncul di sistem.');

            return self::SUCCESS;
        }

        $this->warn('Tidak ada yang terkirim. Cek matched/errors di atas.');

        return self::FAILURE;
    }
}
