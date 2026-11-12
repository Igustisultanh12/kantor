<script setup>
// Log Aktivitas (khusus admin): jejak setiap aksi pengguna terhadap data dari web & aplikasi.
// Login/kunjungan tetap di "Log Pengunjung & Audit".
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array,
    modules: Array,
    pdfLimit: Number,
});

const form = reactive({
    search: props.filters.search || '',
    user_id: props.filters.user_id || '',
    module: props.filters.module || '',
    channel: props.filters.channel || '',
    status: props.filters.status || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
});
const open = ref({});
const pdfUrl = ref(null);

const query = () => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
const apply = () => router.get(route('activity-logs.index'), query(), { preserveState: true, preserveScroll: true, replace: true });
const reset = () => { Object.keys(form).forEach((k) => (form[k] = '')); apply(); };
const showPdf = () => { pdfUrl.value = route('activity-logs.pdf', query()); };

const dt = (iso) => (iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' }) : '-');
const mapUrl = (l) => `https://www.google.com/maps?q=${l.lat},${l.lng}`;
const hasFilter = computed(() => Object.values(form).some((v) => v !== '' && v !== null));
const fmtVal = (v) => (v === null || v === undefined ? '-' : typeof v === 'object' ? JSON.stringify(v) : String(v));
</script>

<template>
    <Head title="Log Aktivitas - SINDEN" />
    <AuthenticatedLayout>
        <div class="space-y-5 font-sans">
            <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl border border-[#E2E8F0] shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-extrabold text-lg text-slate-900">Log Aktivitas</h2>
                    <p class="text-xs text-slate-500 mt-0.5 max-w-2xl">
                        Setiap aksi terhadap data (tambah, ubah, hapus, kirim, setujui, tanda tangan) dari web dan aplikasi: siapa, kapan, perangkat, IP, dan lokasi.
                        Login dan kunjungan ada di Log Pengunjung &amp; Audit.
                    </p>
                </div>
                <button type="button" class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold cursor-pointer" @click="showPdf">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z" /></svg>
                    Cetak PDF
                </button>
            </div>

            <!-- Filter -->
            <form class="bg-white p-4 sm:p-5 rounded-2xl border border-[#E2E8F0] grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 items-end" @submit.prevent="apply">
                <label class="col-span-2 text-xs font-semibold text-slate-600">Cari
                    <input v-model="form.search" type="search" placeholder="Nama, NRP, aksi, IP, perangkat" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                </label>
                <label class="col-span-2 lg:col-span-1 text-xs font-semibold text-slate-600">Personel
                    <select v-model="form.user_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Semua</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}{{ u.nrp ? ` (${u.nrp})` : '' }}</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Modul
                    <select v-model="form.module" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Semua</option>
                        <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Sumber
                    <select v-model="form.channel" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Semua</option>
                        <option value="web">Web</option>
                        <option value="android">Aplikasi Android</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Hasil
                    <select v-model="form.status" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Semua</option>
                        <option value="success">Berhasil</option>
                        <option value="failed">Gagal</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Dari
                    <input v-model="form.from" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                </label>
                <label class="text-xs font-semibold text-slate-600">Sampai
                    <input v-model="form.to" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm" />
                </label>
                <div class="col-span-2 md:col-span-4 lg:col-span-8 flex gap-2 justify-end">
                    <button v-if="hasFilter" type="button" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer" @click="reset">Hapus filter</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold cursor-pointer">Terapkan</button>
                </div>
            </form>

            <!-- Daftar -->
            <div class="bg-white rounded-2xl border border-[#E2E8F0] overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 text-xs text-slate-500">{{ logs.total?.toLocaleString('id-ID') || 0 }} aktivitas</div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="l in logs.data" :key="l.id" class="px-5 py-3.5">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-2 lg:gap-6">
                            <div class="lg:w-36 shrink-0 text-xs text-slate-500 tabular-nums">{{ dt(l.at) }}</div>
                            <div class="lg:w-56 shrink-0 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ l.user.name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ [l.user.pangkat, l.user.nrp ? `NRP ${l.user.nrp}` : null].filter(Boolean).join(' · ') || l.user.role }}</p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-800">{{ l.action }}<span v-if="l.subject" class="text-slate-400"> · data #{{ Object.values(l.subject).join(', #') }}</span></p>
                                <p class="text-xs text-slate-500 mt-0.5 truncate">
                                    {{ l.channel === 'android' ? 'Aplikasi Android' : 'Web' }} · {{ l.device || '-' }} · IP {{ l.ip || '-' }}<template v-if="l.ip_location"> · {{ l.ip_location }}</template>
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="l.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                                    {{ l.success ? 'Berhasil' : 'Gagal' }} · {{ l.status }}
                                </span>
                                <button type="button" class="text-xs font-semibold text-blue-700 hover:underline cursor-pointer" :aria-expanded="!!open[l.id]" @click="open[l.id] = !open[l.id]">
                                    {{ open[l.id] ? 'Tutup' : 'Detail' }}
                                </button>
                            </div>
                        </div>

                        <dl v-if="open[l.id]" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1.5 text-xs bg-slate-50 rounded-xl p-4">
                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Permintaan</dt><dd class="text-slate-800 break-all">{{ l.method }} {{ l.path }}</dd></div>
                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Modul</dt><dd class="text-slate-800">{{ l.module }}</dd></div>
                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Lokasi GPS</dt>
                                <dd class="text-slate-800">
                                    <a v-if="l.location" :href="mapUrl(l.location)" target="_blank" rel="noopener noreferrer" class="text-blue-700 hover:underline">
                                        {{ l.location.lat.toFixed(5) }}, {{ l.location.lng.toFixed(5) }}<template v-if="l.location.accuracy"> (±{{ l.location.accuracy }} m)</template>
                                    </a>
                                    <span v-else class="text-slate-400">Tidak tersedia (izin lokasi belum diberikan)</span>
                                </dd>
                            </div>
                            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Durasi</dt><dd class="text-slate-800">{{ l.duration_ms ?? '-' }} ms</dd></div>
                            <div class="flex gap-2 md:col-span-2"><dt class="w-28 shrink-0 text-slate-500">User agent</dt><dd class="text-slate-600 break-all">{{ l.user_agent || '-' }}</dd></div>
                            <div v-if="l.payload" class="md:col-span-2">
                                <dt class="text-slate-500 mb-1">Isian yang dikirim <span class="text-slate-400">(kata sandi & token disamarkan)</span></dt>
                                <dd>
                                    <table class="w-full text-left">
                                        <tbody>
                                            <tr v-for="(v, k) in l.payload" :key="k" class="border-t border-slate-200">
                                                <td class="py-1 pr-3 text-slate-500 align-top w-40">{{ k }}</td>
                                                <td class="py-1 text-slate-800 break-all">{{ fmtVal(v) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </dd>
                            </div>
                        </dl>
                    </li>
                    <li v-if="logs.data.length === 0" class="px-5 py-10 text-center text-sm text-slate-500">
                        Belum ada aktivitas{{ hasFilter ? ' yang cocok dengan filter' : '' }}.
                    </li>
                </ul>

                <nav v-if="logs.last_page > 1" class="px-5 py-3 border-t border-slate-100 flex flex-wrap gap-1.5" aria-label="Halaman">
                    <button
                        v-for="link in logs.links"
                        :key="link.label"
                        type="button"
                        :disabled="!link.url"
                        class="min-w-9 px-2.5 py-1.5 rounded-lg text-xs cursor-pointer disabled:opacity-40 disabled:cursor-default"
                        :class="link.active ? 'bg-blue-600 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        @click="link.url && router.visit(link.url, { preserveScroll: true })"
                        v-html="link.label"
                    ></button>
                </nav>
            </div>
        </div>

        <!-- Pratinjau PDF di dalam aplikasi -->
        <Teleport to="body">
            <div v-if="pdfUrl" class="fixed inset-0 z-[120] flex flex-col bg-slate-950/90" role="dialog" aria-modal="true" aria-label="Pratinjau PDF log aktivitas" @keydown.esc="pdfUrl = null">
                <div class="flex items-center gap-3 px-4 py-3 bg-slate-900 text-white">
                    <p class="flex-1 text-sm font-semibold">Laporan Log Aktivitas (maks. {{ pdfLimit?.toLocaleString('id-ID') }} baris)</p>
                    <a :href="pdfUrl" download class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-xs font-semibold">Unduh</a>
                    <button type="button" class="p-2 rounded-xl hover:bg-slate-800 cursor-pointer" aria-label="Tutup pratinjau" @click="pdfUrl = null">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <iframe :src="pdfUrl" title="Laporan Log Aktivitas" class="flex-1 w-full bg-white"></iframe>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
