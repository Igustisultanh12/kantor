<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Swal from 'sweetalert2';

const page = usePage();

const settings = computed(() => page.props.settings || {});
const agencyName = computed(() => settings.value.agency_name || 'DENINTEL KODAERAL V');
const appLogo = computed(() => {
    const logo = settings.value.agency_logo || settings.value.logo || settings.value.logo_tni;
    if (!logo) return '/images/logo.png';
    return (logo.startsWith('http') || logo.startsWith('/storage') || logo.startsWith('/images')) ? logo : '/storage/' + logo;
});
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const user = computed(() => page.props.auth.user);

// Theme & Language Engine
const isDarkMode = ref(true);
const currentLang = ref('id');
const isLangMenuOpen = ref(false);
const isProfileMenuOpen = ref(false);

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value;
};

const setLanguage = (lang) => {
    currentLang.value = lang;
    isLangMenuOpen.value = false;
};

// Global Search (Ctrl + K)
const searchQuery = ref('');
const searchInputRef = ref(null);
const searchInputMobileRef = ref(null);

const handleSearchInput = () => {
    const searchEvent = new CustomEvent('sinden-global-search', {
        detail: { query: searchQuery.value }
    });
    window.dispatchEvent(searchEvent);
};

const handleKeyDown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault();
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        } else if (searchInputMobileRef.value) {
            searchInputMobileRef.value.focus();
        }
    }
};

// Sistem Notifikasi In-App Bell
const isNotifOpen = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const isLoadingNotifs = ref(false);

const fetchNotifications = async () => {
    try {
        isLoadingNotifs.value = true;
        const res = await fetch(route('notifications.api'));
        if (res.ok) {
            const data = await res.json();
            notifications.value = data.notifications || [];
            unreadCount.value = data.unreadCount || 0;
        }
    } catch (e) {
        console.error("Gagal mengambil notifikasi sistem:", e);
    } finally {
        isLoadingNotifs.value = false;
    }
};

const formatTimeAgo = (dateStr) => {
    if (!dateStr) return 'Baru saja';
    const date = new Date(dateStr);
    const now = new Date();
    const diffSec = Math.floor((now - date) / 1000);
    if (diffSec < 60) return 'Baru saja';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin}m yang lalu`;
    const diffHour = Math.floor(diffMin / 60);
    if (diffHour < 24) return `${diffHour}j yang lalu`;
    const diffDay = Math.floor(diffHour / 24);
    return `${diffDay}hr yang lalu`;
};

const handleNotifClick = async (notif) => {
    try {
        await fetch(route('notifications.read', notif.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': page.props.csrf_token,
                'Accept': 'application/json'
            }
        });
        notif.is_read = true;
        if (unreadCount.value > 0) unreadCount.value--;
    } catch (e) {}

    isNotifOpen.value = false;

    if (notif.link) {
        router.visit(notif.link);
    } else {
        Swal.fire({
            title: `<span class="uppercase font-bold text-sm tracking-wider text-slate-900">${notif.title}</span>`,
            html: `<p class="text-xs font-medium text-slate-600 leading-relaxed">${notif.message}</p>`,
            icon: notif.type || 'info',
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#f59e0b',
        });
    }
};

const markAllRead = async () => {
    try {
        await fetch(route('notifications.read-all'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': page.props.csrf_token,
                'Accept': 'application/json'
            }
        });
        notifications.value.forEach(n => n.is_read = true);
        unreadCount.value = 0;
    } catch (e) {}
};

// Otoritas Modul Khusus
const canAccessCash = computed(() => {
    return isAdmin.value || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom' || user.value.name === 'Suma Nurhasanah';
});

const canAccessCommanderAccount = computed(() => {
    return isAdmin.value || user.value.name === 'Suma Nurhasanah' || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

const canAccessMitra = computed(() => {
    return isAdmin.value || Boolean(user.value.can_access_mitra) || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

const isDanUnitTeknis = computed(() => {
    return user.value.role === 'danunitteknis' || user.value.role === 'DAN UNIT TEKNIS' || user.value.role === 'dan unit teknis';
});

const canAccessTechnicalCash = computed(() => {
    return isAdmin.value || isDanUnitTeknis.value || Boolean(user.value.can_access_technical_cash) || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

// CORETAX MEGA MENU SYSTEM
// activeMegaMenu: null | 'portal' | 'arsip' | 'skhpp' | 'tte' | 'kas' | 'radar' | 'backup' | 'admin'
const activeMegaMenu = ref(null);

const toggleMegaMenu = (menuKey) => {
    if (activeMegaMenu.value === menuKey) {
        activeMegaMenu.value = null;
    } else {
        activeMegaMenu.value = menuKey;
    }
};

const closeMegaMenu = () => {
    activeMegaMenu.value = null;
};

// Mobile Drawer & Navigation
const isMobileDrawerOpen = ref(false);
const activeMobileTab = ref('portal');

let notifTimer = null;

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    fetchNotifications();
    notifTimer = setInterval(fetchNotifications, 5000);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    if (notifTimer) clearInterval(notifTimer);
});
</script>

<template>
    <div 
        class="min-h-screen font-sans antialiased transition-colors duration-300 flex flex-col justify-between"
        :class="isDarkMode ? 'bg-[#0B0F19] text-slate-100' : 'bg-[#F4F6F9] text-slate-800'"
    >
        <!-- ========================================================================= -->
        <!-- HEADER UTAMA (CORETAX DJP DESKTOP & MOBILE HEADER)                        -->
        <!-- ========================================================================= -->
        <header 
            class="sticky top-0 z-40 w-full border-b transition-colors duration-300"
            :class="isDarkMode ? 'border-white/10 bg-[#0B0F19]' : 'border-slate-200 bg-white shadow-xs'"
        >
            <!-- TOP BAR: Logo, Search Ctrl+K, Notification, Theme, Language, Profile -->
            <div class="px-4 sm:px-8 py-3 flex items-center justify-between gap-4">
                
                <!-- Left: Logo & Brand -->
                <div class="flex items-center gap-3 shrink-0">
                    <Link :href="route('dashboard')" class="flex items-center gap-2.5 group">
                        <img v-if="appLogo" :src="appLogo" class="h-9 sm:h-10 object-contain drop-shadow-md" alt="Logo SINDEN" />
                        <div class="flex flex-col">
                            <span class="text-xl sm:text-2xl font-black tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                                SIN<span class="text-[#FFC107]">DEN</span>
                            </span>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 hidden sm:block">
                                Detasemen Intelijen Kodaeral V
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Middle: Global Search Input with Ctrl+K shortcut (Desktop) -->
                <div class="hidden md:flex flex-1 max-w-md mx-4">
                    <div class="relative w-full">
                        <span class="absolute left-3.5 top-2.5 text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input 
                            ref="searchInputRef"
                            type="text" 
                            v-model="searchQuery"
                            @input="handleSearchInput"
                            placeholder="Cari layanan, surat, SKHPP, personel..." 
                            class="w-full pl-10 pr-16 py-2 rounded-xl border text-xs transition-all focus:outline-hidden focus:ring-2 focus:ring-amber-500/50"
                            :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white placeholder-slate-500' : 'bg-slate-100 border-slate-200 text-slate-900 placeholder-slate-400'"
                        />
                        <span class="absolute right-2.5 top-2 px-1.5 py-0.5 rounded border text-[10px] font-mono font-bold text-slate-400"
                            :class="isDarkMode ? 'border-white/10 bg-white/5' : 'border-slate-300 bg-white'"
                        >
                            Ctrl K
                        </span>
                    </div>
                </div>

                <!-- Right: Action Controls (Theme, Notif, Lang, Profile) -->
                <div class="flex items-center gap-2.5 sm:gap-3 shrink-0">
                    
                    <!-- Theme Toggle (Sun/Moon) -->
                    <button 
                        @click="toggleTheme" 
                        type="button"
                        class="p-2 rounded-xl transition-all cursor-pointer flex items-center justify-center"
                        :class="isDarkMode ? 'bg-white/10 text-amber-400 hover:bg-white/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        :title="isDarkMode ? 'Mode Terang' : 'Mode Gelap'"
                    >
                        <svg v-if="isDarkMode" class="w-4 h-4 sm:w-5 sm:h-5 fill-current" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41s-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41s-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/></svg>
                        <svg v-else class="w-4 h-4 sm:w-5 sm:h-5 fill-current" viewBox="0 0 24 24"><path d="M12.3 2a10 10 0 0 0-1.9 20 10 10 0 0 0 9.8-7.7 1 1 0 0 0-1.2-1.2A8 8 0 0 1 10.9 4a1 1 0 0 0-1.2-1.2 10 10 0 0 0 2.6-.8z"/></svg>
                    </button>

                    <!-- In-App Notification Bell -->
                    <div class="relative">
                        <button 
                            @click="isNotifOpen = !isNotifOpen"
                            type="button"
                            class="p-2 rounded-xl transition-all cursor-pointer relative flex items-center justify-center"
                            :class="isDarkMode ? 'bg-white/10 text-slate-200 hover:bg-white/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        >
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <!-- Badge Counter -->
                            <span 
                                v-if="unreadCount > 0" 
                                class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black px-1.5 py-0.2 rounded-full ring-2"
                                :class="isDarkMode ? 'ring-[#0B0F19]' : 'ring-white'"
                            >
                                {{ unreadCount > 99 ? '99+' : unreadCount }}
                            </span>
                        </button>

                        <!-- Notification Dropdown Menu -->
                        <div 
                            v-if="isNotifOpen" 
                            class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl shadow-2xl border py-2 z-50 transition-all"
                            :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white' : 'bg-white border-slate-200 text-slate-900'"
                        >
                            <div class="px-4 py-2 flex items-center justify-between border-b" :class="isDarkMode ? 'border-white/10' : 'border-slate-100'">
                                <span class="text-xs font-bold uppercase tracking-wider">Notifikasi Sistem</span>
                                <button @click="markAllRead" class="text-[11px] font-bold text-amber-400 hover:underline">Tandai semua dibaca</button>
                            </div>
                            <div class="max-h-72 overflow-y-auto divide-y" :class="isDarkMode ? 'divide-white/5' : 'divide-slate-100'">
                                <div 
                                    v-for="notif in notifications" 
                                    :key="notif.id"
                                    @click="handleNotifClick(notif)"
                                    class="p-3.5 hover:bg-amber-500/10 cursor-pointer transition flex items-start gap-3"
                                    :class="!notif.is_read ? (isDarkMode ? 'bg-white/5' : 'bg-amber-50/50') : ''"
                                >
                                    <div class="w-2 h-2 rounded-full mt-1.5 shrink-0" :class="!notif.is_read ? 'bg-amber-500' : 'bg-transparent'"></div>
                                    <div class="space-y-0.5 flex-1">
                                        <p class="text-xs font-bold leading-snug">{{ notif.title }}</p>
                                        <p class="text-[11px] text-slate-400 line-clamp-2">{{ notif.message }}</p>
                                        <span class="text-[10px] text-slate-500 block pt-1">{{ formatTimeAgo(notif.created_at) }}</span>
                                    </div>
                                </div>
                                <div v-if="notifications.length === 0" class="p-6 text-center text-xs text-slate-500">
                                    Tidak ada notifikasi saat ini.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Language Switcher -->
                    <div class="relative hidden sm:block">
                        <button 
                            @click="isLangMenuOpen = !isLangMenuOpen"
                            type="button" 
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                            :class="isDarkMode ? 'border-white/15 bg-white/5 hover:bg-white/10 text-white' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-800 shadow-2xs'"
                        >
                            <span class="w-4 h-3 rounded-xs flex items-center justify-center text-[10px] overflow-hidden border border-black/20 font-bold">
                                {{ currentLang === 'id' ? 'ðŸ‡®ðŸ‡©' : 'ðŸ‡ºðŸ‡¸' }}
                            </span>
                            <span>{{ currentLang === 'id' ? 'ID' : 'EN' }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div 
                            v-if="isLangMenuOpen" 
                            class="absolute right-0 mt-2 w-28 rounded-xl shadow-2xl border py-1 z-50"
                            :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white' : 'bg-white border-slate-200 text-slate-800'"
                        >
                            <button @click="setLanguage('id')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-amber-500/10 hover:text-amber-400 transition">ðŸ‡®ðŸ‡© ID</button>
                            <button @click="setLanguage('en')" class="w-full text-left px-3 py-1.5 text-xs hover:bg-amber-500/10 hover:text-amber-400 transition">ðŸ‡ºðŸ‡¸ EN</button>
                        </div>
                    </div>

                    <!-- User Profile Pill (CORETAX Header User Badge) -->
                    <div class="relative">
                        <button 
                            @click="isProfileMenuOpen = !isProfileMenuOpen"
                            type="button"
                            class="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-2xl border transition-all cursor-pointer"
                            :class="isDarkMode ? 'border-white/10 bg-[#121827] hover:border-amber-500/40 text-white' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-900 shadow-xs'"
                        >
                            <div class="w-7 h-7 rounded-xl bg-amber-500 text-slate-950 font-black text-xs flex items-center justify-center uppercase shrink-0">
                                {{ user.name ? user.name.charAt(0) : 'U' }}
                            </div>
                            <div class="hidden xl:flex flex-col text-left">
                                <span class="text-[11px] font-bold text-slate-400 tracking-wide truncate max-w-[140px]">
                                    {{ user.pangkat || 'TNI AL' }} {{ user.nrp ? `(${user.nrp})` : '' }}
                                </span>
                                <span class="text-xs font-black uppercase text-amber-400 truncate max-w-[140px]">
                                    {{ user.name }}
                                </span>
                            </div>
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <!-- Profile Dropdown -->
                        <div 
                            v-if="isProfileMenuOpen" 
                            class="absolute right-0 mt-2 w-56 rounded-2xl shadow-2xl border py-2 z-50 transition-all text-xs"
                            :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white' : 'bg-white border-slate-200 text-slate-900'"
                        >
                            <div class="px-4 py-2 border-b" :class="isDarkMode ? 'border-white/10' : 'border-slate-100'">
                                <p class="font-bold truncate">{{ user.name }}</p>
                                <p class="text-[10px] text-slate-400 font-mono">{{ user.email }}</p>
                            </div>
                            <Link :href="route('profile.edit')" @click="isProfileMenuOpen = false" class="block px-4 py-2 hover:bg-amber-500/10 hover:text-amber-400 transition">
                                âš™ï¸ Pengaturan Profil
                            </Link>
                            <Link :href="route('logout')" method="post" as="button" class="w-full text-left px-4 py-2 text-red-400 hover:bg-red-500/10 transition font-bold">
                                ðŸšª Keluar Sistem (Logout)
                            </Link>
                        </div>
                    </div>

                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- DESKTOP MEGA MENU NAVIGATION BAR (8 PILAR LAYANAN PERSIS VIDEO 2)         -->
            <!-- ========================================================================= -->
            <div class="hidden lg:block border-t" :class="isDarkMode ? 'border-white/5 bg-[#0B0F19]' : 'border-slate-200 bg-white'">
                <div class="px-6 flex items-center gap-1 overflow-x-auto no-scrollbar">
                    
                    <!-- 1. Portal Saya -->
                    <button 
                        @click="toggleMegaMenu('portal')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'portal' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>ðŸ‘¤</span>
                        <span>Portal Saya</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'portal' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 2. E-Arsip / Naskah -->
                    <button 
                        @click="toggleMegaMenu('arsip')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'arsip' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>ðŸ“</span>
                        <span>e-Arsip & Surat</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'arsip' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 3. SKHPP Online -->
                    <button 
                        @click="toggleMegaMenu('skhpp')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'skhpp' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>ðŸ›¡ï¸</span>
                        <span>SKHPP Online</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'skhpp' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 4. TTE Digital -->
                    <button 
                        @click="toggleMegaMenu('tte')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'tte' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>âœï¸</span>
                        <span>TTE Digital</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'tte' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 5. Buku Kas -->
                    <button 
                        v-if="canAccessCash || canAccessTechnicalCash"
                        @click="toggleMegaMenu('kas')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'kas' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>ðŸ’°</span>
                        <span>Buku Kas</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'kas' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 6. Radar & Pelanggaran -->
                    <button 
                        @click="toggleMegaMenu('radar')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'radar' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>ðŸ“¡</span>
                        <span>Radar & Disiplin</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'radar' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 7. PC Backup -->
                    <button 
                        @click="toggleMegaMenu('backup')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'backup' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>ðŸ’¾</span>
                        <span>PC Backup</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'backup' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <!-- 8. Manajemen Sistem (Admin) -->
                    <button 
                        v-if="isAdmin"
                        @click="toggleMegaMenu('admin')"
                        type="button" 
                        class="flex items-center gap-2 px-4 py-2.5 rounded-t-xl font-extrabold text-xs uppercase tracking-wider transition-all cursor-pointer border-b-2"
                        :class="activeMegaMenu === 'admin' 
                            ? 'border-amber-400 text-amber-400 bg-white/5' 
                            : 'border-transparent text-slate-300 hover:text-white hover:bg-white/5'"
                    >
                        <span>âš™ï¸</span>
                        <span>Manajemen Sistem</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeMegaMenu === 'admin' ? 'rotate-180 text-amber-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- FULL WIDTH MEGA MENU DROPDOWN PANEL (PERSIS VIDEO 2 CORETAX)              -->
            <!-- ========================================================================= -->
            <div 
                v-if="activeMegaMenu" 
                class="hidden lg:block w-full border-t border-b shadow-2xl transition-all duration-200 z-50 animate-slide-down"
                :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white' : 'bg-white border-slate-200 text-slate-900'"
            >
                <div class="max-w-7xl mx-auto px-8 py-6">
                    
                    <!-- MENU 1: PORTAL SAYA -->
                    <div v-if="activeMegaMenu === 'portal'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Profil & Identitas</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('profile.edit')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Profil Saya & Password</Link></li>
                                <li><Link :href="route('dashboard')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Dashboard Personal</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Aktivitas & Log Saya</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('activities.map')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Riwayat Radar GPS Saya</Link></li>
                                <li><Link :href="route('signature.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Pengajuan Berkas TTE Saya</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Mitra & Otoritas</span>
                            <ul class="space-y-2 text-xs">
                                <li v-if="canAccessMitra"><Link :href="route('mitra.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Daftar Mitra Kerja TNI AL</Link></li>
                                <li><Link :href="route('skhpp.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Permohonan SKHPP Saya</Link></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 2: E-ARSIP & SURAT -->
                    <div v-if="activeMegaMenu === 'arsip'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Agenda Surat Keluar</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('letter-logs.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Agenda & Booking Nomor Surat</Link></li>
                                <li><Link :href="route('letter-logs.index', { status: 'booked' })" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Nomor Terbooking</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Draf & Penerbitan Naskah</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('letters.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Daftar Draf & Arsip Surat</Link></li>
                                <li><Link :href="route('letters.create')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Buat Surat Baru (+ Template)</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Klasifikasi & Tata Naskah</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('categories.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Klasifikasi Surat Dinas</Link></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 3: SKHPP ONLINE -->
                    <div v-if="activeMegaMenu === 'skhpp'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Layanan SKHPP Militer / PNS</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('skhpp.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Daftar Pengajuan SKHPP</Link></li>
                                <li><Link :href="route('skhpp.create', { kategori: 'militer' })" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Buat SKHPP Militer / PNS (+ TTE)</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Layanan SKHPP Mitra Perusahaan</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('skhpp.create', { kategori: 'perusahaan' })" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Buat SKHPP Mitra Kerja Perusahaan</Link></li>
                                <li><Link :href="route('mitra.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Database Perusahaan Rekanan</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Verifikasi & Validitas</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('skhpp.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Lacak Status Pengesahan Komandan</Link></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 4: TTE DIGITAL -->
                    <div v-if="activeMegaMenu === 'tte'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Pengesahan Naskah Dinas</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('signature.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Daftar Pengajuan TTE Berkas</Link></li>
                                <li><Link :href="route('signature.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Unggah Berkas PDF untuk TTE</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Pengaturan Cap & TTD</span>
                            <ul class="space-y-2 text-xs" v-if="isAdmin">
                                <li><Link :href="route('settings.stamp')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Cap Kedinasan & TTD Komandan</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Otentikasi Publik</span>
                            <ul class="space-y-2 text-xs">
                                <li><span class="text-slate-400 block py-1">QR Code terhubung ke /verify-doc/{code}</span></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 5: BUKU KAS -->
                    <div v-if="activeMegaMenu === 'kas'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3" v-if="canAccessCash">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Buku Kas Umum</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('cash.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Buku Kas Satuan</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3" v-if="canAccessTechnicalCash">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Kas Unit Teknis</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('technical-cash.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Buku Kas Unit Teknis</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3" v-if="canAccessCommanderAccount">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Rekening Komandan</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('commander.account')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Laporan Rekening Khusus</Link></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 6: RADAR & PELANGGARAN -->
                    <div v-if="activeMegaMenu === 'radar'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Radar & Tracking Intelijen</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('activities.map')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Peta GPS & Radar Lapangan</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Disiplin & Hukum</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('violations.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-red-400">Catatan Pelanggaran Personel</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3" v-if="isAdmin">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Radar Satuan</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('admin.birthday.radar')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Radar Ulang Tahun Personel</Link></li>
                                <li><Link :href="route('admin.pess')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Evaluasi Kinerja (PESS)</Link></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 7: PC BACKUP -->
                    <div v-if="activeMegaMenu === 'backup'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Penyimpanan Komputer</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('backup.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Daftar PC & Cloud Backup</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Radar Jaringan</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('backup.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Radar IP Kedinasan</Link></li>
                            </ul>
                        </div>
                    </div>

                    <!-- MENU 8: MANAJEMEN SISTEM (ADMIN) -->
                    <div v-if="activeMegaMenu === 'admin'" class="grid grid-cols-3 gap-8">
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Otoritas Personel</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('users.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1 font-bold text-amber-300">Data Personel & Token Aktivasi</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Audit & Keamanan</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('audit-logs.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Audit Log Aktivitas Sistem</Link></li>
                            </ul>
                        </div>
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-amber-400 block border-b pb-1" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">Konfigurasi</span>
                            <ul class="space-y-2 text-xs">
                                <li><Link :href="route('settings.index')" @click="closeMegaMenu" class="hover:text-amber-400 transition block py-1">Pengaturan Aplikasi SINDEN</Link></li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- KONTEN HALAMAN DINAMIS (SLOT UTAMA)                                       -->
        <!-- ========================================================================= -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-8 py-6 pb-28 lg:pb-8">
            <slot />
        </main>

        <!-- ========================================================================= -->
        <!-- MOBILE BOTTOM FLOATING NAVIGATION DOCK (PERSIS VIDEO 1 SMARTPHONE)        -->
        <!-- ========================================================================= -->
        <nav 
            class="lg:hidden fixed bottom-4 left-4 right-4 z-40 rounded-2xl border p-2 flex items-center justify-around shadow-2xl backdrop-blur-xl transition-all"
            :class="isDarkMode ? 'bg-[#121827]/95 border-white/15 text-white' : 'bg-white/95 border-slate-200 text-slate-800'"
        >
            <!-- 1. Portal Saya -->
            <Link 
                :href="route('dashboard')"
                class="flex flex-col items-center gap-1 p-2 rounded-xl text-center transition"
                :class="route().current('dashboard') ? 'text-amber-400 font-bold' : 'text-slate-400'"
            >
                <span class="text-base">ðŸ‘¤</span>
                <span class="text-[9px] font-bold">Portal</span>
            </Link>

            <!-- 2. E-Arsip -->
            <Link 
                :href="route('letter-logs.index')"
                class="flex flex-col items-center gap-1 p-2 rounded-xl text-center transition"
                :class="route().current('letter-logs.*') || route().current('letters.*') ? 'text-amber-400 font-bold' : 'text-slate-400'"
            >
                <span class="text-base">ðŸ“</span>
                <span class="text-[9px] font-bold">e-Arsip</span>
            </Link>

            <!-- 3. SKHPP -->
            <Link 
                :href="route('skhpp.index')"
                class="flex flex-col items-center gap-1 p-2 rounded-xl text-center transition"
                :class="route().current('skhpp.*') ? 'text-amber-400 font-bold' : 'text-slate-400'"
            >
                <span class="text-base">ðŸ›¡ï¸</span>
                <span class="text-[9px] font-bold">SKHPP</span>
            </Link>

            <!-- 4. TTE -->
            <Link 
                :href="route('signature.index')"
                class="flex flex-col items-center gap-1 p-2 rounded-xl text-center transition"
                :class="route().current('signature.*') ? 'text-amber-400 font-bold' : 'text-slate-400'"
            >
                <span class="text-base">âœï¸</span>
                <span class="text-[9px] font-bold">TTE</span>
            </Link>

            <!-- 5. Menu Lengkap Drawer Button -->
            <button 
                @click="isMobileDrawerOpen = true"
                type="button"
                class="flex flex-col items-center gap-1 p-2 rounded-xl text-center cursor-pointer text-slate-400 hover:text-amber-400"
            >
                <span class="text-base">â˜°</span>
                <span class="text-[9px] font-bold">Menu</span>
            </button>
        </nav>

        <!-- ========================================================================= -->
        <!-- MOBILE SLIDING DRAWER MENU LENGKAP                                        -->
        <!-- ========================================================================= -->
        <div v-if="isMobileDrawerOpen" class="fixed inset-0 z-50 lg:hidden flex flex-col justify-end">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-xs" @click="isMobileDrawerOpen = false"></div>
            
            <div 
                class="relative w-full max-h-[85vh] rounded-t-3xl border-t p-6 overflow-y-auto space-y-6 z-10 transition-all animate-slide-up"
                :class="isDarkMode ? 'bg-[#0B0F19] border-white/15 text-white' : 'bg-white border-slate-200 text-slate-900'"
            >
                <!-- Drawer Header -->
                <div class="flex items-center justify-between border-b pb-4" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'">
                    <div class="flex items-center gap-2">
                        <img v-if="appLogo" :src="appLogo" class="h-7 w-7 object-contain" alt="Logo" />
                        <span class="text-base font-black">Layanan SINDEN</span>
                    </div>
                    <button @click="isMobileDrawerOpen = false" class="p-1.5 rounded-full bg-white/10 text-slate-400 hover:text-white">âœ•</button>
                </div>

                <!-- Drawer Links -->
                <div class="grid grid-cols-2 gap-3 text-xs font-bold">
                    <Link :href="route('dashboard')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ‘¤</span> Portal Personal
                    </Link>
                    <Link :href="route('letter-logs.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ“</span> Agenda Surat
                    </Link>
                    <Link :href="route('letters.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ“</span> Buat Draf Surat
                    </Link>
                    <Link :href="route('skhpp.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ›¡ï¸</span> SKHPP Online
                    </Link>
                    <Link :href="route('signature.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>âœï¸</span> TTE Digital Berkas
                    </Link>
                    <Link v-if="canAccessCash" :href="route('cash.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ’°</span> Buku Kas Umum
                    </Link>
                    <Link v-if="canAccessTechnicalCash" :href="route('technical-cash.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ’µ</span> Kas Unit Teknis
                    </Link>
                    <Link :href="route('activities.map')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ“¡</span> Radar GPS Lapangan
                    </Link>
                    <Link :href="route('violations.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5 text-red-400" :class="isDarkMode ? 'bg-white/5 border-white/10' : 'bg-slate-50 border-slate-200'">
                        <span>âš–ï¸</span> Pelanggaran Disiplin
                    </Link>
                    <Link :href="route('backup.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ’¾</span> PC Cloud Backup
                    </Link>
                    <Link v-if="isAdmin" :href="route('users.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5 text-amber-400" :class="isDarkMode ? 'bg-white/5 border-white/10' : 'bg-slate-50 border-slate-200'">
                        <span>ðŸ‘¥</span> Manajemen Personel
                    </Link>
                    <Link v-if="isAdmin" :href="route('audit-logs.index')" @click="isMobileDrawerOpen = false" class="p-3.5 rounded-xl border flex items-center gap-2.5" :class="isDarkMode ? 'bg-white/5 border-white/10 text-white' : 'bg-slate-50 border-slate-200 text-slate-800'">
                        <span>ðŸ“‹</span> Audit Log Sistem
                    </Link>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- FOOTER KEDINASAN RESMI (PERSIS VIDEO 2 CORETAX DJP)                       -->
        <!-- ========================================================================= -->
        <footer 
            class="w-full border-t transition-colors duration-300 py-8 px-4 sm:px-8 text-xs hidden lg:block"
            :class="isDarkMode ? 'border-white/10 bg-[#0B0F19] text-slate-400' : 'border-slate-200 bg-white text-slate-600'"
        >
            <div class="max-w-7xl mx-auto grid grid-cols-4 gap-8">
                <!-- Col 1: Brand & Alamat -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <img v-if="appLogo" :src="appLogo" class="h-8 w-8 object-contain" alt="Logo" />
                        <span class="text-base font-black tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                            SIN<span class="text-[#FFC107]">DEN</span>
                        </span>
                    </div>
                    <p class="text-[11px] leading-relaxed">
                        Detasemen Intelijen Komando Daerah TNI Angkatan Laut V.<br>
                        Surabaya, Jawa Timur.
                    </p>
                </div>

                <!-- Col 2: Hubungi Kami -->
                <div class="space-y-2">
                    <span class="font-bold text-[11px] uppercase tracking-wider block" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Hubungi Kami</span>
                    <p class="text-[11px]">(031) SINDEN-INTEL</p>
                    <p class="text-[11px]">informasi@sisinden.my.id</p>
                </div>

                <!-- Col 3: Layanan Digital -->
                <div class="space-y-2">
                    <span class="font-bold text-[11px] uppercase tracking-wider block" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Layanan Digital</span>
                    <p class="text-[11px]">Sistem TTE Digital Resmi</p>
                    <p class="text-[11px]">Portal SKHPP Terintegrasi</p>
                </div>

                <!-- Col 4: Temukan Kami -->
                <div class="space-y-2">
                    <span class="font-bold text-[11px] uppercase tracking-wider block" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Detasemen Intelijen</span>
                    <p class="text-[11px]">Komando Daerah TNI AL V</p>
                    <p class="text-[10px] text-slate-500 pt-2">Â© {{ new Date().getFullYear() }} SINDEN. Seluruh hak cipta dilindungi.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-slide-down {
    animation: slideDown 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up {
    animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>