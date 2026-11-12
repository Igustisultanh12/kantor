// Realtime SISINDEN: setiap ada data yang berubah di server (siapa pun pengubahnya,
// dari web atau aplikasi), halaman yang sedang dibuka memuat ulang datanya sendiri.
//
// Server hanya mengirim nama modul ("letters", "skhpp", ...), bukan isi data.
// Data baru diambil lewat Inertia seperti biasa sehingga hak akses tetap dicek server.
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { router } from '@inertiajs/vue3';

// Halaman (folder di resources/js/Pages) => modul yang datanya ditampilkan.
// Halaman yang tidak tercantum dimuat ulang untuk modul apa pun kecuali "general".
const PAGE_MODULES = {
    Dashboard: ['*'],
    Letters: ['letters'], LetterLogs: ['letters'], Categories: ['letters'],
    Skhpp: ['skhpp', 'signature'], Signature: ['signature', 'letters', 'skhpp'], Stamp: ['signature'],
    SpJaga: ['duty'], Attendance: ['attendance'],
    Chat: ['chat'], Notifications: ['notifications'],
    Backup: ['backup'],
    Cash: ['finance'], TechnicalUnitCash: ['finance'], CommanderAccount: ['finance'], IbuBetiAccount: ['finance'], Mitra: ['finance'],
    SimpanPinjam: ['koperasi'],
    ScSubmission: ['sc'], SoldierViolations: ['violations'],
    OpenMeetings: ['meetings'], Activities: ['activities'], PrintService: ['print'],
    Users: ['users'], Settings: ['settings'], Admin: ['*'], AuditLogs: ['*'], VisitorLogs: ['*'],
    Profile: ['users'],
};
// Selalu memuat ulang (lonceng notifikasi & badge chat ada di semua halaman).
const ALWAYS = ['notifications'];
// Modul yang tidak memicu muat ulang halaman Inertia ("devices" ditangani di bawah & oleh DeviceSessions.vue).
const QUIET = ['general', 'devices'];

let echo = null;
let timer = null;
let lastReload = 0;
let queued = false;

const pageWants = (component, modules) => {
    const root = String(component || '').split('/')[0];
    const wanted = PAGE_MODULES[root];
    if (modules.some((m) => ALWAYS.includes(m))) return true;
    const loud = modules.filter((m) => !QUIET.includes(m));
    if (!wanted) return loud.length > 0;
    return (wanted.includes('*') && loud.length > 0) || loud.some((m) => wanted.includes(m));
};

// Ada perangkat yang masuk/keluar/dikeluarkan: pastikan peramban ini masih masuk.
// Bila sesi ini yang dikeluarkan, beri tahu lalu arahkan ke halaman masuk.
let checkingSession = false;
let kickedOut = false;
const checkSession = async () => {
    if (checkingSession || kickedOut) return;
    checkingSession = true;
    try {
        const { data } = await axios.get('/session/check', { headers: { Accept: 'application/json' } });
        if (data?.authenticated !== false) return;
        kickedOut = true;
        stopRealtime();
        const revoked = data.reason === 'device_revoked';
        const { default: Swal } = await import('sweetalert2');
        await Swal.fire({
            icon: 'warning',
            title: 'Maaf, Anda telah keluar',
            text: revoked
                ? 'Perangkat ini telah dikeluarkan dari akun Anda. Silakan masuk kembali.'
                : 'Sesi Anda telah berakhir. Silakan masuk kembali.',
            confirmButtonText: 'Masuk kembali',
            allowOutsideClick: false,
            allowEscapeKey: false,
        });
        window.location.href = '/login';
    } catch (e) {
        /* jaringan bermasalah: abaikan, dicek lagi pada sinyal berikutnya */
    } finally {
        checkingSession = false;
    }
};

// Jangan memuat ulang saat pengguna sedang mengetik / membuka dialog: tunda sampai selesai.
const userBusy = () => {
    const el = document.activeElement;
    const typing = el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
    const dialog = document.querySelector('.swal2-container, [role="dialog"][aria-modal="true"]');
    return Boolean(typing || dialog);
};

const reloadSoon = () => {
    queued = true;
    clearTimeout(timer);
    // Kumpulkan beberapa sinyal beruntun, dan jangan lebih dari sekali per 2 detik.
    const wait = Math.max(600, 2000 - (Date.now() - lastReload));
    timer = setTimeout(() => {
        if (document.hidden) return; // dijalankan saat tab kembali dilihat
        if (userBusy()) { reloadSoon(); return; }
        queued = false;
        lastReload = Date.now();
        router.reload({ preserveScroll: true, preserveState: true });
    }, wait);
};

// Logout lewat Inertia tidak memuat ulang halaman: putus sambungan begitu tidak ada user.
router.on('navigate', (ev) => {
    if (!ev?.detail?.page?.props?.auth?.user) stopRealtime();
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden && queued) reloadSoon();
});

/** Mulai sambungan realtime (sekali per sesi halaman). Aman dipanggil berulang. */
export function startRealtime(page) {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (echo || !key || !page?.props?.auth?.user) return;

    window.Pusher = Pusher;
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80));
    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // axios mengirim cookie XSRF terbaru (meta csrf bisa basi setelah login tanpa muat ulang).
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios.post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                    .then((res) => callback(null, res.data))
                    .catch((err) => callback(err));
            },
        }),
    });
    window.Echo = echo;

    echo.private('sinden').listen('.changed', (e) => {
        const modules = Array.isArray(e?.modules) ? e.modules : [];
        // Komponen yang punya logika sendiri (mis. chat) bisa mendengarkan event ini.
        window.dispatchEvent(new CustomEvent('sinden:realtime', { detail: { modules } }));
        // Jeda acak kecil agar semua peramban yang terbuka tidak mengecek bersamaan.
        if (modules.includes('devices')) setTimeout(checkSession, Math.random() * 1500);
        if (pageWants(page.component, modules)) reloadSoon();
    });
}

/** Putus sambungan (dipanggil saat logout). */
export function stopRealtime() {
    try { echo?.disconnect(); } catch (e) { /* abaikan */ }
    echo = null;
    window.Echo = undefined;
}
