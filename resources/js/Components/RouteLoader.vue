<script setup>
// Kapsul loading saat perpindahan halaman agak lama: titik mengorbit + pesan sesuai
// menu tujuan yang berganti halus (melengkapi bilah progres di atas layar).
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const MENUS = [
    ['letter-logs', 'Agenda Surat'], ['letters', 'Arsip Surat'], ['skhpp', 'SKHPP'], ['sc-submissions', 'Tracking SC'],
    ['sp-jaga', 'SP Jaga'], ['rapat-terbuka', 'Rapat Terbuka'], ['signature-requests', 'Tanda Tangan'], ['layanan-printing', 'Layanan Cetak'],
    ['simpan-pinjam', 'Simpan Pinjam'], ['cash', 'Buku Kas'], ['technical-cash', 'Kas Teknis'], ['commander-account', 'Rekening Komandan'],
    ['rekening-ibu-beti', 'Rekening Ibu Beti'], ['mitras', 'Pembayaran Mitra'], ['soldier-violations', 'Pelanggaran'],
    ['community-activities', 'Aktivitas & Peta'], ['users', 'Pengguna'], ['visitor-logs', 'Log Pengunjung'], ['activity-logs', 'Log Aktivitas'],
    ['pc-backup', 'Backup'], ['live-chat', 'Komunikasi'], ['notifications', 'Notifikasi'], ['settings', 'Pengaturan'], ['profile', 'Profil'],
    ['dashboard', 'Dashboard'], ['admin/system-check', 'Cek Sistem'],
];
const PHASES = ['Menyiapkan', 'Mengambil data', 'Menyusun tampilan'];

const visible = ref(false);
const target = ref('halaman');
const phase = ref(0);
let showTimer = null;
let phaseTimer = null;
const offs = [];

const nameFor = (url) => {
    try {
        const path = new URL(url, window.location.origin).pathname.replace(/^\/+/, '');
        const hit = MENUS.find(([p]) => path === p || path.startsWith(p + '/'));
        return hit ? hit[1] : 'halaman';
    } catch (_) { return 'halaman'; }
};

const stop = () => {
    clearTimeout(showTimer); clearInterval(phaseTimer);
    visible.value = false;
};

onMounted(() => {
    offs.push(router.on('start', (e) => {
        // Hanya kunjungan halaman (bukan polling/partial reload kecil).
        if (e.detail.visit?.only?.length) return;
        stop();
        target.value = nameFor(e.detail.visit.url);
        phase.value = 0;
        showTimer = setTimeout(() => {
            visible.value = true;
            phaseTimer = setInterval(() => { phase.value = (phase.value + 1) % PHASES.length; }, 1300);
        }, 450);
    }));
    offs.push(router.on('finish', stop));
});
onBeforeUnmount(() => { stop(); offs.forEach((off) => off && off()); });
</script>

<template>
    <Teleport to="body">
        <Transition name="rl">
            <div v-if="visible" class="rl-pill" role="status" aria-live="polite">
                <span class="rl-orbit" aria-hidden="true"><i></i><i></i><i></i></span>
                <Transition name="rl-msg" mode="out-in">
                    <span :key="phase" class="rl-text">{{ PHASES[phase] }} {{ target }}…</span>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.rl-pill { position: fixed; z-index: 9998; top: 14px; left: 50%; transform: translateX(-50%);
    display: flex; align-items: center; gap: 10px; padding: 8px 16px 8px 10px; border-radius: 999px;
    background: rgba(15, 23, 42, .88); color: #e2e8f0; backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,.12); box-shadow: 0 12px 30px -10px rgba(2,6,23,.6); font-size: 12.5px; font-weight: 700; }
.rl-orbit { position: relative; width: 22px; height: 22px; border-radius: 999px; border: 2px solid rgba(96,165,250,.25); }
.rl-orbit i { position: absolute; inset: -2px; border-radius: 999px; }
.rl-orbit i::before { content: ''; position: absolute; top: -1px; left: 50%; width: 6px; height: 6px; margin-left: -3px; border-radius: 999px; background: #60a5fa; box-shadow: 0 0 8px #60a5fa; }
.rl-orbit i:nth-child(1) { animation: rl-spin 1s linear infinite; }
.rl-orbit i:nth-child(2) { animation: rl-spin 1.6s linear infinite; }
.rl-orbit i:nth-child(2)::before { background: #22d3ee; width: 5px; height: 5px; box-shadow: 0 0 8px #22d3ee; }
.rl-orbit i:nth-child(3) { animation: rl-spin 2.3s linear infinite reverse; }
.rl-orbit i:nth-child(3)::before { background: #34d399; width: 4px; height: 4px; box-shadow: 0 0 8px #34d399; }
.rl-text { white-space: nowrap; }
@keyframes rl-spin { to { transform: rotate(360deg); } }
.rl-enter-active, .rl-leave-active { transition: opacity .2s ease, transform .25s cubic-bezier(.2,.8,.2,1); }
.rl-enter-from, .rl-leave-to { opacity: 0; transform: translate(-50%, -8px); }
.rl-msg-enter-active, .rl-msg-leave-active { transition: opacity .18s ease, transform .18s ease; }
.rl-msg-enter-from { opacity: 0; transform: translateY(6px); }
.rl-msg-leave-to { opacity: 0; transform: translateY(-6px); }
@media (prefers-reduced-motion: reduce) { .rl-orbit i { animation-duration: 3s; } }
</style>
