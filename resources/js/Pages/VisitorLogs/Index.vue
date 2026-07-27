<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3'; 
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object // Menerima filter search dari Controller
});

//hapus Riwayat
const confirmDeleteLogs = () => {
    if (confirm('LAPOR! Apakah Anda yakin ingin MEMUSNAHKAN seluruh riwayat log pengunjung? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(route('visitor-logs.clear'), {
            onSuccess: () => alert('Lapor! Seluruh riwayat telah dibersihkan.'),
            preserveScroll: true
        });
    }
};

// State untuk input pencarian
const search = ref(props.filters?.search || '');

// FUNGSI PERBAIKAN: Memperbaiki URL Google Maps
const openMap = (location) => {
    if (location && location.includes('GPS:')) {
        const coords = location.replace('GPS: ', '').split(',');
        const lat = coords[0].trim();
        const lon = coords[1].trim();
        
        // PERBAIKAN: Menggunakan URL Google Maps standar yang presisi
        const url = `https://www.google.com/maps?q=${lat},${lon}`;
        window.open(url, '_blank');
    } else {
        alert('Data koordinat GPS belum tersedia (Hanya tersedia estimasi wilayah via IP).');
    }
};

// FITUR PENCARIAN: Otomatis mencari saat mengetik
watch(search, (value) => {
    router.get(route('visitor-logs.index'), { search: value }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
});

// FITUR REFRESH OTOMATIS: Update data setiap 30 detik tanpa reload halaman
let refreshInterval;
onMounted(() => {
    refreshInterval = setInterval(() => {
        // PERBAIKAN: Memastikan router memanggil ulang data logs secara spesifik
        router.reload({ 
            only: ['logs'], 
            preserveScroll: true,
            preserveState: true, // Menjaga agar input pencarian tidak hilang saat refresh
            onSuccess: () => {
                console.log("Log Monitoring Diperbarui: " + new Date().toLocaleTimeString());
            }
        });
    }, 1000); // Saya turunkan ke 10 detik agar Bapak lebih cepat melihat hasilnya
});

onUnmounted(() => {
    clearInterval(refreshInterval);
});

const formatTime = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Monitoring Pengunjung" />
    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Log Monitoring Pengunjung</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Sistem Audit Akses Perangkat & Pengawasan Lokasi SINDEN</p>
                </div>

                <div class="flex items-center gap-3">
                    <button @click="confirmDeleteLogs" class="bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 px-4 py-2.5 rounded-2xl text-xs font-extrabold uppercase border border-rose-200 shadow-xs transition tracking-wider flex items-center gap-2">
                        <span>🗑️</span> Musnahkan Riwayat Log
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-3xl shadow-xs border border-[#E2E8F0] flex items-center gap-5">
                    <div class="h-14 w-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center font-black text-xl shadow-xs">📊</div>
                    <div>
                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Total Akses Masuk</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5">{{ logs.total }} <span class="text-xs font-semibold text-slate-400">Record Terdeteksi</span></p>
                    </div>
                </div>
                
                <div class="bg-gray-900 p-6 rounded-[2rem] shadow-xl border border-gray-800 flex items-center gap-5">
                    <div class="h-12 w-12 bg-white/10 text-white rounded-2xl flex items-center justify-center font-black animate-pulse">!</div>
                    <div>
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Status Keamanan Sistem</p>
                        <p class="text-xl font-black text-emerald-400 tracking-tighter uppercase italic">Terpantau Aktif 24/7</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8 border-b border-gray-50 flex flex-col md:flex-row justify-between items-center bg-gray-50/30 gap-4">
                    <h3 class="font-black text-gray-400 uppercase text-xs tracking-widest">Monitoring Perangkat & Lokasi Personel</h3>
                    
                    <div class="relative w-full md:w-72">
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Cari Nama / NRP / IP..." 
                            class="w-full bg-white border-gray-200 rounded-2xl text-xs font-bold px-5 py-3 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm"
                        />
                    </div>
                    
                    <button @click="confirmDeleteLogs" 
                        class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-red-900/20 active:scale-95">
                        ⚠️ Hapus Riwayat
                    </button>
                    
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50/80 uppercase">
                            <tr class="text-[10px] font-black text-gray-400 tracking-[0.15em]">
                                <th class="px-8 py-5">Waktu Akses</th>
                                <th class="px-8 py-5">Identitas Personel</th>
                                <th class="px-8 py-5">Sistem & Perangkat</th>
                                <th class="px-8 py-5">Lokasi Terdeteksi</th>
                                <th class="px-8 py-5 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-sm">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-indigo-50/20 transition-all group">
                                <td class="px-8 py-4 font-mono text-[11px] text-gray-500">
                                    {{ formatTime(log.login_at || log.created_at) }}
                                </td>
                                <td class="px-8 py-4">
                                    <span class="font-black text-indigo-900 block text-sm uppercase">
                                        {{ log.user_name || 'Tamu / Guest' }}
                                    </span>
                                    <code class="text-[10px] font-bold text-indigo-400 bg-indigo-50 px-1.5 py-0.5 rounded mt-1 inline-block">
                                        {{ log.ip_address }}
                                    </code>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-700 uppercase text-[11px] tracking-tight">
                                            {{ log.device || 'Perangkat Unknown' }}
                                        </span>
                                        <span class="text-[9px] text-indigo-500 font-black uppercase italic mt-0.5 truncate max-w-[200px]">
                                            {{ log.user_agent }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-8 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-2 rounded-full bg-red-500 animate-pulse" v-if="log.location && log.location.includes('GPS:')"></div>
                                        <span class="text-xs font-bold text-gray-600 uppercase tracking-tight">
                                            {{ log.location || 'Unknown / Localhost' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-8 py-4 text-right">
                                    <button 
                                        @click="openMap(log.location)"
                                        class="inline-flex items-center gap-2 bg-white border border-gray-200 text-indigo-600 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all shadow-sm disabled:opacity-30 disabled:grayscale"
                                        :disabled="!log.location || !log.location.includes('GPS:')"
                                    >
                                        📍 Buka Peta
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 bg-gray-50/50 border-t border-gray-50 flex justify-between items-center">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        Data: {{ logs.from || 0 }} - {{ logs.to || 0 }} dari {{ logs.total }} Akses
                    </span>
                    <div class="flex gap-2">
                        <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="px-5 py-2 bg-white border border-gray-200 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 transition-all shadow-sm">Sebelumnya</Link>
                        <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="px-5 py-2 bg-white border border-gray-200 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 transition-all shadow-sm">Selanjutnya</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>