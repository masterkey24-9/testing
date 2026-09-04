<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa user (khususnya satker) ganti password dulu kalau flag
 * `must_change_password` masih true — dipakai untuk akun baru dibuat admin
 * atau setelah admin reset/cetak ulang kredensial. Begitu password diganti
 * sendiri lewat halaman wajib ganti password, flag ini di-set false lagi
 * (lihat Auth\ForcePasswordChangeController::update), jadi TIDAK akan
 * memaksa lagi di login-login berikutnya.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.force*') && ! $request->routeIs('logout')) {
            return redirect()->route('password.force');
        }

        return $next($request);
    }
}