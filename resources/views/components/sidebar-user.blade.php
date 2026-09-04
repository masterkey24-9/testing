<div class="h-16 flex items-center gap-3 px-5 border-b border-navy-800">
    <div class="flex items-center gap-1.5 shrink-0">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="Logo Polda Sumbar"
                 class="w-8 h-8 rounded object-contain shrink-0">
        @else
            <div class="w-8 h-8 rounded bg-gold-500 flex items-center justify-center text-navy-950 font-display font-bold text-sm">S</div>
        @endif
        @if (file_exists(public_path('images/bidkeu.png')))
            <img src="{{ asset('images/bidkeu.png') }}" alt="Logo Bidkeu"
                 class="w-8 h-8 rounded object-contain shrink-0">
        @endif
    </div>
    <div class="leading-tight">
        <p class="font-display font-semibold text-sm text-white">Simpati IKPA</p>
        <p class="text-[12px] text-slate-400">Polda Sumbar</p>
    </div>
</div>

<nav class="flex-1 px-3 py-4 space-y-1">
    <a href="{{ route('user.dashboard') }}"
       data-tour="tour-menu-dashboard-satker"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ request()->routeIs('user.dashboard') ? 'bg-navy-800 text-white font-medium' : 'text-slate-300 hover:bg-navy-900 hover:text-white' }}">
        <i class="ti ti-chart-bar text-lg"></i>
        Dashboard Satker
    </a>

    <a href="{{ route('user.inbox') }}"
       data-tour="tour-menu-inbox"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ request()->routeIs('user.inbox') ? 'bg-navy-800 text-white font-medium' : 'text-slate-300 hover:bg-navy-900 hover:text-white' }}">
        <i class="ti ti-inbox text-lg"></i>
        Dokumen masuk
    </a>

    <a href="{{ route('panduan.index') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ request()->routeIs('panduan.index') ? 'bg-navy-800 text-white font-medium' : 'text-slate-300 hover:bg-navy-900 hover:text-white' }}">
        <i class="ti ti-book-2 text-lg"></i>
        Panduan Penggunaan
    </a>
</nav>

<form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-navy-800">
    @csrf
    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-navy-900 hover:text-white">
        <i class="ti ti-logout text-lg"></i>
        Keluar
    </button>
</form>