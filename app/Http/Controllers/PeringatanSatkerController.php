<?php

namespace App\Http\Controllers;

use App\Models\PeringatanSatker;
use App\Models\Satker;
use Illuminate\Http\Request;


class PeringatanSatkerController extends Controller
{
    /**
     * Halaman admin: form buat peringatan baru + daftar peringatan yang sudah dibuat.
     */
    public function index()
    {
        $satkerMerah = $this->satkerBerkategoriMerah();

        $peringatan = PeringatanSatker::with('satker')
            ->latest()
            ->get();

        return view('admin.peringatan', compact('satkerMerah', 'peringatan'));
    }

    /**
     * Admin buat peringatan baru untuk satu satker (harus salah satu satker
     * berkategori merah bulan ini — divalidasi ulang di server, bukan cuma
     * dibatasi lewat dropdown di form).
     */
    public function store(Request $request)
    {
        $satkerMerahIds = $this->satkerBerkategoriMerah()->pluck('id')->all();

        $validated = $request->validate([
            'satker_id' => 'required|exists:satkers,id|in:' . implode(',', $satkerMerahIds ?: [0]),
            'pesan' => 'required|string|max:500',
            'batas_waktu' => 'required|date',
        ], [
            'satker_id.in' => 'Peringatan cuma bisa dibuat untuk satker yang bulan ini berkategori Merah (Nilai IKPA < ' . config('sikoor.ambang_kuning', 70) . ').',
        ]);

        $validated['status'] = 'aktif';
        $validated['dibuat_oleh'] = auth()->id();

        PeringatanSatker::create($validated);

        return redirect()->route('peringatan.index')->with('success', 'Peringatan berhasil dibuat dan akan tampil ke satker terkait.');
    }

    /**
     * Admin menutup/menyelesaikan peringatan — ini yang "membuka kunci" upload
     * satker kalau sebelumnya sempat terkunci karena batas waktu lewat.
     */
    public function selesaikan($id)
    {
        $peringatan = PeringatanSatker::findOrFail($id);
        $peringatan->update(['status' => 'selesai']);

        return redirect()->route('peringatan.index')->with('success', 'Peringatan ditandai selesai, satker bisa mengirim laporan lagi.');
    }

    public function destroy($id)
    {
        PeringatanSatker::findOrFail($id)->delete();

        return redirect()->route('peringatan.index')->with('success', 'Peringatan dihapus.');
    }

    /**
     * Satker yang bulan ini berkategori Merah menurut Nilai IKPA (rumus & ambang batas
     * SAMA PERSIS dengan IkpaScoringService yang dipakai dashboard & Monitoring IKPA),
     * supaya satker yang "merah" di sini = yang "merah" juga di dashboard.
     *
     * Satker yang belum punya nilai sama sekali TIDAK dihitung merah di sini (beda kasus
     * dari "nilai rendah") — kalau mau termasuk juga, tinggal ubah kondisi is_null di bawah.
     */
    private function satkerBerkategoriMerah()
    {
        $awal = now()->startOfMonth();
        $akhir = now()->endOfMonth();

        return Satker::orderBy('nama_satker')->get()
            ->filter(function ($satker) use ($awal, $akhir) {
                $skor = \App\Services\IkpaScoringService::hitungSkorSatker($satker->id, $awal, $akhir)['skor'];

                if (is_null($skor)) {
                    return false;
                }

                return \App\Services\IkpaScoringService::kategori($skor)['label'] === 'Merah';
            })
            ->values();
    }
}