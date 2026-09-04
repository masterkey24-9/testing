<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\IndicatorResultController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SatkerController;
use App\Http\Controllers\SatkerDashboardController;


if (! function_exists('sikoorResolvePeriode')) {
    /**
     * Resolve filter periode (granularitas/tahun/triwulan/semester/periode) dari query
     * string jadi rentang tanggal ($rangeAwal-$rangeAkhir) + label-nya. Dipakai bareng
     * oleh halaman Monitoring IKPA dan kedua halaman cetak (satu satker & semua satker)
     * supaya rumus rentang tanggalnya tidak gampang out-of-sync di 3 tempat berbeda.
     */
    function sikoorResolvePeriode(): array
    {
        $granularitas = in_array(request('granularitas'), ['bulanan', 'triwulan', 'semester', 'tahunan'])
            ? request('granularitas') : 'bulanan';
        $tahunAktif = (int) (request('tahun') ?: now()->year);
        $triwulanAktif = (int) (request('triwulan') ?: ceil(now()->month / 3));
        $semesterAktif = (int) (request('semester') ?: (now()->month <= 6 ? 1 : 2));
        $periodeFilter = request('periode') ?: now()->format('Y-m');
        $periodeAktif = \Carbon\Carbon::createFromFormat('Y-m', $periodeFilter);

        switch ($granularitas) {
            case 'triwulan':
                $bulanAwal = ($triwulanAktif - 1) * 3 + 1;
                $rangeAwal = \Carbon\Carbon::create($tahunAktif, $bulanAwal, 1)->startOfMonth();
                $rangeAkhir = $rangeAwal->copy()->addMonths(2)->endOfMonth();
                $labelPeriodeAktif = "Triwulan {$triwulanAktif} {$tahunAktif}";
                break;
            case 'semester':
                $bulanAwal = $semesterAktif === 1 ? 1 : 7;
                $rangeAwal = \Carbon\Carbon::create($tahunAktif, $bulanAwal, 1)->startOfMonth();
                $rangeAkhir = $rangeAwal->copy()->addMonths(5)->endOfMonth();
                $labelPeriodeAktif = "Semester {$semesterAktif} {$tahunAktif}";
                break;
            case 'tahunan':
                $rangeAwal = \Carbon\Carbon::create($tahunAktif, 1, 1)->startOfYear();
                $rangeAkhir = \Carbon\Carbon::create($tahunAktif, 12, 31)->endOfYear();
                $labelPeriodeAktif = "Tahun {$tahunAktif}";
                break;
            default:
                $rangeAwal = $periodeAktif->copy()->startOfMonth();
                $rangeAkhir = $periodeAktif->copy()->endOfMonth();
                $labelPeriodeAktif = $periodeAktif->translatedFormat('F Y');
        }

        return compact('granularitas', 'tahunAktif', 'triwulanAktif', 'semesterAktif', 'periodeAktif', 'rangeAwal', 'rangeAkhir', 'labelPeriodeAktif');
    }
}

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return view('landing');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'force.password.change'])
    ->name('dashboard');

// Halaman wajib ganti password — SENGAJA cuma pakai middleware 'auth' biasa (BUKAN
// 'force.password.change'), supaya user yang justru sedang diarahkan ke sini tidak
// kena redirect loop.
Route::middleware('auth')->group(function () {
    Route::get('/ganti-password', [ForcePasswordChangeController::class, 'show'])->name('password.force');
    Route::post('/ganti-password', [ForcePasswordChangeController::class, 'update'])->name('password.force.update');
});

    Route::middleware(['auth', 'force.password.change'])->group(function () {

    Route::get('/panduan', function () {
        return view('panduan.index');
    })->name('panduan.index');

    Route::post('/indicator/{indicator_id}/upload', [IndicatorResultController::class, 'store'])->name('indicator.upload');

    Route::middleware('admin')->group(function () {
        Route::get('/indicators', [IndicatorController::class, 'index'])->name('indicators.index');
        Route::post('/indicators', [IndicatorController::class, 'store'])->name('indicators.store');
        Route::post('/indicators/import-pdf', [IndicatorController::class, 'importPdf'])->name('indicators.importPdf');
        Route::get('/indicators/riwayat', [IndicatorController::class, 'riwayat'])->name('indicators.riwayat');
        Route::get('/indicators/riwayat/{batchId}', [IndicatorController::class, 'riwayatDetail'])->name('indicators.riwayat.detail');
        Route::get('/indicators/{id}', [IndicatorController::class, 'show'])->name('indicators.show');
        Route::post('/indicators/{id}/lampiran', [IndicatorController::class, 'updateLampiran'])->name('indicators.updateLampiran');
        Route::post('/indicators-bobot', [IndicatorController::class, 'updateBobot'])->name('indicators.bobot.update');
        Route::post('/indicator-results/{id}/nilai', [IndicatorResultController::class, 'updateStatus'])->name('indicator-results.updateStatus');
        Route::post('/indicators/{id}/laporan', [IndicatorResultController::class, 'storeByAdmin'])->name('indicators.storeLaporan');

        Route::get('/satkers', [SatkerController::class, 'index'])->name('satkers.index');
        Route::post('/satkers', [SatkerController::class, 'store'])->name('satkers.store');
        Route::delete('/satkers/{id}', [SatkerController::class, 'destroy'])->name('satkers.destroy');
        Route::get('/satkers/cetak-kredensial', [SatkerController::class, 'cetakKredensialForm'])->name('satkers.cetakKredensialForm');
        Route::post('/satkers/cetak-kredensial', [SatkerController::class, 'cetakKredensial'])->name('satkers.cetakKredensial');
        Route::get('/satkers/cetak-kredensial/hasil', [SatkerController::class, 'cetakKredensialHasil'])->name('satkers.cetakKredensialHasil');

        
        // ================= MONITORING IKPA (halaman tabel saja) =================
        // Sengaja diringankan: cuma hitung yang dibutuhkan tabel "Monitoring IKPA Terbaru"
        // (filter periode/satker + skor & detail per indikator per satker). Trend, kategori
        // donut, prioritas pembinaan, notifikasi, dan progress tindak lanjut sekarang ada
        // di /dashboard, jadi tidak perlu dihitung ulang di sini.
        Route::get('/monitoring-ikpa', function () {
            $granularitas = in_array(request('granularitas'), ['bulanan', 'triwulan', 'semester', 'tahunan'])
                ? request('granularitas')
                : 'bulanan';

            $periodeFilter = request('periode') ?: now()->format('Y-m');
            $periodeAktif  = \Carbon\Carbon::createFromFormat('Y-m', $periodeFilter);

            $tahunAktif    = (int) (request('tahun') ?: now()->year);
            $triwulanAktif = (int) (request('triwulan') ?: ceil(now()->month / 3));
            $semesterAktif = (int) (request('semester') ?: (now()->month <= 6 ? 1 : 2));

            switch ($granularitas) {
                case 'triwulan':
                    $bulanAwalTriwulan = ($triwulanAktif - 1) * 3 + 1;
                    $rangeAwal  = \Carbon\Carbon::create($tahunAktif, $bulanAwalTriwulan, 1)->startOfMonth();
                    $rangeAkhir = $rangeAwal->copy()->addMonths(2)->endOfMonth();
                    $labelPeriodeAktif = "Triwulan {$triwulanAktif} {$tahunAktif}";
                    break;

                case 'semester':
                    $bulanAwalSemester = $semesterAktif === 1 ? 1 : 7;
                    $rangeAwal  = \Carbon\Carbon::create($tahunAktif, $bulanAwalSemester, 1)->startOfMonth();
                    $rangeAkhir = $rangeAwal->copy()->addMonths(5)->endOfMonth();
                    $labelPeriodeAktif = "Semester {$semesterAktif} {$tahunAktif}";
                    break;

                case 'tahunan':
                    $rangeAwal  = \Carbon\Carbon::create($tahunAktif, 1, 1)->startOfYear();
                    $rangeAkhir = \Carbon\Carbon::create($tahunAktif, 12, 31)->endOfYear();
                    $labelPeriodeAktif = "Tahun {$tahunAktif}";
                    break;

                case 'bulanan':
                default:
                    $rangeAwal  = $periodeAktif->copy()->startOfMonth();
                    $rangeAkhir = $periodeAktif->copy()->endOfMonth();
                    $labelPeriodeAktif = $periodeAktif->translatedFormat('F Y');
                    break;
            }

            $query = \App\Models\Indicator::with(['satker', 'results'])
                ->whereBetween('periode', [$rangeAwal, $rangeAkhir]);

            if (request()->filled('satker_id')) {
                $query->where('satker_id', request('satker_id'));
            }

            $indicators = $query->latest()
                ->get()
                ->map(function ($item) {
                    $item->satker_nama = $item->satker->nama_satker ?? '-';
                    $item->status = $item->results->count() > 0 ? 'terkirim' : 'pending';
                    return $item;
                });

            // Kolom detail per-indikator sekarang dinamis: ikut SEMUA jenis indikator
            // baku, bukan cuma 5. Nilainya adalah poin sumbangan (bobot% x nilai) ke
            // Nilai IKPA, dihitung oleh IkpaScoringService (satu sumber rumus yang
            // sama dipakai juga oleh DashboardController, supaya tidak ada 2 rumus
            // beda yang gampang out-of-sync).
            $judulDetailTabel = \App\Services\IkpaScoringService::jenisIndikator();
            $bobotIndikator = \App\Services\IkpaScoringService::bobotIndikator();

            $satkers = \App\Models\Satker::orderBy('nama_satker')->get();

            $satkerPerformance = $satkers
                ->when(request()->filled('satker_id'), fn ($collection) => $collection->where('id', request('satker_id')))
                ->map(function ($satker) use ($rangeAwal, $rangeAkhir) {
                    $skor = \App\Services\IkpaScoringService::hitungSkorSatker($satker->id, $rangeAwal, $rangeAkhir);

                    $lastResultAt = \App\Models\IndicatorResult::whereHas('indicator', fn ($q) => $q->where('satker_id', $satker->id))->max('created_at');
                    $lastIndicatorAt = \App\Models\Indicator::where('satker_id', $satker->id)->max('created_at');
                    $updateTerakhir = collect([$lastResultAt, $lastIndicatorAt])
                        ->filter()
                        ->map(fn ($d) => \Carbon\Carbon::parse($d))
                        ->sortDesc()
                        ->first();

                    $kategori = \App\Services\IkpaScoringService::kategori($skor['skor']);

                    return (object) [
                        'id' => $satker->id,
                        'nama_satker' => $satker->nama_satker,
                        'total_tugas' => $skor['total_tugas'],
                        'tugas_selesai' => $skor['tugas_selesai'],
                        'nilai' => $skor['skor'],
                        'kategori_label' => $kategori['label'],
                        'kategori_badge' => $kategori['badge'],
                        'detail_indikator' => $skor['poin_indikator'],
                        'update_terakhir' => $updateTerakhir,
                    ];
                })
                ->values();

            return view('admin.monitoring', compact(
                'indicators', 'satkers', 'satkerPerformance',
                'granularitas', 'periodeAktif', 'tahunAktif', 'triwulanAktif', 'semesterAktif', 'labelPeriodeAktif',
                'judulDetailTabel', 'bobotIndikator'
            ));
        })->name('monitoring.ikpa');

       
        Route::get('/monitoring/cetak/{satker}', function (\App\Models\Satker $satker) {
            ['rangeAwal' => $rangeAwal, 'rangeAkhir' => $rangeAkhir, 'labelPeriodeAktif' => $labelPeriodeAktif] = sikoorResolvePeriode();

            $indicatorsSatker = \App\Models\Indicator::with('results')
                ->where('satker_id', $satker->id)
                ->whereBetween('periode', [$rangeAwal, $rangeAkhir])
                ->get();

            $baris = $indicatorsSatker->map(function ($ind) {
                $latest = $ind->results->sortByDesc('created_at')->first();
                return [
                    'judul' => $ind->judul,
                    'status' => $latest->status ?? 'Belum lapor',
                    'nilai' => $latest->nilai ?? null,
                    'catatan' => $latest->catatan_admin ?? null,
                ];
            });

            $rataRata = $baris->pluck('nilai')->filter()->avg();

            // Baris ringkasan Nilai IKPA + kategori, sama persis rumusnya dengan yang
            // dipakai di halaman Monitoring IKPA — cuma untuk 1 satker ini saja.
            $judulDetailTabel = \App\Services\IkpaScoringService::jenisIndikator();
            $bobotIndikator = \App\Services\IkpaScoringService::bobotIndikator();
            $skor = \App\Services\IkpaScoringService::hitungSkorSatker($satker->id, $rangeAwal, $rangeAkhir);
            $kategori = \App\Services\IkpaScoringService::kategori($skor['skor']);

            return view('admin.monitoring-cetak', compact(
                'satker', 'baris', 'rataRata', 'labelPeriodeAktif',
                'skor', 'kategori', 'judulDetailTabel', 'bobotIndikator'
            ));
        })->name('monitoring.cetak');

        Route::get('/monitoring/satker/{satker}/modal', function (\App\Models\Satker $satker) {
            ['rangeAwal' => $rangeAwal, 'rangeAkhir' => $rangeAkhir] = sikoorResolvePeriode();

            $indicatorsSatker = \App\Models\Indicator::with('results')
                ->where('satker_id', $satker->id)
                ->whereBetween('periode', [$rangeAwal, $rangeAkhir])
                ->latest()
                ->get();

            return view('admin._monitoring-satker-modal', compact('satker', 'indicatorsSatker'));
        })->name('monitoring.satkerModal');

        Route::get('/monitoring/cetak-semua', function () {
            ['rangeAwal' => $rangeAwal, 'rangeAkhir' => $rangeAkhir, 'labelPeriodeAktif' => $labelPeriodeAktif] = sikoorResolvePeriode();

            $judulDetailTabel = \App\Services\IkpaScoringService::jenisIndikator();
            $bobotIndikator = \App\Services\IkpaScoringService::bobotIndikator();

            $satkerPerformance = \App\Models\Satker::orderBy('nama_satker')->get()
                ->map(function ($satker) use ($rangeAwal, $rangeAkhir) {
                    $skor = \App\Services\IkpaScoringService::hitungSkorSatker($satker->id, $rangeAwal, $rangeAkhir);
                    $kategori = \App\Services\IkpaScoringService::kategori($skor['skor']);

                    return (object) [
                        'nama_satker' => $satker->nama_satker,
                        'nilai' => $skor['skor'],
                        'kategori_label' => $kategori['label'],
                        'kategori_badge' => $kategori['badge'],
                        'detail_indikator' => $skor['poin_indikator'],
                    ];
                })
                ->sortByDesc(fn ($sp) => $sp->nilai ?? -1)
                ->values();

            return view('admin.monitoring-cetak-semua', compact('satkerPerformance', 'judulDetailTabel', 'bobotIndikator', 'labelPeriodeAktif'));
        })->name('monitoring.cetak.semua');

        Route::get('/peringatan', [\App\Http\Controllers\PeringatanSatkerController::class, 'index'])->name('peringatan.index');
        Route::post('/peringatan', [\App\Http\Controllers\PeringatanSatkerController::class, 'store'])->name('peringatan.store');
        Route::post('/peringatan/{id}/selesai', [\App\Http\Controllers\PeringatanSatkerController::class, 'selesaikan'])->name('peringatan.selesai');
        Route::delete('/peringatan/{id}', [\App\Http\Controllers\PeringatanSatkerController::class, 'destroy'])->name('peringatan.destroy');
    });

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/data', [MessageController::class, 'data'])->name('messages.data');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/broadcast', [MessageController::class, 'broadcastStore'])->name('messages.broadcast');
    Route::post('/messages/email', [MessageController::class, 'sendEmail'])->name('messages.sendEmail');

    // Status online & indikator "sedang mengetik" untuk Live chat (cache-based, polling).
    Route::post('/chat/heartbeat', [MessageController::class, 'heartbeat'])->name('chat.heartbeat');
    Route::post('/chat/typing', [MessageController::class, 'typing'])->name('chat.typing');
    Route::get('/chat/status', [MessageController::class, 'status'])->name('chat.status');
    Route::get('/chat/online-satkers', [MessageController::class, 'onlineSatkers'])->name('chat.onlineSatkers');

    Route::get('/notifications/data', [NotificationController::class, 'data'])->name('notifications.data');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    Route::get('/dashboard-satker', [SatkerDashboardController::class, 'index'])->name('user.dashboard');
    Route::get('/monitoring-saya', [SatkerDashboardController::class, 'monitoring'])->name('user.monitoring');

    Route::get('/inbox', function () {
    // Periode aktif: default bulan berjalan, bisa difilter satker lewat dropdown,
    // supaya alurnya konsisten dengan cara admin memfilter periode di Monitoring IKPA.
    $periodeFilter = request('periode') ?: now()->format('Y-m');
    $periodeAktif = \Carbon\Carbon::createFromFormat('Y-m', $periodeFilter);
    $rangeAwal = $periodeAktif->copy()->startOfMonth();
    $rangeAkhir = $periodeAktif->copy()->endOfMonth();

    $indicators = \App\Models\Indicator::with('results')
        ->where('satker_id', auth()->user()->satker_id)
        ->whereBetween('periode', [$rangeAwal, $rangeAkhir])
        ->latest()
        ->get();

    // Peringatan aktif untuk satker ini (buat running text), dan status terkunci
    // (buat sembunyikan form upload) kalau ada peringatan aktif yang batas waktunya lewat.
    $peringatanAktif = \App\Models\PeringatanSatker::where('satker_id', auth()->user()->satker_id)
        ->aktif()
        ->orderBy('batas_waktu')
        ->get();

    $terkunci = $peringatanAktif->contains(fn ($p) => $p->sudahLewatBatasWaktu());

    return view('user.inbox', compact('indicators', 'peringatanAktif', 'terkunci', 'periodeAktif'));
})->name('user.inbox');

    Route::get('/chat', function () {
        return view('user.chat');
    })->name('user.chat');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';