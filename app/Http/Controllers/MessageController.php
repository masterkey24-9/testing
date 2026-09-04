<?php

namespace App\Http\Controllers;

use App\Mail\PesanEmail;
use App\Models\Message;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class MessageController extends Controller
{
    /**
     * Ambang batas "online": kalau heartbeat terakhir kurang dari sekian detik yang lalu,
     * dianggap online. Dipakai di beberapa tempat, jadi disatukan di sini.
     */
    private const ONLINE_THRESHOLD_SECONDS = 30;

    private function isOnline(?\Illuminate\Support\Carbon $lastSeenAt): bool
    {
        return $lastSeenAt !== null && $lastSeenAt->gt(now()->subSeconds(self::ONLINE_THRESHOLD_SECONDS));
    }

    /**
     * Teks "terakhir online" yang manusiawi, atau null kalau belum pernah online sama sekali.
     */
    private function lastSeenLabel(?\Illuminate\Support\Carbon $lastSeenAt): ?string
    {
        return $lastSeenAt ? $lastSeenAt->diffForHumans() : null;
    }

    /**
     * Menampilkan halaman Live chat.
     *
     * - Admin: melihat daftar semua Satker (untuk dipilih thread-nya) di sisi kiri.
     * - Satker: langsung masuk ke thread miliknya sendiri (hanya dengan Admin).
     */
    public function index()
    {
        if (Auth::user()->role === 'admin') {
            $satkers = Satker::with('user:id,satker_id,last_seen_at')->orderBy('nama_satker')->get()->map(function ($satker) {
                $lastMessage = Message::where('satker_id', $satker->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $satker->last_pesan = $lastMessage->pesan ?? 'Belum ada pesan';

                $lastSeenAt = $satker->user->last_seen_at ?? null;
                $satker->is_online = $this->isOnline($lastSeenAt);
                $satker->last_seen_label = $this->lastSeenLabel($lastSeenAt);

                return $satker;
            });

            return view('admin.chat', compact('satkers'));
        }

        return view('user.chat');
    }

    /**
     * Mengambil riwayat pesan dalam format JSON (dipakai oleh JS di halaman chat).
     */
    public function data(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            $request->validate([
                'satker_id' => 'required|exists:satkers,id',
            ]);
            $satkerId = $request->query('satker_id');
        } else {
            $satkerId = $user->satker_id;

            if (! $satkerId) {
                return response()->json(['message' => 'Akun ini belum terhubung ke Satker manapun.'], 422);
            }
        }

        $messages = Message::with('user:id,name,role')
            ->where('satker_id', $satkerId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    /**
     * Menyimpan pesan baru ke database (satu thread, satu satker).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'pesan' => 'required|string|max:1000',
        ]);

        if ($user->role === 'admin') {
            $request->validate([
                'satker_id' => 'required|exists:satkers,id',
            ]);
            $satkerId = $request->satker_id;
        } else {
            $satkerId = $user->satker_id;

            if (! $satkerId) {
                return response()->json(['message' => 'Akun ini belum terhubung ke Satker manapun.'], 422);
            }
        }

        $message = Message::create([
            'user_id' => $user->id,
            'satker_id' => $satkerId,
            'pesan' => $request->pesan,
        ]);

        Cache::forget("chat_typing_{$satkerId}_{$user->role}");

        event(new \App\Events\MessageSent($message));

        $satkerNama = Satker::find($satkerId)->nama_satker ?? 'Satker';
        \App\Http\Controllers\NotificationController::notifyNewMessage($user, $satkerId, $satkerNama, $request->pesan);

        return response()->json(['status' => 'Pesan terkirim!', 'message' => $message->load('user:id,name,role')]);
    }

    /**
     * Kirim pesan lewat EMAIL SUNGGUHAN, ke alamat email LOGIN penerima (bukan alamat
     * email terpisah — sesuai permintaan: "pakai email login saja"). Berlaku dua arah:
     * - Admin mengirim ke satu satker tertentu → email masuk ke `satker->user->email`.
     * - Satker mengirim (balasan) → email masuk ke SEMUA akun admin.
     *
     * Pesan yang terkirim juga dicatat di tabel `messages` (channel='email') supaya
     * riwayatnya ikut muncul di thread Live Chat yang sama — jadi satu percakapan,
     * dua cara pengiriman.
     *
     * PENTING: fitur ini betulan mengirim email lewat SMTP. Supaya email SAMPAI ke
     * kotak masuk penerima, konfigurasi MAIL_* di file .env harus diisi kredensial
     * SMTP yang asli (Gmail App Password, SMTP kantor, dst) — bukan lagi nilai
     * default/placeholder. "Menerima balasan" di sini artinya penerima membalas ke
     * email aslinya (Gmail/Outlook mereka) atau membalas lewat Live Chat di aplikasi;
     * aplikasi ini TIDAK memantau kotak masuk email secara otomatis.
     */
    public function sendEmail(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'pesan' => 'required|string|max:2000',
            'subjek' => 'nullable|string|max:255',
        ]);

        if ($user->role === 'admin') {
            $request->validate(['satker_id' => 'required|exists:satkers,id']);
            $satkerId = $request->satker_id;

            $satker = Satker::with('user')->find($satkerId);
            if (! $satker || ! $satker->user || ! $satker->user->email) {
                return response()->json(['message' => 'Satker ini belum punya akun/email login.'], 422);
            }

            $tujuanEmail = $satker->user->email;
            $subjek = $request->subjek ?: "Pesan dari Admin Polda Sumbar";
        } else {
            $satkerId = $user->satker_id;
            if (! $satkerId) {
                return response()->json(['message' => 'Akun ini belum terhubung ke Satker manapun.'], 422);
            }

            // Satker mengirim ke SEMUA admin sekaligus.
            $adminEmails = User::where('role', 'admin')->whereNotNull('email')->pluck('email');
            if ($adminEmails->isEmpty()) {
                return response()->json(['message' => 'Belum ada akun admin dengan email terdaftar.'], 422);
            }

            $tujuanEmail = $adminEmails;
            $subjek = $request->subjek ?: "Pesan dari {$user->name}";
        }

        // Catat ke tabel messages (channel='email') supaya riwayatnya nyambung sama thread chat.
        $message = Message::create([
            'user_id' => $user->id,
            'satker_id' => $satkerId,
            'pesan' => $request->pesan,
            'channel' => 'email',
            'subjek' => $subjek,
        ]);

        try {
            Mail::to($tujuanEmail)->send(new PesanEmail($user->name, $subjek, $request->pesan));
        } catch (\Throwable $e) {
            // Pesan tetap tersimpan di riwayat chat walau pengiriman email gagal (misal
            // SMTP belum dikonfigurasi) — admin/satker tetap lihat isi pesannya di app,
            // cuma nggak nyampe ke kotak masuk email penerima.
            return response()->json([
                'status' => 'Pesan tersimpan, tapi gagal terkirim lewat email (cek konfigurasi SMTP di .env).',
                'message' => $message->load('user:id,name,role'),
            ], 200);
        }

        return response()->json(['status' => 'Email terkirim!', 'message' => $message->load('user:id,name,role')]);
    }

    /**
     * Kirim satu pesan yang sama ke SEMUA satker sekaligus (broadcast admin).
     * Setiap satker tetap dapat baris pesan masing-masing di thread-nya sendiri
     * (tabel `messages` tidak berubah struktur), jadi tetap kompatibel dengan
     * fitur notifikasi & event realtime yang sudah ada per-thread.
     */
    public function broadcastStore(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa mengirim pesan ke semua satker.'], 403);
        }

        $request->validate([
            'pesan' => 'required|string|max:1000',
        ]);

        $satkers = Satker::all();
        $terkirim = 0;

        foreach ($satkers as $satker) {
            $message = Message::create([
                'user_id' => $user->id,
                'satker_id' => $satker->id,
                'pesan' => $request->pesan,
            ]);

            event(new \App\Events\MessageSent($message));

            \App\Http\Controllers\NotificationController::notifyNewMessage(
                $user, $satker->id, $satker->nama_satker, $request->pesan
            );

            $terkirim++;
        }

        return response()->json([
            'status' => "Pesan terkirim ke {$terkirim} satker.",
            'pesan' => $request->pesan,
        ]);
    }

    /**
     * "Detak jantung" kehadiran — dipanggil berkala (tiap ~15 detik) dari JS selama halaman
     * chat terbuka. Nyimpen waktu heartbeat terakhir ke kolom users.last_seen_at (persisten).
     */
    public function heartbeat()
    {
        Auth::user()->update(['last_seen_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Menandai user ini "sedang mengetik" di thread satker tertentu. Sinyal ini pakai cache
     * (bukan kolom database) karena sifatnya sangat sementara (auto-expired 4 detik).
     */
    public function typing(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'satker_id' => 'required|exists:satkers,id',
        ]);
        $satkerId = $request->satker_id;

        if ($user->role === 'satker' && (int) $user->satker_id !== (int) $satkerId) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        Cache::put("chat_typing_{$satkerId}_{$user->role}", true, now()->addSeconds(4));

        return response()->json(['status' => 'ok']);
    }

    /**
     * Status lawan bicara untuk satu thread: online, sedang mengetik, dan kapan terakhir
     * online (kalau lagi offline). Dipanggil berkala (tiap ~2 detik) saat sebuah thread
     * sedang dibuka.
     */
    public function status(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            $request->validate([
                'satker_id' => 'required|exists:satkers,id',
            ]);
            $satkerId = $request->satker_id;

            $satkerUser = Satker::find($satkerId)?->user;
            $lastSeenAt = $satkerUser?->last_seen_at;
            $online = $this->isOnline($lastSeenAt);
            $typing = Cache::has("chat_typing_{$satkerId}_satker");
        } else {
            $satkerId = $user->satker_id;

            $adminTerbaru = User::where('role', 'admin')
                ->whereNotNull('last_seen_at')
                ->orderByDesc('last_seen_at')
                ->first();

            $lastSeenAt = $adminTerbaru?->last_seen_at;
            $online = $this->isOnline($lastSeenAt);
            $typing = $satkerId ? Cache::has("chat_typing_{$satkerId}_admin") : false;
        }

        return response()->json([
            'online' => $online,
            'typing' => $typing,
            'last_seen' => $online ? null : $this->lastSeenLabel($lastSeenAt),
        ]);
    }

    /**
     * Status online + "terakhir online" untuk SEMUA satker sekaligus — dipakai admin untuk
     * memantau seluruh daftar satker (bukan cuma thread yang lagi dibuka), refresh berkala.
     */
    public function onlineSatkers()
    {
        $data = Satker::with('user:id,satker_id,last_seen_at')->get()
            ->map(function ($satker) {
                $lastSeenAt = $satker->user->last_seen_at ?? null;

                return [
                    'satker_id' => $satker->id,
                    'online' => $this->isOnline($lastSeenAt),
                    'last_seen' => $this->isOnline($lastSeenAt) ? null : $this->lastSeenLabel($lastSeenAt),
                ];
            })
            ->values();

        return response()->json([
            'online' => $data->where('online', true)->pluck('satker_id')->values(),
            'presence' => $data,
        ]);
    }
}