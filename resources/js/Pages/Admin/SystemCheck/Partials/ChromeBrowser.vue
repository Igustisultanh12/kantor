<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

// --- STATE TAB PERAMBAN CHROME ---
const tabs = ref([
    {
        id: 1,
        title: 'Google',
        url: 'https://www.google.com',
        displayUrl: 'https://www.google.com',
        isLoading: false,
        history: ['https://www.google.com'],
        historyIdx: 0,
    }
]);

const activeTabId = ref(1);
let nextTabId = 2;
const addressInput = ref('https://www.google.com');
const isFullscreen = ref(false);

// Mode Peramban: 'virtual' (Proxy bawaan) atau 'docker' (Desktop Chromium GUI noVNC)
const engineMode = ref('virtual');
const dockerUrl = ref(localStorage.getItem('sinden_chrome_docker_url') || 'http://localhost:3001');
const dockerStatus = ref(null);
const isCheckingDocker = ref(false);

// Panel Pengelola Unduhan (Downloader)
const showDownloader = ref(false);
const directDownloadUrl = ref('');
const isDownloadingToServer = ref(false);
const serverDownloads = ref([]);
const isLoadingDownloads = ref(false);

// Tab aktif saat ini
const activeTab = computed(() => {
    return tabs.value.find(t => t.id === activeTabId.value) || tabs.value[0];
});

// Sinkronisasi bilah alamat saat berpindah tab
watch(activeTabId, () => {
    if (activeTab.value) {
        addressInput.value = activeTab.value.displayUrl || activeTab.value.url;
    }
});

// PINTASAN BOOKMARKS RESMI
const bookmarks = [
    { name: 'Google', url: 'https://www.google.com', icon: 'google' },
    { name: 'Ookla Speedtest', url: 'https://www.speedtest.net', icon: 'speed' },
    { name: 'Fast.com', url: 'https://fast.com', icon: 'fast' },
    { name: 'Cloudflare Radar', url: 'https://radar.cloudflare.com', icon: 'cloudflare' },
    { name: 'GitHub', url: 'https://github.com', icon: 'github' },
    { name: 'Wikipedia', url: 'https://id.wikipedia.org', icon: 'wiki' },
    { name: 'Cek IP Server', url: 'https://whatismyipaddress.com', icon: 'ip' },
];

// Dapatkan URL yang melalui proxy Laravel
const getProxyUrl = (targetUrl) => {
    if (!targetUrl) return '';
    return route('system-check.chrome.browse') + '?url=' + encodeURIComponent(targetUrl);
};

// Navigasi ke URL baru
const navigateTo = (rawUrl) => {
    let target = (rawUrl || '').trim();
    if (!target) return;

    // Deteksi apakah kata kunci pencarian atau alamat URL
    if (!target.startsWith('http://') && !target.startsWith('https://')) {
        if (target.includes('.') && !target.includes(' ') && !target.startsWith('?')) {
            target = 'https://' . target;
        } else {
            target = 'https://www.google.com/search?q=' + encodeURIComponent(target);
        }
    }

    const tab = activeTab.value;
    if (!tab) return;

    tab.isLoading = true;
    tab.url = target;
    tab.displayUrl = target;
    addressInput.value = target;

    // Catat riwayat
    if (tab.history[tab.historyIdx] !== target) {
        tab.history = tab.history.slice(0, tab.historyIdx + 1);
        tab.history.push(target);
        tab.historyIdx = tab.history.length - 1;
    }
};

// Navigasi Back
const goBack = () => {
    const tab = activeTab.value;
    if (tab && tab.historyIdx > 0) {
        tab.historyIdx--;
        const prev = tab.history[tab.historyIdx];
        tab.url = prev;
        tab.displayUrl = prev;
        addressInput.value = prev;
        tab.isLoading = true;
    }
};

// Navigasi Forward
const goForward = () => {
    const tab = activeTab.value;
    if (tab && tab.historyIdx < tab.history.length - 1) {
        tab.historyIdx++;
        const next = tab.history[tab.historyIdx];
        tab.url = next;
        tab.displayUrl = next;
        addressInput.value = next;
        tab.isLoading = true;
    }
};

// Muat Ulang Halaman
const reloadPage = () => {
    const tab = activeTab.value;
    if (!tab) return;
    tab.isLoading = true;
    const current = tab.url;
    tab.url = '';
    setTimeout(() => {
        tab.url = current;
    }, 50);
};

// Beranda
const goHome = () => {
    navigateTo('https://www.google.com');
};

// Tambah Tab Baru
const addTab = (initialUrl = 'https://www.google.com') => {
    const newId = nextTabId++;
    tabs.value.push({
        id: newId,
        title: 'Tab Baru',
        url: initialUrl,
        displayUrl: initialUrl,
        isLoading: true,
        history: [initialUrl],
        historyIdx: 0,
    });
    activeTabId.value = newId;
    addressInput.value = initialUrl;
};

// Tutup Tab
const closeTab = (tabId) => {
    if (tabs.value.length === 1) {
        // Jika hanya sisa satu, reset ke tab baru
        tabs.value[0].title = 'Google';
        tabs.value[0].url = 'https://www.google.com';
        tabs.value[0].displayUrl = 'https://www.google.com';
        tabs.value[0].history = ['https://www.google.com'];
        tabs.value[0].historyIdx = 0;
        addressInput.value = 'https://www.google.com';
        return;
    }

    const idx = tabs.value.findIndex(t => t.id === tabId);
    if (idx !== -1) {
        tabs.value.splice(idx, 1);
        if (activeTabId.value === tabId) {
            const nextActive = tabs.value[Math.max(0, idx - 1)];
            activeTabId.value = nextActive.id;
            addressInput.value = nextActive.displayUrl || nextActive.url;
        }
    }
};

// Dengarkan pesan dari iframe proxy
const handleFrameMessage = (event) => {
    if (event.data && event.data.type === 'CHROME_PAGE_LOADED') {
        const tab = activeTab.value;
        if (tab) {
            tab.isLoading = false;
            if (event.data.title) {
                tab.title = event.data.title.substring(0, 24);
            }
            if (event.data.url) {
                tab.displayUrl = event.data.url;
                addressInput.value = event.data.url;
            }
        }
    }
};

// Buka tautan di tab baru asli browser
const openInRealTab = () => {
    if (activeTab.value?.url) {
        window.open(activeTab.value.url, '_blank');
    }
};

// --- PENGELOLA UNDUHAN (DOWNLOAD MANAGER) ---
const loadServerDownloads = async () => {
    isLoadingDownloads.value = true;
    try {
        const res = await axios.get(route('system-check.chrome.downloads'));
        serverDownloads.value = res.data;
    } catch (err) {
        console.error('Gagal mengambil daftar unduhan:', err);
    } finally {
        isLoadingDownloads.value.false;
    }
};

// Unduh langsung ke laptop/HP via browser
const downloadToClient = (targetUrl) => {
    const url = targetUrl || directDownloadUrl.value;
    if (!url) {
        Swal.fire('Perhatian', 'Harap masukkan URL berkas yang ingin diunduh.', 'warning');
        return;
    }

    const downloadLink = route('system-check.chrome.download') + '?url=' + encodeURIComponent(url) + '&destination=client';
    window.location.href = downloadLink;
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: 'Unduhan Dimulai',
        text: 'Berkas sedang dialirkan ke perangkat Anda.',
        showConfirmButton: false,
        timer: 3000,
    });
};

// Unduh dan simpan ke hardisk VPS server
const downloadToServer = async (targetUrl) => {
    const url = targetUrl || directDownloadUrl.value;
    if (!url) {
        Swal.fire('Perhatian', 'Harap masukkan URL berkas yang ingin diunduh ke server.', 'warning');
        return;
    }

    isDownloadingToServer.value = true;
    try {
        const res = await axios.post(route('system-check.chrome.download'), {
            url: url,
            destination: 'server'
        });

        if (res.data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Diunduh ke Server!',
                text: res.data.message,
            });
            directDownloadUrl.value = '';
            loadServerDownloads();
        } else {
            throw new Error(res.data.message);
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal Mengunduh ke Server',
            text: err.response?.data?.message || err.message || 'Terjadi gangguan jaringan saat mengunduh berkas.',
        });
    } finally {
        isDownloadingToServer.value = false;
    }
};

// Hapus berkas dari server
const deleteServerFile = async (filename) => {
    const confirm = await Swal.fire({
        title: 'Hapus Berkas?',
        text: `Hapus berkas "${filename}" dari penyimpanan server?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
    });

    if (confirm.isConfirmed) {
        try {
            await axios.delete(route('system-check.chrome.downloads.destroy', filename));
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Berkas dihapus.',
                showConfirmButton: false,
                timer: 2000,
            });
            loadServerDownloads();
        } catch (err) {
            Swal.fire('Galat', 'Gagal menghapus berkas.', 'error');
        }
    }
};

// --- DOCKER CHROMIUM ENGINE STATUS ---
const checkDockerStatus = async () => {
    isCheckingDocker.value = true;
    try {
        const res = await axios.get(route('system-check.chrome.check-docker'), {
            params: { url: dockerUrl.value }
        });
        dockerStatus.value = res.data;
    } catch (err) {
        dockerStatus.value = { online: false, error: err.message };
    } finally {
        isCheckingDocker.value = false;
    }
};

const saveDockerUrl = () => {
    localStorage.setItem('sinden_chrome_docker_url', dockerUrl.value);
    checkDockerStatus();
};

const copyCommand = (cmd) => {
    navigator.clipboard.writeText(cmd);
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Perintah disalin!',
        showConfirmButton: false,
        timer: 2000,
    });
};

onMounted(() => {
    window.addEventListener('message', handleFrameMessage);
    loadServerDownloads();
    if (engineMode.value === 'docker') {
        checkDockerStatus();
    }
});

onUnmounted(() => {
    window.removeEventListener('message', handleFrameMessage);
});
</script>

<template>
    <div 
        :class="isFullscreen ? 'fixed inset-0 z-[300] bg-slate-950 p-2 sm:p-4' : 'bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden'"
        class="flex flex-col transition-all duration-300">
        
        <!-- 1. CHROME TOP TAB STRIP (GAYA GOOGLE CHROME RESMI) -->
        <div class="bg-slate-900 px-3 pt-2.5 pb-0 flex items-center justify-between gap-2 overflow-x-auto select-none border-b border-slate-800">
            <!-- Tab Items Strip -->
            <div class="flex items-center gap-1 min-w-0 flex-1 overflow-x-auto py-0.5">
                
                <!-- Chrome Logo Icon -->
                <div class="flex items-center gap-1.5 px-2.5 py-1 text-white shrink-0 mr-1">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" fill="#4285F4"/>
                        <path d="M12 2C6.48 2 2 6.48 2 12c0 2.85 1.2 5.42 3.12 7.24L9.8 11h9.1c-.48-4.99-4.7-9-9.9-9z" fill="#EA4335"/>
                        <path d="M21.9 11h-9.1l-4.68 8.24C9.52 20.35 10.72 21 12 21c4.99 0 9.1-3.68 9.9-8.5.07-.49.1-1 .1-1.5z" fill="#FBBC05"/>
                        <circle cx="12" cy="12" r="4.5" fill="#34A853"/>
                        <circle cx="12" cy="12" r="3.2" fill="#ffffff"/>
                    </svg>
                    <span class="text-xs font-black tracking-wider uppercase text-slate-200 hidden sm:inline">Chrome</span>
                </div>

                <!-- Individual Tabs -->
                <div 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    @click="activeTabId = tab.id"
                    :class="activeTabId === tab.id ? 'bg-slate-800 text-white shadow-md border-t-2 border-indigo-400' : 'bg-slate-900/60 text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'"
                    class="group relative max-w-[180px] sm:max-w-[220px] min-w-[120px] px-3 py-2 rounded-t-xl text-xs font-bold transition flex items-center justify-between gap-2 cursor-pointer shrink-0">
                    
                    <div class="flex items-center gap-2 truncate">
                        <span v-if="tab.isLoading" class="w-3 h-3 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin shrink-0"></span>
                        <svg v-else class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8" />
                        </svg>
                        <span class="truncate text-[11px]">{{ tab.title }}</span>
                    </div>

                    <!-- Close Tab Button -->
                    <button 
                        @click.stop="closeTab(tab.id)"
                        class="w-4 h-4 rounded-full hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer shrink-0"
                        title="Tutup Tab">
                        &times;
                    </button>
                </div>

                <!-- Tombol Tambah Tab Baru (+) -->
                <button 
                    @click="addTab()"
                    class="w-7 h-7 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition cursor-pointer shrink-0 text-sm font-black"
                    title="Buka Tab Baru">
                    +
                </button>
            </div>

            <!-- Toolbar Kanan (Pilihan Engine, Downloader & Fullscreen) -->
            <div class="flex items-center gap-1.5 pb-2 shrink-0">
                <!-- Selector Engine -->
                <div class="bg-slate-800/90 p-0.5 rounded-xl border border-slate-700/80 flex items-center text-[10px] font-bold">
                    <button 
                        @click="engineMode = 'virtual'"
                        :class="engineMode === 'virtual' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200'"
                        class="px-2.5 py-1 rounded-lg transition cursor-pointer">
                        Web Client (Bawaan)
                    </button>
                    <button 
                        @click="engineMode = 'docker'; checkDockerStatus();"
                        :class="engineMode === 'docker' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-400 hover:text-slate-200'"
                        class="px-2.5 py-1 rounded-lg transition cursor-pointer">
                        Docker GUI (noVNC)
                    </button>
                </div>

                <!-- Tombol Buka Panel Unduhan -->
                <button 
                    @click="showDownloader = !showDownloader"
                    :class="showDownloader ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                    class="p-2 rounded-xl text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                    title="Pengelola Unduhan (Downloader)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span class="hidden md:inline text-[11px]">Unduhan</span>
                    <span v-if="serverDownloads.length > 0" class="px-1.5 py-0.2 bg-indigo-500 text-white rounded-full text-[9px]">
                        {{ serverDownloads.length }}
                    </span>
                </button>

                <!-- Tombol Fullscreen -->
                <button 
                    @click="isFullscreen = !isFullscreen"
                    class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition cursor-pointer"
                    :title="isFullscreen ? 'Kecilkan Layar' : 'Layar Penuh (Fullscreen)'">
                    <svg v-if="!isFullscreen" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- 2. CHROME NAVIGATION & OMNIBAR (BILAH ALAMAT) -->
        <div class="bg-slate-800 px-3 py-2 flex items-center gap-2 border-b border-slate-700">
            <!-- Tombol Navigasi: Back, Forward, Reload, Home -->
            <div class="flex items-center gap-1 shrink-0">
                <button 
                    @click="goBack" 
                    :disabled="activeTab.historyIdx <= 0"
                    class="p-1.5 rounded-lg text-slate-300 hover:bg-slate-700 disabled:opacity-30 disabled:hover:bg-transparent transition cursor-pointer"
                    title="Kembali (Back)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button 
                    @click="goForward" 
                    :disabled="activeTab.historyIdx >= activeTab.history.length - 1"
                    class="p-1.5 rounded-lg text-slate-300 hover:bg-slate-700 disabled:opacity-30 disabled:hover:bg-transparent transition cursor-pointer"
                    title="Maju (Forward)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
                <button 
                    @click="reloadPage" 
                    class="p-1.5 rounded-lg text-slate-300 hover:bg-slate-700 transition cursor-pointer"
                    title="Muat Ulang (Reload)">
                    <svg class="w-4 h-4" :class="activeTab.isLoading ? 'animate-spin text-indigo-400' : ''" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </button>
                <button 
                    @click="goHome" 
                    class="p-1.5 rounded-lg text-slate-300 hover:bg-slate-700 transition cursor-pointer"
                    title="Beranda (Google)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                </button>
            </div>

            <!-- OMNIBAR INPUT FORM -->
            <form @submit.prevent="navigateTo(addressInput)" class="flex-1 min-w-0 flex items-center bg-slate-900 rounded-xl px-3 py-1.5 border border-slate-700 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 transition">
                <!-- Status Keamanan / Kunci -->
                <div class="flex items-center gap-1.5 text-emerald-400 mr-2 shrink-0 select-none">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                    <span class="text-[10px] font-mono font-bold uppercase tracking-wider hidden sm:inline text-slate-400">Aman</span>
                </div>

                <!-- Input Teks URL -->
                <input 
                    v-model="addressInput" 
                    type="text" 
                    placeholder="Ketik alamat web (misal: google.com, speedtest.net) atau cari apa saja..."
                    class="w-full bg-transparent border-0 text-xs sm:text-sm font-mono text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-0 p-0"
                />

                <!-- Tombol Navigasi / Cari -->
                <button 
                    type="submit" 
                    class="ml-2 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition shrink-0 cursor-pointer">
                    Buka
                </button>
            </form>

            <!-- Aksi Cepat: Buka di Tab Nyata -->
            <button 
                @click="openInRealTab"
                class="p-2 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-bold transition flex items-center gap-1 shrink-0 cursor-pointer"
                title="Buka situs ini di tab peramban baru">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                <span class="hidden lg:inline text-[11px]">Tab Baru</span>
            </button>
        </div>

        <!-- 3. BOOKMARKS SHORTCUTS BAR -->
        <div class="bg-slate-850 px-3 py-1.5 flex items-center gap-1.5 overflow-x-auto text-[11px] border-b border-slate-750">
            <span class="text-[10px] font-bold uppercase text-slate-400 px-1 shrink-0">Pintasan:</span>
            <button 
                v-for="b in bookmarks" 
                :key="b.name"
                @click="navigateTo(b.url)"
                class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white transition font-medium shrink-0 flex items-center gap-1.5 cursor-pointer">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                <span>{{ b.name }}</span>
            </button>
        </div>

        <!-- 4. VIEWPORT AREA PERAMBAN (VIRTUAL PROXY VS DOCKER GUI) -->
        <div class="relative flex-1 bg-slate-950 flex flex-col min-h-[580px] sm:min-h-[660px]">
            
            <!-- Indikator Loading Bar Halus -->
            <div v-if="activeTab.isLoading && engineMode === 'virtual'" class="w-full bg-slate-800 h-1 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 h-full w-full animate-pulse"></div>
            </div>

            <!-- MODE 1: VIRTUAL BROWSER PROXY (BAWAAN - TANPA DOCKER) -->
            <div v-if="engineMode === 'virtual'" class="w-full h-full flex-1 flex flex-col relative">
                <iframe 
                    v-if="activeTab.url"
                    :src="getProxyUrl(activeTab.url)"
                    class="w-full flex-1 border-0 bg-white"
                    style="min-height: 600px; height: 100%;"
                    sandbox="allow-scripts allow-forms allow-same-origin allow-popups allow-downloads allow-modals"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                ></iframe>
            </div>

            <!-- MODE 2: DOCKER CHROMIUM GUI (FULL DESKTOP CHROME NOVNC) -->
            <div v-else-if="engineMode === 'docker'" class="w-full h-full flex-1 flex flex-col p-3 sm:p-4 space-y-4">
                <div class="bg-slate-900 rounded-2xl p-4 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-sm text-white uppercase tracking-tight">
                                Docker Chromium Desktop Container
                            </h3>
                            <span 
                                :class="dockerStatus?.online ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border-rose-500/30'"
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full" :class="dockerStatus?.online ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                                {{ dockerStatus?.online ? 'Kontainer Aktif' : 'Belum Terhubung' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Menjalankan peramban Google Chrome asli berbasis kontainer GUI di VPS (mendukung 100% web store, ekstensi, audio, dan download).
                        </p>
                    </div>

                    <!-- Input Port / Host Docker -->
                    <div class="flex items-center gap-2">
                        <input 
                            v-model="dockerUrl" 
                            type="text" 
                            placeholder="http://localhost:3001" 
                            class="bg-slate-800 text-xs font-mono text-white px-3 py-1.5 rounded-xl border border-slate-700 focus:outline-none focus:border-indigo-500 min-w-[180px]"
                        />
                        <button 
                            @click="saveDockerUrl" 
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition cursor-pointer">
                            Simpan & Cek
                        </button>
                    </div>
                </div>

                <!-- Iframe noVNC jika online -->
                <div v-if="dockerStatus?.online" class="flex-1 w-full rounded-2xl overflow-hidden border border-slate-800 bg-black min-h-[550px]">
                    <iframe 
                        :src="dockerUrl" 
                        class="w-full h-full border-0"
                        style="min-height: 550px;"
                        allow="clipboard-read; clipboard-write; fullscreen"
                    ></iframe>
                </div>

                <!-- Petunjuk Instalasi 1-Baris jika kontainer belum aktif -->
                <div v-else class="flex-1 bg-slate-900/90 rounded-2xl p-6 border border-slate-800 flex flex-col items-center justify-center text-center space-y-4 max-w-2xl mx-auto my-6">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-950 text-indigo-400 border border-indigo-800 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-base font-black text-white uppercase tracking-tight">Aktifkan Chromium Desktop di VPS Anda</h4>
                        <p class="text-xs text-slate-400 mt-1 max-w-md">
                            Untuk menggunakan tampilan desktop Chrome utuh, jalankan perintah Docker berikut satu kali di terminal VPS Anda:
                        </p>
                    </div>

                    <div class="w-full bg-slate-950 p-3 rounded-xl border border-slate-800 flex items-center justify-between gap-3 text-left">
                        <code class="text-xs font-mono text-emerald-400 select-all overflow-x-auto break-all">
                            docker run -d --name=chromium -p 3001:3000 -e PUID=1000 -e PGID=1000 -e TITLE="SINDEN Chrome" --restart unless-stopped lscr.io/linuxserver/chromium:latest
                        </code>
                        <button 
                            @click="copyCommand('docker run -d --name=chromium -p 3001:3000 -e PUID=1000 -e PGID=1000 -e TITLE=\&quot;SINDEN Chrome\&quot; --restart unless-stopped lscr.io/linuxserver/chromium:latest')"
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-bold uppercase shrink-0 transition cursor-pointer">
                            Salin
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button 
                            @click="checkDockerStatus" 
                            :disabled="isCheckingDocker"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold uppercase transition flex items-center gap-2 cursor-pointer disabled:opacity-50">
                            <span v-if="isCheckingDocker" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ isCheckingDocker ? 'Memeriksa...' : 'Cek Status Sekarang' }}</span>
                        </button>
                        <button 
                            @click="engineMode = 'virtual'" 
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold uppercase transition cursor-pointer">
                            Gunakan Web Client Biasa
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- 5. PANEL PENGELOLA UNDUHAN (DRAWER DOWNLOAD MANAGER) -->
        <div 
            v-if="showDownloader" 
            class="bg-slate-900 border-t border-slate-800 p-4 sm:p-5 space-y-4 animate-fade-in text-slate-200">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase text-white tracking-tight">Pusat Pengelola Unduhan (Downloader)</h3>
                        <p class="text-xs text-slate-400">Unduh berkas apa saja dari internet langsung ke laptop/HP Anda atau disimpan di penyimpanan server VPS.</p>
                    </div>
                </div>

                <button 
                    @click="showDownloader = false" 
                    class="w-7 h-7 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition cursor-pointer self-end sm:self-auto">
                    &times;
                </button>
            </div>

            <!-- Form Tempel URL Download Langsung -->
            <div class="bg-slate-800/80 p-3.5 rounded-2xl border border-slate-700/80 space-y-3">
                <label class="text-xs font-bold text-slate-300 uppercase tracking-wider block">
                    Unduh Langsung dari Tautan (Direct URL):
                </label>
                <div class="flex flex-col sm:flex-row items-center gap-2">
                    <input 
                        v-model="directDownloadUrl" 
                        type="text" 
                        placeholder="https://contoh.com/berkas-installer.zip atau .deb / .iso / .pdf..."
                        class="w-full bg-slate-900 text-xs font-mono text-white px-3.5 py-2.5 rounded-xl border border-slate-700 focus:outline-none focus:border-indigo-500 placeholder-slate-500"
                    />
                    
                    <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                        <!-- Tombol Download ke Laptop/HP -->
                        <button 
                            @click="downloadToClient(directDownloadUrl)"
                            type="button"
                            class="flex-1 sm:flex-initial px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            <span>Unduh ke Laptop/HP</span>
                        </button>

                        <!-- Tombol Download ke Server Storage -->
                        <button 
                            @click="downloadToServer(directDownloadUrl)"
                            :disabled="isDownloadingToServer"
                            type="button"
                            class="flex-1 sm:flex-initial px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold uppercase transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm disabled:opacity-50">
                            <span v-if="isDownloadingToServer" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                            <span>{{ isDownloadingToServer ? 'Mengunduh...' : 'Simpan di Server' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabel Daftar Berkas Unduhan di Server -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-300">
                        Berkas Tersimpan di Server (storage/app/downloads):
                    </h4>
                    <button 
                        @click="loadServerDownloads" 
                        class="text-[11px] text-indigo-400 hover:text-indigo-300 font-bold transition flex items-center gap-1 cursor-pointer">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        <span>Segarkan</span>
                    </button>
                </div>

                <div v-if="serverDownloads.length === 0" class="p-6 bg-slate-800/40 rounded-xl border border-slate-800 text-center text-xs text-slate-500">
                    Belum ada berkas unduhan yang disimpan di server. Anda dapat menempelkan URL di atas lalu klik "Simpan di Server".
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-800 border-b border-slate-700 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                <th class="p-3">Nama Berkas</th>
                                <th class="p-3 text-center">Ukuran</th>
                                <th class="p-3">Tanggal Unduh</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 font-medium">
                            <tr v-for="item in serverDownloads" :key="item.name" class="hover:bg-slate-800/50 transition">
                                <td class="p-3 text-white font-mono font-bold flex items-center gap-2">
                                    <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    <span class="truncate max-w-xs sm:max-w-md">{{ item.name }}</span>
                                </td>
                                <td class="p-3 text-center font-mono font-bold text-slate-300">
                                    {{ item.size_human }}
                                </td>
                                <td class="p-3 text-slate-400 text-[11px]">
                                    {{ item.modified_at }}
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Unduh ke Laptop -->
                                        <button 
                                            @click="downloadToClient(route('system-check.chrome.browse') + '?url=' + encodeURIComponent(item.name))"
                                            class="p-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition cursor-pointer"
                                            title="Unduh ke Perangkat Saya">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        </button>
                                        <!-- Hapus -->
                                        <button 
                                            @click="deleteServerFile(item.name)"
                                            class="p-1.5 bg-rose-600/80 hover:bg-rose-600 text-white rounded-lg transition cursor-pointer"
                                            title="Hapus dari Server">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</template>

<style scoped>
.bg-slate-850 {
    background-color: #172033;
}
.border-slate-750 {
    border-color: #243049;
}
</style>
