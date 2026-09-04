@extends('layouts.app')

@section('title', 'Ganti Password')

@section('content')

<div class="min-h-screen flex items-center justify-center bg-navy-950 px-4 relative overflow-hidden">
    <div class="absolute inset-0 opacity-[0.08]"
         style="background-image: radial-gradient(circle, #C89B3C 1px, transparent 1px); background-size: 28px 28px;"></div>

    <div class="w-full max-w-sm relative">
        <div class="flex flex-col items-center mb-6">
            @if (file_exists(public_path('images/logo.png')))
                <img src="{{ asset('images/logo.png') }}" alt="Logo Polda Sumbar"
                     class="w-14 h-14 rounded-xl object-contain shadow-lg mb-3">
            @endif
            <h1 class="font-display font-extrabold text-xl text-white tracking-tight">
                Ganti Password <span class="text-gold-400">Terlebih Dahulu</span>
            </h1>
            <p class="text-slate-300 text-sm mt-2 text-center leading-relaxed max-w-xs">
                Ini login pertama Anda dengan password yang diberikan admin. Demi keamanan,
                silakan buat password baru sebelum melanjutkan.
            </p>
        </div>

        <div class="bg-white rounded-2xl p-7 shadow-xl">
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.force.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="password" class="block text-sm font-bold text-navy-950 mb-1.5">Password baru</label>
                    <input type="password" id="password" name="password" required autofocus
                           placeholder="Minimal 8 karakter"
                           class="w-full h-11 px-3.5 rounded-lg border border-slate-300 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-navy-800 focus:border-navy-800">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-bold text-navy-950 mb-1.5">Ulangi password baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           placeholder="Ketik ulang password baru"
                           class="w-full h-11 px-3.5 rounded-lg border border-slate-300 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-navy-800 focus:border-navy-800">
                </div>

                <button type="submit"
                        class="w-full h-11 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-bold tracking-wide transition">
                    Simpan &amp; Lanjutkan
                </button>
            </form>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-slate-300 hover:text-white text-xs font-semibold tracking-wide">
                Keluar dan login nanti
            </button>
        </form>
    </div>
</div>

@endsection
