<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_account_groups', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('multi_account_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('multi_account_groups')
                ->cascadeOnDelete();
            $table->string('no_cust', 50)->unique();
            $table->string('va_display', 80)->nullable();
            $table->string('nama', 150)->nullable();
            $table->string('kelas', 100)->nullable();
            $table->string('jenjang', 50)->nullable();
            $table->string('last_academic_year', 50)->nullable();
            $table->timestamps();

            $table->index('group_id', 'idx_members_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_account_members');
        Schema::dropIfExists('multi_account_groups');
    }
};
