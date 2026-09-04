<header class="h-16 shrink-0 bg-white border-b border-slate-200 flex items-center justify-between gap-3 px-3 sm:px-6 relative">
    {{-- Aksen geometris tipis khas ukiran, sebagai border bawah header --}}
    <div class="absolute bottom-0 left-0 right-0 h-[3px] opacity-70"
         style="background-image: repeating-linear-gradient(135deg, #D4AF37 0 6px, transparent 6px 12px);"></div>

    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        {{-- Tombol buka/tutup sidebar, cuma tampil di HP/tablet (di layar md ke atas sidebar selalu terlihat) --}}
        <button id="sidebarToggleBtn" type="button"
                class="md:hidden shrink-0 w-10 h-10 -ml-1 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-100 active:bg-slate-200"
                aria-label="Buka menu navigasi">
            <i class="ti ti-menu-2 text-2xl"></i>
        </button>
        <h1 class="font-display font-semibold text-base sm:text-lg text-navy-900 truncate">@yield('page-title', 'Dashboard')</h1>
    </div>

    <div class="flex items-center gap-2 sm:gap-4 shrink-0">
        <div class="hidden lg:flex items-center gap-2 text-xs text-slate-500 pr-4 border-r border-slate-200">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            Sistem Online
            <span id="liveClock" class="font-medium text-slate-600 tabular-nums"></span>
        </div>

        @php
            $emailTujuanSatker = (auth()->user()->role ?? null) === 'admin'
                ? \App\Models\Satker::orderBy('nama_satker')->get(['id', 'nama_satker'])
                : collect();
        @endphp

        <button id="emailComposeBtn" type="button" class="relative text-slate-500 hover:text-navy-900" aria-label="Kirim Email">
            <i class="ti ti-mail text-xl"></i>
        </button>

        <div class="relative">

            <button id="notifBell" class="relative text-slate-500 hover:text-navy-900" aria-label="Notifikasi">
                <i class="ti ti-bell text-xl"></i>
                <span id="notifBadge" class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-gold-500 text-navy-950 text-2xs font-medium flex items-center justify-center"></span>
            </button>

            {{-- Dropdown notifikasi: lebar menyesuaikan layar biar tidak kepotong di HP --}}
            <div id="notifDropdown" class="hidden fixed sm:absolute left-3 right-3 sm:left-auto sm:right-0 top-16 sm:top-auto mt-0 sm:mt-2 w-auto sm:w-80 bg-white border border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                    <p class="text-sm font-medium text-slate-800">Notifikasi</p>
                    <button id="notifMarkAll" class="text-xs text-navy-800 hover:underline">Tandai semua dibaca</button>
                </div>
                <div id="notifList" class="max-h-96 overflow-y-auto divide-y divide-slate-100">
                    <p class="text-center text-xs text-slate-400 p-5">Memuat notifikasi...</p>
                </div>
            </div>
        </div>

        <div class="relative pl-2 sm:pl-4 sm:border-l border-slate-200">
            <button id="profileMenuBtn" type="button"
                    class="flex items-center gap-2 sm:gap-3 rounded-lg hover:bg-slate-50 pr-1 sm:pr-2 py-1 transition"
                    aria-haspopup="true" aria-expanded="false">
                <div class="w-9 h-9 rounded-full bg-navy-900 text-white flex items-center justify-center text-sm font-medium shrink-0">
                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                </div>
                {{-- Nama & role disembunyikan di layar sangat kecil supaya topbar tidak sesak --}}
                <div class="hidden sm:block text-sm leading-tight text-left">
                    <p class="font-medium text-slate-800">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-slate-400 text-xs">{{ ucfirst(auth()->user()->role ?? 'admin') }}</p>
                </div>
                <i class="ti ti-chevron-down text-slate-400 text-base ml-1"></i>
            </button>

            {{-- Dropdown menu profil --}}
            <div id="profileMenuDropdown" class="hidden absolute right-0 mt-2 w-56 max-w-[calc(100vw-1.5rem)] bg-white border border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <p class="text-sm font-medium text-slate-800 truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-navy-900">
                    <i class="ti ti-user-circle text-lg"></i>
                    Profil saya
                </a>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                        <i class="ti ti-logout text-lg"></i>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

{{-- ================= MODAL "Kirim Email" (akses cepat, kirim ke email login) ================= --}}
<div id="emailComposeOverlay" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div>
                <p class="text-sm font-medium text-slate-700">Kirim Email</p>
                <p class="text-xs text-slate-400 mt-0.5">
                    @if ((auth()->user()->role ?? null) === 'admin')
                        Terkirim ke alamat email LOGIN satker tujuan.
                    @else
                        Terkirim ke alamat email LOGIN semua admin.
                    @endif
                </p>
            </div>
            <button type="button" id="emailComposeClose" class="text-slate-400 hover:text-slate-600">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>
        <form id="emailComposeForm" class="p-5 space-y-3">
            @if ((auth()->user()->role ?? null) === 'admin')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Kirim ke Satker</label>
                    <select name="satker_id" required
                            class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
                        <option value="">— Pilih satker —</option>
                        @foreach ($emailTujuanSatker as $s)
                            <option value="{{ $s->id }}">{{ $s->nama_satker }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Subjek</label>
                <input type="text" name="subjek" maxlength="255" placeholder="(opsional)"
                       class="w-full h-10 px-3 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Pesan</label>
                <textarea name="pesan" rows="5" required maxlength="2000"
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-navy-800"
                          placeholder="Tulis pesan di sini..."></textarea>
            </div>
            <div id="emailComposeAlert" class="text-xs"></div>
            <button type="submit"
                    class="w-full h-10 rounded-lg bg-navy-900 hover:bg-navy-800 text-white text-sm font-semibold transition">
                Kirim Email
            </button>
        </form>
    </div>
</div>

@once
@push('scripts')
<script>
    (function () {
        const emailBtn = document.getElementById('emailComposeBtn');
        const emailOverlay = document.getElementById('emailComposeOverlay');
        const emailClose = document.getElementById('emailComposeClose');
        const emailForm = document.getElementById('emailComposeForm');
        const emailAlert = document.getElementById('emailComposeAlert');
        const emailCsrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        emailBtn.addEventListener('click', () => {
            emailAlert.textContent = '';
            emailOverlay.classList.remove('hidden');
        });

        emailClose.addEventListener('click', () => emailOverlay.classList.add('hidden'));
        emailOverlay.addEventListener('click', (e) => {
            if (e.target === emailOverlay) emailOverlay.classList.add('hidden');
        });

        emailForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = emailForm.querySelector('button[type="submit"]');
            const teksAsli = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Mengirim...';
            emailAlert.textContent = '';

            try {
                const res = await fetch('{{ route('messages.sendEmail') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': emailCsrfToken,
                        'Accept': 'application/json',
                    },
                    body: new FormData(emailForm),
                });
                const data = await res.json().catch(() => null);

                if (!res.ok) {
                    const pesan = data?.message || (data?.errors ? Object.values(data.errors).flat().join(' ') : 'Gagal mengirim email.');
                    throw new Error(pesan);
                }

                emailAlert.className = 'text-xs text-emerald-600';
                emailAlert.textContent = data.status || 'Email terkirim!';
                emailForm.reset();
                setTimeout(() => emailOverlay.classList.add('hidden'), 1200);
            } catch (err) {
                emailAlert.className = 'text-xs text-red-500';
                emailAlert.textContent = err.message;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = teksAsli;
            }
        });
    })();
</script>
@endpush
@endonce

@once
@push('scripts')
<script>
    (function () {
        const bell = document.getElementById('notifBell');
        const dropdown = document.getElementById('notifDropdown');
        const badge = document.getElementById('notifBadge');
        const list = document.getElementById('notifList');
        const markAllBtn = document.getElementById('notifMarkAll');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function timeAgo(dateStr) {
            const diffSec = Math.floor((Date.now() - new Date(dateStr)) / 1000);
            if (diffSec < 60) return 'Baru saja';
            const diffMin = Math.floor(diffSec / 60);
            if (diffMin < 60) return `${diffMin} menit lalu`;
            const diffHour = Math.floor(diffMin / 60);
            if (diffHour < 24) return `${diffHour} jam lalu`;
            const diffDay = Math.floor(diffHour / 24);
            return `${diffDay} hari lalu`;
        }

        function iconFor(type) {
            return type === 'chat' ? 'ti-message-circle' : 'ti-file-text';
        }

        function renderNotifications(notifications) {
            if (notifications.length === 0) {
                list.innerHTML = '<p class="text-center text-xs text-slate-400 p-5">Belum ada notifikasi.</p>';
                return;
            }

            list.innerHTML = '';
            notifications.forEach((n) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = `w-full text-left flex items-start gap-3 px-4 py-3 hover:bg-slate-50 ${n.read_at ? '' : 'bg-slate-50/70'}`;
                item.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-navy-900/10 text-navy-900 flex items-center justify-center shrink-0">
                        <i class="ti ${iconFor(n.type)} text-base"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800 truncate"></p>
                        <p class="text-xs text-slate-500 line-clamp-2"></p>
                        <p class="text-2xs text-slate-400 mt-0.5"></p>
                    </div>
                    ${n.read_at ? '' : '<span class="w-2 h-2 rounded-full bg-gold-500 mt-1.5 shrink-0"></span>'}
                `;
                item.querySelector('.font-medium').textContent = n.title;
                item.querySelectorAll('p')[1].textContent = n.body;
                item.querySelectorAll('p')[2].textContent = timeAgo(n.created_at);

                item.addEventListener('click', async () => {
                    try {
                        await fetch(`/notifications/${n.id}/read`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        });
                    } catch (e) { /* diamkan, tetap lanjut navigasi */ }

                    if (n.link) window.location.href = n.link;
                });

                list.appendChild(item);
            });
        }

        async function loadNotifications() {
            try {
                const res = await fetch('{{ route('notifications.data') }}', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error('Gagal memuat notifikasi');
                const data = await res.json();

                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }

                renderNotifications(data.notifications);
            } catch (err) {
                list.innerHTML = '<p class="text-center text-xs text-red-500 p-5">Gagal memuat notifikasi.</p>';
            }
        }

        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
            if (!dropdown.classList.contains('hidden')) loadNotifications();
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== bell) {
                dropdown.classList.add('hidden');
            }
        });

        const profileBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileMenuDropdown');

        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const willShow = profileDropdown.classList.contains('hidden');
            profileDropdown.classList.toggle('hidden');
            profileBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });

        document.addEventListener('click', (e) => {
            if (!profileDropdown.contains(e.target) && e.target !== profileBtn && !profileBtn.contains(e.target)) {
                profileDropdown.classList.add('hidden');
                profileBtn.setAttribute('aria-expanded', 'false');
            }
        });

        markAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            try {
                await fetch('{{ route('notifications.readAll') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                loadNotifications();
            } catch (err) {
                alert('Gagal menandai semua dibaca.');
            }
        });

        loadNotifications();

        setInterval(loadNotifications, 10000);

        const liveClock = document.getElementById('liveClock');
        if (liveClock) {
            function updateClock() {
                const now = new Date();
                const time = now.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit' });
                liveClock.textContent = `${time} WIB`;
            }
            updateClock();
            setInterval(updateClock, 1000);
        }
    })();
</script>
@endpush
@endonce