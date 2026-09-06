<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

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

// Modal Petinjau Hasil SC Berproteksi Tinggi & Token Rahasia
const isPreviewModalOpen = ref(false);
const isLoadingPreview = ref(false);
const previewPdfUrl = ref('');

const openPreviewModal = async () => {
    if (!activeSubmission.value?.is_sc_preview_available) return;

    isLoadingPreview.value = true;
    try {
        const response = await axios.post(route('tracking-sc.token'), {
            tracking_code: activeSubmission.value.tracking_code,
        });

        if (response.data && response.data.stream_url) {
            // Tautan rahasia dengan token heksadesimal 64 karakter (kedaluwarsa 15 menit)
            previewPdfUrl.value = response.data.stream_url + '#toolbar=0&navpanes=0&scrollbar=1&statusbar=0&messages=0&view=FitH';
            isPreviewModalOpen.value = true;
        } else {
            Swal.fire({
                title: 'Gagal',
                text: 'Gagal memuat sesi petinjau rahasia.',
                icon: 'error',
                confirmButtonColor: '#2563eb',
            });
        }
    } catch (err) {
        const msg = err.response?.data?.error || 'Gagal memuat berkas petinjau resmi. Masa aktif berkas mungkin telah berakhir.';
        Swal.fire({
            title: 'Informasi Dokumen',
            text: msg,
            icon: 'warning',
            confirmButtonColor: '#2563eb',
        });
    } finally {
        isLoadingPreview.value = false;
    }
};

const closePreviewModal = () => {
    isPreviewModalOpen.value = false;
    previewPdfUrl.value = ''; // Segera bersihkan URL rahasia dari memori browser
};

const warnAntiDownload = () => {
    Swal.fire({
        title: 'Peringatan Kedinasan',
        text: 'Fitur unduh dan simpan dinonaktifkan untuk dokumen petinjau resmi ber-watermark.',
        icon: 'warning',
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Dimengerti',
    });
};

// Pengamanan Anti-Download Ketat: blokir shortcut Ctrl+S, Ctrl+P, Ctrl+U, Ctrl+C, F12
const handleKeydown = (e) => {
    if (!isPreviewModalOpen.value) return;
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.key === 'p' || e.key === 'P' || e.key === 'u' || e.key === 'U' || e.key === 'c' || e.key === 'C')) {
        e.preventDefault();
        warnAntiDownload();
    }
    if (e.key === 'F12') {
        e.preventDefault();
    }
    if (e.key === 'PrintScreen') {
        Swal.fire({
            title: 'Peringatan Kedinasan',
            text: 'Fitur tangkapan layar dibatasi untuk berkas petinjau intelijen.',
            icon: 'warning',
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Dimengerti',
        });
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
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
                            <span class="font-mono font-extrabold text-emerald-400 mt-0.5 block">{{ activeSubmission.nomor_sc || 'Menunggu Terbit' }}</span>
                        </div>
                    </div>
                </div>

                <!-- BANNER PETINJAU HASIL SC (JIKA TERSEDIA DARI SINTEL - PROTEKSI 2X24 JAM & WATERMARK) -->
                <div v-if="activeSubmission.is_sc_preview_available" class="bg-gradient-to-r from-indigo-950 via-slate-900 to-blue-950 border-2 border-indigo-500/80 rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black text-2xl shrink-0 shadow-lg shadow-indigo-600/30">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 rounded-full text-[10px] font-black uppercase tracking-wider">
                                    PETINJAU DIGITAL RESMI SINTEL
                                </span>
                                <span class="px-2.5 py-0.5 bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-full text-[10px] font-mono font-bold">
                                    Sisa Masa Aktif: {{ activeSubmission.sc_preview_remaining_hours }} Jam
                                </span>
                            </div>
                            <h4 class="text-lg sm:text-xl font-black text-white">
                                Petinjau Hasil Security Clearance Tersedia
                            </h4>
                            <p class="text-xs text-slate-300 leading-relaxed font-medium max-w-2xl">
                                Petugas Staf Intelijen telah mengunggah softfile petinjau dokumen Security Clearance Anda. Dokumen ini dilindungi watermark kedinasan dan tidak dapat diunduh. Berkas digital ini otomatis terhapus dalam waktu 2x24 jam demi kerahasiaan dinas.
                            </p>
                        </div>
                    </div>

                    <button 
                        @click="openPreviewModal"
                        :disabled="isLoadingPreview"
                        class="shrink-0 px-6 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer disabled:opacity-50"
                    >
                        <svg v-if="isLoadingPreview" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>{{ isLoadingPreview ? 'Menyiapkan Petinjau...' : 'Lihat Petinjau Dokumen' }}</span>
                    </button>
                </div>

                <!-- NOTIFIKASI PETINJAU KADALUARSA JIKA SUDAH LEBIH 48 JAM -->
                <div v-else-if="activeSubmission.sc_preview_expired_at || (activeSubmission.current_stage >= 8 && !activeSubmission.is_sc_preview_available)" class="bg-slate-900 border border-slate-800 rounded-3xl p-5 sm:p-6 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 flex items-center justify-center font-black shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-xs space-y-1">
                        <span class="font-extrabold text-slate-300 block">Masa Berlaku Petinjau Softfile Telah Berakhir</span>
                        <p class="text-slate-500 font-medium leading-relaxed">
                            Sesuai protokol pengamanan informasi kedinasan, berkas softfile petinjau otomatis terhapus secara permanen setelah melampaui masa aktif 2x24 jam (48 jam). Naskah fisik sah dapat diambil di Mako Kodaeral V.
                        </p>
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

        <!-- MODAL PETINJAU HASIL SC DENGAN WATERMARK & ANTI-DOWNLOAD -->
        <Teleport to="body">
            <div 
                v-if="isPreviewModalOpen" 
                class="fixed inset-0 z-[200] bg-slate-950/90 backdrop-blur-md flex flex-col p-2 sm:p-6 overflow-hidden animate-in fade-in duration-200"
                @contextmenu.prevent
            >
                <div class="bg-slate-900 border border-slate-800 rounded-3xl flex-1 flex flex-col overflow-hidden shadow-2xl relative">
                    
                    <!-- Header Modal Viewer -->
                    <div class="px-5 py-4 border-b border-slate-800 bg-slate-950/90 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase text-indigo-400 tracking-wider">PETINJAU RESMI KEDINASAN</span>
                                    <span class="px-2 py-0.5 bg-rose-500/20 text-rose-300 border border-rose-500/30 rounded-full text-[9px] font-black uppercase">
                                        PROTEKSI ANTI-DOWNLOAD
                                    </span>
                                </div>
                                <h3 class="text-sm sm:text-base font-extrabold text-white">
                                    Petinjau Hasil SC: {{ activeSubmission?.nama }} ({{ activeSubmission?.tracking_code }})
                                </h3>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-[11px] font-mono font-bold text-amber-400 bg-amber-950/60 border border-amber-800/60 px-3 py-1.5 rounded-xl">
                                Sisa Waktu Petinjau: {{ activeSubmission?.sc_preview_remaining_hours }} Jam (2x24 Jam)
                            </span>
                            <button 
                                @click="closePreviewModal" 
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition cursor-pointer border border-slate-700 flex items-center gap-1.5"
                            >
                                <span>Tutup</span>
                                <span class="text-base leading-none">&times;</span>
                            </button>
                        </div>
                    </div>

                    <!-- Security Alert Strip -->
                    <div class="bg-amber-500/10 border-b border-amber-500/20 px-5 py-2 text-[11px] text-amber-300 font-medium flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <span>Dilarang menyebarluaskan, mencetak, atau mengambil salinan dokumen ini. Dokumen petinjau akan terhapus otomatis setelah 2x24 jam.</span>
                        </div>
                        <span class="font-mono text-[10px] text-amber-400/80 uppercase">STATUS: PETINJAU SEMENTARA</span>
                    </div>

                    <!-- PDF Viewer Container With Built-in Physical Watermark & Anti-Download Shield -->
                    <div class="flex-1 relative bg-slate-950 overflow-hidden" @contextmenu.prevent>
                        
                        <!-- Embedded PDF Viewer -->
                        <iframe 
                            v-if="previewPdfUrl"
                            :src="previewPdfUrl"
                            class="w-full h-full border-0 select-none bg-slate-900"
                            title="Petinjau Berkas SC Resmi"
                        ></iframe>

                        <!-- Transparent Shield across top bar of iframe to block browser PDF menu/download clicks -->
                        <div 
                            class="absolute top-0 inset-x-0 h-12 z-20 cursor-default bg-transparent"
                            @click.stop="warnAntiDownload"
                            @contextmenu.prevent
                        ></div>

                        <!-- Security Watermark Banner at Bottom -->
                        <div class="absolute bottom-4 inset-x-0 z-20 pointer-events-none flex justify-center px-4">
                            <div class="bg-red-950/90 border border-red-600/80 text-red-200 px-6 py-2 rounded-full text-[11px] font-black uppercase tracking-widest shadow-2xl backdrop-blur-md flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                <span>DOKUMEN PETINJAU RESMI KEDINASAN - DILARANG MENYALIN / MENGUNDUH - OTOMATIS TERHAPUS DALAM 2X24 JAM</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Footer -->
        <footer class="border-t border-slate-800/80 bg-slate-950 py-6 text-center text-xs text-slate-500 font-medium">
            <div class="max-w-6xl mx-auto px-4">
                © {{ new Date().getFullYear() }} {{ appName }} - Sistem Informasi Detasemen Intelijen (SINDEN). Seluruh Hak Cipta Dilindungi Kedinasan.
            </div>
        </footer>

    </div>
</template>
