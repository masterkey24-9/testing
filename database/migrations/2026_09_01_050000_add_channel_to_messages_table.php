<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'channel')) {
                // 'chat' = pesan Live Chat biasa (default), 'email' = dikirim juga lewat email
                // ke alamat email login penerima.
                $table->string('channel')->default('chat')->after('pesan');
            }
            if (! Schema::hasColumn('messages', 'subjek')) {
                // Cuma dipakai kalau channel = 'email'. Live Chat biasa tidak butuh subjek.
                $table->string('subjek')->nullable()->after('channel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'subjek')) {
                $table->dropColumn('subjek');
            }
            if (Schema::hasColumn('messages', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};
