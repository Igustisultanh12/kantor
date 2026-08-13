<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    news: Object,
    filters: Object,
    stats: Object,
    executive_summary: String
});

const isModalOpen = ref(false);
const selectedCategory = ref(props.filters.category || 'all');
const selectedRisk = ref(props.filters.risk_level || 'all');
const searchQuery = ref(props.filters.search || '');

const form = useForm({
    title: '',
    source_name: 'Input Staf Intel',
    category: 'hankam',
    risk_level: 'medium',
    sentiment: 'neutral',
    location: 'Surabaya',
    summary: '',
    url: ''
});

const customTopicQuery = ref('');

const handleFilter = () => {
    router.get(route('media-monitoring.index'), {
        category: selectedCategory.value,
        risk_level: selectedRisk.value,
        search: searchQuery.value
    }, { preserveState: true, preserveScroll: true });
};

const resetFilter = () => {
    selectedCategory.value = 'all';
    selectedRisk.value = 'all';
    searchQuery.value = '';
    customTopicQuery.value = '';
    handleFilter();
};

const refreshFeeds = (topic = null) => {
    const targetTopic = typeof topic === 'string' ? topic : customTopicQuery.value;
    
    Swal.fire({
        title: targetTopic ? `Memindai Topik: "${targetTopic}"...` : 'Pemindaian AI OSINT...',
        text: 'Menghubungkan ke portal berita online & media sosial terkini.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    router.post(route('media-monitoring.refresh'), { topic: targetTopic }, {
        onSuccess: () => { 
            Swal.close(); 
            if (targetTopic) customTopicQuery.value = '';
        }
    });
};

const clearAllNews = () => {
    Swal.fire({
        title: 'Hapus Semua Berita EWS?',
        text: 'Seluruh data berita di radar EWS akan dibersihkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Ya, Hapus Semua',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('media-monitoring.clear-all'), {}, {
                onSuccess: () => {
                    Swal.fire('Terhapus!', 'Seluruh berita EWS berhasil dibersihkan.', 'success');
                }
            });
        }
    });
};

const submitForm = () => {
    form.post(route('media-monitoring.store'), {
        onSuccess: () => {
            isModalOpen.value = false;
            form.reset();
            Swal.fire('Berhasil', 'Laporan Isu Media Berhasil Ditambahkan ke Radar EWS.', 'success');
        }
    });
};

const togglePin = (id) => {
    router.patch(route('media-monitoring.toggle-pin', id), {}, { preserveScroll: true });
};

const deleteNews = (id) => {
    Swal.fire({
        title: 'Hapus Laporan Berita?',
        text: 'Data berita ini akan dihapus dari radar EWS.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if (res.isConfirmed) {
            router.delete(route('media-monitoring.destroy', id), { preserveScroll: true });
        }
    });
};

const getRiskClass = (level) => {
    switch(level) {
        case 'critical': return 'bg-rose-600 text-white animate-pulse';
        case 'high': return 'bg-amber-500 text-white';
        case 'medium': return 'bg-blue-600 text-white';
        default: return 'bg-emerald-600 text-white';
    }
};

const getSentimentBadge = (sentiment) => {
    switch(sentiment) {
        case 'negative': return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'positive': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <Head title="AI Media Monitoring & EWS" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 px-3 sm:px-6 space-y-6 font-sans">

            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest block">RADAR EARLY WARNING SYSTEM (EWS)</span>
                    <h2 class="font-black text-xl text-slate-900 uppercase tracking-tight flex items-center gap-2">
                        <span>AI Media Monitoring & Isu Wilayah</span>
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Pemindaian Otomatis Berita OSINT, Sentimen Publik, & Peta Kerawanan Kodaeral V</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                    <button @click="refreshFeeds(null)" class="flex-1 sm:flex-none bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-2xl text-xs font-black uppercase shadow-md flex items-center justify-center gap-2 transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Sinkronkan AI News</span>
                    </button>
                    <button @click="isModalOpen = true" class="flex-1 sm:flex-none bg-slate-900 hover:bg-slate-800 text-white px-4 py-3 rounded-2xl text-xs font-black uppercase shadow-md flex items-center justify-center gap-2 transition active:scale-95">
                        <span>+ Input Isu Berita</span>
                    </button>
                    <button @click="clearAllNews" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-4 py-3 rounded-2xl text-xs font-black uppercase shadow-xs flex items-center justify-center gap-1.5 transition active:scale-95" title="Kosongkan seluruh berita di radar EWS">
                        🗑️ <span>Hapus Semua</span>
                    </button>
                </div>
            </div>

            <!-- Custom Topic AI OSINT Scanner Widget -->
            <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 p-5 rounded-3xl shadow-md text-white flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <span class="text-[9px] font-black uppercase tracking-widest text-indigo-200">🔍 AI CUSTOM TOPIC SCANNER</span>
                    <h3 class="text-sm font-extrabold">Pindai Berita & Medsos Berdasarkan Topik / Tokoh / Objek Spesifik</h3>
                </div>
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <input v-model="customTopicQuery" @keyup.enter="refreshFeeds()" type="text" placeholder="Contoh: Pangkoarmada II, Penyelundupan Rokok, Danlantamal..." class="w-full md:w-80 text-xs font-bold rounded-2xl border-0 bg-white/10 text-white placeholder-indigo-200/60 focus:ring-2 focus:ring-indigo-300 py-2.5 px-4" />
                    <button @click="refreshFeeds()" class="bg-indigo-500 hover:bg-indigo-400 text-white px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider shrink-0 transition shadow-sm active:scale-95">
                        🔍 Pindai Topik
                    </button>
                </div>
            </div>

            <!-- Executive AI Intelligence Briefing Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-7 rounded-3xl shadow-xl border border-indigo-900/50 space-y-3 relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500 animate-ping"></span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-300">EXECUTIVE AI INTEL BRIEFING</span>
                    </div>
                    <span class="text-[9px] font-bold bg-indigo-900/80 border border-indigo-700/50 px-3 py-1 rounded-full text-indigo-200 uppercase">
                        REAL-TIME OSINT ASSESSMENT
                    </span>
                </div>
                <p class="text-xs sm:text-sm font-semibold leading-relaxed text-slate-200">
                    {{ executive_summary }}
                </p>
                <div class="pt-2 flex flex-wrap gap-2 text-[10px] font-extrabold uppercase text-slate-400">
                    <span>Wilayah Pantau: Surabaya, Selat Madura, Gresik, Sidoarjo & Pesisir Jatim</span>
                </div>
            </div>

            <!-- Statistic Overview Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">TOTAL BERITA DIPANTAU</span>
                    <div class="text-2xl font-black text-slate-900">{{ stats.total_news }}</div>
                    <span class="text-[9px] text-slate-500 font-bold">Sumber OSINT & Media Publik</span>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[9px] font-black text-rose-500 uppercase tracking-wider">ISU RISIKO TINGGI / KRITIS</span>
                    <div class="text-2xl font-black text-rose-600">{{ stats.critical_count }}</div>
                    <span class="text-[9px] text-rose-500 font-bold">Perlu Perhatian Khusus</span>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[9px] font-black text-amber-500 uppercase tracking-wider">SENTIMEN NEGATIF</span>
                    <div class="text-2xl font-black text-amber-600">{{ stats.negative_count }}</div>
                    <span class="text-[9px] text-slate-500 font-bold">Dinamika Perhatian Publik</span>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[9px] font-black text-indigo-600 uppercase tracking-wider">BIDANG UTAMA (HANKAM)</span>
                    <div class="text-2xl font-black text-indigo-700">{{ stats.categories.hankam }}</div>
                    <span class="text-[9px] text-slate-500 font-bold">Isu Pertahanan & Keamanan</span>
                </div>
            </div>

            <!-- Filters & Search Bar -->
            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs space-y-3">
                <div class="flex flex-col md:flex-row justify-between items-stretch md:items-center gap-3">
                    
                    <!-- Category Tabs (IPOLEKSOSBUDHANKAM) -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 custom-scrollbar">
                        <button @click="selectedCategory = 'all'; handleFilter();"
                                :class="selectedCategory === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition">
                            Semua
                        </button>
                        <button @click="selectedCategory = 'hankam'; handleFilter();"
                                :class="selectedCategory === 'hankam' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition">
                            Hankam ({{ stats.categories.hankam }})
                        </button>
                        <button @click="selectedCategory = 'politik'; handleFilter();"
                                :class="selectedCategory === 'politik' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition">
                            Politik ({{ stats.categories.politik }})
                        </button>
                        <button @click="selectedCategory = 'ekonomi'; handleFilter();"
                                :class="selectedCategory === 'ekonomi' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition">
                            Ekonomi ({{ stats.categories.ekonomi }})
                        </button>
                        <button @click="selectedCategory = 'sosbud'; handleFilter();"
                                :class="selectedCategory === 'sosbud' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition">
                            Sosbud ({{ stats.categories.sosbud }})
                        </button>
                        <button @click="selectedCategory = 'ideologi'; handleFilter();"
                                :class="selectedCategory === 'ideologi' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition">
                            Ideologi ({{ stats.categories.ideologi }})
                        </button>
                    </div>

                    <!-- Risk Selector & Search Input -->
                    <div class="flex items-center gap-2 shrink-0">
                        <select v-model="selectedRisk" @change="handleFilter" class="rounded-xl border-slate-200 text-xs font-bold bg-slate-50 focus:ring-indigo-500 py-2">
                            <option value="all">Semua Level Risiko</option>
                            <option value="critical">Critical (Kritis)</option>
                            <option value="high">High (Tinggi)</option>
                            <option value="medium">Medium (Sedang)</option>
                            <option value="low">Low (Rendah)</option>
                        </select>

                        <div class="relative flex-1 sm:w-48">
                            <input v-model="searchQuery" @keyup.enter="handleFilter" type="text" placeholder="Cari Berita / Lokasi..." class="w-full text-xs font-bold rounded-xl border-slate-200 bg-slate-50 focus:ring-indigo-500 py-2 pr-8" />
                            <button @click="handleFilter" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">🔍</button>
                        </div>

                        <button @click="resetFilter" class="bg-slate-100 text-slate-500 hover:text-slate-700 p-2 rounded-xl text-xs font-bold">Reset</button>
                    </div>

                </div>
            </div>

            <!-- News Feed Cards Grid -->
            <div v-if="news.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="item in news.data" :key="item.id" class="bg-white rounded-3xl border border-slate-200 p-6 shadow-xs flex flex-col justify-between space-y-4 hover:shadow-md transition relative group">
                    
                    <!-- Card Top Header (Source & Risk Badge) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-black text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-100 truncate">
                                {{ item.source_name }}
                            </span>
                            <div class="flex items-center gap-1">
                                <button @click="togglePin(item.id)" :title="item.is_pinned ? 'Unpin Berita' : 'Pin Berita'" class="text-xs p-1 rounded hover:bg-slate-100">
                                    <span v-if="item.is_pinned" class="text-amber-500 font-bold">📌 Pinned</span>
                                    <span v-else class="text-slate-300">📌</span>
                                </button>
                                <span :class="getRiskClass(item.risk_level)" class="text-[9px] font-black px-2.5 py-0.5 rounded-full uppercase">
                                    {{ item.risk_level }}
                                </span>
                            </div>
                        </div>

                        <!-- Title -->
                        <h3 class="font-extrabold text-sm sm:text-base text-slate-900 leading-snug line-clamp-2">
                            {{ item.title }}
                        </h3>

                        <!-- Location & Category Badges -->
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 flex-wrap">
                            <span class="bg-slate-100 px-2 py-0.5 rounded-md">📍 {{ item.location }}</span>
                            <span class="bg-slate-100 px-2 py-0.5 rounded-md uppercase">Bidang: {{ item.category }}</span>
                            <span :class="getSentimentBadge(item.sentiment)" class="px-2 py-0.5 rounded-md border text-[9px] uppercase font-black">
                                Sentimen {{ item.sentiment }}
                            </span>
                        </div>

                        <!-- AI Summary Box -->
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80 space-y-1">
                            <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest block">RINGKASAN AI OSINT</span>
                            <p class="text-xs text-slate-600 font-medium leading-relaxed italic">
                                "{{ item.summary }}"
                            </p>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                        <span class="text-[9px] text-slate-400 font-bold">
                            {{ item.published_at ? new Date(item.published_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Hari Ini' }}
                        </span>
                        
                        <div class="flex items-center gap-2">
                            <button @click="deleteNews(item.id)" class="text-[10px] text-rose-500 hover:text-rose-700 font-bold px-2 py-1">Hapus</button>
                            <a v-if="item.url" :href="item.url" target="_blank" class="bg-slate-900 hover:bg-slate-800 text-white px-3.5 py-1.5 rounded-xl text-[10px] font-black uppercase transition">
                                Buka Sumber →
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="bg-white p-12 rounded-3xl border border-slate-200 text-center space-y-3">
                <div class="text-4xl text-slate-300">🌐</div>
                <h4 class="font-extrabold text-slate-700 text-sm uppercase">Tidak Ada Data Berita EWS Ditemukan</h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">Coba reset filter pencarian Anda atau klik tombol 'Sinkronkan AI News' untuk memindai berita online terbaru.</p>
                <button @click="resetFilter" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase">Reset Filter</button>
            </div>

        </div>

        <!-- MODAL INPUT ISU BERITA MANUALL -->
        <div v-if="isModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm">
            <div class="bg-white w-full max-w-lg rounded-3xl p-6 sm:p-7 shadow-2xl space-y-4 text-left">
                <div class="flex items-center justify-between border-b pb-3">
                    <div>
                        <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest block">INPUT LAPORAN ISU WILAYAH</span>
                        <h3 class="font-black text-base text-slate-900 uppercase">Tambah Berita / Temuan EWS</h3>
                    </div>
                    <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-3.5">
                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Judul Berita / Kejadian</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500" placeholder="Judul Berita / Laporan Isu..." required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Sumber Berita</label>
                            <input v-model="form.source_name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500" placeholder="Detik / X / Staf Intel" required />
                        </div>
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Lokasi Kejadian</label>
                            <input v-model="form.location" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500" placeholder="Surabaya / Gresik..." required />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Bidang (IPOLEKSOSBUDHANKAM)</label>
                            <select v-model="form.category" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500">
                                <option value="hankam">Hankam</option>
                                <option value="politik">Politik</option>
                                <option value="ekonomi">Ekonomi</option>
                                <option value="sosbud">Sosbud</option>
                                <option value="ideologi">Ideologi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Tingkat Risiko</label>
                            <select v-model="form.risk_level" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500">
                                <option value="low">Rendah (Low)</option>
                                <option value="medium">Sedang (Medium)</option>
                                <option value="high">Tinggi (High)</option>
                                <option value="critical">Kritis (Critical)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Sentimen</label>
                            <select v-model="form.sentiment" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500">
                                <option value="neutral">Netral</option>
                                <option value="negative">Negatif</option>
                                <option value="positive">Positif</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Link URL Berita (Opsional)</label>
                        <input v-model="form.url" type="url" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500" placeholder="https://news.com/..." />
                    </div>

                    <div>
                        <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Ringkasan / Catatan AI (Opsional)</label>
                        <textarea v-model="form.summary" rows="2" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 focus:ring-indigo-500" placeholder="Biarkan kosong untuk ringkasan otomatis..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button type="button" @click="isModalOpen = false" class="bg-slate-100 text-slate-600 px-4 py-2.5 rounded-xl text-xs font-bold uppercase">Batal</button>
                        <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-xs font-black uppercase shadow-md">Simpan ke Radar EWS</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
