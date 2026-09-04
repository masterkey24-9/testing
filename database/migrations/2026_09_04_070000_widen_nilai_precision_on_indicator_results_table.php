<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kolom `nilai` tadinya unsignedTinyInteger (cuma bilangan bulat 0-100), cukup untuk
     * penilaian manual admin (100/0). Tapi nilai dari PDF resmi DJPb berupa desimal
     * (misal 91,02 / 19,74), jadi perlu DECIMAL(5,2) supaya tidak dibulatkan paksa saat
     * diimpor otomatis dari PDF.
     *
     * Pakai raw SQL (bukan ->unsignedDecimal()->change()) supaya tidak perlu tambah
     * dependency doctrine/dbal cuma untuk migration ini (ikut pola migration file_excel).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE indicator_results MODIFY nilai DECIMAL(5,2) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE indicator_results MODIFY nilai TINYINT UNSIGNED NULL');
    }
};
