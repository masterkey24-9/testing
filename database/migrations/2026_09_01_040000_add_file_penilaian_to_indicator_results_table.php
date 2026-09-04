<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('indicator_results', 'file_penilaian')) {
            Schema::table('indicator_results', function (Blueprint $table) {
                // File hasil penilaian yang diunggah ADMIN (beda dari file_pdf/file_excel
                // yang itu punya SATKER, laporan yang mereka kirim). Ini dokumen resmi
                // penilaian admin — pengganti input nilai angka manual.
                $table->string('file_penilaian')->nullable()->after('tindak_lanjut');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('indicator_results', 'file_penilaian')) {
            Schema::table('indicator_results', function (Blueprint $table) {
                $table->dropColumn('file_penilaian');
            });
        }
    }
};
