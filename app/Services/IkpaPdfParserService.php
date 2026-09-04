<?php

namespace App\Services;

use Smalot\PdfParser\Parser as SmalotParser;

/**
 * Parser untuk PDF resmi DJPb Kemenkeu "Indikator Pelaksanaan Anggaran Satker".
 *
 * KENAPA BERBASIS KOORDINAT (X, Y), BUKAN URUTAN TEKS BIASA?
 * Kalau kita cuma ambil teks berurutan dari kiri-atas ke kanan-bawah (seperti versi
 * lama), setiap kali ada 1 kolom yang KOSONG untuk satker tertentu (misalnya RUMKIT
 * BHAYANGKARA yang tidak punya nilai "Pengelolaan UP/TUP"), semua nilai kolom
 * SESUDAHNYA akan ikut geser dan tertukar ke kolom yang salah. Ini beneran terjadi di
 * PDF contoh yang dikirim (baris RUMKIT).
 *
 * Solusinya: pakai posisi X tiap potongan teks (didapat dari Smalot\PdfParser::getDataTm())
 * untuk menentukan kolom, dan posisi Y untuk menentukan baris. Kolom yang kosong otomatis
 * dilewati tanpa mengganggu kolom lain.
 */
class IkpaPdfParserService
{
    /**
     * Kata kunci unik di header PDF untuk tiap kolom nilai yang kita butuhkan.
     * Key HARUS persis sama dengan salah satu nilai di config('sikoor.jenis_indikator'),
     * supaya hasil parsing langsung nyambung ke Indicator/IndicatorResult & Monitoring IKPA.
     */
    private const KOLOM_KEYWORD = [
        'Revisi DIPA' => 'Revisi',
        'Deviasi Halaman III DIPA' => 'Deviasi',
        'Penyerapan Anggaran' => 'Penyerapan',
        'Belanja Kontraktual' => 'Belanja',
        'Penyelesaian Tagihan' => 'Penyelesaian',
        'Pengelolaan UP/TUP' => 'Pengelolaan',
        'Capaian Output' => 'Capaian',
        'Dispensasi SPM' => 'Dispensasi',
    ];

    /** Toleransi jarak Y (satuan PDF/point) supaya 2 potongan teks dianggap 1 baris yang sama. */
    private const TOLERANSI_Y = 3.0;

    /**
     * @return array<int, array{kode_satker:string, nama_satker:string, nilai: array<string,float>}>
     */
    public function parsePdf(string $fullPath): array
    {
        $parser = new SmalotParser();
        $pdf = $parser->parseFile($fullPath);

        $hasil = [];

        foreach ($pdf->getPages() as $page) {
            $potongan = $this->ambilPotonganTeks($page);
            if (empty($potongan)) {
                continue;
            }

            $batasKolom = $this->deteksiBatasKolom($potongan);
            if (! $batasKolom) {
                // Halaman tanpa header tabel yang dikenali (jarang terjadi), lewati saja.
                continue;
            }

            $hasil = array_merge($hasil, $this->ekstrakBarisSatker($potongan, $batasKolom));
        }

        return $hasil;
    }

    /** Ambil semua potongan teks 1 halaman beserta koordinat X,Y-nya. */
    private function ambilPotonganTeks($page): array
    {
        $out = [];
        foreach ($page->getDataTm() as $entry) {
            $tm = $entry[0] ?? null;
            $teks = trim((string) ($entry[1] ?? ''));
            if ($teks === '' || ! is_array($tm) || ! isset($tm[4], $tm[5])) {
                continue;
            }
            $out[] = [
                'x' => (float) $tm[4],
                'y' => (float) $tm[5],
                'teks' => $teks,
            ];
        }

        return $out;
    }

    /**
     * Cari posisi X kolom-kolom nilai dengan mencocokkan kata kunci header, lalu urutkan
     * kiri ke kanan. Batas antar kolom = titik tengah jarak ke kata kunci tetangganya.
     */
    private function deteksiBatasKolom(array $potongan): ?array
    {
        $anchor = [];

        foreach (self::KOLOM_KEYWORD as $judul => $kataKunci) {
            foreach ($potongan as $p) {
                if (stripos($p['teks'], $kataKunci) === 0) {
                    $anchor[$judul] = $p['x'];
                    break;
                }
            }
        }

        // Kurang dari separuh header ketemu -> kemungkinan bukan halaman tabel ini.
        if (count($anchor) < 4) {
            return null;
        }

        asort($anchor);
        $judulUrut = array_keys($anchor);
        $xUrut = array_values($anchor);
        $n = count($judulUrut);

        $batas = [];
        foreach ($judulUrut as $i => $judul) {
            $kiri = $i === 0 ? $xUrut[$i] - 40 : ($xUrut[$i - 1] + $xUrut[$i]) / 2;
            $kanan = $i === $n - 1 ? $xUrut[$i] + 60 : ($xUrut[$i] + $xUrut[$i + 1]) / 2;
            $batas[$judul] = ['min' => $kiri, 'max' => $kanan];
        }

        return $batas;
    }

    /**
     * Deteksi tiap baris satker lewat token "Kode Satker" (angka 6 digit berdiri sendiri),
     * lalu ambil nilai baris ATAS blok satker itu (baris "NILAI" mentah 0-100), BUKAN baris
     * "BOBOT"/"NILAI AKHIR" di bawahnya (karena app ini punya bobot sendiri yang diatur admin
     * di halaman "Pengaturan Bobot Indikator").
     */
    private function ekstrakBarisSatker(array $potongan, array $batasKolom): array
    {
        $barisSatker = [];
        foreach ($potongan as $p) {
            if (preg_match('/^\d{6}$/', $p['teks'])) {
                $barisSatker[] = $p; // anchor: kode satker, y-nya = baris "NILAI"
            }
        }

        if (empty($barisSatker)) {
            return [];
        }

        // Urut dari atas ke bawah halaman.
        usort($barisSatker, fn ($a, $b) => $b['y'] <=> $a['y']);

        $xKodeSatker = min(array_column($barisSatker, 'x'));
        $xUraianMulai = $xKodeSatker + 5;
        $xKolomPertama = min(array_map(fn ($b) => $b['min'], $batasKolom));

        $hasil = [];

        foreach ($barisSatker as $idx => $anchor) {
            $yAtas = $anchor['y'];
            $yBawah = $barisSatker[$idx + 1]['y'] ?? ($yAtas - 60);

            $kodeSatker = $anchor['teks'];

            // --- Uraian Satker: gabung semua potongan teks (bukan angka, bukan label
            //     "NILAI/BOBOT/NILAI AKHIR") di rentang X [Kode Satker, kolom nilai pertama)
            //     dan Y [yBawah, yAtas] (nama satker kadang wrap jadi 2-3 baris). ---
            $kataUraian = [];
            foreach ($potongan as $p) {
                if ($p['x'] >= $xUraianMulai && $p['x'] < $xKolomPertama
                    && $p['y'] <= $yAtas + self::TOLERANSI_Y
                    && $p['y'] > $yBawah + self::TOLERANSI_Y
                ) {
                    $t = strtoupper($p['teks']);
                    if (in_array($t, ['NILAI', 'BOBOT', 'NILAI AKHIR'], true)) {
                        continue;
                    }
                    if (preg_match('/^\d+([.,]\d+)?%?$/', $t)) {
                        continue;
                    }
                    $kataUraian[] = ['x' => $p['x'], 'y' => $p['y'], 'teks' => $p['teks']];
                }
            }
            // Urut atas-ke-bawah dulu, lalu kiri-ke-kanan, supaya nama yang wrap 2-3 baris
            // tersambung dengan urutan yang benar.
            usort($kataUraian, fn ($a, $b) => $b['y'] <=> $a['y'] ?: $a['x'] <=> $b['x']);
            $namaSatker = trim(preg_replace('/\s+/', ' ', implode(' ', array_column($kataUraian, 'teks'))));

            if ($namaSatker === '') {
                continue; // nama satker gagal terbaca, lewati baris ini daripada nyimpen data ngawur
            }

            // --- Nilai tiap kolom, HANYA dari baris atas (y ~ yAtas) ---
            $nilaiPerKolom = [];
            foreach (self::KOLOM_KEYWORD as $judul => $kataKunci) {
                $batas = $batasKolom[$judul] ?? null;
                if (! $batas) {
                    continue;
                }
                foreach ($potongan as $p) {
                    if (abs($p['y'] - $yAtas) > self::TOLERANSI_Y) {
                        continue;
                    }
                    if ($p['x'] < $batas['min'] || $p['x'] >= $batas['max']) {
                        continue;
                    }
                    if (! preg_match('/^-?\d+(?:[.,]\d+)?%?$/', $p['teks'])) {
                        continue;
                    }
                    $nilaiPerKolom[$judul] = $this->keFloat($p['teks']);
                    break; // ambil kecocokan pertama di kolom itu
                }
            }

            if (empty($nilaiPerKolom)) {
                continue; // tidak ada satupun nilai terbaca di baris ini, kemungkinan salah deteksi
            }

            $hasil[] = [
                'kode_satker' => $kodeSatker,
                'nama_satker' => $namaSatker,
                'nilai' => $nilaiPerKolom,
            ];
        }

        return $hasil;
    }

    private function keFloat(string $angka): float
    {
        $angka = rtrim(trim($angka), '%');
        $angka = str_replace(',', '.', $angka);

        return (float) $angka;
    }
}
