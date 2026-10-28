<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import QrcodeVue from 'qrcode.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    meeting: Object,
    attendees: Array,
    publicUrl: String,
});

const isProjectorModalOpen = ref(false);
const attendeeSearch = ref('');
const qrCanvasRef = ref(null);

const filteredAttendees = computed(() => {
    if (!attendeeSearch.value.trim()) return props.attendees || [];
    const query = attendeeSearch.value.toLowerCase();
    return (props.attendees || []).filter(att => {
        const nameMatch = att.name?.toLowerCase().includes(query);
        const rankMatch = att.pangkat_nrp?.toLowerCase().includes(query);
        const jobMatch = att.jabatan?.toLowerCase().includes(query);
        return nameMatch || rankMatch || jobMatch;
    });
});

const copyPublicUrl = () => {
    navigator.clipboard.writeText(props.publicUrl).then(() => {
        Swal.fire({
            title: 'Tautan Disalin!',
            text: 'Link formulir absensi publik telah disalin ke clipboard. Anda dapat membagikannya ke grup WhatsApp peserta.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
        });
    });
};

const toggleStatus = () => {
    const actionText = props.meeting.status === 'open' ? 'menutup' : 'membuka kembali';
    Swal.fire({
        title: `Konfirmasi ${props.meeting.status === 'open' ? 'Tutup' : 'Buka'} Absensi`,
        text: `Apakah Anda yakin ingin ${actionText} sesi absensi ini?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Ya, ${props.meeting.status === 'open' ? 'Tutup' : 'Buka'}`,
        cancelButtonText: 'Batal',
        confirmButtonColor: props.meeting.status === 'open' ? '#e11d48' : '#2563eb',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('rapat-terbuka.toggle', props.meeting.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Status sesi absensi rapat berhasil diperbarui.', 'success');
                }
            });
        }
    });
};

const deleteAttendee = (att) => {
    Swal.fire({
        title: 'Hapus Peserta?',
        text: `Apakah Anda yakin menghapus presensi kehadiran atas nama "${att.name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('rapat-terbuka.destroy-attendee', [props.meeting.id, att.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Terhapus', 'Data kehadiran peserta berhasil dihapus.', 'success');
                }
            });
        }
    });
};

const refreshData = () => {
    router.reload({ preserveScroll: true });
};
</script>

<template>
    <Head :title="meeting.title + ' - Absensi Rapat SINDEN'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('rapat-terbuka.index')"
                        class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-black text-slate-800 tracking-tight">{{ meeting.title }}</h2>
                            <span
                                :class="meeting.status === 'open' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                                class="px-2 py-0.5 text-[10px] font-extrabold rounded-md uppercase tracking-wider border inline-flex items-center gap-1"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="meeting.status === 'open' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                                {{ meeting.status === 'open' ? 'Dibuka' : 'Ditutup' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">
                            {{ meeting.formatted_date }} &bull; {{ meeting.location || 'Mako Denintel Kodaeral V' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Cetak PDF -->
                    <a
                        :href="route('rapat-terbuka.pdf', meeting.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Cetak PDF Dinas</span>
                    </a>

                    <!-- Toggle Status -->
                    <button
                        @click="toggleStatus"
                        :class="meeting.status === 'open' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700'"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-white text-xs font-bold rounded-xl shadow-xs transition"
                    >
                        <svg v-if="meeting.status === 'open'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                        <span>{{ meeting.status === 'open' ? 'Tutup Absensi' : 'Buka Absensi' }}</span>
                    </button>

                    <!-- Edit -->
                    <Link
                        :href="route('rapat-terbuka.edit', meeting.id)"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Edit</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">

            <!-- KARTU QR CODE & KONTROL PUBLIK -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-3xl p-6 sm:p-8 shadow-lg overflow-hidden relative">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    
                    <!-- QR Code Card Box -->
                    <div class="lg:col-span-4 flex flex-col items-center text-center">
                        <div class="bg-white p-4 rounded-2xl shadow-xl inline-block">
                            <qrcode-vue
                                :value="publicUrl"
                                :size="200"
                                level="H"
                                render-as="canvas"
                                class="mx-auto"
                            />
                        </div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-3">
                            SCAN UNTUK ABSENSI
                        </div>
                    </div>

                    <!-- Detail & Opsi Share -->
                    <div class="lg:col-span-8 space-y-4">
                        <div>
                            <span class="px-2.5 py-1 bg-blue-500/20 text-blue-300 font-extrabold text-[11px] rounded-lg uppercase tracking-wider border border-blue-400/30">
                                Akses Publik Tanpa Login
                            </span>
                            <h3 class="text-xl sm:text-2xl font-black text-white mt-2 leading-tight">
                                {{ meeting.title }}
                            </h3>
                            <p class="text-xs text-slate-300 mt-1 max-w-xl">
                                Peserta rapat dapat memindai QR Code di samping menggunakan kamera smartphone untuk mengisi daftar hadir secara langsung tanpa perlu login akun.
                            </p>
                        </div>

                        <!-- Tautan Publik -->
                        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-3 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 truncate w-full sm:w-auto text-xs text-slate-300 font-mono">
                                <svg class="w-4 h-4 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                <span class="truncate">{{ publicUrl }}</span>
                            </div>

                            <button
                                @click="copyPublicUrl"
                                class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold shrink-0 transition flex items-center justify-center gap-1.5 shadow-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                <span>Salin Tautan</span>
                            </button>
                        </div>

                        <!-- Tombol Mode Proyektor & Info & Cetak PDF -->
                        <div class="flex items-center gap-3 pt-1 flex-wrap">
                            <!-- Tombol Cetak PDF Dinas -->
                            <a
                                :href="route('rapat-terbuka.pdf', meeting.id)"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold transition shadow-sm"
                            >
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Cetak Dokumen PDF Dinas</span>
                            </a>

                            <button
                                @click="isProjectorModalOpen = true"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition border border-white/10"
                            >
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                <span>Mode Proyektor / Layar Penuh</span>
                            </button>

                            <a
                                :href="publicUrl"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition border border-white/10"
                            >
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                <span>Buka Form Publik</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- TABEL DAFTAR PESERTA HADIR -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
                
                <!-- Table Header Bar -->
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black">
                            {{ attendees.length }}
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">
                                Daftar Hadir Peserta
                            </h3>
                            <p class="text-xs text-slate-400">Total peserta yang telah mengisi formulir presensi rapat.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Pencarian Peserta -->
                        <div class="relative w-full sm:w-64">
                            <input
                                type="text"
                                v-model="attendeeSearch"
                                placeholder="Cari nama atau jabatan..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>

                        <!-- Tombol Refresh -->
                        <button
                            @click="refreshData"
                            title="Perbarui Data Kehadiran"
                            class="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl border border-slate-200 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </button>

                        <!-- Tombol Cetak PDF di Tabel -->
                        <a
                            :href="route('rapat-terbuka.pdf', meeting.id)"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-xs"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span>Cetak PDF</span>
                        </a>
                    </div>
                </div>

                <!-- Tabel Data -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                                <th class="p-3.5 text-center w-12">No</th>
                                <th class="p-3.5">Nama Lengkap</th>
                                <th class="p-3.5">Pangkat / NRP</th>
                                <th class="p-3.5">Jabatan / Satuan</th>
                                <th v-for="field in meeting.custom_fields || []" :key="field.id" class="p-3.5">
                                    {{ field.label }}
                                </th>
                                <th class="p-3.5 text-center">Paraf / TTD</th>
                                <th class="p-3.5 text-center">Waktu Hadir</th>
                                <th class="p-3.5 text-center w-14">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr
                                v-for="(att, idx) in filteredAttendees"
                                :key="att.id"
                                class="hover:bg-slate-50/50 transition"
                            >
                                <td class="p-3.5 text-center font-bold text-slate-400">{{ idx + 1 }}</td>
                                <td class="p-3.5 font-bold text-slate-800 uppercase">{{ att.name }}</td>
                                <td class="p-3.5">{{ att.pangkat_nrp || '-' }}</td>
                                <td class="p-3.5">{{ att.jabatan || '-' }}</td>
                                
                                <!-- Dynamic Custom Data -->
                                <td v-for="field in meeting.custom_fields || []" :key="field.id" class="p-3.5">
                                    {{ att.custom_data?.[field.name]?.value || att.custom_data?.[field.name] || '-' }}
                                </td>

                                <!-- Paraf / TTD Digital -->
                                <td class="p-3.5 text-center">
                                    <div v-if="att.signature" class="inline-block bg-white p-1 rounded border border-slate-200">
                                        <img :src="att.signature" class="max-h-7 max-w-[80px]" alt="Paraf" />
                                    </div>
                                    <span v-else class="text-[11px] text-slate-400 italic">-</span>
                                </td>

                                <td class="p-3.5 text-center text-slate-500 font-mono text-[11px]">
                                    {{ att.formatted_attended_at }}
                                </td>

                                <!-- Hapus -->
                                <td class="p-3.5 text-center">
                                    <button
                                        @click="deleteAttendee(att)"
                                        title="Hapus Peserta"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="filteredAttendees.length === 0">
                                <td :colspan="7 + (meeting.custom_fields?.length || 0)" class="p-12 text-center text-slate-400">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Belum ada peserta yang mengisi absensi pada sesi rapat ini.</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

        <!-- MODAL PROYEKTOR / LAYAR PENUH -->
        <div
            v-if="isProjectorModalOpen"
            class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-md flex flex-col items-center justify-center p-6 text-white text-center"
        >
            <button
                @click="isProjectorModalOpen = false"
                class="absolute top-6 right-6 p-3 bg-white/10 hover:bg-white/20 rounded-2xl transition"
            >
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="max-w-2xl mx-auto space-y-6">
                <div class="space-y-1">
                    <div class="text-xs font-bold text-blue-400 uppercase tracking-widest">
                        KOMANDO DAERAH TNI AL V &bull; DETASEMEN INTELIJEN
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                        {{ meeting.title }}
                    </h2>
                    <p class="text-sm text-slate-300">
                        {{ meeting.formatted_date }} &bull; {{ meeting.location || 'Mako Denintel Kodaeral V' }}
                    </p>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow-2xl inline-block">
                    <qrcode-vue
                        :value="publicUrl"
                        :size="320"
                        level="H"
                        render-as="canvas"
                        class="mx-auto"
                    />
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-bold text-slate-200">
                        Arahkan kamera HP Anda ke QR Code di atas untuk mengisi presensi kehadiran
                    </p>
                    <div class="text-xs text-slate-400 font-mono">
                        {{ publicUrl }}
                    </div>
                </div>

                <div class="pt-2">
                    <span class="px-4 py-2 bg-blue-500/20 text-blue-300 border border-blue-400/30 rounded-xl text-xs font-bold">
                        Total Peserta Terdata: {{ attendees.length }} Orang
                    </span>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
