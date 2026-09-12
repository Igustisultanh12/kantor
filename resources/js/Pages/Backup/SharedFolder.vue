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
            // Muat ulang halaman setelah verifikasi session tersimpan
            window.location.reload();
        }
    } catch (err) {
        if (err.response && err.response.data && err.response.data.message) {
            errorMessage.value = err.response.data.message;
        } else {
            errorMessage.value = 'Terjadi kesalahan sistem. Silakan coba lagi.';
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

const isArw = (filename) => {
    return filename?.toLowerCase().endsWith('.arw');
};

const isImage = (filename) => {
    const ext = filename?.split('.').pop()?.toLowerCase();
    return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(ext);
};

const isPdf = (filename) => {
    return filename?.toLowerCase().endsWith('.pdf');
};

const openPreview = (item) => {
    activePreviewItem.value = item;
    previewTitle.value = item.file_name;
    previewUrl.value = item.preview_url;

    if (isImage(item.file_name)) {
        previewType.value = 'image';
    } else if (isArw(item.file_name)) {
        previewType.value = 'arw';
    } else if (isPdf(item.file_name)) {
        previewType.value = 'pdf';
    } else {
        previewType.value = 'other';
    }
};

const closePreview = () => {
    previewUrl.value = null;
    previewTitle.value = '';
    previewType.value = '';
    activePreviewItem.value = null;
};

const exitAndLock = () => {
    Swal.fire({
        title: 'Kunci Kembali Folder?',
        text: 'Sesi Anda akan ditutup. PIN keamanan akan diminta kembali jika tautan dibuka.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Kunci Kembali',
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
        <!-- TOP NAVIGATION BAR -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-700 to-indigo-600 flex items-center justify-center text-white shadow-md font-black text-xl">
                        📁
                    </div>
                    <div>
                        <h1 class="text-sm sm:text-base font-black text-slate-900 tracking-tight uppercase">
                            {{ shareName || 'Pusat Cadangan Berbagi' }}
                        </h1>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                            Akses Terbatas Personel (Hanya Lihat & Unduh)
                        </p>
                    </div>
                </div>

                <div v-if="!needsPin && !isNotFound && !isDeactivated" class="flex items-center gap-2">
                    <button 
                        @click="exitAndLock" 
                        class="px-3.5 py-1.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300 text-xs font-black uppercase transition flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        title="Kunci kembali folder dan keluar dari sesi">
                        <span>🔒</span>
                        <span class="hidden sm:inline">Kunci Kembali</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- MAIN BODY CONTENT -->
        <main class="flex-1 flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8">

            <!-- STATE 1: TAUTAN TIDAK DITEMUKAN (404) -->
            <div v-if="isNotFound" class="max-w-md w-full bg-white rounded-3xl shadow-xl p-8 border border-slate-200 text-center space-y-5 animate-fade-in">
                <div class="w-20 h-20 mx-auto rounded-full bg-rose-50 border-2 border-rose-200 flex items-center justify-center text-4xl shadow-inner">
                    ❌
                </div>
                <div class="space-y-2">
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Tautan Tidak Ditemukan</h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Tautan berbagi yang Anda akses tidak valid, sudah kadaluarsa, atau telah dihapus oleh pengelola.
                    </p>
                </div>
                <div class="pt-4 border-t border-slate-100">
                    <Link :href="route('dashboard')" class="inline-flex items-center justify-center w-full px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-black uppercase tracking-wider transition shadow-md">
                        Kembali ke Beranda
                    </Link>
                </div>
            </div>

            <!-- STATE 2: TAUTAN DINONAKTIFKAN OLEH ADMIN -->
            <div v-else-if="isDeactivated" class="max-w-md w-full bg-white rounded-3xl shadow-xl p-8 border border-amber-200 text-center space-y-5 animate-fade-in">
                <div class="w-20 h-20 mx-auto rounded-full bg-amber-50 border-2 border-amber-200 flex items-center justify-center text-4xl shadow-inner">
                    🚫
                </div>
                <div class="space-y-2">
                    <div class="inline-block px-3 py-1 bg-amber-100 text-amber-800 text-[10px] font-black uppercase tracking-widest rounded-full mb-1">
                        Akses Dinonaktifkan
                    </div>
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">{{ shareName }}</h2>
                    <p class="text-xs text-slate-500 font-medium">
                        Akses tautan berbagi untuk folder ini sedang dinonaktifkan atau ditutup oleh Administrator.
                    </p>
                </div>
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-[11px] text-slate-600 text-left space-y-1">
                    <p class="font-bold">Informasi:</p>
                    <p>Jika Anda memerlukan berkas di dalam folder ini, silakan hubungi Administrator Pangkalan untuk mengaktifkan kembali tautan.</p>
                </div>
            </div>

            <!-- STATE 3: FORMULIR INPUT PIN KEAMANAN -->
            <div v-else-if="needsPin" class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-200 animate-fade-in">
                <!-- Header Banner -->
                <div class="p-6 bg-gradient-to-br from-blue-700 via-indigo-700 to-slate-900 text-white text-center space-y-3">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-3xl shadow-lg">
                        🔒
                    </div>
                    <div>
                        <h2 class="text-lg font-black tracking-tight uppercase">Proteksi PIN Keamanan</h2>
                        <p class="text-xs text-blue-100 font-medium mt-1">
                            Folder ini dibagikan secara privat dengan verifikasi PIN.
                        </p>
                    </div>
                </div>

                <!-- PIN Body -->
                <div class="p-6 sm:p-8 space-y-6">
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center gap-3">
                        <span class="text-2xl">📁</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Folder Target</p>
                            <p class="text-sm font-black text-slate-800 truncate uppercase">{{ shareName || folderName }}</p>
                            <p class="text-[10px] text-slate-500 font-semibold">Pangkalan: {{ pcName }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitPin" class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center justify-between">
                                <span>Masukkan PIN Keamanan</span>
                                <button 
                                    type="button" 
                                    @click="showPin = !showPin" 
                                    class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 lowercase">
                                    {{ showPin ? 'sembunyikan' : 'lihat pin' }}
                                </button>
                            </label>
                            
                            <div class="relative">
                                <input 
                                    v-model="pinInput" 
                                    :type="showPin ? 'text' : 'password'" 
                                    maxlength="12" 
                                    autofocus
                                    placeholder="Ketik PIN..."
                                    autocomplete="off"
                                    class="w-full text-center text-2xl tracking-[0.3em] font-black py-3.5 px-4 bg-slate-50 border-2 border-slate-300 focus:border-indigo-600 focus:bg-white rounded-2xl shadow-inner transition outline-none"
                                />
                            </div>

                            <p v-if="errorMessage" class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 p-2.5 rounded-xl flex items-center gap-1.5 animate-shake">
                                <span>⚠️</span>
                                <span>{{ errorMessage }}</span>
                            </p>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="isVerifying || !pinInput.trim()"
                            class="w-full py-3.5 px-5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-2xl font-black text-xs uppercase tracking-wider shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                            <span v-if="isVerifying" class="animate-spin">⏳</span>
                            <span>{{ isVerifying ? 'Memverifikasi...' : 'Buka Folder Berkas' }}</span>
                        </button>
                    </form>

                    <div class="text-center pt-2">
                        <p class="text-[11px] text-slate-400">
                            * PIN keamanan diberikan oleh pemilik pangkalan atau Administrator. Akses terbatas hanya untuk melihat dan mengunduh berkas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- STATE 4: DAFTAR BERKAS TERVERIFIKASI (READ-ONLY GOOGLE DRIVE STYLE) -->
            <div v-else class="w-full max-w-7xl flex-1 flex flex-col space-y-4">
                
                <!-- TOP ACTION & SEARCH BAR -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    
                    <!-- Search Input -->
                    <div class="relative w-full md:w-80">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-sm">
                            🔍
                        </span>
                        <input 
                            v-model="localSearch" 
                            type="text" 
                            placeholder="Cari berkas dalam folder..." 
                            class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:border-indigo-600 focus:outline-none transition"
                        />
                    </div>

                    <!-- Right Buttons: Download Zip Folder -->
                    <div class="flex items-center gap-2 self-end md:self-auto">
                        <a 
                            :href="route('backup.shared.download-zip', { token: shareToken, folder_id: currentFolderId })" 
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black uppercase flex items-center gap-2 transition shadow-sm cursor-pointer"
                            title="Unduh seluruh berkas di folder ini sebagai arsip .ZIP">
                            <span>📦</span>
                            <span>Unduh Seluruh Folder (.ZIP)</span>
                        </a>
                    </div>
                </div>

                <!-- BREADCRUMBS SCOPED TO SHARE ROOT -->
                <nav v-if="breadcrumbs && breadcrumbs.length > 0" class="flex items-center px-4 py-2 bg-white rounded-xl border border-slate-200 text-xs font-bold uppercase tracking-tight text-slate-500 overflow-x-auto">
                    <div class="flex items-center gap-1.5 whitespace-nowrap">
                        <span class="text-slate-400">📂</span>
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

                <!-- FILE LIST TABLE -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex-1 flex flex-col min-h-[420px]">
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <th class="p-4">Nama Berkas</th>
                                    <th class="p-4 text-center">Ukuran</th>
                                    <th class="p-4">Tanggal Modifikasi</th>
                                    <th class="p-4 text-right">Opsi Akses</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-medium">
                                <tr 
                                    v-for="item in filteredContents" 
                                    :key="item.id"
                                    @dblclick="item.is_folder ? openFolder(item) : openPreview(item)"
                                    class="hover:bg-indigo-50/40 transition cursor-pointer select-none group">
                                    
                                    <!-- Nama Item -->
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <span v-if="item.is_folder" class="text-2xl">📁</span>
                                            <span v-else-if="item.file_type?.toLowerCase() === 'xlsx' || item.file_type?.toLowerCase() === 'xls'" class="text-2xl">📊</span>
                                            <span v-else-if="isPdf(item.file_name)" class="text-2xl">📕</span>
                                            <span v-else-if="isArw(item.file_name)" class="text-2xl" title="Sony Alpha RAW (.ARW)">📷</span>
                                            <span v-else-if="isImage(item.file_name)" class="text-2xl">🖼️</span>
                                            <span v-else-if="item.file_type?.toLowerCase() === 'zip'" class="text-2xl">📦</span>
                                            <span v-else class="text-2xl">📄</span>

                                            <div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <p class="font-black text-slate-800 uppercase tracking-tight group-hover:text-indigo-700 transition">
                                                        {{ item.file_name }}
                                                    </p>
                                                    <span v-if="isArw(item.file_name)" class="px-2 py-0.5 text-[9px] bg-amber-100 text-amber-800 rounded-full font-bold uppercase border border-amber-300">
                                                        Sony RAW
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-slate-400 font-bold uppercase">
                                                    {{ item.is_folder ? 'Folder Direktori' : (isArw(item.file_name) ? 'Foto Sony RAW' : (item.file_type || 'Berkas')) }}
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
                                                class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 rounded-xl text-xs font-black uppercase transition cursor-pointer flex items-center gap-1">
                                                <span>Buka</span>
                                                <span>→</span>
                                            </button>

                                            <!-- Pratinjau Dokumen -->
                                            <button 
                                                v-if="!item.is_folder" 
                                                @click.stop="openPreview(item)" 
                                                class="p-2 bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 rounded-xl transition cursor-pointer"
                                                :title="isArw(item.file_name) ? 'Lihat Pratinjau Foto Sony RAW' : 'Lihat Pratinjau Berkas'">
                                                👁️
                                            </button>

                                            <!-- Unduh Sebagai JPG HD Khusus Berkas Sony RAW -->
                                            <a 
                                                v-if="!item.is_folder && isArw(item.file_name) && item.download_jpg_url" 
                                                :href="item.download_jpg_url" 
                                                class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-600 hover:text-white text-amber-800 rounded-xl transition cursor-pointer inline-flex items-center justify-center font-bold text-[10px] uppercase gap-1"
                                                title="Unduh Berkas Sony RAW Ini Sebagai Format JPG HD">
                                                <span>JPG</span>
                                                <span>⬇️</span>
                                            </a>

                                            <!-- Unduh Berkas Asli -->
                                            <a 
                                                v-if="!item.is_folder && item.download_url" 
                                                :href="item.download_url" 
                                                class="p-2 bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 rounded-xl transition cursor-pointer inline-flex items-center justify-center"
                                                :title="isArw(item.file_name) ? 'Unduh Berkas Asli (.ARW)' : 'Unduh Berkas Ini'">
                                                ⬇️
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Empty State -->
                                <tr v-if="filteredContents.length === 0">
                                    <td colspan="4" class="p-16 text-center">
                                        <div class="flex flex-col items-center opacity-40 space-y-2">
                                            <span class="text-5xl">📂</span>
                                            <p class="font-black uppercase tracking-wider text-xs text-slate-600">
                                                {{ localSearch ? 'Tidak ada berkas yang sesuai dengan pencarian' : 'Folder ini masih kosong' }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Info Bar -->
                    <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center text-[11px] font-bold text-slate-500 gap-2">
                        <div>
                            Total: <span class="text-slate-800">{{ filteredContents.length }} item</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Akses Terverifikasi PIN</span>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <!-- FOOTER -->
        <footer class="py-4 text-center text-[11px] text-slate-400 border-t border-slate-200 bg-white">
            Pusat Cadangan Data & Penyimpanan Terintegrasi &bull; Akses Terbatas Khusus Personel
        </footer>

        <!-- MODAL PRATINJAU DOKUMEN / GAMBAR -->
        <div v-if="previewUrl" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
            <div class="bg-white w-full max-w-5xl h-[88vh] rounded-3xl flex flex-col overflow-hidden shadow-2xl border-t-8"
                :class="previewType === 'arw' ? 'border-amber-500' : 'border-indigo-600'">
                
                <!-- Preview Header -->
                <div class="p-4 sm:p-5 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-2xl">{{ previewType === 'arw' ? '📷' : '👁️' }}</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-black text-sm uppercase tracking-tight truncate text-slate-800">{{ previewTitle }}</h3>
                                <span v-if="previewType === 'arw'" class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800 uppercase tracking-wider border border-amber-300">
                                    Sony RAW HD
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">
                                {{ previewType === 'arw' ? 'Pratinjau Foto RAW (Engine BIONZ / ImageMagick HD)' : 'Mode Pratinjau Dokumen' }}
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <!-- Unduh JPG HD jika ARW -->
                        <a 
                            v-if="previewType === 'arw' && activePreviewItem?.download_jpg_url"
                            :href="activePreviewItem.download_jpg_url"
                            class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black uppercase shadow-xs transition flex items-center gap-1.5"
                            title="Unduh versi JPG resolusi tinggi">
                            <span>⬇️ Unduh JPG HD</span>
                        </a>

                        <a 
                            :href="previewUrl" 
                            target="_blank" 
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                            Buka di Tab Baru ↗
                        </a>
                        <button 
                            @click="closePreview" 
                            class="w-8 h-8 rounded-full bg-slate-200 hover:bg-rose-100 hover:text-rose-700 text-slate-700 font-bold flex items-center justify-center transition">
                            &times;
                        </button>
                    </div>
                </div>

                <!-- Preview Content -->
                <div class="flex-1 bg-slate-900 overflow-hidden flex items-center justify-center p-4 relative">
                    <!-- ARW or standard image preview -->
                    <img 
                        v-if="previewType === 'image' || previewType === 'arw'" 
                        :src="previewUrl" 
                        class="max-w-full max-h-full object-contain rounded-lg shadow-lg" 
                        :alt="previewTitle"
                    />

                    <iframe 
                        v-else-if="previewType === 'pdf'" 
                        :src="previewUrl" 
                        class="w-full h-full rounded-lg border-0 bg-white shadow-lg"
                    ></iframe>

                    <div v-else class="text-center text-white space-y-4 max-w-sm p-6 bg-slate-800 rounded-2xl">
                        <span class="text-5xl block">📄</span>
                        <div>
                            <h4 class="font-black text-sm uppercase tracking-wider">Pratinjau Tidak Didukung Langsung</h4>
                            <p class="text-xs text-slate-400 mt-1">
                                Format berkas ini tidak dapat dipratinjau langsung di browser. Silakan unduh berkas untuk membukanya di perangkat Anda.
                            </p>
                        </div>
                        <a 
                            :href="previewUrl" 
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs uppercase rounded-xl transition shadow-md">
                            <span>⬇️ Unduh Berkas Ini</span>
                        </a>
                    </div>

                    <!-- ARW Floating Badge in bottom corner -->
                    <div v-if="previewType === 'arw'" class="absolute bottom-6 right-6 bg-slate-950/80 backdrop-blur-md px-3.5 py-1.5 rounded-xl border border-white/10 text-white text-[11px] font-bold flex items-center gap-2 shadow-xl pointer-events-none">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Sony RAW Converted Preview (95% HD Quality)</span>
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
</style>
