<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class IkpaPdfParserService
{
    public function parsePdf(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        $lines = explode("\n", $text);
        $results = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Deteksi baris yang memuat kode satker 6 digit
            if (preg_match('/\b(\d{6})\b/', $trimmed, $satkerMatch)) {
                $kodeSatker = $satkerMatch[1];

                // Normalisasi koma desimal Indonesia ke format titik standar
                $normalizedLine = str_replace(',', '.', $trimmed);

                // Ambil seluruh angka (termasuk desimal)
                preg_match_all('/\d+(?:\.\d+)?/', $normalizedLine, $matches);
                $numbers = $matches[0] ?? [];

                // Angka paling belakang pada baris IKPA DJPb adalah Nilai Akhir
                $nilaiAkhir = !empty($numbers) ? (float) end($numbers) : 0.0;

                $results[] = [
                    'kode_satker' => $kodeSatker,
                    'nilai_akhir' => $nilaiAkhir,
                    'raw_line'    => $trimmed
                ];
            }
        }

        return $results;
    }
}