<script setup>
// Perangkat & sesi aktif akun ini: web (peramban) dan aplikasi Android.
// Pemilik akun bisa melihat detail perangkat dan mengeluarkannya dari jarak jauh.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const devices = ref([]);
const webSupported = ref(true);
const loading = ref(true);
const error = ref('');
const busy = ref(null);
const open = ref({});

const others = computed(() => devices.value.filter((d) => !d.current));

const load = async ({ silent = false } = {}) => {
    if (!silent) loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('profile.devices'));
        devices.value = data.devices || [];
        webSupported.value = data.web_supported !== false;
    } catch (e) {
        error.value = 'Daftar perangkat gagal dimuat. Coba muat ulang.';
    } finally {
        loading.value = false;
    }
};

const ago = (iso) => {
    if (!iso) return '-';
    const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
    if (s < 90) return 'baru saja';
    if (s < 3600) return `${Math.round(s / 60)} menit lalu`;
    if (s < 86400) return `${Math.round(s / 3600)} jam lalu`;
    return `${Math.round(s / 86400)} hari lalu`;
};
const dateTime = (iso) => (iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-');
const mapUrl = (loc) => `https://www.google.com/maps?q=${loc.lat},${loc.lng}`;

const confirmAndRun = async ({ title, text, confirm, run }) => {
    const res = await Swal.fire({ title, text, icon: 'warning', showCancelButton: true, confirmButtonText: confirm, cancelButtonText: 'Batal', confirmButtonColor: '#DC2626' });
    if (!res.isConfirmed) return;
    try {
        const { data } = await run();
        Swal.fire({ icon: 'success', title: data.message || 'Selesai', timer: 1800, showConfirmButton: false });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || 'Coba lagi beberapa saat.' });
    }
    await load();
};

const revoke = (d) => {
    busy.value = `${d.kind}-${d.id}`;
    confirmAndRun({
        title: 'Keluarkan perangkat ini?',
        text: `${d.name} akan keluar dari akun Anda dan harus masuk lagi untuk memakai SISINDEN.`,
        confirm: 'Keluarkan',
        run: () => axios.delete(route('profile.devices.destroy', { kind: d.kind, id: d.id })),
    }).finally(() => (busy.value = null));
};

const revokeOthers = () => {
    busy.value = 'all';
    confirmAndRun({
        title: 'Keluarkan semua perangkat lain?',
        text: `${others.value.length} perangkat lain (web dan Android) akan keluar. Perangkat ini tetap masuk.`,
        confirm: 'Keluarkan semua',
        run: () => axios.post(route('profile.devices.logout-others')),
    }).finally(() => (busy.value = null));
};

// Realtime: perangkat masuk/keluar/dikeluarkan di mana pun -> daftar diperbarui tanpa klik "Muat ulang".
let refreshTimer = null;
const onRealtime = (e) => {
    if (!e?.detail?.modules?.includes('devices')) return;
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => load({ silent: true }), 700);
};

onMounted(() => {
    load();
    window.addEventListener('sinden:realtime', onRealtime);
});
onBeforeUnmount(() => {
    clearTimeout(refreshTimer);
    window.removeEventListener('sinden:realtime', onRealtime);
});
</script>

<template>
    <section>
        <header class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Perangkat & sesi aktif</h2>
                <p class="mt-1 text-sm text-gray-600 max-w-2xl">
                    Semua perangkat yang sedang masuk ke akun Anda, di web maupun aplikasi Android. Jika ada yang tidak Anda kenali, keluarkan lalu ganti kata sandi.
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <button type="button" class="px-3.5 py-2 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer disabled:opacity-50" :disabled="loading" @click="load">
                    Muat ulang
                </button>
                <button
                    type="button"
                    class="px-3.5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold cursor-pointer disabled:opacity-50"
                    :disabled="loading || others.length === 0 || busy === 'all'"
                    @click="revokeOthers"
                >
                    Keluarkan semua perangkat lain
                </button>
            </div>
        </header>

        <p v-if="!webSupported" class="mt-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
            Sesi web tidak bisa ditampilkan karena server menyimpan sesi di luar basis data (SESSION_DRIVER bukan "database"). Sesi aplikasi Android tetap tampil.
        </p>

        <div v-if="loading && devices.length === 0" class="mt-6 space-y-3" aria-busy="true">
            <div v-for="i in 2" :key="i" class="h-20 rounded-2xl bg-slate-100 animate-pulse"></div>
        </div>
        <p v-else-if="error" class="mt-6 text-sm text-red-600">{{ error }}</p>

        <ul v-else class="mt-6 space-y-3">
            <li v-for="d in devices" :key="`${d.kind}-${d.id}`" class="rounded-2xl border p-4" :class="d.current ? 'border-blue-200 bg-blue-50/50' : 'border-slate-200'">
                <div class="flex items-start gap-3">
                    <!-- Ikon jenis perangkat -->
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" :class="d.kind === 'android' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'">
                        <svg v-if="d.kind === 'android'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2.5" /><path d="M11 18h2" stroke-linecap="round" /></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" /><path d="M8 20h8M12 16v4" stroke-linecap="round" /></svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="font-semibold text-slate-900 text-sm truncate">{{ d.name }}</p>
                            <span v-if="d.current" class="px-2 py-0.5 rounded-full bg-blue-600 text-white text-[11px] font-semibold">Perangkat ini</span>
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-semibold">{{ d.kind === 'android' ? 'Aplikasi Android' : 'Web' }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Aktif {{ ago(d.last_seen) }} · IP {{ d.ip || '-' }}<template v-if="d.ip_location"> · {{ d.ip_location }}</template></p>

                        <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                            <span v-if="d.battery !== null && d.battery !== undefined" class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700">
                                Baterai {{ d.battery }}%<template v-if="d.charging"> · mengisi</template>
                            </span>
                            <span v-if="d.network" class="px-2 py-1 rounded-lg bg-slate-100 text-slate-700">{{ d.network }}</span>
                            <a
                                v-if="d.location"
                                :href="mapUrl(d.location)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:underline"
                            >
                                GPS {{ d.location.lat.toFixed(5) }}, {{ d.location.lng.toFixed(5) }}<template v-if="d.location.accuracy"> (±{{ d.location.accuracy }} m)</template> · {{ ago(d.location.at) }}
                            </a>
                            <span v-else class="px-2 py-1 rounded-lg bg-slate-50 text-slate-400">GPS belum tersedia</span>
                        </div>

                        <button type="button" class="mt-2 text-xs font-semibold text-blue-700 hover:underline cursor-pointer" :aria-expanded="!!open[`${d.kind}-${d.id}`]" @click="open[`${d.kind}-${d.id}`] = !open[`${d.kind}-${d.id}`]">
                            {{ open[`${d.kind}-${d.id}`] ? 'Sembunyikan detail' : 'Lihat detail' }}
                        </button>
                        <dl v-if="open[`${d.kind}-${d.id}`]" class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 text-xs">
                            <div class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Sistem operasi</dt><dd class="text-slate-800">{{ [d.os, d.os_version].filter(Boolean).join(' ') || '-' }}</dd></div>
                            <div v-if="d.kind === 'web'" class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Peramban</dt><dd class="text-slate-800">{{ d.browser || '-' }}</dd></div>
                            <div v-else class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Versi aplikasi</dt><dd class="text-slate-800">{{ d.app_version || '-' }}</dd></div>
                            <div class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Model</dt><dd class="text-slate-800">{{ d.model || '-' }}</dd></div>
                            <div v-if="d.kind === 'android'" class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">ID perangkat</dt><dd class="text-slate-800 font-mono break-all">{{ d.device_key || '-' }}</dd></div>
                            <div v-if="d.screen" class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Layar</dt><dd class="text-slate-800">{{ d.screen }}</dd></div>
                            <div class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Mulai masuk</dt><dd class="text-slate-800">{{ dateTime(d.signed_in_at) }}</dd></div>
                            <div class="flex gap-2"><dt class="text-slate-500 w-32 shrink-0">Terakhir aktif</dt><dd class="text-slate-800">{{ dateTime(d.last_seen) }}</dd></div>
                            <div v-if="d.user_agent" class="flex gap-2 sm:col-span-2"><dt class="text-slate-500 w-32 shrink-0">User agent</dt><dd class="text-slate-600 break-all">{{ d.user_agent }}</dd></div>
                        </dl>
                        <p v-if="open[`${d.kind}-${d.id}`] && d.kind === 'android'" class="mt-2 text-[11px] text-slate-400">
                            IMEI tidak ditampilkan: sejak Android 10 aplikasi biasa tidak diizinkan membacanya. ID perangkat di atas unik untuk HP ini.
                        </p>
                    </div>

                    <button
                        v-if="!d.current"
                        type="button"
                        class="shrink-0 px-3 py-2 rounded-xl border border-red-200 text-red-700 hover:bg-red-50 text-xs font-semibold cursor-pointer disabled:opacity-50"
                        :disabled="busy === `${d.kind}-${d.id}` || busy === 'all'"
                        @click="revoke(d)"
                    >
                        Keluarkan
                    </button>
                </div>
            </li>
            <li v-if="devices.length === 0" class="text-sm text-slate-500">Belum ada sesi yang tercatat.</li>
        </ul>
    </section>
</template>
