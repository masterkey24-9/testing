<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default FALSE supaya user yang sudah ada (termasuk semua akun admin) tidak
     * tiba-tiba dipaksa ganti password saat migration ini dijalankan. Kolom ini
     * baru di-set TRUE secara eksplisit saat:
     *   1) akun satker baru dibuat (SatkerController::store), atau
     *   2) admin reset/cetak ulang kredensial semua satker (SatkerController::cetakKredensial)
     * lalu otomatis balik ke FALSE begitu satker berhasil ganti password sendiri.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
