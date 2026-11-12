// Arsitektur informasi Nova (PRD bagian 8): 5 grup.
// Aturan hak akses disalin apa adanya dari sidebar desktop lama
// (Layouts/ClassicLayout.vue) supaya tidak ada menu yang muncul/hilang.
// Menu WhatsApp Web dihapus atas permintaan pemilik proyek (11-11-2026).
import { computed } from 'vue';

// Hak akses dihitung dari role & flag server (auth.user), TIDAK dari nama (audit K01).
export function useNovaAccess(user) {
    const u = () => user.value || {};
    const isAdmin = computed(() => u().role === 'admin');
    const isDev = () => Boolean(u().is_developer);
    const isDanUnitTeknis = computed(() => ['danunitteknis', 'DAN UNIT TEKNIS', 'dan unit teknis'].includes(u().role));
    const isAnggotaSintel = computed(() => {
        if (isAdmin.value) return false;
        const r = (u().role || '').toLowerCase().replace(/[\s_-]+/g, '');
        const j = (u().jabatan || '').toLowerCase();
        return r === 'anggotasintel' || j.includes('anggota sintel');
    });
    const canAccessCash = computed(() => Boolean(u().can_access_cash));
    const canAccessCommanderAccount = computed(() => Boolean(u().can_view_commander_account));
    const canAccessIbuBeti = computed(() => isAdmin.value || isDev() || Boolean(u().can_access_ibu_beti));
    const canAccessMitra = computed(() => isAdmin.value || isDev() || Boolean(u().can_access_mitra));
    const canManageKoperasi = computed(() => isAdmin.value || isDev() || Boolean(u().can_manage_koperasi));
    const canAccessTechnicalCash = computed(() => isAdmin.value || isDev() || isDanUnitTeknis.value || Boolean(u().can_access_technical_cash));

    return {
        isAdmin, isDanUnitTeknis, isAnggotaSintel, canAccessCash, canAccessCommanderAccount,
        canAccessIbuBeti, canAccessMitra, canManageKoperasi, canAccessTechnicalCash,
    };
}

/**
 * Item: { label, route, match (pola route().current), icon, show, count? }
 * `show` dievaluasi sekali per render; grup tanpa item yang tampil disembunyikan.
 */
export function buildNovaGroups(a, { unread = 0 } = {}) {
    const notAnggota = !a.isAnggotaSintel.value;
    const notDanUnit = !a.isDanUnitTeknis.value || a.isAdmin.value;
    const groups = [
        {
            id: 'beranda', title: 'Beranda', items: [
                { label: 'Dashboard', route: 'dashboard', match: 'dashboard', icon: 'dashboard', show: notAnggota },
                { label: 'Notifikasi', route: 'notifications.index', match: 'notifications.*', icon: 'bell', show: true, count: unread, tone: 'accent' },
                { label: 'Komunikasi & VC', route: 'chat.index', match: 'chat.*', icon: 'video', show: notAnggota },
            ],
        },
        {
            id: 'persuratan', title: 'Persuratan', items: [
                { label: 'Agenda Surat', route: 'letter-logs.index', match: 'letter-logs.*', icon: 'inbox', show: notAnggota },
                { label: 'Buat & Draf Surat', route: 'letters.index', match: 'letters.*', icon: 'pen', show: notAnggota },
                { label: 'SKHPP', route: 'skhpp.index', match: 'skhpp.*', icon: 'file', show: notAnggota },
                { label: 'Tracking SC', route: 'sc-submissions.index', match: 'sc-submissions.*', icon: 'pin', show: true },
                { label: a.isAnggotaSintel.value ? 'Jadwal SP Jaga' : 'SP Jaga', route: 'sp-jaga.index', match: 'sp-jaga.*', icon: 'calendar', show: true },
                { label: 'Rapat Terbuka', route: 'rapat-terbuka.index', match: 'rapat-terbuka.*', icon: 'qr', show: notAnggota },
                { label: 'Kategori Surat', route: 'categories.index', match: 'categories.*', icon: 'tag', show: notAnggota },
            ],
        },
        {
            id: 'validasi', title: 'Validasi', items: [
                { label: 'Tanda Tangan (TTE)', route: 'signature.index', match: 'signature.*', icon: 'signature', show: notAnggota },
                { label: 'Layanan Cetak', route: 'printing.index', match: 'printing.*', icon: 'printer', show: notAnggota },
            ],
        },
        {
            id: 'keuangan', title: 'Keuangan & Logistik', items: [
                { label: 'Simpan Pinjam', route: 'simpan-pinjam.index', match: 'simpan-pinjam.index', icon: 'wallet', show: notAnggota },
                { label: 'Kelola Simpan Pinjam', route: 'simpan-pinjam.kelola', match: 'simpan-pinjam.kelola', icon: 'building', show: notAnggota && a.canManageKoperasi.value },
                { label: 'Kas', route: 'cash.index', match: 'cash.*', icon: 'trending', show: notAnggota && a.canAccessCash.value && notDanUnit },
                { label: 'Kas Teknis', route: 'technical-cash.index', match: 'technical-cash.*', icon: 'wrench', show: notAnggota && a.canAccessTechnicalCash.value },
                { label: 'Rekening Komandan', route: 'commander.index', match: 'commander.*', icon: 'card', show: notAnggota && a.canAccessCommanderAccount.value && notDanUnit },
                { label: 'Rekening Ibu Beti', route: 'ibu-beti.index', match: 'ibu-beti.*', icon: 'card', show: notAnggota && a.canAccessIbuBeti.value && notDanUnit },
                { label: 'Mitra', route: 'mitras.index', match: 'mitras.*', icon: 'handshake', show: notAnggota && a.canAccessMitra.value && notDanUnit },
            ],
        },
        {
            id: 'administrasi', title: 'Administrasi', items: [
                { label: 'Pelanggaran', route: 'soldier-violations.index', match: 'soldier-violations.*', icon: 'alert', show: notAnggota },
                { label: 'Aktivitas & Peta', route: 'activities.index', match: 'activities.*', icon: 'map', show: notAnggota },
                { label: 'Pengguna', route: 'users.index', match: 'users.*', icon: 'users', show: notAnggota && a.isAdmin.value },
                { label: 'Log Pengunjung & Audit', route: 'visitor-logs.index', match: 'visitor-logs.*', icon: 'clipboard', show: notAnggota },
                { label: 'Log Aktivitas', route: 'activity-logs.index', match: 'activity-logs.*', icon: 'activity', show: notAnggota && a.isAdmin.value },
                { label: 'Backup', route: 'backup.index', match: 'backup.*', icon: 'archive', show: notAnggota && notDanUnit },
                { label: 'Cek Sistem', route: 'system-check.index', match: 'system-check.*', icon: 'activity', show: notAnggota && a.isAdmin.value },
                { label: 'Pengaturan', route: 'settings.index', match: 'settings.*', icon: 'settings', show: notAnggota && a.isAdmin.value },
            ],
        },
    ];
    return groups
        .map((g) => ({ ...g, items: g.items.filter((i) => i.show && routeExists(i.route)) }))
        .filter((g) => g.items.length);
}

function routeExists(name) {
    try { return typeof route === 'function' && route().has(name); } catch (e) { return false; }
}
