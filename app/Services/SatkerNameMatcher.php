<?php

namespace App\Services;

use App\Models\Satker;
use Illuminate\Support\Collection;

/**
 * Cocokkan "Uraian Satker" dari PDF resmi DJPb (nama panjang/singkatan resmi Polri,
 * misal "ROOPS POLDA SUMBAR", "DITBINMAS POLDA SUMBAR") ke record Satker aplikasi ini
 * (nama yang lebih umum dipakai sehari-hari, misal "Biro Operasi", "Direktorat Binmas").
 *
 * Tabel `satkers` di app ini CUMA punya kolom `nama_satker` (tidak ada kolom "kode DJPb"),
 * jadi pencocokan terpaksa lewat nama, bukan kode. Kalau nanti mau lebih presisi & tidak
 * ada risiko salah cocok sama sekali, pertimbangkan tambah kolom `kode_satker` di migrasi
 * dan isi manual sekali per satker (paling akurat, tidak perlu fuzzy matching lagi).
 */
class SatkerNameMatcher
{
    /**
     * Kata kunci yang cuma bikin "noise" saat dibandingkan (nama instansi induk, dsb).
     */
    private const HAPUS_KATA = ['POLDA', 'SUMATERA', 'BARAT', 'SUMBAR', 'DAN', 'DI'];

    /**
     * Padanan istilah resmi DJPb <-> istilah singkat yang biasa dipakai di aplikasi.
     * Silakan tambah baris baru di sini kalau ternyata ada satker yang tidak ketemu
     * otomatis (lihat pesan "Satker yang TIDAK ditemukan padanannya" setelah import).
     */
    private const SINONIM = [
        'BIRO OPERASI' => ['ROOPS', 'BIRO OPS'],
        'BIRO PERENCANAAN' => ['RORENA', 'BIRO RENA'],
        'BIRO SUMBER DAYA MANUSIA' => ['RO SDM', 'BIRO SDM'],
        'BIRO LOGISTIK' => ['ROLOG'],
        'DIREKTORAT RESERSE KRIMINAL UMUM' => ['DITRESKRIMUM'],
        'DIREKTORAT RESERSE KRIMINAL KHUSUS' => ['DITRESKRIMSUS'],
        'DIREKTORAT RESERSE NARKOBA' => ['DITRESNARKOBA'],
        'DIREKTORAT LALU LINTAS' => ['DITLANTAS'],
        'DIREKTORAT SAMAPTA' => ['DITSAMAPTA'],
        'DIREKTORAT BINMAS' => ['DITBINMAS'],
        'DIREKTORAT INTELKAM' => ['DITINTELKAM'],
        'DIREKTORAT POLISI AIR DAN UDARA' => ['DITPOLAIRUD'],
        'DIREKTORAT PENGAMANAN OBJEK VITAL' => ['DITPAMOBVIT'],
        'BIDANG PROPAM' => ['BIDPROPAM'],
        'BIDANG HUMAS' => ['BIDHUMAS'],
        'BIDANG DOKKES' => ['BIDDOKKES', 'BIDDOKES'],
        'BIDANG KEUANGAN' => ['BIDKEU'],
        'BIDANG HUKUM' => ['BIDKUM'],
        'BIDANG TIK' => ['BID TIK'],
        'BIDANG YANMA' => ['YANMA', 'SATKER YANMA'],
        'RUMAH SAKIT BHAYANGKARA' => ['RUMKIT BHAYANGKARA'],
        'SEKRETARIAT PRIBADI PIMPINAN' => ['SPRIPIM'],
        'SEKOLAH POLISI NEGARA' => ['SPN'],
        'SATUAN BRIMOB' => ['SATBRIMOB', 'SAT BRIMOB'],
        'INSPEKTORAT PENGAWASAN DAERAH' => ['ITWASDA'],
    ];

    /** Ambang persentase kemiripan minimal untuk fallback fuzzy match (0-100). */
    private const AMBANG_FUZZY = 65.0;

    public static function normalize(string $nama): string
    {
        $nama = strtoupper($nama);
        $nama = preg_replace('/[^A-Z0-9\s]/', ' ', $nama) ?? $nama;
        $kata = preg_split('/\s+/', trim($nama)) ?: [];
        $kata = array_filter($kata, fn ($w) => $w !== '' && ! in_array($w, self::HAPUS_KATA, true));

        return implode(' ', $kata);
    }

    /**
     * @param  Collection<int, Satker>  $satkers  Daftar semua satker (ambil sekali lalu pakai ulang, jangan query per baris)
     */
    public static function match(string $namaPdf, Collection $satkers): ?Satker
    {
        $target = self::normalize($namaPdf);
        if ($target === '') {
            return null;
        }

        // 1) Exact match setelah normalisasi.
        foreach ($satkers as $satker) {
            if (self::normalize($satker->nama_satker) === $target) {
                return $satker;
            }
        }

        // 2) Lewat kamus sinonim/singkatan resmi.
        foreach (self::SINONIM as $panjang => $daftarSingkat) {
            $panjangNorm = self::normalize($panjang);
            $namaPdfCocokSinonim = str_contains($target, $panjangNorm);
            foreach ($daftarSingkat as $singkat) {
                if (str_contains($target, self::normalize($singkat))) {
                    $namaPdfCocokSinonim = true;
                }
            }
            if (! $namaPdfCocokSinonim) {
                continue;
            }

            foreach ($satkers as $satker) {
                $namaSatkerNorm = self::normalize($satker->nama_satker);
                if ($namaSatkerNorm === $panjangNorm
                    || str_contains($panjangNorm, $namaSatkerNorm)
                    || str_contains($namaSatkerNorm, $panjangNorm)
                ) {
                    return $satker;
                }
                foreach ($daftarSingkat as $singkat) {
                    if ($namaSatkerNorm === self::normalize($singkat)) {
                        return $satker;
                    }
                }
            }
        }

        // 3) Salah satu nama memuat penuh nama yang lain.
        foreach ($satkers as $satker) {
            $namaSatkerNorm = self::normalize($satker->nama_satker);
            if ($namaSatkerNorm !== '' && (str_contains($target, $namaSatkerNorm) || str_contains($namaSatkerNorm, $target))) {
                return $satker;
            }
        }

        // 4) Fallback fuzzy: persentase kemiripan karakter (similar_text), ambil yang terbaik.
        $terbaik = null;
        $skorTerbaik = 0.0;
        foreach ($satkers as $satker) {
            similar_text($target, self::normalize($satker->nama_satker), $persen);
            if ($persen > $skorTerbaik) {
                $skorTerbaik = $persen;
                $terbaik = $satker;
            }
        }

        return $skorTerbaik >= self::AMBANG_FUZZY ? $terbaik : null;
    }
}
