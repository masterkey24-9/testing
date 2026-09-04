@extends('layouts.app')

@section('title', 'Panduan Penggunaan')
@section('page-title', 'Panduan Penggunaan')

@section('sidebar')
    @if (auth()->user()->role === 'admin')
        @include('components.sidebar-admin')
    @else
        @include('components.sidebar-user')
    @endif
@endsection

@section('content')

@php
    $isAdmin = auth()->user()->role === 'admin';
    $daftarFitur = $isAdmin
        ? ['Dashboard', 'Monitoring IKPA', 'Indicators', 'Peringatan Satker', 'Kelola Satker', 'Notifikasi', 'Kirim Email', 'Live Chat']
        : ['Dashboard Satker', 'Dokumen Masuk', 'Notifikasi', 'Kirim Email', 'Live Chat'];
@endphp

<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-slate-200 p-6 sm:p-8">
        <div class="w-12 h-12 rounded-xl bg-navy-900 text-white flex items-center justify-center mb-4">
            <i class="ti ti-map-2 text-2xl"></i>
        </div>

        <h1 class="font-display font-bold text-lg text-navy-950 mb-2">Panduan Penggunaan</h1>
        <p class="text-sm text-slate-500 mb-5">
            Ikuti tur singkat untuk berkeliling ke fitur-fitur utama SIKOOR POLDA SUMBAR sebagai akun
            <span class="font-medium text-slate-700">{{ $isAdmin ? 'Admin' : 'Satker' }}</span>.
            Tur ini akan menyorot satu fitur pada satu waktu, lengkap dengan penjelasan singkat, dan otomatis
            membuka halaman terkait bila perlu.
        </p>

        <button type="button" id="btnMulaiPanduan"
                class="inline-flex items-center gap-2 h-11 px-5 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-medium transition">
            <i class="ti ti-player-play"></i> Mulai Panduan
        </button>

        <div class="mt-6 pt-5 border-t border-slate-100">
            <p class="text-xs font-medium text-slate-500 mb-2">Fitur yang akan dijelaskan:</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($daftarFitur as $fitur)
                    <span class="px-2.5 py-1 rounded-full text-[12px] font-medium bg-slate-100 text-slate-600">{{ $fitur }}</span>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-slate-400 mt-5">
            Tur bisa dihentikan kapan saja lewat tombol "Lewati" atau tombol Esc di keyboard. Kalau ada yang masih
            membingungkan, hubungi admin lewat menu Live Chat.
        </p>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('btnMulaiPanduan')?.addEventListener('click', function () {
        SikoorTour.start(@json($isAdmin ? 'admin' : 'satker'));
    });
</script>
@endpush

@endsection