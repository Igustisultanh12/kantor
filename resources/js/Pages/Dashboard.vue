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
        <template #header-title>DASHBOARD ANALITIK UTAMA</template>

        <div class="space-y-8 font-sans">
            
            <!-- Ringkasan Card Statistik Utama (SISFOPERSKC Style) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">TOTAL NOMOR SURAT</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total_logs }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#2563EB]/5 flex items-center justify-center text-[#2563EB]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">ARSIP BERKAS</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total_archives }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#2563EB]/5 flex items-center justify-center text-[#2563EB]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">ANGGOTA AKTIF</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.active_personnel }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#2563EB]/5 flex items-center justify-center text-[#2563EB]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">ANTREAN PENDING</p>
                        <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ pending_users ? pending_users.length : 0 }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#2563EB]/5 flex items-center justify-center text-[#2563EB]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>

            </div>

            <!-- Ruang Panel Grafik Analitis & Proporsi -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Tren Kepadatan Akses Sistem (Bar Chart) -->
                <div class="lg:col-span-2 bg-white border border-[#E2E8F0] p-6 rounded-2xl shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide">TREN KEPADATAN AKSES SISTEM (7 HARI TERAKHIR)</h4>
                        <span class="text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">Sistem Normal</span>
                    </div>
                    <div class="h-64 flex items-end gap-3 pt-6 px-2">
                        <div v-for="(val, idx) in [12, 28, 45, 62, 100, 18, 14]" :key="idx" class="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                            <div class="w-full bg-[#2563EB] rounded-t-md opacity-85 hover:opacity-100 transition-all duration-200" :style="{ height: `${val}%` }"></div>
                            <span class="text-[10px] font-medium text-slate-400 truncate w-full text-center">{{ ['21 Jul', '22 Jul', '23 Jul', '24 Jul', '25 Jul', '26 Jul', '27 Jul'][idx] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Distribusi Proporsi Anggota Per Matra -->
                <div class="bg-white border border-[#E2E8F0] p-6 rounded-2xl shadow-sm flex flex-col justify-between">
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-6">PROPORSI ANGGOTA PER MATRA</h4>
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <div class="flex justify-between text-xs font-medium">
                                    <span class="text-slate-600 font-bold">TNI AD</span>
                                    <span class="font-bold text-slate-800">1 Personel</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#2563EB] h-full rounded-full" style="width: 5%"></div>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-xs font-medium">
                                    <span class="text-slate-600 font-bold">TNI AL</span>
                                    <span class="font-bold text-slate-800">{{ stats.active_personnel || 107 }} Personel</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#2563EB] h-full rounded-full" style="width: 95%"></div>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-xs font-medium">
                                    <span class="text-slate-600 font-bold">TNI AU</span>
                                    <span class="font-bold text-slate-800">0 Personel</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#2563EB] h-full rounded-full" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center pt-4 border-t border-[#E2E8F0] mt-6">
                        <p class="text-xs text-slate-400 font-medium">Total Data Teragregasi Nasional</p>
                    </div>
                </div>

            </div>

            <!-- Tabel Aktivitas Sistem Terakhir (Audit Trail) -->
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-[#E2E8F0] flex justify-between items-center">
                    <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Aktivitas Sistem Terakhir (Audit Trail)</h4>
                    <Link :href="route('letters.index')" class="text-xs font-extrabold text-blue-600 hover:underline uppercase tracking-wider">
                        + Buat Surat Baru
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-400 font-semibold text-[11px] border-b border-[#E2E8F0] uppercase tracking-wider">
                                <th class="p-4">Operator</th>
                                <th class="p-4">Aksi Operasi</th>
                                <th class="p-4">Alamat IP / Lokasi</th>
                                <th class="p-4">Waktu Operasional</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0] text-xs text-slate-600 font-medium">
                            <template v-if="combined_activities && combined_activities.length > 0">
                                <tr v-for="act in combined_activities" :key="act.id" class="hover:bg-slate-50/40 transition">
                                    <td class="p-4 font-semibold text-slate-800">{{ act.user_name }}</td>
                                    <td class="p-4">
                                        <span :class="act.type === 'ADMIN_ACTION' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase">
                                            {{ act.type === 'ADMIN_ACTION' ? act.description : 'Akses Portal: ' + (act.location || 'SINDEN System') }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500 font-mono">{{ act.ip_address || '127.0.0.1' }}</td>
                                    <td class="p-4 text-slate-400">{{ act.login_at || new Date().toLocaleString('id-ID') }}</td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="4" class="p-8 text-center text-slate-400 italic">Menunggu aktivitas sistem...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>