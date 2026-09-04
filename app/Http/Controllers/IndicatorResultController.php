<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IndicatorResultController extends Controller
{
    /**
     * ALUR TERBARU: admin cuma menilai (Status + catatan + tindak lanjut) — TIDAK ADA
     * upload dokumen apa pun di form penilaian ini. Status (Diterima/Perlu Revisi) itu
     * sendiri LANGSUNG jadi penilaian, otomatis diterjemahkan sistem ke nilai 100/0.
     */
    public function storeByAdmin(Request $request, $indicator_id)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $validated = $request->validate([
            'status' => 'required|in:diterima,direvisi',
            'catatan_admin' => 'nullable|string|max:1000',
            'tindak_lanjut' => 'nullable|string|max:1000',
            'tindak_lanjut_status' => 'nullable|in:belum,sudah_dikerjakan,revisi',
        ]);

        $indicator = Indicator::findOrFail($indicator_id);

        $result = IndicatorResult::create([
            'indicator_id' => $indicator_id,
            'satker_id' => $indicator->satker_id,
            'status' => $validated['status'],
            'catatan_admin' => $validated['catatan_admin'] ?? null,
            'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
            'tindak_lanjut_status' => $validated['tindak_lanjut_status'] ?? 'belum',
            'nilai' => $validated['status'] === 'diterima' ? 100 : 0,
        ]);

        NotificationController::notifyResultReviewed($result);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Penilaian berhasil disimpan.', 'result' => $result]);
        }

        return redirect()->back()->with('success', 'Penilaian berhasil disimpan.');
    }

    /**
     * Admin mengubah penilaian yang sudah ada. Kolom `nilai` tetap ada di database
     * (semua fitur lain — kategori, trend chart, donut, Daftar Satker Prioritas,
     * bobot indikator, notifikasi otomatis, Peringatan Satker — TETAP JALAN tanpa
     * perlu ditulis ulang), tapi diisi OTOMATIS dari status: Diterima = 100,
     * Perlu Revisi = 0.
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $validated = $request->validate([
            'status' => 'required|in:diterima,direvisi',
            'catatan_admin' => 'nullable|string|max:1000',
            'tindak_lanjut' => 'nullable|string|max:1000',
            'tindak_lanjut_status' => 'nullable|in:belum,sudah_dikerjakan,revisi',
        ]);

        $result = IndicatorResult::findOrFail($id);

        $result->update([
            'status' => $validated['status'],
            'catatan_admin' => $validated['catatan_admin'] ?? null,
            'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
            'tindak_lanjut_status' => $validated['tindak_lanjut_status'] ?? 'belum',
            'nilai' => $validated['status'] === 'diterima' ? 100 : 0,
        ]);

        NotificationController::notifyResultReviewed($result);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Penilaian laporan berhasil disimpan.', 'result' => $result]);
        }

        return redirect()->back()->with('success', 'Penilaian laporan berhasil disimpan.');
    }
}