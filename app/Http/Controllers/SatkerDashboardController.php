<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\PeringatanSatker;
use App\Models\Satker;
use Illuminate\Support\Facades\DB;

class SatkerDashboardController extends Controller
{
    /**
     * Kategori + warna Nilai IKPA satker — didelegasikan ke
     * App\Services\IkpaScoringService (satu-satunya sumber skema kategori di
     * seluruh aplikasi: Hijau >= ambang_hijau/95, Kuning >= ambang_kuning/70,
     * Merah < ambang_kuning) supaya SAMA PERSIS dengan warna yang dipakai
     * admin. Sebelumnya di sini ada skema 4-tingkat terpisah (Sangat
     * Baik/Baik/Cukup/Kurang, warna Biru untuk 2 tingkat teratas) yang bikin
     * kategori "Hijau" di sisi admin tampil sebagai "Biru" di sisi satker
     * untuk nilai yang sama persis — sudah disatukan di sini.
     */
    private function kategoriDanWarna(?float $nilai): array
    {
        $label = \App\Services\IkpaScoringService::kategori($nilai)['label'];
        $tl = \App\Services\IkpaScoringService::trafficLight($nilai);

        return [
            'label' => $label,
            'warna' => $tl['warna'],
            'kelas' => $tl['badge'],
            'kelas_bar' => $tl['bar'],
        ];
    }

    /**
     * Nilai IKPA satu satker untuk satu rentang tanggal — delegasi ke
     * App\Services\IkpaScoringService (satu-satunya sumber rumus IKPA di seluruh
     * aplikasi) supaya nilai yang tampil di dashboard satker SAMA PERSIS dengan
     * yang dihitung admin di DashboardController & /monitoring-ikpa. Sebelumnya
     * di sini masih pakai helper terpisah (HitungSkorSatker) yang rumusnya sudah
     * beda (rata-rata vs nilai terbaru, bobot dinormalisasi vs langsung persen) —
     * dua rumus itu bisa menghasilkan Nilai IKPA yang berbeda untuk satker yang
     * sama, sudah disatukan di sini.
     */
    private function hitungSkor(int $satkerId, \Carbon\Carbon $awal, \Carbon\Carbon $akhir): ?float
    {
        return \App\Services\IkpaScoringService::hitungSkorSatker($satkerId, $awal, $akhir)['skor'];
    }

    /**
     * Tren nilai IKPA satker ini untuk TAHUN BERJALAN, mulai dari Januari — dipecah
     * 2 titik: 6 bulan dari Januari (Jan-Jun) dan 6 bulan setelahnya (Jul-Des).
     * Konsisten dengan tren mode "Tahunan" di sisi admin (DashboardController).
     */
    private function trendSatkerTahunIni(int $satkerId): \Illuminate\Support\Collection
    {
        $tahun = now()->year;

        $titik = [
            ['awal' => \Carbon\Carbon::create($tahun, 1, 1)->startOfDay(), 'akhir' => \Carbon\Carbon::create($tahun, 6, 30)->endOfDay(), 'label' => "Jan-Jun {$tahun}"],
            ['awal' => \Carbon\Carbon::create($tahun, 7, 1)->startOfDay(), 'akhir' => \Carbon\Carbon::create($tahun, 12, 31)->endOfDay(), 'label' => "Jul-Des {$tahun}"],
        ];

        return collect($titik)->map(function ($t) use ($satkerId) {
            return [
                'bulan' => $t['label'],
                'nilai' => $this->hitungSkor($satkerId, $t['awal'], $t['akhir']) ?? 0,
            ];
        });
    }

    /**
     * Dashboard Satker: performa satker sendiri bulan ini, status kategori
     * (Hijau/Kuning/Merah — sama dengan sisi admin), peringatan aktif (kalau ada, otomatis mengambil dari
     * sistem Peringatan Satker & running text yang sudah ada di halaman inbox),
     * dan trend 6 bulan — SEMUA data cuma punya satker yang sedang login, bukan
     * gabungan semua satker seperti punya admin.
     */
    public function index()
    {
        $satkerId = auth()->user()->satker_id;

        if (! $satkerId) {
            return redirect()->route('user.inbox')->with('error', 'Akun ini belum terhubung ke Satker manapun.');
        }

        $satker = Satker::findOrFail($satkerId);

        $awal = now()->startOfMonth();
        $akhir = now()->endOfMonth();

        $skorSaya = $this->hitungSkor($satkerId, $awal, $akhir);
        $kategoriSaya = $this->kategoriDanWarna($skorSaya);

        $bulanLalu = now()->copy()->subMonthNoOverflow();
        $skorBulanLalu = $this->hitungSkor($satkerId, $bulanLalu->copy()->startOfMonth(), $bulanLalu->copy()->endOfMonth());
        $selisihBulanLalu = (! is_null($skorSaya) && ! is_null($skorBulanLalu)) ? round($skorSaya - $skorBulanLalu, 2) : null;

        // Peringatan aktif untuk satker ini — otomatis muncul di sini kalau admin
        // sudah mengirim early warning (sistem yang sama dengan running text di
        // halaman "Dokumen masuk"), tidak ada logic terpisah yang perlu dibuat lagi.
        $peringatanAktif = PeringatanSatker::where('satker_id', $satkerId)
            ->aktif()
            ->orderBy('batas_waktu')
            ->get();

        // Trend tahun berjalan, data punya satker ini saja — mulai dari Januari.
        $trendSaya = $this->trendSatkerTahunIni($satkerId);

        $totalTugasBulanIni = Indicator::where('satker_id', $satkerId)->whereBetween('periode', [$awal, $akhir])->count();
        $tugasSelesaiBulanIni = Indicator::where('satker_id', $satkerId)
            ->whereBetween('periode', [$awal, $akhir])
            ->whereHas('results')
            ->count();

        // ================= WIDGET NILAI PER INDIKATOR + RATA-RATA (khusus satker ini) =================
        // Nilai per indikator pakai helper yang sama dengan halaman "View Indicator"
        // supaya angkanya selalu konsisten di kedua halaman. Rata-rata di sini adalah
        // rata-rata SEDERHANA dari indikator yang sudah dinilai bulan ini (bukan Nilai
        // IKPA berbobot seperti $skorSaya), jadi satker bisa lihat 2 angka berbeda:
        // performa keseluruhan (berbobot) vs rata-rata mentah antar indikator.
        $nilaiPerIndikatorSaya = $this->nilaiPerIndikatorSatker($satkerId, $awal, $akhir);
        $indikatorSudahDinilai = $nilaiPerIndikatorSaya->pluck('rata')->filter(fn ($r) => ! is_null($r));
        $rataRataIndikatorSaya = $indikatorSudahDinilai->isNotEmpty()
            ? round($indikatorSudahDinilai->avg(), 2)
            : null;

        // ================= RINCIAN POIN NILAI IKPA PER INDIKATOR — SEMUA SATKER =================
        // Sebelumnya tabel ini cuma nampilin 1 baris (satker yang sedang login). Sekarang
        // dibikin jadi leaderboard semua satker (sama seperti "Monitoring IKPA Terbaru"
        // punya admin), diurutkan dari Nilai IKPA tertinggi ke terendah. Dihitung sekali di
        // sini lalu dipakai ulang juga untuk peringkat satker sendiri di bawah, supaya tidak
        // menghitung skor setiap satker dua kali dengan cara yang berbeda.
        $judulDetailTabel = \App\Services\IkpaScoringService::jenisIndikator();
        $bobotIndikatorSaya = \App\Services\IkpaScoringService::bobotIndikator();

        $semuaSatker = Satker::orderBy('nama_satker')->get();
        $rincianSemuaSatker = $semuaSatker->map(function ($s) use ($awal, $akhir, $satkerId) {
            $rincian = \App\Services\IkpaScoringService::hitungSkorSatker($s->id, $awal, $akhir);
            $kategori = $this->kategoriDanWarna($rincian['skor']);

            $lastResultAt = IndicatorResult::whereHas('indicator', fn ($q) => $q->where('satker_id', $s->id))->max('created_at');
            $lastIndicatorAt = Indicator::where('satker_id', $s->id)->max('created_at');
            $updateTerakhir = collect([$lastResultAt, $lastIndicatorAt])
                ->filter()
                ->map(fn ($d) => \Carbon\Carbon::parse($d))
                ->sortDesc()
                ->first();

            return (object) [
                'id' => $s->id,
                'nama_satker' => $s->nama_satker,
                'nilai' => $rincian['skor'],
                'kategori_label' => $kategori['label'],
                'kategori_kelas' => $kategori['kelas'],
                'poin_indikator' => $rincian['poin_indikator'],
                'update_terakhir' => $updateTerakhir,
                'is_saya' => $s->id === $satkerId,
            ];
        })->sortByDesc(fn ($s) => $s->nilai ?? -1)->values();

        // updateTerakhirSaya (dipakai widget lain di halaman ini) diambil dari baris satker
        // sendiri di $rincianSemuaSatker, tidak perlu query ulang.
        $updateTerakhirSaya = $rincianSemuaSatker->firstWhere('id', $satkerId)->update_terakhir ?? null;

        // ================= PERINGKAT SAYA (sebelumnya di halaman "View Indicator", sekarang
        // digabung ke Dashboard Satker) — peringkat satker ini di antara semua satker yang
        // sudah punya nilai bulan ini, bukan daftar semua satker. =================
        $skorSemuaSatker = $rincianSemuaSatker->filter(fn ($s) => ! is_null($s->nilai))->sortByDesc('nilai')->values();

        $totalSatkerDinilai = $skorSemuaSatker->count();
        $peringkatSaya = $skorSemuaSatker->search(fn ($s) => $s->id === $satkerId);
        $peringkatSaya = $peringkatSaya === false ? null : $peringkatSaya + 1;

        return view('user.dashboard', compact(
            'satker', 'skorSaya', 'kategoriSaya', 'selisihBulanLalu', 'peringatanAktif',
            'trendSaya', 'totalTugasBulanIni', 'tugasSelesaiBulanIni',
            'nilaiPerIndikatorSaya', 'rataRataIndikatorSaya',
            'judulDetailTabel', 'bobotIndikatorSaya', 'rincianSemuaSatker', 'updateTerakhirSaya',
            'peringkatSaya', 'totalSatkerDinilai'
        ));
    }

    /**
     * Nilai per jenis indikator IKPA KHUSUS satu satker pada satu rentang periode —
     * dipakai bareng oleh widget di dashboard satker (index) dan halaman "View
     * Indicator" (monitoring) supaya angkanya selalu sama, tidak dihitung dua kali
     * dengan cara berbeda.
     *
     * Return: Collection of ['judul','rata','warna','kelas','kelas_bar'].
     */
    private function nilaiPerIndikatorSatker(int $satkerId, \Carbon\Carbon $awal, \Carbon\Carbon $akhir): \Illuminate\Support\Collection
    {
        $jenisIndikator = config('sikoor.jenis_indikator', []);

        $rataPerJudul = IndicatorResult::whereNotNull('indicator_results.nilai')
            ->join('indicators', 'indicator_results.indicator_id', '=', 'indicators.id')
            ->where('indicators.satker_id', $satkerId)
            ->whereBetween('indicators.periode', [$awal, $akhir])
            ->select('indicators.judul', DB::raw('AVG(indicator_results.nilai) as rata'))
            ->groupBy('indicators.judul')
            ->pluck('rata', 'judul');

        return collect($jenisIndikator)->map(function ($judul) use ($rataPerJudul) {
            $rata = isset($rataPerJudul[$judul]) ? round((float) $rataPerJudul[$judul], 2) : null;
            $kat = $this->kategoriDanWarna($rata);

            return [
                'judul' => $judul,
                'rata' => $rata,
                'warna' => $kat['warna'],
                'kelas' => $kat['kelas'],
                'kelas_bar' => $kat['kelas_bar'],
            ];
        });
    }

    /**
     * "View Indicator": peringkat satker ini di antara semua satker (bukan daftar
     * semua satker), rata-rata nilai per jenis indikator KHUSUS satker ini, dan
     * trend nilai untuk diri sendiri.
     */
    public function monitoring()
    {
        $satkerId = auth()->user()->satker_id;

        if (! $satkerId) {
            return redirect()->route('user.inbox')->with('error', 'Akun ini belum terhubung ke Satker manapun.');
        }

        $awal = now()->startOfMonth();
        $akhir = now()->endOfMonth();

        // ================= PERINGKAT SATKER INI (bukan daftar semua satker) =================
        $semuaSatker = Satker::orderBy('nama_satker')->get();
        $skorSemuaSatker = $semuaSatker->map(function ($s) use ($awal, $akhir) {
            return (object) [
                'id' => $s->id,
                'nilai' => $this->hitungSkor($s->id, $awal, $akhir),
            ];
        })->filter(fn ($s) => ! is_null($s->nilai))->sortByDesc('nilai')->values();

        $totalSatkerDinilai = $skorSemuaSatker->count();
        $peringkatSaya = $skorSemuaSatker->search(fn ($s) => $s->id === $satkerId);
        $peringkatSaya = $peringkatSaya === false ? null : $peringkatSaya + 1;
        $skorSaya = $this->hitungSkor($satkerId, $awal, $akhir);
        $kategoriSaya = $this->kategoriDanWarna($skorSaya);

        // ================= NILAI PER INDIKATOR IKPA (khusus satker ini) =================
        $nilaiPerIndikatorSaya = $this->nilaiPerIndikatorSatker($satkerId, $awal, $akhir);

        // ================= TREND UNTUK DIRI SENDIRI (tahun berjalan, mulai Januari) =================
        $trendSaya = $this->trendSatkerTahunIni($satkerId);

        return view('user.monitoring', compact(
            'peringkatSaya', 'totalSatkerDinilai', 'skorSaya', 'kategoriSaya',
            'nilaiPerIndikatorSaya', 'trendSaya'
        ));
    }
}