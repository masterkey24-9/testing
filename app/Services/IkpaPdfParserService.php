<?php

namespace App\Services;

class IkpaPdfParserService
{
    /**
     * Parse teks mentah dari PDF menjadi array nilai satker.
     * Menggunakan Regex dengan modifier /s (dotall) untuk menangani multiline.
     *
     * @param string $text Hasil ekstraksi teks dari PDF
     * @return array Array asosiatif ['kode_satker' => nilai_akhir]
     */
    public function parseData(string $text): array
    {
        $results = [];

        // 1. Pola untuk memecah setiap baris data Satker.
        // Di PDF DJPb, strukturnya selalu berurutan: | Kode KPPN (3 digit) | Kode BA (3 digit) | Kode Satker (6 digit) |
        // \s* digunakan untuk menoleransi spasi atau enter (newline) di antara garis vertikal.
        $patternSatker = '/\|\s*\d{3}\s*\|\s*\d{3}\s*\|\s*(?<satker>\d{6})\s*\|(.*?)(?=\|\s*\d{3}\s*\|\s*\d{3}\s*\|\s*\d{6}\s*\||$)/s';

        if (preg_match_all($patternSatker, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $kodeSatker = trim($match['satker']);
                
                // Blok teks ini berisi data dari satu satker spesifik, hingga menemukan kode satker berikutnya
                $blokTeks = $match[2]; 

                // 2. Pola untuk mencari Nilai Akhir di dalam blok teks satker tersebut.
                // Nilai akhir selalu muncul setelah Konversi Bobot (%) dan Dispensasi SPM (angka).
                // Contoh di teks PDF: 100,00% \n | 0,00 \n | 99,55
                $patternNilai = '/%\s*\|\s*\d+[.,]\d{2}\s*\|\s*(?<nilai>\d{1,3}[.,]\d{2})/';

                if (preg_match($patternNilai, $blokTeks, $nilaiMatch)) {
                    // Ubah koma (,) menjadi titik (.) agar dikenali sebagai float yang valid untuk database
                    $nilaiString = str_replace(',', '.', trim($nilaiMatch['nilai']));
                    
                    $results[$kodeSatker] = (float) $nilaiString;
                }
            }
        }

        return $results;
    }
}