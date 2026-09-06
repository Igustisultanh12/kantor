<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    submission: Object,
    all_submissions: Array,
    searched_identifier: String,
    not_found: Boolean,
    stages: Array,
});

const page = usePage();
const settings = computed(() => page.props.settings || {});
const appName = computed(() => settings.value.agency_name || 'DENINTEL KODAERAL V');
const configuredLogo = computed(() => {
    const logo = settings.value.agency_logo || settings.value.logo;
    if (!logo) return null;
    return (logo.startsWith('http') || logo.startsWith('/storage')) ? logo : '/storage/' + logo;
});

const searchInput = ref(props.searched_identifier || '');
const isSearching = ref(false);

const activeSubmission = ref(props.submission);

watch(() => props.submission, (newVal) => {
    activeSubmission.value = newVal;
});

const selectSubmission = (sub) => {
    activeSubmission.value = sub;
};

const handleSearch = () => {
    const query = searchInput.value.trim();
    if (!query) return;

    isSearching.value = true;
    router.get(route('tracking-sc.index'), { identifier: query }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isSearching.value = false;
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) + ' WIB';
};
</script>

<template>
    <Head title="Pelacakan Pengajuan Security Clearance (SC) - SINDEN" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-blue-600 selection:text-white flex flex-col justify-between">
        
        <!-- Header Kedinasan -->
        <header class="border-b border-slate-800/80 bg-slate-900/80 backdrop-blur-md sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img v-if="configuredLogo" :src="configuredLogo" class="h-10 w-10 object-contain" alt="Logo Kedinasan" />
                    <div v-else class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center font-black text-white text-lg">
                        S
                    </div>
                    <div>
                        <span class="text-[10px] font-mono font-bold text-blue-400 uppercase tracking-widest block">PORTAL RESMI SINDEN</span>
                        <h1 class="text-sm sm:text-base font-extrabold text-white tracking-tight">Pelacakan Security Clearance (SC)</h1>
                    </div>
                </div>

                <Link 
                    :href="route('login')" 
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white border border-slate-700 rounded-xl text-xs font-extrabold uppercase tracking-wider transition shadow-xs flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Masuk Sistem</span>
                </Link>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-8">
            
            <!-- Hero Search Card -->
            <div class="bg-gradient-to-b from-slate-900 via-slate-900/90 to-slate-950 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden">
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-16 -top-16 w-64 h-64 bg-teal-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="max-w-2xl mx-auto text-center space-y-3 relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-full text-[10px] font-extrabold uppercase tracking-widest">
                        <span>Layanan Mandiri Publik</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        Cek Riwayat & Posisi Berkas SC
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                        Masukkan Nomor Registrasi Pokok (NRP), Nomor Induk Pegawai (NIP), atau Nomor Induk Kependudukan (NIK) pemohon untuk mengetahui posisi berkas Security Clearance Anda secara transparan.
                    </p>

                    <!-- Search Input Form -->
                    <form @submit.prevent="handleSearch" class="pt-4 flex flex-col sm:flex-row gap-2 max-w-lg mx-auto">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input 
                                type="text"
                                v-model="searchInput"
                                required
                                placeholder="Masukkan NRP / NIP / NIK Anda..."
                                class="w-full pl-10 pr-4 py-3.5 bg-slate-950 border border-slate-700 focus:border-blue-500 rounded-2xl text-xs sm:text-sm font-bold text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                            />
                        </div>
                        <button 
                            type="submit"
                            :disabled="isSearching"
                            class="px-6 py-3.5 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider transition shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <svg v-if="isSearching" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isSearching ? 'Memeriksa...' : 'Cek Status' }}</span>
                        </button>
                    </form>
                    <p class="text-[10px] text-slate-500 font-medium">
                        *Sistem mencocokkan nomor identitas secara otomatis tanpa memerlukan login.
                    </p>
                </div>
            </div>

            <!-- HASIL TIDAK DITEMUKAN -->
            <div v-if="not_found" class="bg-rose-950/30 border border-rose-800/60 rounded-3xl p-6 sm:p-8 text-center max-w-2xl mx-auto space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-rose-900/60 border border-rose-700/60 text-rose-400 mx-auto flex items-center justify-center font-black text-xl">
                    !
                </div>
                <h3 class="text-base font-extrabold text-white">Data Pengajuan Tidak Ditemukan</h3>
                <p class="text-xs text-rose-300 leading-relaxed max-w-md mx-auto font-medium">
                    Tidak ditemukan berkas pengajuan Security Clearance dengan identitas <span class="font-mono font-bold text-white uppercase">{{ searched_identifier }}</span>. Pastikan nomor yang dimasukkan tepat atau berkas Anda telah diregistrasikan oleh petugas Denintel.
                </p>
            </div>

            <!-- JIKA HASIL DITEMUKAN -->
            <div v-if="activeSubmission" class="space-y-6">
                
                <!-- Pemilihan Tab jika ada beberapa riwayat untuk identitas yang sama -->
                <div v-if="all_submissions && all_submissions.length > 1" class="flex flex-wrap items-center gap-2 pb-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mr-2">Ditemukan {{ all_submissions.length }} Berkas:</span>
                    <button 
                        v-for="(sub, idx) in all_submissions" 
                        :key="sub.id"
                        @click="selectSubmission(sub)"
                        :class="activeSubmission.id === sub.id ? 'bg-blue-600 text-white border-blue-500 shadow-md' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'"
                        class="px-3.5 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer"
                    >
                        Berkas #{{ all_submissions.length - idx }} ({{ sub.tracking_code }})
                    </button>
                </div>

                <!-- Kartu Identitas Pemohon -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-lg">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6">
                        <div>
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="px-2.5 py-0.5 bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded-full text-[10px] font-mono font-bold uppercase">
                                    {{ activeSubmission.tracking_code }}
                                </span>
                                <span :class="{
                                    'bg-emerald-500/20 text-emerald-400 border-emerald-500/30': activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai',
                                    'bg-amber-500/20 text-amber-400 border-amber-500/30': activeSubmission.current_stage < 10 && activeSubmission.status !== 'ditolak',
                                    'bg-rose-500/20 text-rose-400 border-rose-500/30': activeSubmission.status === 'ditolak',
                                }" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border">
                                    {{ activeSubmission.status === 'selesai' || activeSubmission.current_stage === 10 ? 'SELESAI (SIAP DIAMBIL)' : 'DALAM PROSES' }}
                                </span>
                            </div>
                            <h3 class="text-xl sm:text-2xl font-black text-white">
                                {{ activeSubmission.nama }}
                            </h3>
                            <p class="text-xs text-slate-400 font-mono mt-0.5">
                                {{ activeSubmission.pangkat_korps ? activeSubmission.pangkat_korps + ' - ' : '' }}{{ activeSubmission.identifier_type.toUpperCase() }}. {{ activeSubmission.identifier_number }}
                            </p>
                        </div>

                        <!-- Progress Circle & Stage Info -->
                        <div class="flex items-center gap-4 self-start md:self-auto bg-slate-950/80 border border-slate-800 p-3.5 rounded-2xl">
                            <div class="text-right">
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Kemajuan Berkas</span>
                                <span class="text-lg font-mono font-black text-white">{{ activeSubmission.current_stage }}/10</span>
                            </div>
                            <div class="w-14 h-14 rounded-full border-4 border-slate-800 flex items-center justify-center font-mono font-black text-sm"
                                 :class="activeSubmission.current_stage === 10 ? 'border-emerald-500 text-emerald-400' : 'border-blue-500 text-blue-400'">
                                {{ activeSubmission.progress_percentage }}%
                            </div>
                        </div>
                    </div>

                    <!-- Metadata Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs pt-6">
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Satuan / Kesatuan:</span>
                            <span class="font-extrabold text-slate-200 mt-0.5 block">{{ activeSubmission.kesatuan || '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Keperluan SC:</span>
                            <span class="font-extrabold text-slate-200 mt-0.5 block">{{ activeSubmission.keperluan || 'Kedinasan' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Status SKHPP:</span>
                            <span class="font-bold text-slate-200 mt-0.5 block">
                                <template v-if="activeSubmission.skhpp_id">
                                    <span class="text-emerald-400 font-extrabold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        TTE Komandan
                                    </span>
                                </template>
                                <template v-else-if="activeSubmission.file_skhpp">
                                    <span class="text-blue-400 font-extrabold flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        TTD Basah (Unggahan)
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="text-slate-400">{{ activeSubmission.nomor_skhpp || 'Menunggu Pengesahan' }}</span>
                                </template>
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Nomor SC Resmi:</span>
                            <span class="font-mono font-extrabold text-emerald-400 mt-0.5 block text-sm">{{ activeSubmission.nomor_sc || 'Menunggu Terbit' }}</span>
                        </div>
                    </div>

                    <!-- KOTAK KHUSUS NOMOR SC RESMI KEDINASAN -->
                    <div v-if="activeSubmission.nomor_sc" class="mt-6 pt-6 border-t border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-emerald-950/30 p-4 rounded-2xl border border-emerald-500/30">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center font-black shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-mono font-bold text-emerald-400 uppercase tracking-widest block">NOMOR SURAT SECURITY CLEARANCE (SC) RESMI:</span>
                                <span class="text-base sm:text-xl font-mono font-black text-white tracking-wide">{{ activeSubmission.nomor_sc }}</span>
                            </div>
                        </div>
                        <div class="text-[11px] font-bold text-emerald-300 bg-emerald-900/50 px-3 py-1.5 rounded-xl border border-emerald-700/40 shrink-0">
                            Terdaftar Sah di Denintel Kodaeral V
                        </div>
                    </div>
                </div>

                <!-- BANNER KHUSUS TAHAP 10 (SIAP DIAMBIL DI SINTEL MAKO KODAERAL V) -->
                <div v-if="activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'" class="bg-gradient-to-r from-emerald-950 via-slate-900 to-teal-950 border-2 border-emerald-500/80 rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-2xl shrink-0 shadow-lg shadow-emerald-600/30">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="space-y-1.5">
                            <span class="px-2.5 py-0.5 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-full text-[10px] font-black uppercase tracking-wider">
                                TAHAP AKHIR TERPENUHI - SELESAI
                            </span>
                            <h4 class="text-lg sm:text-xl font-black text-white">
                                Dokumen SC Telah Terbit & Siap Diambil di Sintel Mako Kodaeral V
                            </h4>
                            <p class="text-xs text-slate-300 leading-relaxed font-medium max-w-2xl">
                                Surat Security Clearance (SC) atas nama personel bersangkutan telah resmi ditandatangani dan selesai diproses. Berkas fisik siap diambil oleh pemohon di <b class="text-white">Sintel Mako Kodaeral V</b> dengan menunjukkan identitas kedinasan.
                            </p>
                            <div class="pt-2">
                                <a 
                                    href="https://maps.app.goo.gl/STtPpz4G6G2DfnKC7" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-lg shadow-emerald-600/30 cursor-pointer"
                                >
                                    <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Buka Maps</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0 flex flex-col items-start md:items-end gap-2">
                        <div class="font-mono text-xs font-bold text-emerald-300 bg-emerald-950/80 px-4 py-2.5 rounded-2xl border border-emerald-600/40">
                            No. SC: {{ activeSubmission.nomor_sc || 'Tercatat di Mako' }}
                        </div>
                        <span class="text-[11px] font-bold text-emerald-400 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                            Status: Siap Diambil Pemohon
                        </span>
                    </div>
                </div>

                <!-- 10-STAGE TIMELINE TRACKER -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-lg space-y-6">
                    <div>
                        <span class="text-[10px] font-mono font-bold text-blue-400 uppercase tracking-widest block">ALUR 10 TAHAPAN KEDINASAN</span>
                        <h4 class="text-base sm:text-lg font-black text-white">
                            Linimasa Perkembangan Berkas Pengajuan
                        </h4>
                    </div>

                    <!-- Stage Stepper List -->
                    <div class="space-y-3">
                        <div 
                            v-for="stg in stages" 
                            :key="stg.id"
                            :class="[
                                (stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))) ? 'bg-emerald-950/20 border-emerald-800/40' : '',
                                (stg.id === activeSubmission.current_stage && activeSubmission.current_stage < 10 && activeSubmission.status !== 'selesai') ? 'bg-blue-950/40 border-blue-500 ring-2 ring-blue-500/30' : '',
                                (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai')) ? 'bg-emerald-950/30 border-emerald-500 ring-2 ring-emerald-500/40' : '',
                                (stg.id > activeSubmission.current_stage && activeSubmission.status !== 'selesai') ? 'bg-slate-950/40 border-slate-800/70 opacity-60' : '',
                            ]"
                            class="p-4 sm:p-5 rounded-2xl border transition-all duration-200 flex items-start gap-4"
                        >
                            <!-- Circle Status Icon -->
                            <div class="shrink-0 mt-0.5">
                                <div 
                                    v-if="stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))" 
                                    class="w-9 h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black text-sm shadow-md shadow-emerald-600/20"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div 
                                    v-else-if="stg.id === activeSubmission.current_stage && activeSubmission.current_stage < 10" 
                                    class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-mono font-black text-sm shadow-lg shadow-blue-600/30 animate-pulse"
                                >
                                    {{ stg.id }}
                                </div>
                                <div 
                                    v-else 
                                    class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 text-slate-400 flex items-center justify-center font-mono font-bold text-xs"
                                >
                                    {{ stg.id }}
                                </div>
                            </div>

                            <!-- Stage Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs sm:text-sm font-extrabold text-white">
                                            Tahap {{ stg.id }}: {{ stg.title }}
                                        </span>
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-md uppercase"
                                              :class="stg.category === 'Denintel' ? 'bg-amber-950/60 text-amber-300 border border-amber-800/40' : (stg.category === 'Sintel' ? 'bg-indigo-950/60 text-indigo-300 border border-indigo-800/40' : 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/40')">
                                            {{ stg.category }}
                                        </span>
                                    </div>
                                    
                                    <span v-if="stg.id < activeSubmission.current_stage || (stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai'))" class="text-[10px] font-extrabold text-emerald-400 uppercase tracking-wider flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                        <span>Selesai</span>
                                    </span>
                                    <span v-else-if="stg.id === activeSubmission.current_stage" class="text-[10px] font-extrabold text-blue-400 uppercase tracking-wider animate-pulse">
                                        Sedang Berjalan
                                    </span>
                                    <span v-else class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                        Menunggu
                                    </span>
                                </div>

                                <p class="text-xs text-slate-400 leading-relaxed font-medium">
                                    {{ stg.desc }}
                                </p>

                                <!-- KOTAK KHUSUS TAHAP 10: KOORDINAT & PENGAMBILAN DOKUMEN -->
                                <div v-if="stg.id === 10 && (activeSubmission.current_stage === 10 || activeSubmission.status === 'selesai')" class="mt-3.5 p-4 rounded-2xl bg-emerald-950/60 border border-emerald-600/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-lg shadow-emerald-950/40">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-emerald-600/30 border border-emerald-500/40 flex items-center justify-center text-emerald-300 shrink-0">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="text-xs font-black text-emerald-300 block">Siap Diambil oleh Pemohon di Sintel Mako Kodaeral V</span>
                                            <span class="text-[11px] text-slate-300 font-medium">Silakan membawa identitas resmi kedinasan saat mengambil fisik berkas di Sintel Mako Kodaeral V.</span>
                                        </div>
                                    </div>
                                    <a 
                                        href="https://maps.app.goo.gl/STtPpz4G6G2DfnKC7" 
                                        target="_blank" 
                                        rel="noopener noreferrer"
                                        class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-md shadow-emerald-600/30 cursor-pointer"
                                    >
                                        <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Buka Maps</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>

                                <!-- Keterangan Khusus Tahap Aktif -->
                                <div v-else-if="stg.id === activeSubmission.current_stage && activeSubmission.catatan_petugas" class="mt-2.5 p-3 rounded-xl bg-slate-950/70 border border-blue-900/50 text-xs">
                                    <span class="text-[10px] font-bold text-blue-400 uppercase block mb-0.5">Catatan Petugas:</span>
                                    <p class="text-slate-200 font-medium italic">"{{ activeSubmission.catatan_petugas }}"</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIWAYAT LOG PERJALANAN BERKAS (AUDIT LOGS) -->
                <div v-if="activeSubmission.logs && activeSubmission.logs.length > 0" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-lg space-y-4">
                    <h4 class="text-sm font-extrabold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                        Catatan Waktu & Riwayat Pembaruan
                    </h4>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-950/70 border-b border-slate-800 text-[10px] font-mono uppercase text-slate-400">
                                    <th class="p-3">Waktu</th>
                                    <th class="p-3">Tahapan</th>
                                    <th class="p-3">Keterangan / Catatan</th>
                                    <th class="p-3">Petugas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 font-medium text-slate-300">
                                <tr v-for="log in activeSubmission.logs" :key="log.id" class="hover:bg-slate-800/30 transition">
                                    <td class="p-3 font-mono text-[11px] text-slate-400 shrink-0">
                                        {{ formatDate(log.created_at) }}
                                    </td>
                                    <td class="p-3">
                                        <span class="font-extrabold text-white">Tahap {{ log.stage }}:</span> {{ log.stage_title }}
                                    </td>
                                    <td class="p-3 text-slate-300">
                                        {{ log.notes || '-' }}
                                    </td>
                                    <td class="p-3 text-[11px] text-slate-400">
                                        {{ log.user_name || 'Petugas Kedinasan' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>



        <!-- Footer -->
        <footer class="border-t border-slate-800/80 bg-slate-950 py-6 text-center text-xs text-slate-500 font-medium">
            <div class="max-w-6xl mx-auto px-4">
                © {{ new Date().getFullYear() }} {{ appName }} - Sistem Informasi Detasemen Intelijen (SINDEN). Seluruh Hak Cipta Dilindungi Kedinasan.
            </div>
        </footer>

    </div>
</template>

<style>
@media print {
  /* DOKUMEN 100% BLANK KOSONG SAAT PRINT */
  html, body {
    background: #ffffff !important;
    color: transparent !important;
    height: 100% !important;
    width: 100% !important;
    overflow: hidden !important;
  }
  
  body * {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
  }
}
</style>
