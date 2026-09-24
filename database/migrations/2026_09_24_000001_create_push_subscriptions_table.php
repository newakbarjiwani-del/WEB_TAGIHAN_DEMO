<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tagihan';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);

        // Bersihkan sisa migrate gagal (unique endpoint terlalu panjang di MySQL lama)
        $conn->dropIfExists('push_subscriptions');

        $conn->create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            // endpoint URL push bisa > 191 chars; unique pakai hash agar lolos batas 767 bytes
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key', 255)->nullable();
            $table->string('auth_token', 255)->nullable();
            $table->string('content_encoding', 32)->default('aes128gcm');
            $table->string('nocust', 50)->nullable()->index();
            $table->string('vano', 80)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('push_subscriptions');
    }
};
