<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue'; 
import axios from 'axios'; 

// Menangkap data terpadu dari routes/web.php
const props = defineProps({
    stats: Object,
    recent_logs: Array,
    combined_activities: Array, 
    pending_users: Array, 
});

// FUNGSI OTOMATIS: Ambil lokasi GPS Perangkat
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
    <Head title="Portal Saya - Overview" />

    <AuthenticatedLayout>
        <template #header>
            <div class="w-full bg-gradient-to-r from-slate-50 to-blue-50/30 p-6 rounded-2xl border border-slate-200/60 mb-6 text-left">
                <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Portal Analitik Utama</h2>
                    <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                        Selamat datang kembali, 
                        <span class="text-[#0B4087] font-bold">
                            {{ $page.props.auth.user.pangkat || 'Letnan Dua (E)' }} {{ $page.props.auth.user.name }}
                        </span>. 
                        Sinden siap mendukung akuntabilitas, efisiensi, dan transparansi manajemen data Personel hari ini.
                    </p>
            </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 text-left">
            
            <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-[0_2px_8px_rgba(0,0,0,0.01)] flex items-center justify-between transition-all hover:border-slate-300">
                <div class="space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Buku Nomor</span>
                    <h3 class="text-3xl font-black text-slate-900 leading-none py-1">{{ stats.total_logs }}</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Total Registrasi Keluar</p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center font-mono font-bold text-xs shadow-sm">
                    BN
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-[0_2px_8px_rgba(0,0,0,0.01)] flex items-center justify-between transition-all hover:border-slate-300">
                <div class="space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Arsip Surat</span>
                    <h3 class="text-3xl font-black text-slate-900 leading-none py-1">{{ stats.total_archives }}</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Dokumen Terarsip</p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center font-mono font-bold text-xs shadow-sm">
                    AS
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200/70 shadow-[0_2px_8px_rgba(0,0,0,0.01)] flex items-center justify-between transition-all hover:border-slate-300">
                <div class="space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Master Personnel</span>
                    <h3 class="text-3xl font-black text-slate-900 leading-none py-1">{{ stats.active_personnel }}</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Pengguna Sistem Aktif</p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center font-mono font-bold text-xs shadow-sm">
                    MP
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-left">
            
            <div v-if="combined_activities" class="bg-white rounded-2xl border border-slate-200/70 p-6 lg:col-span-2 shadow-[0_2px_12px_rgba(0,0,0,0.005)]">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <span class="h-1.5 w-1.5 bg-blue-600 rounded-full"></span>
                        Log Aktivitas Terkini
                    </h4>
                </div>
                
                <div v-if="combined_activities.length > 0" class="space-y-3.5">
                    <div v-for="activity in combined_activities" :key="activity.id" 
                         :class="activity.type === 'ADMIN_ACTION' ? 'bg-amber-50/10 border-amber-100/70' : 'bg-slate-50/50 border-slate-100'"
                         class="flex items-center justify-between p-4 rounded-xl border transition-all">
                        
                        <div class="min-w-0 flex-1 pr-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-bold text-slate-900 uppercase truncate max-w-[200px]">{{ activity.user_name }}</span>
                                <span v-if="activity.type === 'ADMIN_ACTION'" class="text-[8px] font-bold bg-amber-500 text-white px-1.5 py-0.5 rounded uppercase tracking-wider">Action</span>
                                <span v-else class="text-[8px] font-bold bg-blue-600 text-white px-1.5 py-0.5 rounded uppercase tracking-wider">Access</span>
                            </div>
                            <p :class="activity.type === 'ADMIN_ACTION' ? 'text-amber-700' : 'text-slate-500'" 
                               class="text-[11px] font-medium tracking-tight truncate">
                                {{ activity.type === 'ADMIN_ACTION' ? activity.description : 'Akses Portal: ' + activity.location }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                             <span class="text-[10px] font-semibold font-mono text-slate-400 uppercase tracking-tighter">{{ activity.login_at }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12 text-xs text-slate-400 italic">Menunggu sinkronisasi aktivitas...</div>
            </div>

            <div v-if="pending_users" class="bg-white rounded-2xl border border-slate-200/70 p-6 shadow-[0_2px_12px_rgba(0,0,0,0.005)]">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <span class="h-1.5 w-1.5 bg-rose-500 rounded-full"></span>
                        Antrean Registrasi WP
                    </h4>
                </div>

                <div v-if="pending_users.length > 0" class="space-y-3.5">
                    <div v-for="pending in pending_users" :key="pending.id" class="flex items-center justify-between p-4 bg-slate-50/40 rounded-xl border border-slate-100">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-slate-800 block uppercase truncate">{{ pending.name }}</span>
                            <span class="text-[10px] text-slate-400 font-semibold block mt-0.5">NRP. {{ pending.nrp }}</span>
                        </div>
                        <Link :href="route('users.index')" class="ms-4 px-3 py-1.5 bg-white border border-slate-200 hover:border-slate-800 text-slate-700 hover:text-slate-900 rounded-lg shadow-sm transition-all text-[10px] font-bold uppercase tracking-wider">
                            Tinjau
                        </Link>
                    </div>
                </div>
                <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/20 rounded-xl border border-dashed border-slate-200/60">0 Antrean Pending</div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Menghapus default scrollbar bawaan browser untuk estetika minimalis */
::-webkit-scrollbar {
    width: 0px;
    height: 0px;
}
</style>