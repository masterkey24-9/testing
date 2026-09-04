<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\IndikatorBobot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Pusat rumus Nilai IKPA per satker + kategori warnanya (Hijau/Kuning/Merah).
 *
 * Sebelumnya rumus ini ada 2 salinan (DashboardController & closure
 * /monitoring-ikpa di routes/web.php) yang gampang out-of-sync. Sekarang
 * keduanya panggil class ini saja.
 *
 * Nilai IKPA satker = jumlah "poin sumbangan" tiap jenis indikator, persis
 * seperti IKPA Kemenkeu asli:
 *   poin per indikator = (bobot% / 100) x nilai indikator itu (0-100)
 *   Nilai IKPA         = jumlah semua poin (maksimal 100 kalau semua bobot
 *                         total 100% dan semua indikator bernilai 100)
 *
 * Indikator yang belum ditugaskan/belum dinilai pada periode berjalan
 * dianggap menyumbang 0 poin (bukan diabaikan dari pembagi), supaya satker
 * yang belum lapor beberapa indikator tidak "diuntungkan" nilai rata-ratanya.
 */
class IkpaScoringService
{
    /** Daftar baku jenis indikator (satu-satunya sumber kebenaran ada di config). */
    public static function jenisIndikator(): array
    {
        return config('sikoor.jenis_indikator', []);
    }

    /** Bobot (%) tiap jenis indikator, di-set admin lewat halaman "Pengaturan Bobot Indikator". */
    public static function bobotIndikator(): Collection
    {
        return IndikatorBobot::pluck('bobot', 'judul')->map(fn ($b) => (float) $b);
    }

    /**
     * Kategori warna Nilai IKPA satker.
     *   >= ambang_hijau (95)                 => Hijau
     *   >= ambang_kuning (70) tapi < hijau    => Kuning
     *   < ambang_kuning                       => Merah
     */
    public static function kategori(?float $nilai): array
    {
        if (is_null($nilai)) {
            return ['label' => 'Belum Dinilai', 'badge' => 'bg-slate-100 text-slate-500'];
        }
        if ($nilai >= config('sikoor.ambang_hijau', 95)) {
            return ['label' => 'Hijau', 'badge' => 'bg-emerald-50 text-emerald-600'];
        }
        if ($nilai >= config('sikoor.ambang_kuning', 70)) {
            return ['label' => 'Kuning', 'badge' => 'bg-amber-50 text-amber-600'];
        }
        return ['label' => 'Merah', 'badge' => 'bg-red-50 text-red-600'];
    }

    /** Versi traffic-light (dipakai panel "Monitoring Indikator IKPA" per jenis indikator). */
    public static function trafficLight(?float $nilai): array
    {
        return match (self::kategori($nilai)['label']) {
            'Hijau' => ['warna' => 'Hijau', 'bar' => 'bg-emerald-500', 'teks' => 'text-emerald-600', 'badge' => 'bg-emerald-50 text-emerald-600'],
            'Kuning' => ['warna' => 'Kuning', 'bar' => 'bg-amber-500', 'teks' => 'text-amber-600', 'badge' => 'bg-amber-50 text-amber-600'],
            'Merah' => ['warna' => 'Merah', 'bar' => 'bg-red-500', 'teks' => 'text-red-600', 'badge' => 'bg-red-50 text-red-600'],
            default => ['warna' => 'Belum Dinilai', 'bar' => 'bg-slate-200', 'teks' => 'text-slate-400', 'badge' => 'bg-slate-100 text-slate-500'],
        };
    }

    /**
     * Hitung Nilai IKPA + rincian poin per indikator untuk satu satker pada satu rentang periode.
     *
     * Return:
     *   skor           : Nilai IKPA (0-100) atau null kalau satker belum punya tugas sama sekali
     *                     pada periode ini
     *   total_tugas    : jumlah indicator yang ditugaskan ke satker ini pada periode ini
     *   tugas_selesai  : jumlah yang sudah ada laporan (results)
     *   poin_indikator : [judul => poin sumbangan (bobot% x nilai)] untuk tiap jenis indikator baku
     */
    public static function hitungSkorSatker(int $satkerId, Carbon $awal, Carbon $akhir): array
    {
        $jenisIndikator = self::jenisIndikator();
        $bobotIndikator = self::bobotIndikator();

        $tugasPeriode = Indicator::with('results')
            ->where('satker_id', $satkerId)
            ->whereBetween('periode', [$awal, $akhir])
            ->get();

        $totalTugas = $tugasPeriode->count();
        $tugasSelesai = $tugasPeriode->filter(fn ($ind) => $ind->results->isNotEmpty())->count();

        $poinIndikator = array_fill_keys($jenisIndikator, 0.0);

        if ($totalTugas === 0) {
            return [
                'skor' => null,
                'total_tugas' => 0,
                'tugas_selesai' => 0,
                'poin_indikator' => $poinIndikator,
            ];
        }

        // Nilai terbaru per jenis indikator pada periode ini (kalau ada lebih dari satu
        // tugas dengan judul yang sama dalam satu periode, pakai laporan yang paling baru).
        $nilaiPerJudul = [];
        foreach ($tugasPeriode as $ind) {
            $latestResult = $ind->results->sortByDesc('created_at')->first();
            if ($latestResult && ! is_null($latestResult->nilai)) {
                $nilaiPerJudul[$ind->judul] = (float) $latestResult->nilai;
            }
        }

        $skor = 0.0;
        foreach ($jenisIndikator as $judul) {
            $bobot = (float) ($bobotIndikator[$judul] ?? 0);
            $nilai = $nilaiPerJudul[$judul] ?? null;
            $poin = ! is_null($nilai) ? round(($bobot / 100) * $nilai, 2) : 0.0;
            $poinIndikator[$judul] = $poin;
            $skor += $poin;
        }

        return [
            'skor' => round($skor, 2),
            'total_tugas' => $totalTugas,
            'tugas_selesai' => $tugasSelesai,
            'poin_indikator' => $poinIndikator,
        ];
    }
}