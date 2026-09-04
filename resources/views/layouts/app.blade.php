<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Simpati IKPA') - Simpati IKPA Polda Sumbar</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { 950: '#3B2312', 900: '#4A2E16', 800: '#5C3B1E', 700: '#6E4726' },
                        gold: { 500: '#D4AF37', 400: '#E0C165' },
                        canvas: '#F8F9FA',
                    },
                    fontFamily: {
                        display: ['"Plus Jakarta Sans"', 'sans-serif'],
                        sans: ['Inter', 'sans-serif'],
                    },
                    // Skala ukuran huruf dinaikkan sedikit dari bawaan Tailwind supaya
                    // lebih gampang dibaca di HP (teks kecil di layar kecil paling
                    // sering jadi keluhan keterbacaan). Berlaku otomatis ke SELURUH
                    // halaman karena semua view sudah pakai class text-xs/sm/base/dst.
                    // Termasuk ukuran arbitrary text-[11px]/text-[12px] yang tersebar
                    // di beberapa komponen (badge, label kecil) supaya konsisten.
                    fontSize: {
                        '2xs': ['0.75rem', { lineHeight: '1.05rem' }],  // 12px, pengganti text-[11px]/[12px]
                        xs: ['0.8125rem', { lineHeight: '1.3rem' }],   // 13px (bawaan 12px)
                        sm: ['0.9375rem', { lineHeight: '1.5rem' }],   // 15px (bawaan 14px)
                        base: ['1rem', { lineHeight: '1.65rem' }],     // 16px, line-height dilebarkan
                        lg: ['1.1875rem', { lineHeight: '1.8rem' }],   // ~19px (bawaan 18px)
                        xl: ['1.3125rem', { lineHeight: '1.9rem' }],   // ~21px (bawaan 20px)
                        '2xl': ['1.5625rem', { lineHeight: '2.05rem' }], // ~25px (bawaan 24px)
                    },
                    boxShadow: {
                        // Bayangan lembut khas kartu dashboard elegan (bukan shadow tajam bawaan)
                        card: '0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px -4px rgba(59, 35, 18, 0.08)',
                        'card-hover': '0 4px 10px rgba(15, 23, 42, 0.06), 0 14px 28px -8px rgba(59, 35, 18, 0.14)',
                    },
                }
            }
        }
    </script>
    <style>
        /* ===== Sentuhan visual global: kartu, scrollbar, dan transisi halus =====
           Ditambahkan lewat CSS agar tidak perlu mengubah class satu per satu di
           setiap halaman, dan tidak menyentuh fungsi/JS apa pun yang sudah ada. */

        /* Semua "kartu" putih bersudut membulat di seluruh sistem otomatis dapat
           bayangan lembut + transisi halus saat hover, biar terlihat lebih premium
           dan tidak datar. Selector di-scope ke dalam <main> supaya sidebar/topbar
           (yang juga punya elemen rounded-xl) tidak ikut berubah. */
        main .rounded-xl.border,
        main .rounded-lg.border {
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px -6px rgba(59, 35, 18, 0.08);
            transition: box-shadow .25s ease, transform .25s ease, border-color .25s ease;
        }
        main .bg-white.rounded-xl.border:hover {
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06), 0 14px 26px -8px rgba(59, 35, 18, 0.14);
            border-color: #E2D6C3;
        }

        /* Scrollbar tipis & elegan (webkit + firefox), konsisten dengan warna navy/gold */
        * { scrollbar-width: thin; scrollbar-color: #C9B48A #F1F0EC; }
        *::-webkit-scrollbar { width: 8px; height: 8px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb { background-color: #D8CBAE; border-radius: 999px; }
        *::-webkit-scrollbar-thumb:hover { background-color: #C9B48A; }

        /* Highlight seleksi teks & focus ring memakai warna khas brand, bukan biru bawaan browser */
        ::selection { background: #E8D9AE; color: #3B2312; }

        /* Body sedikit lebih lega & tipografi lebih tajam untuk keterbacaan */
        body { text-rendering: optimizeLegibility; -webkit-font-smoothing: antialiased; }
    </style>
    @stack('styles')
</head>
<body class="font-sans bg-canvas text-slate-800 antialiased">

    @hasSection('sidebar')
        <div class="flex min-h-screen">
            {{-- Overlay gelap di belakang sidebar, cuma muncul saat sidebar dibuka di HP/tablet --}}
            <div id="sidebarOverlay" class="fixed inset-0 bg-navy-950/50 z-30 hidden md:hidden"></div>

            {{-- Sidebar: di HP jadi panel geser (off-canvas) yang disembunyikan di luar layar,
                 di layar md ke atas (tablet lanskap/desktop) kembali statis seperti biasa. --}}
            <aside id="sidebarPanel"
                   class="fixed inset-y-0 left-0 z-40 w-72 max-w-[85vw] -translate-x-full
                          bg-navy-950 text-slate-200 flex flex-col
                          transition-transform duration-300 ease-in-out
                          md:static md:z-auto md:w-64 md:max-w-none md:shrink-0 md:translate-x-0">
                @yield('sidebar')
            </aside>

            <div class="flex-1 flex flex-col min-w-0">
                @include('components.topbar')
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    @yield('content')
                </main>
            </div>
        </div>

        @auth
            <a href="{{ route('messages.index') }}"
               data-tour="tour-live-chat"
               class="fixed bottom-5 right-5 sm:bottom-6 sm:right-6 w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-navy-900 hover:bg-navy-800 text-white shadow-lg flex items-center justify-center transition z-50"
               aria-label="Buka live chat">
                <i class="ti ti-message-circle text-xl sm:text-2xl"></i>
            </a>
        @endauth

        @push('scripts')
        <script>
            // Toggle sidebar off-canvas di layar kecil (HP/tablet). Di layar md ke atas
            // sidebar selalu statis (class md: di atas yang mengambil alih), jadi script
            // ini efektif hanya berpengaruh di bawah breakpoint md.
            (function () {
                const toggleBtn = document.getElementById('sidebarToggleBtn');
                const panel = document.getElementById('sidebarPanel');
                const overlay = document.getElementById('sidebarOverlay');
                if (!toggleBtn || !panel || !overlay) return;

                function openSidebar() {
                    panel.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                    document.documentElement.classList.add('overflow-hidden');
                }

                function closeSidebar() {
                    panel.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                    document.documentElement.classList.remove('overflow-hidden');
                }

                toggleBtn.addEventListener('click', () => {
                    panel.classList.contains('-translate-x-full') ? openSidebar() : closeSidebar();
                });

                overlay.addEventListener('click', closeSidebar);

                // Otomatis tutup sidebar setelah pilih salah satu menu (biar tidak
                // nutupin halaman baru yang sedang dibuka di HP).
                panel.querySelectorAll('a').forEach((link) => {
                    link.addEventListener('click', () => {
                        if (window.innerWidth < 768) closeSidebar();
                    });
                });

                // Kalau layar di-resize/rotasi ke ukuran desktop, pastikan sidebar & overlay
                // balik ke kondisi normal (tidak nyangkut ke-translate atau overlay nyala).
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 768) closeSidebar();
                });
            })();
        </script>
        @endpush
    @else
        <main>
            @yield('content')
        </main>
    @endif

    {{-- Onboarding tour / guided walkthrough: config-nya perlu route() dari Blade,
         jadi diisi di sini sebelum onboarding-tour.js dimuat. Route name dipakai
         untuk tahu tur sedang di halaman mana, role dipakai untuk pilih daftar
         langkah (admin/satker). --}}
    @auth
        <script>
            window.SikoorTourConfig = {
                role: @json(auth()->user()->role === 'admin' ? 'admin' : 'satker'),
                routeName: @json(Route::currentRouteName()),
                routes: {
                    'dashboard': @json(route('dashboard')),
                    'monitoring.ikpa': @json(route('monitoring.ikpa')),
                    'indicators.index': @json(route('indicators.index')),
                    'peringatan.index': @json(route('peringatan.index')),
                    'satkers.index': @json(route('satkers.index')),
                    'panduan.index': @json(route('panduan.index')),
                    'user.inbox': @json(route('user.inbox')),
                    'user.dashboard': @json(route('user.dashboard')),
                    'user.monitoring': @json(route('user.monitoring')),
                    'messages.index': @json(route('messages.index')),
                },
            };
        </script>
        <script src="{{ asset('js/onboarding-tour.js') }}"></script>
    @endauth

    @stack('scripts')
</body>
</html>