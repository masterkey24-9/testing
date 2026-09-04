<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    /**
     * Halaman wajib ganti password — cuma muncul kalau `must_change_password`
     * user yang login masih true (akun baru dibuat admin, atau baru direset
     * lewat "Cetak Kredensial"). Kalau flag-nya sudah false, tidak ada gunanya
     * user buka halaman ini manual, jadi langsung dialihkan ke dashboard.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-password-change');
    }

    /**
     * Simpan password baru + matikan flag `must_change_password`, supaya
     * halaman ini TIDAK muncul lagi di login-login berikutnya sampai admin
     * reset kredensial lagi.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password berhasil diganti. Selamat menggunakan sistem.');
    }
}
