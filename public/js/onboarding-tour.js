/**
 * SIKOOR — Onboarding Tour / Guided Walkthrough
 * ------------------------------------------------
 * Spotlight satu elemen UI pada satu waktu + kotak popup kecil (dengan panah)
 * yang menjelaskan fitur itu. Tur bisa melompat antar halaman (mis. dari
 * Dashboard ke Monitoring IKPA) — progresnya disimpan di sessionStorage
 * supaya lanjut otomatis begitu halaman tujuan selesai dimuat.
 *
 * Pemakaian dari Blade:
 *   window.SikoorTourConfig = { role, routeName, routes: {...} };
 *   SikoorTour.start('admin');   // atau SikoorTour.start('satker')
 */
(function (window, document) {
    'use strict';

    // ================= DEFINISI LANGKAH TUR =================
    // route  : nama route Laravel (harus ada di SikoorTourConfig.routes)
    // selector: elemen yang disorot (null = kotak tengah, tanpa spotlight)
    // placement: sisi kotak popup relatif ke elemen ('top'|'bottom'|'left'|'right')
    var STEPS = {
        admin: [
            { route: 'panduan.index', selector: null,
                title: 'Selamat datang di SIKOOR 👋',
                text: 'Panduan ini akan mengajak Anda berkeliling ke fitur-fitur utama sebagai Admin, langkah demi langkah. Beberapa langkah akan otomatis membuka halaman terkait. Klik "Selanjutnya" untuk mulai.' },

            { route: 'panduan.index', selector: '[data-tour="tour-menu-dashboard"]', placement: 'right',
                title: '1. Dashboard',
                text: 'Halaman utama: ringkasan Nilai IKPA seluruh satker, jumlah satker per kategori Hijau/Kuning/Merah, grafik tren, dan daftar prioritas pembinaan. Klik Selanjutnya, Anda akan diarahkan ke halaman ini.' },

            { route: 'dashboard', selector: '[data-tour="tour-dashboard-filter"]', placement: 'bottom',
                title: 'Filter periode',
                text: 'Pilih tampilan Bulanan / Triwulan / Semester / Tahunan, dan bisa difilter per satker juga.' },

            { route: 'dashboard', selector: '[data-tour="tour-dashboard-ringkasan"]', placement: 'bottom',
                title: 'Kartu ringkasan',
                text: 'Rata-rata Nilai IKPA dan jumlah satker per kategori, sekilas lihat langsung tampil di sini.' },

            { route: 'dashboard', selector: '#prioritas', placement: 'top',
                title: 'Prioritas pembinaan',
                text: 'Daftar satker yang paling memerlukan perhatian, diurutkan dari prioritas tertinggi.' },

            { route: 'dashboard', selector: '[data-tour="tour-menu-monitoring"]', placement: 'right',
                title: '2. Monitoring IKPA',
                text: 'Selanjutnya kita lihat tabel lengkap Nilai IKPA semua satker beserta rincian poin per jenis indikator.' },

            { route: 'monitoring.ikpa', selector: '[data-tour="tour-monitoring-filter"]', placement: 'bottom',
                title: 'Filter tabel',
                text: 'Bisa difilter per satker dan per periode, sama seperti di Dashboard.' },

            { route: 'monitoring.ikpa', selector: '.js-open-satker-modal', placement: 'left',
                title: 'Nilai laporan satker',
                text: 'Klik ikon mata untuk membuka panel penilaian satker itu langsung di halaman ini — pilih tugasnya, isi Status/Catatan/Tindak lanjut, lalu simpan.' },

            { route: 'monitoring.ikpa', selector: '[data-tour="tour-monitoring-cetak-semua"]', placement: 'bottom',
                title: 'Cetak semua nilai',
                text: 'Cetak rekap Nilai IKPA seluruh satker sekaligus. Ada juga ikon printer di setiap baris untuk mencetak satu satker saja.' },

            { route: 'monitoring.ikpa', selector: '[data-tour="tour-menu-indicators"]', placement: 'right',
                title: '3. Indicators',
                text: 'Sekarang ke menu tempat membuat & mengirim tugas/indikator ke satker.' },

            { route: 'indicators.index', selector: '#indicatorForm', placement: 'right',
                title: 'Buat indikator baru',
                text: 'Buat & kirim tugas ke satu atau beberapa satker sekaligus, lengkap dengan lampiran PDF/Excel kalau perlu.' },

            { route: 'indicators.index', selector: '[data-tour="tour-indicators-riwayat"]', placement: 'bottom',
                title: 'Riwayat pengiriman',
                text: 'Menampilkan semua batch pengiriman yang pernah dibuat beserta progres satker yang sudah lapor. Di halaman detail tugas juga ada tombol "Ganti lampiran" kalau file yang di-upload sebelumnya salah/rusak.' },

            { route: 'indicators.index', selector: '[data-tour="tour-menu-peringatan"]', placement: 'right',
                title: '4. Peringatan Satker',
                text: 'Berikutnya, cara mengirim peringatan manual ke satker.' },

            { route: 'peringatan.index', selector: '#peringatanForm', placement: 'right',
                title: 'Kirim peringatan',
                text: 'Kirim peringatan manual (running text) ke satker kategori merah, misalnya untuk mengingatkan batas waktu. Peringatan ini otomatis muncul di halaman Dokumen Masuk satker yang bersangkutan.' },

            { route: 'peringatan.index', selector: '[data-tour="tour-menu-satkers"]', placement: 'right',
                title: '5. Kelola Satker',
                text: 'Terakhir, tempat mengelola data & akun login satker.' },

            { route: 'satkers.index', selector: '#satkerTambahForm', placement: 'right',
                title: 'Tambah satker',
                text: 'Tambah data satker baru beserta akun login-nya. Akun baru otomatis diwajibkan ganti password saat login pertama kali.' },

            { route: 'satkers.index', selector: '[data-tour="tour-satkers-cetak-kredensial"]', placement: 'bottom',
                title: 'Cetak kredensial',
                text: 'Me-reset password SEMUA akun satker sekaligus ke password acak baru, lalu bisa dicetak/di-download.' },

            { route: 'satkers.index', selector: '[data-tour="tour-live-chat"]', placement: 'left',
                title: '6. Live Chat',
                text: 'Ikon ini ada di semua halaman — membuka percakapan langsung dengan satker mana pun untuk koordinasi cepat.' },

            { route: 'satkers.index', selector: null,
                title: 'Selesai! 🎉',
                text: 'Anda sudah berkeliling semua fitur utama sebagai Admin. Panduan ini bisa dibuka lagi kapan saja dari menu "Panduan Penggunaan".' },
        ],

        satker: [
            { route: 'panduan.index', selector: null,
                title: 'Selamat datang di SIKOOR 👋',
                text: 'Panduan ini akan mengajak Anda berkeliling ke fitur-fitur utama untuk akun Satker, langkah demi langkah. Klik "Selanjutnya" untuk mulai.' },

            { route: 'panduan.index', selector: '[data-tour="tour-menu-dashboard-satker"]', placement: 'right',
                title: '1. Dashboard Satker',
                text: 'Ringkasan performa satker Anda sendiri: Nilai IKPA, status kategori, nilai per indikator, tren, sampai peringkat Anda dibanding satker lain — semua di satu halaman.' },

            { route: 'user.dashboard', selector: '#satkerRingkasan', placement: 'bottom',
                title: 'Ringkasan performa',
                text: 'Nilai IKPA bulan berjalan, kategori (Merah/Kuning/Hijau), dan grafik tren tahun berjalan.' },

            { route: 'user.dashboard', selector: '#satkerPeringkat', placement: 'bottom',
                title: 'Peringkat Anda',
                text: 'Lihat peringkat satker Anda dibanding satker lain, dan tren nilai Anda sendiri.' },

            { route: 'user.dashboard', selector: '[data-tour="tour-menu-inbox"]', placement: 'right',
                title: '2. Dokumen Masuk',
                text: 'Selanjutnya, halaman yang menampilkan semua tugas/indikator yang dikirim admin untuk satker Anda. Klik Selanjutnya untuk melihat halamannya.' },

            { route: 'user.inbox', selector: '#satkerInboxList', placement: 'top',
                title: 'Daftar tugas',
                text: 'Kalau ada lampiran PDF dari admin dan tugasnya belum dinilai, pratinjaunya tampil otomatis. Status tiap tugas ditandai badge: Belum ada laporan, Menunggu dinilai, Diterima, atau Perlu direvisi.' },

            { route: 'user.inbox', selector: '[data-tour="tour-live-chat"]', placement: 'left',
                title: '3. Live Chat',
                text: 'Ikon ini ada di semua halaman — untuk koordinasi langsung dengan admin.' },

            { route: 'user.inbox', selector: null,
                title: 'Selesai! 🎉',
                text: 'Anda sudah berkeliling semua fitur utama. Panduan ini bisa dibuka lagi kapan saja dari menu "Panduan Penggunaan".' },
        ],
    };

    var STORAGE_KEY = 'sikoorTourState';
    var els = {};        // node-node overlay/popup yang sedang aktif
    var repositionFn = null;

    function cfg() {
        return window.SikoorTourConfig || {};
    }

    function saveState(role, index) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ role: role, index: index }));
        } catch (e) { /* sessionStorage tidak tersedia — tur cukup jalan tanpa lanjut-otomatis */ }
    }

    function loadState() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function clearState() {
        try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* noop */ }
    }

    function teardown() {
        if (repositionFn) {
            window.removeEventListener('resize', repositionFn);
            window.removeEventListener('scroll', repositionFn, true);
            repositionFn = null;
        }
        Object.keys(els).forEach(function (k) {
            if (els[k] && els[k].parentNode) els[k].parentNode.removeChild(els[k]);
        });
        els = {};
        document.removeEventListener('keydown', onKeydown);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') skip();
    }

    function openSidebarIfNeeded(target) {
        var panel = document.getElementById('sidebarPanel');
        if (panel && target && panel.contains(target)) {
            panel.classList.remove('-translate-x-full');
        }
    }

    function ensureStyles() {
        if (document.getElementById('sikoorTourStyles')) return;
        var style = document.createElement('style');
        style.id = 'sikoorTourStyles';
        style.textContent =
            '.sikoor-tour-overlay{position:fixed;inset:0;z-index:9998;transition:box-shadow .25s ease,top .25s ease,left .25s ease,width .25s ease,height .25s ease;border-radius:10px;pointer-events:none;}' +
            '.sikoor-tour-backdrop{position:fixed;inset:0;z-index:9997;background:rgba(15,23,42,.55);}' +
            '.sikoor-tour-ring{position:fixed;z-index:9998;border-radius:10px;box-shadow:0 0 0 4px #D4AF37,0 0 0 9999px rgba(15,23,42,.55);transition:top .25s ease,left .25s ease,width .25s ease,height .25s ease;pointer-events:none;}' +
            '.sikoor-tour-popup{position:fixed;z-index:9999;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);max-width:320px;padding:18px 20px 16px;font-family:Inter,sans-serif;transition:top .2s ease,left .2s ease;}' +
            '.sikoor-tour-popup.sikoor-tour-center{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);max-width:380px;}' +
            '.sikoor-tour-arrow{position:absolute;width:14px;height:14px;background:#fff;transform:rotate(45deg);}' +
            '.sikoor-tour-close{position:absolute;top:10px;right:10px;width:24px;height:24px;border:none;background:transparent;color:#cbd5e1;cursor:pointer;font-size:15px;line-height:1;border-radius:6px;display:flex;align-items:center;justify-content:center;}' +
            '.sikoor-tour-close:hover{background:#f1f5f9;color:#64748b;}' +
            '.sikoor-tour-title{font-family:"Plus Jakarta Sans",sans-serif;font-weight:700;font-size:14px;color:#3B2312;margin:0 26px 6px 0;}' +
            '.sikoor-tour-text{font-size:13px;line-height:1.5rem;color:#475569;margin:0 0 16px;}' +
            '.sikoor-tour-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;padding-top:12px;border-top:1px solid #f1f5f9;}' +
            '.sikoor-tour-progress{font-size:11px;color:#94a3b8;white-space:nowrap;flex-shrink:0;}' +
            '.sikoor-tour-btns{display:flex;align-items:center;gap:8px;margin-left:auto;flex-shrink:0;}' +
            '.sikoor-tour-btn{border:none;cursor:pointer;font-size:12.5px;font-weight:600;padding:7px 14px;border-radius:8px;font-family:Inter,sans-serif;white-space:nowrap;flex-shrink:0;}' +
            '.sikoor-tour-btn-prev{background:#f1f5f9;color:#334155;}' +
            '.sikoor-tour-btn-prev:hover{background:#e2e8f0;}' +
            '.sikoor-tour-btn-next{background:#3B2312;color:#fff;}' +
            '.sikoor-tour-btn-next:hover{background:#4A2E16;}' +
            '@media (max-width:480px){.sikoor-tour-popup{max-width:88vw;}.sikoor-tour-footer{justify-content:flex-end;}.sikoor-tour-progress{width:100%;order:-1;margin-bottom:2px;}}';
        document.head.appendChild(style);
    }

    function findElement(selector, cb, triesLeft) {
        triesLeft = triesLeft === undefined ? 20 : triesLeft;
        var el = null;
        try { el = document.querySelector(selector); } catch (e) { el = null; }
        if (el) { cb(el); return; }
        if (triesLeft <= 0) { cb(null); return; }
        setTimeout(function () { findElement(selector, cb, triesLeft - 1); }, 120);
    }

    function computePlacement(rect, preferred) {
        var vw = window.innerWidth, vh = window.innerHeight;
        var space = { top: rect.top, bottom: vh - rect.bottom, left: rect.left, right: vw - rect.right };
        var order = [preferred || 'bottom', 'bottom', 'top', 'right', 'left'];
        for (var i = 0; i < order.length; i++) {
            if (space[order[i]] > 130) return order[i];
        }
        return 'bottom';
    }

    function renderPopupAt(rect, placement, title, text, footer) {
        var popup = document.createElement('div');
        popup.className = 'sikoor-tour-popup';
        var arrow = document.createElement('div');
        arrow.className = 'sikoor-tour-arrow';

        popup.innerHTML =
            '<p class="sikoor-tour-title"></p>' +
            '<p class="sikoor-tour-text"></p>';
        popup.querySelector('.sikoor-tour-title').textContent = title;
        popup.querySelector('.sikoor-tour-text').textContent = text;
        popup.appendChild(buildCloseBtn(skip));
        popup.appendChild(footer);
        document.body.appendChild(popup);
        popup.appendChild(arrow);

        var pw = popup.offsetWidth, ph = popup.offsetHeight;
        var gap = 14, top, left, arrowStyle = {};

        if (placement === 'bottom') {
            top = rect.bottom + gap; left = rect.left + rect.width / 2 - pw / 2;
            arrowStyle = { top: '-6px', left: (pw / 2 - 7) + 'px' };
        } else if (placement === 'top') {
            top = rect.top - ph - gap; left = rect.left + rect.width / 2 - pw / 2;
            arrowStyle = { bottom: '-6px', top: 'auto', left: (pw / 2 - 7) + 'px' };
        } else if (placement === 'right') {
            top = rect.top + rect.height / 2 - ph / 2; left = rect.right + gap;
            arrowStyle = { left: '-6px', top: (ph / 2 - 7) + 'px' };
        } else { // left
            top = rect.top + rect.height / 2 - ph / 2; left = rect.left - pw - gap;
            arrowStyle = { right: '-6px', left: 'auto', top: (ph / 2 - 7) + 'px' };
        }

        left = Math.max(10, Math.min(left, window.innerWidth - pw - 10));
        top = Math.max(10, Math.min(top, window.innerHeight - ph - 10));

        popup.style.top = top + 'px';
        popup.style.left = left + 'px';
        Object.keys(arrowStyle).forEach(function (k) { arrow.style[k] = arrowStyle[k]; });

        return popup;
    }

    function buildFooter(index, total, onPrev, onNext, onSkip) {
        var footer = document.createElement('div');
        footer.className = 'sikoor-tour-footer';

        var progress = document.createElement('span');
        progress.className = 'sikoor-tour-progress';
        progress.textContent = 'Langkah ' + (index + 1) + ' dari ' + total;

        var btns = document.createElement('div');
        btns.className = 'sikoor-tour-btns';

        if (index > 0) {
            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'sikoor-tour-btn sikoor-tour-btn-prev';
            prevBtn.textContent = 'Sebelumnya';
            prevBtn.addEventListener('click', onPrev);
            btns.appendChild(prevBtn);
        }

        var nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'sikoor-tour-btn sikoor-tour-btn-next';
        nextBtn.textContent = index >= total - 1 ? 'Selesai' : 'Selanjutnya';
        nextBtn.addEventListener('click', onNext);
        btns.appendChild(nextBtn);

        footer.appendChild(progress);
        footer.appendChild(btns);
        return footer;
    }

    function buildCloseBtn(onSkip) {
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'sikoor-tour-close';
        closeBtn.setAttribute('aria-label', 'Lewati panduan');
        closeBtn.title = 'Lewati panduan';
        closeBtn.innerHTML = '&#10005;';
        closeBtn.addEventListener('click', onSkip);
        return closeBtn;
    }

    function renderCentered(step, index, total, role) {
        teardown();
        ensureStyles();

        var backdrop = document.createElement('div');
        backdrop.className = 'sikoor-tour-backdrop';
        document.body.appendChild(backdrop);
        els.backdrop = backdrop;

        var footer = buildFooter(index, total,
            function () { goToStep(role, index - 1); },
            function () { goToStep(role, index + 1); },
            skip);

        var popup = document.createElement('div');
        popup.className = 'sikoor-tour-popup sikoor-tour-center';
        popup.innerHTML = '<p class="sikoor-tour-title"></p><p class="sikoor-tour-text"></p>';
        popup.querySelector('.sikoor-tour-title').textContent = step.title;
        popup.querySelector('.sikoor-tour-text').textContent = step.text;
        popup.appendChild(buildCloseBtn(skip));
        popup.appendChild(footer);
        document.body.appendChild(popup);
        els.popup = popup;

        document.addEventListener('keydown', onKeydown);
    }

    function renderSpotlight(step, index, total, role, el) {
        teardown();
        ensureStyles();
        openSidebarIfNeeded(el);

        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        setTimeout(function () {
            var draw = function () {
                var rect = el.getBoundingClientRect();
                var pad = 6;
                var ring = els.ring;
                if (!ring) {
                    ring = document.createElement('div');
                    ring.className = 'sikoor-tour-ring';
                    document.body.appendChild(ring);
                    els.ring = ring;
                }
                ring.style.top = (rect.top - pad) + 'px';
                ring.style.left = (rect.left - pad) + 'px';
                ring.style.width = (rect.width + pad * 2) + 'px';
                ring.style.height = (rect.height + pad * 2) + 'px';

                if (els.popup && els.popup.parentNode) {
                    els.popup.parentNode.removeChild(els.popup);
                }
                var placement = computePlacement(rect, step.placement);
                var footer = buildFooter(index, total,
                    function () { goToStep(role, index - 1); },
                    function () { goToStep(role, index + 1); },
                    skip);
                els.popup = renderPopupAt(rect, placement, step.title, step.text, footer);
            };

            draw();
            repositionFn = draw;
            window.addEventListener('resize', repositionFn);
            window.addEventListener('scroll', repositionFn, true);
            document.addEventListener('keydown', onKeydown);
        }, 280);
    }

    function renderStep(role, index) {
        var steps = STEPS[role];
        if (!steps || !steps[index]) { clearState(); teardown(); return; }
        var step = steps[index];
        var total = steps.length;

        if (!step.selector) {
            renderCentered(step, index, total, role);
            return;
        }

        findElement(step.selector, function (el) {
            if (el) {
                renderSpotlight(step, index, total, role, el);
            } else {
                // Elemen tidak ditemukan (mis. beda kondisi data) — tetap tampilkan
                // penjelasannya lewat kotak tengah supaya tur tidak macet.
                renderCentered(step, index, total, role);
            }
        });
    }

    function goToStep(role, index) {
        var steps = STEPS[role];
        if (!steps) return;
        if (index < 0) index = 0;
        if (index >= steps.length) { clearState(); teardown(); return; }

        var step = steps[index];
        var current = cfg().routeName;

        saveState(role, index);

        if (step.route !== current) {
            var url = (cfg().routes || {})[step.route];
            if (!url) { clearState(); teardown(); return; }
            window.location.href = url;
            return;
        }

        renderStep(role, index);
    }

    function skip() {
        clearState();
        teardown();
    }

    function start(role) {
        saveState(role, 0);
        goToStep(role, 0);
    }

    function resume() {
        var state = loadState();
        if (!state || !STEPS[state.role]) return;
        var step = STEPS[state.role][state.index];
        if (!step) { clearState(); return; }
        if (step.route !== cfg().routeName) return; // tunggu sampai user ada di halaman yang tepat
        renderStep(state.role, state.index);
    }

    window.SikoorTour = { start: start, skip: skip, resume: resume };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resume);
    } else {
        resume();
    }
})(window, document);