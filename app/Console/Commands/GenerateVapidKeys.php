<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID key pair for Web Push (Fase 2)';

    public function handle(): int
    {
        $script = base_path('scripts/generate-vapid.cjs');
        if (! is_file($script)) {
            $this->error('scripts/generate-vapid.cjs tidak ditemukan');

            return self::FAILURE;
        }

        $cmd = 'node '.escapeshellarg($script);
        $out = [];
        $code = 0;
        exec($cmd, $out, $code);
        if ($code !== 0 || empty($out)) {
            $this->error('Gagal generate. Pastikan Node.js terpasang, lalu jalankan: node scripts/generate-vapid.cjs');

            return self::FAILURE;
        }

        $this->info('Tambahkan ke .env:');
        foreach ($out as $line) {
            $this->line($line);
        }
        $this->newLine();
        $this->comment('Lalu di islamic_center (env / demoInstallment):');
        $this->line('WEBPUSH_NOTIFY_URL = https://DOMAIN-LARAVEL-ANDA/push/notify-paid');
        $this->line('WEBPUSH_NOTIFY_SECRET = sama dengan WEBPUSH_NOTIFY_SECRET di .env');

        return self::SUCCESS;
    }
}
