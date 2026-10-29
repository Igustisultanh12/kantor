<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    meetings: Object,
    filters: Object,
    stats: Object,
});

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const handleFilter = () => {
    router.get(route('rapat-terbuka.index'), {
        search: searchQuery.value,
        status: statusFilter.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const toggleMeetingStatus = (meeting) => {
    const actionText = meeting.status === 'open' ? 'menutup' : 'membuka kembali';
    Swal.fire({
        title: `Konfirmasi ${meeting.status === 'open' ? 'Tutup' : 'Buka'} Absensi`,
        text: `Apakah Anda yakin ingin ${actionText} sesi absensi untuk "${meeting.title}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Ya, ${meeting.status === 'open' ? 'Tutup' : 'Buka'}`,
        cancelButtonText: 'Batal',
        confirmButtonColor: meeting.status === 'open' ? '#e11d48' : '#2563eb',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('rapat-terbuka.toggle', meeting.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Status sesi absensi rapat berhasil diperbarui.', 'success');
                }
            });
        }
    });
};

const deleteMeeting = (meeting) => {
    Swal.fire({
        title: 'Hapus Sesi Rapat?',
        html: `<p class="text-xs text-slate-600">Sesi rapat <b>${meeting.title}</b> beserta seluruh daftar hadir peserta (${meeting.attendees_count} orang) akan dihapus secara permanen.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Permanen',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('rapat-terbuka.destroy', meeting.id), {
                onSuccess: () => {
                    Swal.fire('Terhapus', 'Sesi rapat berhasil dihapus.', 'success');
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Absensi Rapat Terbuka - SINDEN" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-800 tracking-tight flex items-center gap-2.5">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                        <span>Absensi Rapat Terbuka</span>
                    </h2>
                    <p class="text-xs text-slate-500 font-medium mt-1">
                        Buat formulir presensi dinas, generate QR Code publik tanpa login, dan cetak daftar hadir format kedinasan.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('rapat-terbuka.create')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm shadow-blue-500/20 transition active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Buat Sesi Rapat Baru</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            
            <!-- KARTU STATISTIK -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Agenda</div>
                        <div class="text-xl font-black text-slate-800">{{ stats?.total_meetings || 0 }}</div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Sesi Terbuka</div>
                        <div class="text-xl font-black text-emerald-600">{{ stats?.open_meetings || 0 }}</div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Selesai / Tutup</div>
                        <div class="text-xl font-black text-slate-700">{{ stats?.closed_meetings || 0 }}</div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Peserta Hadir</div>
                        <div class="text-xl font-black text-violet-600">{{ stats?.total_attendees || 0 }}</div>
                    </div>
                </div>
            </div>

            <!-- FILTER & PENCARIAN -->
            <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex-1 w-full sm:w-auto relative">
                    <input
                        type="text"
                        v-model="searchQuery"
                        @keyup.enter="handleFilter"
                        placeholder="Cari agenda rapat, tempat, atau petugas pembuat..."
                        class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select
                        v-model="statusFilter"
                        @change="handleFilter"
                        class="text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 py-2 px-3 text-slate-700 font-medium"
                    >
                        <option value="">Semua Status</option>
                        <option value="open">Sesi Dibuka</option>
                        <option value="closed">Sesi Ditutup</option>
                    </select>

                    <button
                        @click="handleFilter"
                        class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition"
                    >
                        Filter
                    </button>
                </div>
            </div>

            <!-- DAFTAR RAPAT -->
            <div v-if="meetings && meetings.data && meetings.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="meeting in meetings.data"
                    :key="meeting.id"
                    class="bg-white rounded-2xl border border-slate-100 shadow-xs hover:shadow-md transition duration-200 flex flex-col overflow-hidden"
                >
                    <!-- Header Card -->
                    <div class="p-5 pb-4 flex-1">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <span
                                :class="meeting.status === 'open' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'"
                                class="px-2.5 py-1 text-[10.5px] font-extrabold rounded-lg uppercase tracking-wider border inline-flex items-center gap-1.5"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="meeting.status === 'open' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                                {{ meeting.status === 'open' ? 'Absensi Dibuka' : 'Absensi Ditutup' }}
                            </span>

                            <div class="flex items-center gap-1">
                                <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-bold text-xs rounded-lg inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    {{ meeting.attendees_count }} Peserta
                                </span>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-slate-800 line-clamp-2 leading-snug mb-3">
                            {{ meeting.title }}
                        </h3>

                        <div class="space-y-1.5 text-xs text-slate-500">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="font-medium text-slate-700">{{ meeting.formatted_date }}</span>
                            </div>

                            <div v-if="meeting.time_start" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>Pukul {{ meeting.time_start }}{{ meeting.time_end ? (' s.d. ' + meeting.time_end) : '' }} WIB</span>
                            </div>

                            <div v-if="meeting.location" class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="11" r="3"></circle></svg>
                                <span class="truncate">{{ meeting.location }}</span>
                            </div>

                            <div class="flex items-center gap-2 pt-2 border-t border-slate-50 text-[11px] text-slate-400">
                                <span>Pembuat: <strong class="text-slate-600">{{ meeting.creator_name || 'Petugas SINDEN' }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="px-5 py-3.5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('rapat-terbuka.show', meeting.id)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-xs"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <span>Kelola & QR</span>
                            </Link>

                            <!-- Tombol Cetak PDF Berlabel Jelas -->
                            <a
                                :href="route('rapat-terbuka.pdf', meeting.id)"
                                target="_blank"
                                title="Cetak Format PDF Dinas"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition border border-rose-200"
                            >
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Cetak PDF</span>
                            </a>
                        </div>

                        <div class="flex items-center gap-1">
                            <!-- Toggle Status Buka / Tutup -->
                            <button
                                @click="toggleMeetingStatus(meeting)"
                                :title="meeting.status === 'open' ? 'Tutup Absensi' : 'Buka Absensi'"
                                class="p-1.5 text-slate-600 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                            >
                                <svg v-if="meeting.status === 'open'" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                <svg v-else class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </button>

                            <!-- Hapus -->
                            <button
                                @click="deleteMeeting(meeting)"
                                title="Hapus Agenda"
                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATE KOSONG -->
            <div v-else class="bg-white rounded-2xl border border-slate-100 p-12 text-center shadow-xs">
                <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Belum Ada Sesi Rapat Terbuka</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto mb-5">
                    Buat sesi rapat baru untuk mengizinkan peserta luar mengisi daftar hadir melalui pemindaian QR Code tanpa harus login.
                </p>
                <Link
                    :href="route('rapat-terbuka.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition shadow-xs"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    <span>Buat Sesi Rapat Sekarang</span>
                </Link>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
