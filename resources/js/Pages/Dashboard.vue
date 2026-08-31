<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue'; 
import axios from 'axios'; 

const props = defineProps({
    stats: Object,
    recent_logs: Array,
    combined_activities: Array, 
    pending_users: Array, 
});

const sendPreciseLocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                axios.post('/update-location', {
                    latitude: lat,
                    longitude: lng
                }).then(response => {
                    console.log('Sistem SINDEN: Lokasi GPS diperbarui.');
                }).catch(error => {
                    console.error('Gagal memperbarui lokasi GPS.');
                });
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    console.warn("Akses lokasi ditolak oleh personel.");
                }
            },
            { enableHighAccuracy: true } 
        );
    }
};

onMounted(() => {
    sendPreciseLocation();
});
</script>

<template>
    <Head title="Dashboard Utama - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-8 font-sans">
            
            <!-- Hero Welcome Card -->
            <div class="bg-white p-8 rounded-3xl border border-[#E2E8F0] shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 font-extrabold text-[10px] uppercase rounded-full tracking-wider">Dashboard Analitik</span>
                        <span class="text-slate-400 text-xs font-semibold"> Live System SINDEN</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight"> Selamat datang kembali, {{ $page.props.auth.user.pangkat || '' }} {{ $page.props.auth.user.name }}
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed max-w-2xl"> Sistem Informasi Detasemen Intelijen  Siap mendukung efisiensi, pengawasan lokasi, administrasi surat, & manajemen data personel hari ini.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link :href="route('letters.index')" class="px-5 py-3 bg-blue-600 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider hover:bg-blue-700 transition shadow-md shadow-blue-500/20">
                        + Buat Surat Baru
                    </Link>
                </div>
            </div>

            <!-- Stats Widgets Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Buku Nomor Surat</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.total_logs }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Total Registrasi Surat Keluar</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Arsip Berkas</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.total_archives }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Dokumen Terarsip</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Master Personel</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.active_personnel }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Pengguna Sistem Aktif</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                </div>

            </div>

            <!-- Content Grid: Activity Logs & Pending Registrations -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Log Aktivitas Terkini -->
                <div v-if="combined_activities" class="bg-white rounded-2xl border border-[#E2E8F0] p-6 lg:col-span-2 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 bg-blue-600 rounded-full animate-pulse"></span> Log Aktivitas & Akses Terkini
                        </h4>
                    </div>
                    
                    <div v-if="combined_activities.length > 0" class="space-y-3">
                        <div v-for="activity in combined_activities" :key="activity.id" 
                             :class="activity.type === 'ADMIN_ACTION' ? 'bg-amber-50/50 border-amber-200/60' : 'bg-slate-50/70 border-slate-100'"class="flex items-center justify-between p-4 rounded-2xl border transition hover:shadow-xs">
                            
                            <div class="min-w-0 flex-1 pr-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-extrabold text-slate-900 uppercase truncate max-w-[200px]">{{ activity.user_name }}</span>
                                    <span v-if="activity.type === 'ADMIN_ACTION'" class="text-[8px] font-extrabold bg-amber-500 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Action</span>
                                    <span v-else class="text-[8px] font-extrabold bg-blue-600 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Access</span>
                                </div>
                                <p :class="activity.type === 'ADMIN_ACTION' ? 'text-amber-800' : 'text-slate-600'"class="text-[11px] font-medium tracking-tight truncate">
                                    {{ activity.type === 'ADMIN_ACTION' ? activity.description : 'Akses Portal: ' + activity.location }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                 <span class="text-[10px] font-mono font-bold text-slate-400 uppercase">{{ activity.login_at }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold italic">Menunggu sinkronisasi aktivitas...</div>
                </div>

                <!-- Antrean Registrasi Pending -->
                <div v-if="pending_users" class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 bg-rose-500 rounded-full"></span> Antrean Registrasi Pending
                        </h4>
                    </div>

                    <div v-if="pending_users.length > 0" class="space-y-3">
                        <div v-for="pending in pending_users" :key="pending.id" class="flex items-center justify-between p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-extrabold text-slate-900 block uppercase truncate">{{ pending.name }}</span>
                                <span class="text-[10px] text-slate-400 font-bold block mt-0.5">NRP. {{ pending.nrp }}</span>
                            </div>
                            <Link :href="route('users.index')" class="ms-3 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-900 hover:text-white text-slate-700 rounded-xl shadow-xs transition-all text-[10px] font-extrabold uppercase tracking-wider"> Tinjau
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/40 rounded-2xl border border-dashed border-slate-200">0 Antrean Pending</div>
                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>