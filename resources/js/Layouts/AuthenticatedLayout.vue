<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Swal from 'sweetalert2'; 

const page = usePage();

/**
 * Data Pengaturan & Identitas Personel
 */
const settings = computed(() => page.props.settings || {});
const agencyName = computed(() => settings.value.agency_name || 'DENINTEL KODAERAL V');
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const user = computed(() => page.props.auth.user);

/**
 * LOGIKA AKTIF: FITUR PENCARIAN REAKTIF CORETAX GLOBAL
 */
const searchQuery = ref('');
const searchInputRef = ref(null);

// Fungsi mengirimkan kata kunci pencarian ke halaman anak (Dashboard/Tabel) secara real-time
const handleSearchInput = () => {
    const searchEvent = new CustomEvent('sinden-global-search', {
        detail: { query: searchQuery.value }
    });
    window.dispatchEvent(searchEvent);
};

// Fungsi menangkap shortcut Ctrl + K untuk otomatis fokus ke input pencarian
const handleKeyDown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault(); // Gagalkan fungsi bawaan browser
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        }
    }
};

/**
 * SISTEM NOTIFIKASI TAKTIS
 */
const isNotifOpen = ref(false);
const notifications = ref([
    { id: 1, title: 'Ulang Birthday', message: 'Hari ini ada personel yang berulang tahun. Silakan cek Periksa pada menu ucapan', time: 'Baru saja' },
    { id: 2, title: 'Sistem SINDEN', message: 'Pangkalan data UcapanConfig berhasil disinkronisasi dengan jalur utama.', time: '1 Jam yang lalu' },
]);

const showNotifDetail = (notif) => {
    isNotifOpen.value = false; 
    Swal.fire({
        title: `<span class="uppercase font-bold text-sm tracking-wider text-slate-900">${notif.title}</span>`,
        html: `<p class="text-xs font-medium text-slate-600 leading-relaxed">${notif.message}</p>`,
        icon: 'info',
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#0b4087',
        customClass: {
            popup: 'rounded-2xl border border-slate-100 shadow-xl',
            confirmButton: 'rounded-xl text-[10px] font-bold uppercase px-6 py-2.5 tracking-wider'
        }
    });
};

/**
 * OTORITAS KAS & REKENING KOMANDAN
 */
const canAccessCash = computed(() => {
    return isAdmin.value || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom' || user.value.name === 'Suma Nurhasanah';
});

const canAccessCommanderAccount = computed(() => {
    return isAdmin.value || user.value.name === 'Suma Nurhasanah' || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

// STATE RADAR UTAMA UNTUK MEGA MENU (HOVER RESPONSIVE)
const activeMegaMenu = ref(null);

const setMegaMenu = (menuName) => {
    activeMegaMenu.value = menuName;
};

const clearMegaMenu = () => {
    activeMegaMenu.value = null;
};

/**
 * Logika Deteksi Mobile
 */
const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth < 1024 || window.location.hostname.startsWith('m.');
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    window.addEventListener('keydown', handleKeyDown); // Pasang detektor pintasan Ctrl + K
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    window.removeEventListener('keydown', handleKeyDown); // Cabut detektor saat layout ditutup
});
</script>

<template>
    <div class="min-h-screen bg-[#F4F6FA] font-sans antialiased text-left select-none text-slate-700">
        
        <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-50 shadow-[0_2px_12px_rgba(0,0,0,0.015)]">
            
            <div class="max-w-[1440px] mx-auto px-6 sm:px-8 h-16 flex items-center justify-between gap-4">
                
                <div class="flex items-center gap-4 shrink-0">
                    <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden text-left" @mouseenter="clearMegaMenu">
                        <ApplicationLogo class="block h-8 w-auto shrink-0 text-slate-900" />
                        <div class="flex flex-col whitespace-nowrap border-l border-slate-200 ps-3">
                            <h1 class="text-base font-black text-slate-800 uppercase tracking-tight leading-none">
                                SINDEN<span class="text-blue-600">.</span>
                            </h1>
                            <span class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mt-1">{{ agencyName }}</span>
                        </div>
                    </Link>
                </div>

                <div v-if="!isMobile" class="flex-1 max-w-md relative flex items-center">
                    <div class="absolute left-3.5 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" 
                           ref="searchInputRef"
                           v-model="searchQuery"
                           @input="handleSearchInput"
                           placeholder="Cari data log, nama personel, atau instansi..." 
                           class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-10 pr-12 py-1.5 text-xs font-semibold placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all" />
                    <span class="absolute right-3 bg-white border border-slate-200 rounded px-1.5 py-0.5 text-[9px] font-mono text-slate-400 font-bold shadow-sm">Ctrl K</span>
                </div>

                <div class="flex items-center gap-4 shrink-0">
                    <button @click="isNotifOpen = !isNotifOpen" class="p-2 text-slate-400 hover:text-slate-800 hover:bg-slate-50 rounded-lg transition relative">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <div v-if="notifications.length > 0" class="absolute top-1.5 right-1.5 h-1.5 w-1.5 bg-blue-600 rounded-full"></div>
                    </button>

                    <Dropdown align="right" width="64">
                        <template #trigger>
                            <button class="flex items-center gap-3 p-1 hover:bg-slate-50 rounded-xl transition group">
                                <div class="h-8 w-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-mono font-bold text-xs shrink-0 uppercase shadow-sm">
                                    {{ user.name.charAt(0) }}
                                </div>
                                <div class="flex flex-col text-left hidden sm:flex max-w-[150px]">
                                    <span class="text-[11px] font-bold text-slate-800 truncate leading-none">{{ user.name }}</span>
                                    <span class="text-[9px] font-semibold text-slate-400 tracking-wider mt-1 truncate">NRP. {{ user.nrp || '--------' }}</span>
                                </div>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')"> Edit Profil </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button" class="text-rose-600 font-bold text-[10px] uppercase"> 
                                Keluar Sistem 
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </div>

            <div class="w-full border-t border-slate-100 bg-white overflow-x-auto custom-scrollbar" @mouseleave="clearMegaMenu">
                <div class="max-w-[1440px] mx-auto px-6 sm:px-8 h-12 flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    
                    <Link :href="route('dashboard')" @mouseenter="clearMegaMenu"
                          :class="route().current('dashboard') ? 'text-[#0B4087] border-b-2 border-[#0B4087]' : 'hover:text-slate-900 border-b-2 border-transparent'"
                          class="px-3.5 h-12 flex items-center gap-2 transition">
                        <div class="p-1 bg-slate-50 text-slate-600 rounded border border-slate-200/60 shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        </div>
                        <span>Portal Saya</span>
                    </Link>

                    <div class="relative h-12 flex items-center" @mouseenter="setMegaMenu('surat')">
                        <button :class="route().current('letter-logs.*') || route().current('letters.*') || route().current('categories.*') ? 'text-[#0B4087] border-b-2 border-[#0B4087]' : 'hover:text-slate-900 border-b-2 border-transparent'"
                                class="px-3.5 h-12 flex items-center gap-2 focus:outline-none transition">
                            <div class="p-1 bg-amber-50 text-amber-600 rounded border border-amber-200/40 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M10 14h4" /></svg>
                            </div>
                            <span>Administrasi Surat</span>
                            <svg class="w-2.5 h-3 transition-transform duration-200" :class="activeMegaMenu === 'surat' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    <div class="relative h-12 flex items-center" @mouseenter="setMegaMenu('validation')">
                        <button :class="route().current('signature.*') || route().current('stamp.*') ? 'text-[#0B4087] border-b-2 border-[#0B4087]' : 'hover:text-slate-900 border-b-2 border-transparent'"
                                class="px-3.5 h-12 flex items-center gap-2 focus:outline-none transition">
                            <div class="p-1 bg-purple-50 text-purple-600 rounded border border-purple-200/40 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </div>
                            <span>Validasi & TTE</span>
                            <svg class="w-2.5 h-3 transition-transform duration-200" :class="activeMegaMenu === 'validation' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    <div class="relative h-12 flex items-center" @mouseenter="setMegaMenu('finance')">
                        <button :class="route().current('backup.*') || route().current('cash.*') || route().current('commander.*') ? 'text-[#0B4087] border-b-2 border-[#0B4087]' : 'hover:text-slate-900 border-b-2 border-transparent'"
                                class="px-3.5 h-12 flex items-center gap-2 focus:outline-none transition">
                            <div class="p-1 bg-emerald-50 text-emerald-600 rounded border border-emerald-200/40 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <span>Logistik & Finansial</span>
                            <svg class="w-2.5 h-3 transition-transform duration-200" :class="activeMegaMenu === 'finance' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    <div class="relative h-12 flex items-center" @mouseenter="setMegaMenu('security')">
                        <button :class="route().current('soldier-violations.*') || route().current('activities.*') ? 'text-[#0B4087] border-b-2 border-[#0B4087]' : 'hover:text-slate-900 border-b-2 border-transparent'"
                                class="px-3.5 h-12 flex items-center gap-2 focus:outline-none transition">
                            <div class="p-1 bg-rose-50 text-rose-600 rounded border border-rose-200/40 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            </div>
                            <span>Sektor Pengamanan</span>
                            <svg class="w-2.5 h-3 transition-transform duration-200" :class="activeMegaMenu === 'security' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    <div v-if="isAdmin" class="relative h-12 flex items-center pl-4 border-l border-slate-200" @mouseenter="setMegaMenu('access')">
                        <button :class="route().current('users.*') || route().current('admin.pess.*') || route().current('settings.*') || route().current('visitor-logs.*') || route().current('audit-logs.*') || route().current('birthday.*') ? 'text-[#0B4087]' : 'text-slate-500'"
                                class="h-12 flex items-center gap-2 focus:outline-none hover:text-slate-900 transition">
                            <div class="p-1 bg-indigo-50 text-indigo-600 rounded border border-indigo-200/40 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            </div>
                            <span>Manajemen Akses</span>
                            <svg class="w-2.5 h-3 transition-transform duration-200" :class="activeMegaMenu === 'access' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>
                </div>

                <div v-if="activeMegaMenu" 
                     class="absolute left-0 w-full bg-white border-b border-slate-200 shadow-2xl z-40 text-left normal-case tracking-normal animate-megaMenuSlide"
                     @mouseenter="setMegaMenu(activeMegaMenu)"
                     @mouseleave="clearMegaMenu">
                    
                    <div class="max-w-[1440px] mx-auto px-12 py-8 grid grid-cols-4 gap-10">
                        
                        <template v-if="activeMegaMenu === 'surat'">
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Penomoran & Registrasi</h4>
                                <Link :href="route('letter-logs.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Buku Nomor Keluar</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Pencatatan taktis alokasi kode registrasi surat resmi.</span>
                                </Link>
                            </div>
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Manajemen Berkas</h4>
                                <Link :href="route('letters.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Arsip Dokumen Mako</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Gudang biner penyimpanan arsip surat internal.</span>
                                </Link>
                                <Link v-if="isAdmin" :href="route('categories.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Klasifikasi Kategori</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Pengaturan sektor indeks kode persuratan intelijen.</span>
                                </Link>
                            </div>
                        </template>

                        <template v-if="activeMegaMenu === 'validation'">
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Otoritas Validasi</h4>
                                <Link :href="route('signature.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Konfirmasi Pendaftaran (TTD)</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Pintu persetujuan penempelan tanda tangan Komandan.</span>
                                </Link>
                            </div>
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Stempel Digital</h4>
                                <Link :href="route('stamp.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Monitoring Log Perkara</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Eksekusi validasi cap stempel resmi Detasemen.</span>
                                </Link>
                            </div>
                        </template>

                        <template v-if="activeMegaMenu === 'finance'">
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Buku Besar</h4>
                                <Link :href="route('backup.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">PC File Backup Server</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Radar kendali berkas sinkronisasi pangkalan biner.</span>
                                </Link>
                            </div>
                            <div class="space-y-3.5" v-if="canAccessCash || canAccessCommanderAccount">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Kas & Anggaran</h4>
                                <Link v-if="canAccessCash" :href="route('cash.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-emerald-700 group-hover:text-emerald-900 transition-colors">Buku Kas Denintel</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Logistik keluar masuk anggaran operasional.</span>
                                </Link>
                                <Link v-if="canAccessCommanderAccount" :href="route('commander.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-amber-700 group-hover:text-amber-900 transition-colors">Rekening Komandan</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Monitoring barikade kas mutasi khusus Rekening Komandan.</span>
                                </Link>
                            </div>
                        </template>

                        <template v-if="activeMegaMenu === 'security'">
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Gakkum Prajurit</h4>
                                <Link :href="route('soldier-violations.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-rose-700 group-hover:text-rose-900 transition-colors">Berkas Perkara Kasus</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Pencatatan penegakan disiplin pelanggaran anggota.</span>
                                </Link>
                            </div>
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Intel Teritorial</h4>
                                <Link :href="route('activities.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Peta Rencana Operasi</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Monitoring giat peta teritorial aktivitas masyarakat.</span>
                                </Link>
                            </div>
                        </template>

                        <template v-if="activeMegaMenu === 'access'">
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Otoritas Lintas VPS</h4>
                                <Link :href="route('admin.pess.dashboard')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Antrean PESS Admin</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Verifikasi biner berkas digital Sinkodv.</span>
                                </Link>
                                <Link :href="route('birthday.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Ucapan Personel</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Konfigurasi otomatis radar ucapan HUT personel.</span>
                                </Link>
                            </div>
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Identitas & Keamanan</h4>
                                <Link :href="route('users.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Master Personnel (Akun)</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Manajemen hak akses portal akun anggota aktif.</span>
                                </Link>
                                <Link :href="route('visitor-logs.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Log Keamanan Radar</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Catatan log otentikasi pangkalan data pengunjung.</span>
                                </Link>
                            </div>
                            <div class="space-y-3.5">
                                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Sistem Inti</h4>
                                <Link :href="route('audit-logs.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Audit Otoritas Kam</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Audit jejak digital tindakan konfigurasional administrator.</span>
                                </Link>
                                <Link :href="route('settings.index')" @click="clearMegaMenu" class="group flex flex-col p-2 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-xs font-bold text-slate-800 group-hover:text-[#0B4087] transition-colors">Pengaturan Sistem</span>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5">Kalibrasi global hak cipta dan agensi mako.</span>
                                </Link>
                            </div>
                        </template>

                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-[1440px] mx-auto px-6 sm:px-8 py-8" @mouseenter="clearMegaMenu">
            
            <div v-if="$slots.header" class="mb-6">
                <slot name="header" />
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_4px_24px_rgba(0,0,0,0.01)] min-h-[72vh] p-6 md:p-8">
                <slot />
            </div>

            <footer class="mt-12 py-6 text-center border-t border-slate-200/60">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em]">
                    &copy; {{ new Date().getFullYear() }} {{ settings.copyright || agencyName }} — DENINTEL KODAERAL V
                </p>
            </footer>
        </main>

        <div v-if="isNotifOpen" @click.self="isNotifOpen = false" class="fixed inset-0 z-[100] bg-black/5 flex justify-end p-4 pt-20">
            <div class="bg-white w-80 h-fit max-h-[400px] rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
                <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Notifikasi Masuk</h3>
                    <button @click="isNotifOpen = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">&times;</button>
                </div>
                <div class="overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                    <div v-for="n in notifications" :key="n.id" @click="showNotifDetail(n)" class="p-4 hover:bg-slate-50/50 cursor-pointer transition flex gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-slate-900 uppercase truncate text-left">{{ n.title }}</p>
                            <p class="text-[9px] text-slate-500 font-medium truncate mt-0.5 text-left">{{ n.message }}</p>
                            <p class="text-[8px] text-slate-400 font-semibold uppercase mt-1 tracking-wider text-left">{{ n.time }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 0px;
    height: 0px;
}
@keyframes megaMenuSlide {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-megaMenuSlide {
    animation: megaMenuSlide 0.16s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>