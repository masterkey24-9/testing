<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Halaman utama admin: kartu ringkasan, trend nilai IKPA, kategori satker,
     * daftar satker prioritas pembinaan, peringkat satker terbaik, panel
     * indikator IKPA, early warning, dan progress tindak lanjut.
     *
     * Dipindah dari closure di routes/web.php (sebelumnya ~400 baris nyangkut
     * langsung di file routing) supaya logic bisnisnya lebih gampang dibaca,
     * ditest, dan di-maintain terpisah dari definisi route.
     */
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            return redirect()->route('user.inbox');
        }

        // ================= PERIODE FILTER (multi-granularitas) =================
        // Granularitas: bulanan (default) | triwulan | semester | tahunan
        $granularitas = in_array(request('granularitas'), ['bulanan', 'triwulan', 'semester', 'tahunan'])
            ? request('granularitas')
            : 'bulanan';

        $periodeFilter = request('periode') ?: now()->format('Y-m');
        $periodeAktif  = \Carbon\Carbon::createFromFormat('Y-m', $periodeFilter);

        $tahunAktif     = (int) (request('tahun') ?: now()->year);
        $triwulanAktif  = (int) (request('triwulan') ?: ceil(now()->month / 3));
        $semesterAktif  = (int) (request('semester') ?: (now()->month <= 6 ? 1 : 2));

        // Tentukan rentang tanggal (awal-akhir) periode aktif sesuai granularitas yang dipilih.
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

        // atau 12 Bulan Terakhir. Untuk mode triwulan/semester/tahunan, jumlah titik tren
        // tetap mengikuti default masing-masing (kurang relevan dipotong ke satuan bulan).
        $trendRange = (int) request('trend_range', 6);
        if (! in_array($trendRange, [6, 12], true)) {
            $trendRange = 6;
        }

        $jumlahTitikTren = $granularitas === 'bulanan'
            ? $trendRange
            // tahunan: tidak dipakai lagi di sini — 2 titiknya (Jan-Jun & Jul-Des) dihitung
            // langsung di blok pembangunan $trendBulanan di bawah, anchor dari Januari.
            : ['triwulan' => 4, 'semester' => 4, 'tahunan' => 2][$granularitas];
        $panjangBulanPeriode = ['bulanan' => 1, 'triwulan' => 3, 'semester' => 6, 'tahunan' => 12][$granularitas];

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

        // Kolom detail per-indikator di tabel "Monitoring IKPA Terbaru" sekarang dinamis:
        // ikut SEMUA jenis indikator baku (config('sikoor.jenis_indikator')), bukan cuma 5.
        // Nilainya juga sudah berupa poin sumbangan ke Nilai IKPA (bobot% x nilai), dihitung
        // langsung oleh IkpaScoringService::hitungSkorSatker() di bawah — bukan nilai mentah lagi.
        $judulDetailTabel = \App\Services\IkpaScoringService::jenisIndikator();
        $bobotIndikator = \App\Services\IkpaScoringService::bobotIndikator();

        $satkers = \App\Models\Satker::orderBy('nama_satker')->get();

        $kategoriIkpa = fn (?float $nilai) => \App\Services\IkpaScoringService::kategori($nilai);

        $hitungSkorSatker = fn (int $satkerId, \Carbon\Carbon $awal, \Carbon\Carbon $akhir)
            => \App\Services\IkpaScoringService::hitungSkorSatker($satkerId, $awal, $akhir);

        $rangeAwalSebelumnya = $rangeAwal->copy()->subMonths($panjangBulanPeriode);
        $rangeAkhirSebelumnya = $rangeAwal->copy()->subDay()->endOfDay();

        $prioritasPembinaan = function (string $kategoriLabel, ?float $skorAkhir, ?float $skorSebelumnya) {
            if (is_null($skorAkhir)) {
                return ['label' => 'Tinggi', 'badge' => 'bg-red-50 text-red-600'];
            }

            $turunSignifikan = ! is_null($skorSebelumnya) && ($skorSebelumnya - $skorAkhir) >= 5;

            $level = match ($kategoriLabel) {
                'Merah' => 'Tinggi',
                'Kuning' => $turunSignifikan ? 'Tinggi' : 'Sedang',
                'Hijau' => $turunSignifikan ? 'Sedang' : 'Rendah',
                default => 'Sedang',
            };

            $badge = match ($level) {
                'Tinggi' => 'bg-red-50 text-red-600',
                'Sedang' => 'bg-amber-50 text-amber-600',
                default => 'bg-emerald-50 text-emerald-600',
            };

            return ['label' => $level, 'badge' => $badge];
        };

        $satkerPerformance = $satkers
            ->when(request()->filled('satker_id'), fn ($collection) => $collection->where('id', request('satker_id')))
            ->map(function ($satker) use ($rangeAwal, $rangeAkhir, $rangeAwalSebelumnya, $rangeAkhirSebelumnya, $kategoriIkpa, $hitungSkorSatker, $prioritasPembinaan) {
                $skorSekarang = $hitungSkorSatker($satker->id, $rangeAwal, $rangeAkhir);
                $skorAkhir = $skorSekarang['skor'];
                $totalTugas = $skorSekarang['total_tugas'];
                $tugasSelesai = $skorSekarang['tugas_selesai'];

                $skorPeriodeLalu = $hitungSkorSatker($satker->id, $rangeAwalSebelumnya, $rangeAkhirSebelumnya)['skor'];

                $status = 'Belum ada tugas';
                if (!is_null($skorAkhir)) {
                    $ambangBaik = config('sikoor.ambang_baik', 85);
                    $ambangCukup = config('sikoor.ambang_cukup', 60);
                    $status = $skorAkhir >= $ambangBaik ? 'Baik' : ($skorAkhir >= $ambangCukup ? 'Cukup' : 'Perlu Perhatian');
                }

                // Aktivitas terakhir satker ini: laporan terbaru, atau kalau belum ada, tugas terbaru
                $lastResultAt = \App\Models\IndicatorResult::whereHas('indicator', fn ($q) => $q->where('satker_id', $satker->id))->max('created_at');
                $lastIndicatorAt = \App\Models\Indicator::where('satker_id', $satker->id)->max('created_at');
                $updateTerakhir = collect([$lastResultAt, $lastIndicatorAt])
                    ->filter()
                    ->map(fn ($d) => \Carbon\Carbon::parse($d))
                    ->sortDesc()
                    ->first();

                $kategori = $kategoriIkpa($skorAkhir);
                $prioritas = $prioritasPembinaan($kategori['label'], $skorAkhir, $skorPeriodeLalu);

                // Label tren dibanding periode sebelumnya, buat indikator kecil di tabel prioritas
                $trendLabel = 'Baru';
                if (! is_null($skorAkhir) && ! is_null($skorPeriodeLalu)) {
                    $selisih = round($skorAkhir - $skorPeriodeLalu, 1);
                    $trendLabel = $selisih > 0 ? "Naik {$selisih}" : ($selisih < 0 ? 'Turun ' . abs($selisih) : 'Tetap');
                }

                return (object) [
                    'id' => $satker->id,
                    'nama_satker' => $satker->nama_satker,
                    'total_tugas' => $totalTugas,
                    'tugas_selesai' => $tugasSelesai,
                    'nilai' => $skorAkhir,
                    'status' => $status,
                    'kategori_label' => $kategori['label'],
                    'kategori_badge' => $kategori['badge'],
                    'prioritas_label' => $prioritas['label'],
                    'prioritas_badge' => $prioritas['badge'],
                    'trend_label' => $trendLabel,
                    'detail_indikator' => $skorSekarang['poin_indikator'],
                    'update_terakhir' => $updateTerakhir,
                ];
            })
            ->values();

        $totalSatker = $satkerPerformance->count();
        $rataRataKinerja = $satkerPerformance->whereNotNull('nilai')->avg('nilai');

        $urutanPrioritas = ['Tinggi' => 0, 'Sedang' => 1, 'Rendah' => 2];
        $satkerPrioritas = $satkerPerformance
            ->sort(function ($a, $b) use ($urutanPrioritas) {
                $rankA = $urutanPrioritas[$a->prioritas_label] ?? 3;
                $rankB = $urutanPrioritas[$b->prioritas_label] ?? 3;
                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }
                return ($a->nilai ?? -1) <=> ($b->nilai ?? -1);
            })
            ->values()
            ->take(8);

        // Leaderboard "Satker Nilai Tertinggi" — HANYA satker berkategori Hijau
        // (Nilai IKPA >= ambang_hijau, default 95), diurutkan dari nilai tertinggi.
        $satkerTerbaik = $satkerPerformance
            ->filter(fn ($sp) => $sp->kategori_label === 'Hijau')
            ->sortByDesc('nilai')
            ->take(5)
            ->values();

        $labelPeriode = function (\Carbon\Carbon $awal) use ($granularitas) {
            return match ($granularitas) {
                'triwulan' => 'TW' . ceil($awal->month / 3) . ' ' . $awal->year,
                'semester' => 'S' . ($awal->month <= 6 ? 1 : 2) . ' ' . $awal->year,
                'tahunan'  => (string) $awal->year,
                default    => $awal->translatedFormat('M Y'),
            };
        };

        $trendBulanan = collect();

        if ($granularitas === 'tahunan') {
            // Mode Tahunan: tren dipecah 2 titik DALAM TAHUN YANG SAMA, mulai dari Januari
            // — bukan tren antar-tahun. Titik 1 = 6 bulan dari Januari (Jan-Jun), titik 2 =
            // 6 bulan setelahnya (Jul-Des).
            $titikTahunan = [
                ['awal' => \Carbon\Carbon::create($tahunAktif, 1, 1)->startOfDay(), 'akhir' => \Carbon\Carbon::create($tahunAktif, 6, 30)->endOfDay(), 'label' => "Jan-Jun {$tahunAktif}"],
                ['awal' => \Carbon\Carbon::create($tahunAktif, 7, 1)->startOfDay(), 'akhir' => \Carbon\Carbon::create($tahunAktif, 12, 31)->endOfDay(), 'label' => "Jul-Des {$tahunAktif}"],
            ];

            foreach ($titikTahunan as $titik) {
                $rataPeriodeIni = \App\Models\IndicatorResult::whereNotNull('indicator_results.nilai')
                    ->join('indicators', 'indicator_results.indicator_id', '=', 'indicators.id')
                    ->whereBetween('indicators.periode', [$titik['awal'], $titik['akhir']])
                    ->when(request()->filled('satker_id'), fn ($q) => $q->where('indicators.satker_id', request('satker_id')))
                    ->avg('indicator_results.nilai');

                $trendBulanan->push([
                    'bulan' => $titik['label'],
                    'nilai' => $rataPeriodeIni ? round($rataPeriodeIni, 2) : 0,
                ]);
            }
        } else {
            for ($i = $jumlahTitikTren - 1; $i >= 0; $i--) {
                $awalPeriode  = $rangeAwal->copy()->subMonths($panjangBulanPeriode * $i);
                $akhirPeriode = $awalPeriode->copy()->addMonths($panjangBulanPeriode)->subDay()->endOfDay();

                $rataPeriodeIni = \App\Models\IndicatorResult::whereNotNull('indicator_results.nilai')
                    ->join('indicators', 'indicator_results.indicator_id', '=', 'indicators.id')
                    ->whereBetween('indicators.periode', [$awalPeriode, $akhirPeriode])
                    ->when(request()->filled('satker_id'), fn ($q) => $q->where('indicators.satker_id', request('satker_id')))
                    ->avg('indicator_results.nilai');

                $trendBulanan->push([
                    'bulan' => $labelPeriode($awalPeriode),
                    'nilai' => $rataPeriodeIni ? round($rataPeriodeIni, 2) : 0,
                ]);
            }
        }

        $ikpaPeriodeIni = $trendBulanan->last()['nilai'] ?? 0;
        $ikpaPeriodeSebelumnya = $trendBulanan->count() > 1 ? $trendBulanan[$trendBulanan->count() - 2]['nilai'] : null;
        $selisihBulanLalu = ! is_null($ikpaPeriodeSebelumnya) ? round($ikpaPeriodeIni - $ikpaPeriodeSebelumnya, 2) : null;

        $totalHijau = $satkerPerformance->where('kategori_label', 'Hijau')->count();
        $totalKuning = $satkerPerformance->where('kategori_label', 'Kuning')->count();
        $totalMerah = $satkerPerformance->where('kategori_label', 'Merah')->count();

        $totalPerluPerhatian = $totalKuning + $totalMerah;

        $persenHijau = $totalSatker > 0 ? round($totalHijau / $totalSatker * 100, 2) : 0;
        $persenKuning = $totalSatker > 0 ? round($totalKuning / $totalSatker * 100, 2) : 0;
        $persenMerah = $totalSatker > 0 ? round($totalMerah / $totalSatker * 100, 2) : 0;
        $persenPerluPerhatian = $totalSatker > 0 ? round($totalPerluPerhatian / $totalSatker * 100, 2) : 0;

        $urutanIndikatorBaku = [
            'Revisi DIPA', 'Deviasi Halaman III DIPA', 'Penyerapan Anggaran', 'Belanja Kontraktual',
            'Penyelesaian Tagihan', 'Pengelolaan UP/TUP', 'Dispensasi SPM', 'Retur SP2D', 'Capaian Output',
        ];

        $trafficLightIndikator = fn (?float $nilai) => \App\Services\IkpaScoringService::trafficLight($nilai);

        $judulIndikatorAktif = collect(config('sikoor.jenis_indikator', []));

        $rataPerJudul = \App\Models\IndicatorResult::whereNotNull('indicator_results.nilai')
            ->join('indicators', 'indicator_results.indicator_id', '=', 'indicators.id')
            ->when(request()->filled('satker_id'), fn ($q) => $q->where('indicators.satker_id', request('satker_id')))
            ->whereBetween('indicators.periode', [$rangeAwal, $rangeAkhir])
            ->select('indicators.judul', DB::raw('AVG(indicator_results.nilai) as rata'))
            ->groupBy('indicators.judul')
            ->pluck('rata', 'judul');

        $nilaiPerIndikator = $judulIndikatorAktif
            ->map(function ($judul) use ($rataPerJudul, $trafficLightIndikator) {
                $rata = isset($rataPerJudul[$judul]) ? round((float) $rataPerJudul[$judul], 2) : null;
                $tl = $trafficLightIndikator($rata);

                return [
                    'judul' => $judul,
                    'rata' => $rata,
                    'warna' => $tl['warna'],
                    'kelas_bar' => $tl['bar'],
                    'kelas_teks' => $tl['teks'],
                    'kelas_badge' => $tl['badge'],
                ];
            })
            ->sortBy(function ($item) use ($urutanIndikatorBaku) {
                $posisi = array_search($item['judul'], $urutanIndikatorBaku, true);
                return $posisi !== false ? sprintf('0-%02d', $posisi) : '1-' . $item['judul'];
            })
            ->values();

        if (! request()->filled('satker_id')) {
            \App\Http\Controllers\NotificationController::generateNotifikasiIkpa(
                $satkerPerformance, $nilaiPerIndikator, $indicators, $rangeAkhir, $labelPeriodeAktif
            );
        }

        // Early Warning untuk admin yang sedang login — hanya notifikasi otomatis
        // hasil deteksi kondisi IKPA (bukan chat/dokumen biasa).
        $tipeEarlyWarning = ['penurunan_ikpa', 'deviasi_anggaran', 'keterlambatan_tagihan', 'batas_tindak_lanjut'];
        $earlyWarnings = \App\Models\Notification::where('user_id', auth()->id())
            ->whereIn('type', $tipeEarlyWarning)
            ->latest()
            ->take(6)
            ->get();

        // Progress tindak lanjut: status laporan tiap tugas (indicator) sesuai filter satker/periode saat ini
        $tindakLanjutSelesai = 0;
        $tindakLanjutProses = 0;
        $tindakLanjutBelum = 0;
        foreach ($indicators as $ind) {
            $latestResult = $ind->results->sortByDesc('created_at')->first();
            if (! $latestResult) {
                $tindakLanjutBelum++;
            } elseif ($latestResult->status === 'diterima') {
                $tindakLanjutSelesai++;
            } else {
                // 'dikirim' (menunggu dinilai admin) atau 'direvisi' (menunggu satker perbaiki)
                $tindakLanjutProses++;
            }
        }
        $totalTindakLanjut = $indicators->count();

        return view('admin.dashboard', compact(
            'indicators', 'satkers', 'satkerPerformance',
            'granularitas', 'periodeAktif', 'tahunAktif', 'triwulanAktif', 'semesterAktif', 'labelPeriodeAktif', 'trendRange',
            'totalSatker', 'rataRataKinerja', 'selisihBulanLalu', 'trendBulanan',
            'totalHijau', 'totalKuning', 'totalMerah', 'totalPerluPerhatian',
            'persenHijau', 'persenKuning', 'persenMerah', 'persenPerluPerhatian',
            'satkerPrioritas', 'satkerTerbaik', 'nilaiPerIndikator', 'earlyWarnings',
            'tindakLanjutSelesai', 'tindakLanjutProses', 'tindakLanjutBelum', 'totalTindakLanjut',
            'judulDetailTabel', 'bobotIndikator'
        ));
    }
}