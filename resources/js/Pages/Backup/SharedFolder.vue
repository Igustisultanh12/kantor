<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    needsPin: {
        type: Boolean,
        default: false,
    },
    isDeactivated: {
        type: Boolean,
        default: false,
    },
    isNotFound: {
        type: Boolean,
        default: false,
    },
    shareToken: String,
    shareName: String,
    folderName: String,
    pcName: String,
    currentUser: {
        type: Object,
        default: null,
    },
    contents: {
        type: Array,
        default: () => [],
    },
    currentFolderId: [Number, String, null],
    shareRootFolderId: [Number, String, null],
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    searchQuery: {
        type: String,
        default: '',
    },
});

// --- STATE VERIFIKASI PIN ---
const pinInput = ref('');
const showPin = ref(false);
const isVerifying = ref(false);
const errorMessage = ref('');

const submitPin = async () => {
    if (!pinInput.value.trim()) {
        errorMessage.value = 'Silakan masukkan PIN keamanan.';
        return;
    }

    errorMessage.value = '';
    isVerifying.value = true;

    try {
        const response = await axios.post(route('backup.shared.verify-pin', props.shareToken), {
            pin: pinInput.value.trim(),
        });

        if (response.data.status === 'success') {
            window.location.reload();
        }
    } catch (err) {
        if (err.response && err.response.data && err.response.data.message) {
            errorMessage.value = err.response.data.message;
        } else {
            errorMessage.value = 'Terjadi kesalahan verifikasi. Silakan coba kembali.';
        }
    } finally {
        isVerifying.value = false;
    }
};

// --- STATE PENCARIAN & NAVIGASI BERKAS ---
const localSearch = ref(props.searchQuery || '');
const previewUrl = ref(null);
const previewTitle = ref('');
const previewType = ref('');
const activePreviewItem = ref(null);

const filteredContents = computed(() => {
    if (!localSearch.value.trim()) return props.contents;
    const query = localSearch.value.toLowerCase().trim();
    return props.contents.filter(item => item.file_name.toLowerCase().includes(query));
});

const openFolder = (folderItem) => {
    router.get(route('backup.shared.view', props.shareToken), {
        folder: folderItem.id,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const goToBreadcrumb = (crumbId) => {
    router.get(route('backup.shared.view', props.shareToken), {
        folder: crumbId === props.shareRootFolderId ? null : crumbId,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const thumbnailErrors = ref({});
const viewMode = ref(typeof window !== 'undefined' ? (localStorage.getItem('sinden_shared_view_mode') || 'large') : 'large');

const setViewMode = (mode) => {
    viewMode.value = mode;
    if (typeof window !== 'undefined') {
        localStorage.setItem('sinden_shared_view_mode', mode);
    }
};

const isArw = (filename) => {
    return filename?.toLowerCase().endsWith('.arw');
};

const isImage = (filename) => {
    const ext = filename?.split('.').pop()?.toLowerCase();
    return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'bmp'].includes(ext);
};

const isPdf = (filename) => {
    return filename?.toLowerCase().endsWith('.pdf');
};

const isExcel = (filename) => {
    const ext = filename?.split('.').pop()?.toLowerCase();
    return ['xlsx', 'xls', 'csv'].includes(ext);
};

// --- STATE ZOOM, ROTATE, PAN & SECURE BLOB PREVIEW ---
const zoomLevel = ref(1);
const rotationDegree = ref(0);
const panPosition = ref({ x: 0, y: 0 });
const isPanning = ref(false);
const panStart = ref({ x: 0, y: 0 });
const isImageLoading = ref(false);

const resetZoomAndRotate = () => {
    zoomLevel.value = 1;
    rotationDegree.value = 0;
    panPosition.value = { x: 0, y: 0 };
    isPanning.value = false;
};

const zoomIn = () => {
    zoomLevel.value = Math.min(5, +(zoomLevel.value + 0.25).toFixed(2));
};

const zoomOut = () => {
    zoomLevel.value = Math.max(0.25, +(zoomLevel.value - 0.25).toFixed(2));
    if (zoomLevel.value <= 1) {
        panPosition.value = { x: 0, y: 0 };
    }
};

const rotateRight = () => {
    rotationDegree.value = (rotationDegree.value + 90) % 360;
};

const rotateLeft = () => {
    rotationDegree.value = (rotationDegree.value - 90 + 360) % 360;
};

const handleWheelZoom = (e) => {
    if (previewType.value !== 'image' && previewType.value !== 'arw') return;
    if (e.deltaY < 0) {
        zoomIn();
    } else {
        zoomOut();
    }
};

const startPan = (e) => {
    if (zoomLevel.value <= 1) return;
    isPanning.value = true;
    panStart.value = {
        x: e.clientX - panPosition.value.x,
        y: e.clientY - panPosition.value.y
    };
};

const onPan = (e) => {
    if (!isPanning.value) return;
    panPosition.value = {
        x: e.clientX - panStart.value.x,
        y: e.clientY - panStart.value.y
    };
};

const endPan = () => {
    isPanning.value = false;
};

const fetchSecureBlob = async (url) => {
    if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = null;
    isImageLoading.value = true;

    try {
        const response = await axios.get(url, {
            responseType: 'blob',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const blobUrl = URL.createObjectURL(response.data);
        previewUrl.value = blobUrl;
    } catch (err) {
        Swal.fire({
            title: 'Gagal Memuat Pratinjau',
            text: err.response?.data?.message || 'Tidak dapat memuat berkas gambar secara aman.',
            icon: 'error'
        });
        closePreview();
    } finally {
        isImageLoading.value = false;
    }
};

const openPreview = async (item) => {
    activePreviewItem.value = item;
    previewTitle.value = item.file_name;
    resetZoomAndRotate();

    if (isImage(item.file_name)) {
        previewType.value = 'image';
        await fetchSecureBlob(item.preview_url);
    } else if (isArw(item.file_name)) {
        previewType.value = 'arw';
        await fetchSecureBlob(item.preview_url);
    } else if (isPdf(item.file_name)) {
        previewType.value = 'pdf';
        previewUrl.value = item.preview_url;
    } else {
        previewType.value = 'other';
        previewUrl.value = item.preview_url;
    }
};

const closePreview = () => {
    if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = null;
    previewTitle.value = '';
    previewType.value = '';
    activePreviewItem.value = null;
    resetZoomAndRotate();
};

const exitAndLock = () => {
    Swal.fire({
        title: 'Kunci Akses Folder?',
        text: 'Sesi akses Anda akan ditutup. PIN keamanan akan diminta kembali.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Kunci Sesi',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('backup.shared.exit', props.shareToken));
        }
    });
};
</script>

<template>
    <Head :title="shareName ? `${shareName} - Berkas Berbagi` : 'Akses Folder Berbagi'" />

    <div class="min-h-screen bg-slate-100 text-slate-800 flex flex-col font-sans selection:bg-indigo-500 selection:text-white">
        <!-- TOP NAVIGATION BAR (RESPONSIF MOBILE & DESKTOP) -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-xs">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-slate-900 flex items-center justify-center text-white shadow-xs shrink-0">
                        <!-- SVG Folder with Lock -->
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h1 class="text-xs sm:text-base font-black text-slate-900 tracking-tight uppercase truncate">
                                {{ shareName || 'Pusat Berkas Berbagi' }}
                            </h1>
                            <!-- Ikon Gembok Minimalis -->
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <p class="text-[9px] sm:text-[10px] text-slate-400 font-bold uppercase tracking-wider truncate">
                            Akses Personel &bull; Lihat & Unduh
                        </p>
                    </div>
                </div>

                <div v-if="!needsPin && !isNotFound && !isDeactivated" class="flex items-center gap-2 shrink-0">
                    <button 
                        @click="exitAndLock" 
                        class="px-2.5 sm:px-3.5 py-1.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300 text-[11px] sm:text-xs font-bold uppercase transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        title="Kunci kembali folder">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span class="hidden sm:inline">Kunci Sesi</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- MAIN BODY CONTENT -->
        <main class="flex-1 flex flex-col items-center justify-center p-3 sm:p-6 lg:p-8">

            <!-- STATE 1: TAUTAN TIDAK DITEMUKAN (404) -->
            <div v-if="isNotFound" class="max-w-md w-full bg-white rounded-3xl shadow-xl p-6 sm:p-8 border border-slate-200 text-center space-y-5 animate-fade-in mx-auto">
                <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-full bg-rose-50 border-2 border-rose-200 flex items-center justify-center text-rose-600 shadow-inner">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <div class="space-y-2">
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight">Tautan Tidak Ditemukan</h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Tautan yang Anda akses tidak valid, sudah kadaluarsa, atau telah dinonaktifkan oleh pengelola.
                    </p>
                </div>
                <div class="pt-4 border-t border-slate-100">
                    <Link :href="route('dashboard')" class="inline-flex items-center justify-center w-full px-5 py-3 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold uppercase tracking-wider transition shadow-md">
                        Kembali ke Dashboard
                    </Link>
                </div>
            </div>

            <!-- STATE 2: TAUTAN DINONAKTIFKAN OLEH ADMIN -->
            <div v-else-if="isDeactivated" class="max-w-md w-full bg-white rounded-3xl shadow-xl p-6 sm:p-8 border border-amber-200 text-center space-y-5 animate-fade-in mx-auto">
                <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-full bg-amber-50 border-2 border-amber-200 flex items-center justify-center text-amber-600 shadow-inner">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div class="space-y-2">
                    <div class="inline-block px-3 py-1 bg-amber-100 text-amber-800 text-[10px] font-black uppercase tracking-widest rounded-full mb-1">
                        Akses Dinonaktifkan
                    </div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 uppercase tracking-tight">{{ shareName }}</h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Akses tautan berbagi untuk folder ini sedang ditutup oleh Administrator.
                    </p>
                </div>
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-[11px] text-slate-600 text-left space-y-1">
                    <p class="font-bold">Informasi:</p>
                    <p>Silakan hubungi Administrator untuk mengaktifkan kembali tautan akses folder ini.</p>
                </div>
            </div>

            <!-- STATE 3: FORMULIR INPUT PIN KEAMANAN (DENGAN IDENTITAS AKUN LOGIN) -->
            <div v-else-if="needsPin" class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-200 animate-fade-in mx-auto">
                <!-- Header Banner -->
                <div class="p-5 sm:p-6 bg-slate-900 text-white text-center space-y-2.5">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center text-white shadow-md">
                        <!-- Clean Lock SVG -->
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-black tracking-tight uppercase">Proteksi PIN Keamanan</h2>
                        <p class="text-[11px] text-slate-300 font-medium">
                            Verifikasi otorisasi untuk membuka berkas folder berbagi.
                        </p>
                    </div>
                </div>

                <!-- PIN Body -->
                <div class="p-5 sm:p-8 space-y-5">
                    
                    <!-- IDENTITAS PERSONEL LOGIN -->
                    <div v-if="currentUser" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-slate-800 text-white flex items-center justify-center font-black text-xs shrink-0">
                                {{ currentUser.name ? currentUser.name.charAt(0).toUpperCase() : 'P' }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-[9px] font-bold uppercase text-slate-400 tracking-wider">Akun Personel Aktif</p>
                                <p class="text-xs font-black text-slate-800 truncate uppercase">{{ currentUser.name }}</p>
                                <p class="text-[10px] text-slate-500 font-semibold">{{ currentUser.pangkat || 'Personel' }} &bull; {{ currentUser.nrp ? `NRP: ${currentUser.nrp}` : 'Terdaftar' }}</p>
                            </div>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0" title="Akun Terhubung"></span>
                    </div>

                    <!-- Target Folder Info -->
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[9px] font-bold uppercase text-slate-400 tracking-wider">Folder Target</p>
                            <p class="text-xs font-black text-slate-800 truncate uppercase">{{ shareName || folderName }}</p>
                        </div>
                    </div>

                    <!-- PIN Form -->
                    <form @submit.prevent="submitPin" class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-700 flex items-center justify-between">
                                <span>Ketik PIN Akses</span>
                                <button 
                                    type="button" 
                                    @click="showPin = !showPin" 
                                    class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 lowercase">
                                    {{ showPin ? 'sembunyikan' : 'lihat pin' }}
                                </button>
                            </label>
                            
                            <div class="relative">
                                <input 
                                    v-model="pinInput" 
                                    :type="showPin ? 'text' : 'password'" 
                                    maxlength="12" 
                                    inputmode="numeric"
                                    autofocus
                                    placeholder="PIN..."
                                    autocomplete="off"
                                    class="w-full text-center text-xl sm:text-2xl tracking-[0.3em] font-black py-3 px-4 bg-slate-50 border-2 border-slate-300 focus:border-indigo-600 focus:bg-white rounded-2xl shadow-inner transition outline-none"
                                />
                            </div>

                            <p v-if="errorMessage" class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 p-2.5 rounded-xl flex items-center gap-1.5 animate-shake">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ errorMessage }}</span>
                            </p>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="isVerifying || !pinInput.trim()"
                            class="w-full py-3 px-5 bg-slate-900 hover:bg-slate-800 text-white rounded-2xl font-bold text-xs uppercase tracking-wider shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <span v-if="isVerifying" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            <span>{{ isVerifying ? 'Memverifikasi...' : 'Buka Folder' }}</span>
                        </button>
                    </form>

                    <div class="text-center pt-1">
                        <p class="text-[10px] text-slate-400">
                            PIN diberikan oleh pemilik pangkalan atau pengelola. Akses dibatasi hanya untuk melihat dan mengunduh berkas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- STATE 4: DAFTAR BERKAS TERVERIFIKASI (MOBILE & DESKTOP OPTIMIZED) -->
            <div v-else class="w-full max-w-7xl flex-1 flex flex-col space-y-3 sm:space-y-4">
                
                <!-- TOP ACTION & SEARCH BAR (RESPONSIF HP & DESKTOP) -->
                <div class="bg-white p-3 sm:p-4 rounded-2xl shadow-xs border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    
                    <!-- Search Input -->
                    <div class="relative w-full sm:w-80">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="localSearch" 
                            type="text" 
                            placeholder="Cari berkas dalam folder..." 
                            class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:border-slate-800 focus:outline-none transition"
                        />
                    </div>

                    <!-- Actions: View Modes & Download Zip Folder -->
                    <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto">
                        <!-- View Mode Switcher -->
                        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
                            <button 
                                @click="setViewMode('large')" 
                                :class="viewMode === 'large' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800 font-medium'"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition cursor-pointer"
                                title="Tampilan Ikon Besar (Pratinjau Foto)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                <span>Besar</span>
                            </button>
                            <button 
                                @click="setViewMode('medium')" 
                                :class="viewMode === 'medium' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800 font-medium'"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition cursor-pointer"
                                title="Tampilan Ikon Sedang">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                </svg>
                                <span>Sedang</span>
                            </button>
                            <button 
                                @click="setViewMode('list')" 
                                :class="viewMode === 'list' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-800 font-medium'"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition cursor-pointer"
                                title="Tampilan Daftar">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                                <span>Daftar</span>
                            </button>
                        </div>

                        <!-- Action: Download Zip Folder -->
                        <a 
                            :href="route('backup.shared.download-zip', { token: shareToken, folder_id: currentFolderId })" 
                            class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold uppercase flex items-center justify-center gap-2 transition shadow-xs cursor-pointer shrink-0"
                            title="Unduh seluruh berkas di folder ini sebagai arsip .ZIP">
                            <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span class="hidden sm:inline">Unduh ZIP</span>
                            <span class="sm:hidden">ZIP</span>
                        </a>
                    </div>
                </div>

                <!-- BREADCRUMBS HORIZONTAL SWIPEABLE DI HP -->
                <nav v-if="breadcrumbs && breadcrumbs.length > 0" class="flex items-center px-3 sm:px-4 py-2 bg-white rounded-xl border border-slate-200 text-xs font-bold uppercase tracking-tight text-slate-500 overflow-x-auto scrollbar-none">
                    <div class="flex items-center gap-1.5 whitespace-nowrap min-w-max">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <template v-for="(crumb, idx) in breadcrumbs" :key="crumb.id">
                            <span v-if="idx > 0" class="text-slate-300">/</span>
                            <button 
                                v-if="idx < breadcrumbs.length - 1"
                                @click="goToBreadcrumb(crumb.id)" 
                                class="text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer">
                                {{ crumb.name }}
                            </button>
                            <span v-else class="text-slate-800 font-black">
                                {{ crumb.name }}
                            </span>
                        </template>
                    </div>
                </nav>

                <!-- FILE LIST CONTAINER -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden flex-1 flex flex-col min-h-[380px]">
                    
                    <!-- EMPTY STATE (JIKA TIDAK ADA BERKAS / PENCARIAN NIHIL) -->
                    <div v-if="filteredContents.length === 0" class="p-16 text-center text-slate-400 flex-1 flex flex-col items-center justify-center">
                        <svg class="w-12 h-12 mx-auto opacity-30 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-600">
                            {{ localSearch ? 'Tidak ada berkas yang sesuai dengan pencarian' : 'Folder ini masih kosong' }}
                        </p>
                    </div>

                    <!-- 1. TAMPILAN IKON BESAR DENGAN PRATINJAU FOTO REAL (LARGE VIEW) -->
                    <div v-else-if="viewMode === 'large'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 p-3.5 sm:p-5 flex-1">
                        <div 
                            v-for="item in filteredContents" 
                            :key="'large-' + item.id"
                            @click="item.is_folder ? openFolder(item) : null"
                            @dblclick="item.is_folder ? openFolder(item) : openPreview(item)"
                            class="group bg-white hover:bg-slate-50/50 rounded-2xl border border-slate-200/90 hover:border-indigo-400 hover:shadow-md transition-all duration-200 flex flex-col p-2.5 sm:p-3 relative cursor-pointer select-none">
                            
                            <!-- Thumbnail / Ikon -->
                            <div class="w-full">
                                <!-- Folder -->
                                <div v-if="item.is_folder" class="w-full aspect-4/3 sm:aspect-square bg-indigo-50/80 rounded-xl flex flex-col items-center justify-center border border-indigo-100/80 group-hover:bg-indigo-100/70 transition relative">
                                    <svg class="w-12 h-12 sm:w-14 sm:h-14 text-indigo-600 drop-shadow-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    <span class="text-[9px] sm:text-[10px] font-black text-indigo-700 uppercase mt-1">Folder</span>
                                </div>

                                <!-- Gambar / Foto Sony RAW (Real Thumbnail Preview) -->
                                <div 
                                    v-else-if="(isImage(item.file_name) || isArw(item.file_name)) && !thumbnailErrors[item.id]"
                                    @click="openPreview(item)"
                                    class="relative w-full aspect-4/3 sm:aspect-square bg-slate-900/5 rounded-xl overflow-hidden flex items-center justify-center border border-slate-200/80 group-hover:border-indigo-400 transition shadow-2xs">
                                    <img 
                                        :src="item.preview_url" 
                                        :alt="item.file_name"
                                        loading="lazy"
                                        @error="thumbnailErrors[item.id] = true"
                                        @contextmenu.prevent=""
                                        draggable="false"
                                        class="w-full h-full object-cover select-none pointer-events-none transition-transform duration-300 group-hover:scale-105"
                                    />
                                    <!-- Badge RAW jika ARW -->
                                    <span v-if="isArw(item.file_name)" class="absolute top-1.5 left-1.5 px-1.5 py-0.5 text-[8px] sm:text-[9px] bg-amber-500 text-white rounded font-black uppercase shadow-xs">
                                        RAW
                                    </span>
                                    <!-- Ikon Gembok Minimalis -->
                                    <span class="absolute top-1.5 right-1.5 p-1 bg-black/40 backdrop-blur-xs rounded-full text-white/90">
                                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </div>

                                <!-- PDF Document -->
                                <div v-else-if="isPdf(item.file_name)" class="w-full aspect-4/3 sm:aspect-square bg-rose-50/80 rounded-xl flex flex-col items-center justify-center border border-rose-100/80 group-hover:bg-rose-100/70 transition relative">
                                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-rose-500 drop-shadow-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 3v5a1 1 0 001 1h5" />
                                    </svg>
                                    <span class="text-[9px] sm:text-[10px] font-black text-rose-600 uppercase mt-1">PDF</span>
                                </div>

                                <!-- Excel Document -->
                                <div v-else-if="isExcel(item.file_name)" class="w-full aspect-4/3 sm:aspect-square bg-emerald-50/80 rounded-xl flex flex-col items-center justify-center border border-emerald-100/80 group-hover:bg-emerald-100/70 transition relative">
                                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-emerald-600 drop-shadow-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-[9px] sm:text-[10px] font-black text-emerald-700 uppercase mt-1">EXCEL</span>
                                </div>

                                <!-- Berkas Lainnya / Fallback -->
                                <div v-else class="w-full aspect-4/3 sm:aspect-square bg-slate-100 rounded-xl flex flex-col items-center justify-center border border-slate-200/80 group-hover:bg-slate-200/60 transition relative">
                                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-slate-500 drop-shadow-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-[9px] sm:text-[10px] font-black text-slate-500 uppercase mt-1">
                                        {{ item.file_name.split('.').pop()?.substring(0, 4) || 'FILE' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Meta Info -->
                            <div class="mt-2 min-w-0 flex-1 flex flex-col justify-between">
                                <div>
                                    <p class="font-bold text-xs text-slate-800 group-hover:text-indigo-600 transition truncate uppercase" :title="item.file_name">
                                        {{ item.file_name }}
                                    </p>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-semibold mt-1">
                                        <span>{{ item.size_human }}</span>
                                        <span class="truncate max-w-[85px]">{{ item.date_human }}</span>
                                    </div>
                                </div>

                                <!-- Tombol Aksi Cepat -->
                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between gap-1" @click.stop>
                                    <template v-if="item.is_folder">
                                        <button 
                                            @click="openFolder(item)" 
                                            class="w-full py-1.5 bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 cursor-pointer">
                                            <span>Buka</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button 
                                            @click="openPreview(item)" 
                                            class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition cursor-pointer flex-1 flex items-center justify-center gap-1 text-[11px] font-bold"
                                            title="Lihat Pratinjau">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span class="text-[10px]">Lihat</span>
                                        </button>
                                        <a 
                                            v-if="isArw(item.file_name) && item.download_jpg_url" 
                                            :href="item.download_jpg_url" 
                                            class="px-2 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition cursor-pointer flex items-center justify-center text-[10px] font-bold uppercase gap-0.5 shrink-0"
                                            title="Unduh format JPG HD">
                                            <span>JPG</span>
                                        </a>
                                        <a 
                                            v-if="item.download_url" 
                                            :href="item.download_url" 
                                            class="p-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg transition cursor-pointer flex items-center justify-center shrink-0"
                                            :title="isArw(item.file_name) ? 'Unduh Berkas Sony RAW (.ARW)' : 'Unduh Berkas'">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                    </template>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- 2. TAMPILAN IKON SEDANG (MEDIUM VIEW) -->
                    <div v-else-if="viewMode === 'medium'" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 gap-2.5 sm:gap-3 p-3 sm:p-4 flex-1">
                        <div 
                            v-for="item in filteredContents" 
                            :key="'medium-' + item.id"
                            @click="item.is_folder ? openFolder(item) : null"
                            @dblclick="item.is_folder ? openFolder(item) : openPreview(item)"
                            class="group bg-white hover:bg-slate-50/50 rounded-xl border border-slate-200/90 hover:border-indigo-400 hover:shadow-xs transition flex flex-col p-2 relative cursor-pointer select-none">
                            
                            <!-- Thumbnail / Ikon Sedang -->
                            <div class="w-full">
                                <div v-if="item.is_folder" class="w-full aspect-square bg-indigo-50 rounded-lg flex items-center justify-center border border-indigo-100 group-hover:bg-indigo-100/70 transition">
                                    <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                </div>

                                <div 
                                    v-else-if="(isImage(item.file_name) || isArw(item.file_name)) && !thumbnailErrors[item.id]"
                                    @click="openPreview(item)"
                                    class="relative w-full aspect-square bg-slate-900/5 rounded-lg overflow-hidden flex items-center justify-center border border-slate-200/80 group-hover:border-indigo-400 transition">
                                    <img 
                                        :src="item.preview_url" 
                                        :alt="item.file_name"
                                        loading="lazy"
                                        @error="thumbnailErrors[item.id] = true"
                                        @contextmenu.prevent=""
                                        draggable="false"
                                        class="w-full h-full object-cover select-none pointer-events-none transition-transform duration-300 group-hover:scale-105"
                                    />
                                    <span v-if="isArw(item.file_name)" class="absolute top-1 left-1 px-1 py-0.2 text-[8px] bg-amber-500 text-white rounded font-black uppercase shadow-xs">
                                        RAW
                                    </span>
                                </div>

                                <div v-else-if="isPdf(item.file_name)" class="w-full aspect-square bg-rose-50 rounded-lg flex items-center justify-center border border-rose-100">
                                    <svg class="w-8 h-8 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 3v5a1 1 0 001 1h5" />
                                    </svg>
                                </div>

                                <div v-else-if="isExcel(item.file_name)" class="w-full aspect-square bg-emerald-50 rounded-lg flex items-center justify-center border border-emerald-100">
                                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>

                                <div v-else class="w-full aspect-square bg-slate-100 rounded-lg flex items-center justify-center border border-slate-200">
                                    <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Info Sedang -->
                            <div class="mt-1.5 min-w-0">
                                <p class="font-bold text-[11px] text-slate-800 group-hover:text-indigo-600 transition truncate uppercase" :title="item.file_name">
                                    {{ item.file_name }}
                                </p>
                                <p class="text-[9px] text-slate-400 font-semibold">
                                    {{ item.size_human }}
                                </p>
                            </div>

                            <!-- Mini Actions -->
                            <div class="mt-1.5 pt-1.5 border-t border-slate-100 flex items-center justify-between gap-1" @click.stop>
                                <template v-if="item.is_folder">
                                    <button 
                                        @click="openFolder(item)" 
                                        class="w-full py-1 bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 rounded text-[10px] font-bold transition flex items-center justify-center gap-0.5 cursor-pointer">
                                        <span>Buka</span>
                                    </button>
                                </template>
                                <template v-else>
                                    <button 
                                        @click="openPreview(item)" 
                                        class="p-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded transition cursor-pointer flex-1 flex items-center justify-center"
                                        title="Pratinjau">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <a 
                                        v-if="isArw(item.file_name) && item.download_jpg_url" 
                                        :href="item.download_jpg_url" 
                                        class="px-1.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded transition cursor-pointer text-[9px] font-black uppercase"
                                        title="Unduh JPG">
                                        JPG
                                    </a>
                                    <a 
                                        v-if="item.download_url" 
                                        :href="item.download_url" 
                                        class="p-1 bg-slate-900 hover:bg-slate-800 text-white rounded transition cursor-pointer"
                                        :title="isArw(item.file_name) ? 'Unduh RAW' : 'Unduh'">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                </template>
                            </div>

                        </div>
                    </div>

                    <!-- 3. TAMPILAN DAFTAR / TABLE (LIST VIEW) -->
                    <div v-else-if="viewMode === 'list'" class="flex-1 flex flex-col">
                        
                        <!-- 3A. LIST TAMPILAN KHUSUS HP (< sm) -->
                        <div class="block sm:hidden divide-y divide-slate-100 flex-1">
                            <div 
                                v-for="item in filteredContents" 
                                :key="'list-m-' + item.id"
                                @click="item.is_folder ? openFolder(item) : openPreview(item)"
                                class="p-3 hover:bg-slate-50 active:bg-slate-100 transition flex items-center justify-between gap-3 cursor-pointer">
                                
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <!-- Mini Thumbnail atau SVG Icon -->
                                    <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 flex items-center justify-center border border-slate-200/80 bg-slate-100">
                                        <img 
                                            v-if="(isImage(item.file_name) || isArw(item.file_name)) && !thumbnailErrors[item.id]"
                                            :src="item.preview_url" 
                                            :alt="item.file_name"
                                            loading="lazy"
                                            @error="thumbnailErrors[item.id] = true"
                                            @contextmenu.prevent=""
                                            draggable="false"
                                            class="w-full h-full object-cover select-none pointer-events-none"
                                        />
                                        <svg v-else-if="item.is_folder" class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                        <svg v-else-if="isPdf(item.file_name)" class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 3v5a1 1 0 001 1h5" />
                                        </svg>
                                        <svg v-else-if="isExcel(item.file_name)" class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <svg v-else class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <p class="font-bold text-xs text-slate-900 truncate uppercase">
                                                {{ item.file_name }}
                                            </p>
                                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                            </svg>
                                            <span v-if="isArw(item.file_name)" class="px-1.5 py-0.2 text-[8px] bg-amber-100 text-amber-800 rounded font-bold uppercase">
                                                RAW
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] text-slate-400 font-semibold mt-0.5">
                                            <span>{{ item.size_human }}</span>
                                            <span>&bull;</span>
                                            <span>{{ item.date_human }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Opsi Tindakan Cepat di HP -->
                                <div class="flex items-center gap-1.5 shrink-0" @click.stop>
                                    <button 
                                        v-if="item.is_folder" 
                                        @click="openFolder(item)" 
                                        class="p-2 bg-indigo-50 text-indigo-700 rounded-xl">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                    <template v-else>
                                        <button 
                                            @click="openPreview(item)" 
                                            class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl"
                                            title="Pratinjau">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <a 
                                            v-if="isArw(item.file_name) && item.download_jpg_url" 
                                            :href="item.download_jpg_url" 
                                            class="px-2 py-1.5 bg-amber-500 text-white rounded-xl text-[10px] font-bold"
                                            title="Unduh JPG HD">
                                            JPG
                                        </a>
                                        <a 
                                            v-if="item.download_url" 
                                            :href="item.download_url" 
                                            class="p-2 bg-slate-900 text-white rounded-xl"
                                            title="Unduh Berkas">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                    </template>
                                </div>

                            </div>
                        </div>

                        <!-- 3B. TAMPILAN TABEL DESKTOP & TABLET (>= sm) -->
                        <div class="hidden sm:block overflow-x-auto flex-1">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="p-4">Nama Berkas</th>
                                        <th class="p-4 text-center">Ukuran</th>
                                        <th class="p-4">Tanggal Modifikasi</th>
                                        <th class="p-4 text-right">Opsi Akses</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs font-medium">
                                    <tr 
                                        v-for="item in filteredContents" 
                                        :key="'table-' + item.id"
                                        @dblclick="item.is_folder ? openFolder(item) : openPreview(item)"
                                        class="hover:bg-slate-50 transition cursor-pointer select-none group">
                                        
                                        <!-- Nama Item & Thumbnail -->
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <!-- Thumbnail / SVG Icon -->
                                                <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0 flex items-center justify-center border border-slate-200/80 bg-slate-100">
                                                    <img 
                                                        v-if="(isImage(item.file_name) || isArw(item.file_name)) && !thumbnailErrors[item.id]"
                                                        :src="item.preview_url" 
                                                        :alt="item.file_name"
                                                        loading="lazy"
                                                        @error="thumbnailErrors[item.id] = true"
                                                        @contextmenu.prevent=""
                                                        draggable="false"
                                                        class="w-full h-full object-cover select-none pointer-events-none"
                                                    />
                                                    <div v-else-if="item.is_folder" class="w-full h-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                                        </svg>
                                                    </div>
                                                    <div v-else-if="isPdf(item.file_name)" class="w-full h-full bg-rose-50 flex items-center justify-center text-rose-500">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 3v5a1 1 0 001 1h5" />
                                                        </svg>
                                                    </div>
                                                    <div v-else-if="isExcel(item.file_name)" class="w-full h-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    </div>
                                                    <div v-else class="w-full h-full bg-slate-100 flex items-center justify-center text-slate-500">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    </div>
                                                </div>

                                                <div>
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <p class="font-bold text-slate-800 uppercase tracking-tight group-hover:text-indigo-700 transition">
                                                            {{ item.file_name }}
                                                        </p>
                                                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span v-if="isArw(item.file_name)" class="px-2 py-0.5 text-[9px] bg-amber-100 text-amber-800 rounded font-bold uppercase border border-amber-300">
                                                            Sony RAW
                                                        </span>
                                                    </div>
                                                    <p class="text-[10px] text-slate-400 font-bold uppercase">
                                                        {{ item.is_folder ? 'Folder' : (isArw(item.file_name) ? 'Foto Sony RAW' : (item.file_type || 'Berkas')) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Ukuran -->
                                        <td class="p-4 text-center font-mono font-bold text-slate-500 text-xs">
                                            {{ item.size_human }}
                                        </td>

                                        <!-- Tanggal Modifikasi -->
                                        <td class="p-4 text-slate-500 font-semibold text-xs">
                                            {{ item.date_human }}
                                        </td>

                                        <!-- Opsi Akses -->
                                        <td class="p-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- Buka Folder -->
                                                <button 
                                                    v-if="item.is_folder" 
                                                    @click.stop="openFolder(item)" 
                                                    class="px-3 py-1.5 bg-slate-100 hover:bg-slate-900 hover:text-white text-slate-700 rounded-xl text-xs font-bold uppercase transition cursor-pointer flex items-center gap-1">
                                                    <span>Buka</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>

                                                <!-- Pratinjau Dokumen / Gambar -->
                                                <button 
                                                    v-if="!item.is_folder" 
                                                    @click.stop="openPreview(item)" 
                                                    class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition cursor-pointer"
                                                    :title="isArw(item.file_name) ? 'Lihat Pratinjau Foto Sony RAW' : 'Lihat Pratinjau Berkas'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>

                                                <!-- Unduh Sebagai JPG HD Khusus Berkas Sony RAW -->
                                                <a 
                                                    v-if="!item.is_folder && isArw(item.file_name) && item.download_jpg_url" 
                                                    :href="item.download_jpg_url" 
                                                    class="px-2.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl transition cursor-pointer inline-flex items-center justify-center font-bold text-[10px] uppercase gap-1"
                                                    title="Unduh Berkas Sony RAW Ini Sebagai Format JPG HD">
                                                    <span>JPG</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </a>

                                                <!-- Unduh Berkas Asli -->
                                                <a 
                                                    v-if="!item.is_folder && item.download_url" 
                                                    :href="item.download_url" 
                                                    class="p-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl transition cursor-pointer inline-flex items-center justify-center"
                                                    :title="isArw(item.file_name) ? 'Unduh Berkas Asli (.ARW)' : 'Unduh Berkas Ini'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>


                    <!-- Footer Info Bar -->
                    <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-[11px] font-bold text-slate-500 gap-2">
                        <div>
                            Total: <span class="text-slate-800">{{ filteredContents.length }} item</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-slate-700 bg-white px-2.5 py-0.5 rounded-full border border-slate-200 shadow-2xs">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                            <span>Akses Terverifikasi</span>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <!-- FOOTER -->
        <footer class="py-3 sm:py-4 text-center text-[10px] sm:text-[11px] text-slate-400 border-t border-slate-200 bg-white">
            Pusat Cadangan Data & Penyimpanan Terintegrasi &bull; Akses Khusus Personel
        </footer>

        <!-- MODAL PRATINJAU DOKUMEN / GAMBAR (OPTIMAL DI HP & DESKTOP) -->
        <div v-if="previewUrl || isImageLoading" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 backdrop-blur-xs p-0 sm:p-4 animate-fade-in">
            <div class="bg-white w-full h-full sm:h-[88vh] sm:max-w-5xl sm:rounded-3xl flex flex-col overflow-hidden shadow-2xl">
                
                <!-- Preview Header -->
                <div class="p-3 sm:p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 gap-2 flex-wrap">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-xl bg-slate-200 text-slate-700 flex items-center justify-center shrink-0">
                            <svg v-if="previewType === 'arw'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h3 class="font-bold text-xs sm:text-sm uppercase tracking-tight truncate text-slate-900">{{ previewTitle }}</h3>
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                                <span v-if="previewType === 'arw'" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 uppercase tracking-wider border border-amber-300">
                                    Sony RAW
                                </span>
                            </div>
                            <p class="text-[9px] sm:text-[10px] text-slate-400 font-bold uppercase">
                                {{ previewType === 'arw' ? 'Pratinjau Foto RAW Resolusi Tinggi' : 'Mode Pratinjau Dokumen' }}
                            </p>
                        </div>
                    </div>

                    <!-- Toolbar Zoom & Rotasi Gambar -->
                    <div v-if="previewType === 'image' || previewType === 'arw'" class="flex items-center gap-1 bg-slate-200/80 p-1 rounded-xl shadow-2xs">
                        <button @click="zoomOut" 
                                type="button"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center shadow-xs transition cursor-pointer" 
                                title="Perkecil (-)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                        </button>
                        <span class="text-[10px] sm:text-xs font-mono font-bold px-1 sm:px-1.5 text-slate-700 select-none min-w-8 text-center">
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>
                        <button @click="zoomIn" 
                                type="button"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center shadow-xs transition cursor-pointer" 
                                title="Perbesar (+)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </button>
                        <div class="h-4 w-px bg-slate-300 mx-0.5"></div>
                        <button @click="rotateLeft" 
                                type="button"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center shadow-xs transition cursor-pointer" 
                                title="Putar Kiri (-90°)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2m0 0l-3-3m3 3l3-3M3 10l3 3m-3-3l3-3" /></svg>
                        </button>
                        <button @click="rotateRight" 
                                type="button"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center shadow-xs transition cursor-pointer" 
                                title="Putar Kanan (+90°)">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2m0 0l3-3m-3 3l-3-3m17-4l-3 3m3-3l-3-3" /></svg>
                        </button>
                        <button @click="resetZoomAndRotate" 
                                type="button"
                                class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-white hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center shadow-xs transition cursor-pointer" 
                                title="Reset Posisi & Rotasi">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                        <!-- Unduh JPG HD jika ARW -->
                        <a 
                            v-if="previewType === 'arw' && activePreviewItem?.download_jpg_url"
                            :href="activePreviewItem.download_jpg_url"
                            class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-[11px] sm:text-xs font-bold uppercase shadow-2xs transition flex items-center gap-1"
                            title="Unduh versi JPG resolusi tinggi">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span class="hidden sm:inline">Unduh JPG HD</span>
                            <span class="sm:hidden">JPG</span>
                        </a>

                        <button 
                            @click="closePreview" 
                            class="w-8 h-8 rounded-full bg-slate-200 hover:bg-rose-100 hover:text-rose-700 text-slate-700 font-bold flex items-center justify-center transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Preview Content (Anti-Copy & Anti-New-Tab) -->
                <div class="flex-1 bg-slate-950 overflow-hidden flex items-center justify-center p-2 sm:p-4 relative select-none"
                     @contextmenu.prevent=""
                     @wheel.prevent="handleWheelZoom"
                     @mousedown="startPan"
                     @mousemove="onPan"
                     @mouseup="endPan"
                     @mouseleave="endPan"
                     :style="{ cursor: zoomLevel > 1 ? (isPanning ? 'grabbing' : 'grab') : 'default' }">
                    
                    <!-- Loading Spinner Saat Dekripsi Blob -->
                    <div v-if="isImageLoading" class="flex flex-col items-center gap-3 text-slate-300">
                        <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-xs font-bold uppercase tracking-wider">Mendekripsi Data Gambar...</p>
                    </div>

                    <!-- ARW or standard image preview -->
                    <img 
                        v-else-if="(previewType === 'image' || previewType === 'arw') && previewUrl" 
                        :src="previewUrl" 
                        draggable="false"
                        @dragstart.prevent=""
                        @contextmenu.prevent=""
                        class="max-w-full max-h-full object-contain rounded-md sm:rounded-lg shadow-2xl pointer-events-auto" 
                        :style="{
                            transform: `translate(${panPosition.x}px, ${panPosition.y}px) scale(${zoomLevel}) rotate(${rotationDegree}deg)`,
                            transition: isPanning ? 'none' : 'transform 0.15s cubic-bezier(0.4, 0, 0.2, 1)'
                        }"
                        :alt="previewTitle"
                    />

                    <iframe 
                        v-else-if="previewType === 'pdf'" 
                        :src="previewUrl" 
                        class="w-full h-full rounded-md sm:rounded-lg border-0 bg-white shadow-lg"
                    ></iframe>

                    <div v-else-if="!isImageLoading" class="text-center text-white space-y-4 max-w-sm p-6 bg-slate-800 rounded-2xl mx-4">
                        <svg class="w-12 h-12 mx-auto text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div>
                            <h4 class="font-bold text-xs sm:text-sm uppercase tracking-wider">Pratinjau Tidak Didukung</h4>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Format berkas ini tidak dapat dipratinjau langsung di peramban. Silakan unduh berkas untuk membukanya.
                            </p>
                        </div>
                        <a 
                            :href="previewUrl" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs uppercase rounded-xl transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Unduh Berkas</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-6px); }
    40%, 80% { transform: translateX(6px); }
}

.animate-fade-in {
    animation: fadeIn 0.25s ease-out;
}

.animate-shake {
    animation: shake 0.35s ease-in-out;
}

/* Sembunyikan scrollbar untuk breadcrumb swipeable di mobile */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
