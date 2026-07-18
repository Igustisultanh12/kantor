<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue'; 
import Swal from 'sweetalert2'; // Import untuk SweetAlert

const page = usePage();
const isSidebarOpen = ref(true); 

/**
 * Data Pengaturan & Identitas Personel
 */
const settings = computed(() => page.props.settings || {});
const agencyName = computed(() => settings.value.agency_name || 'DENINTEL KODAERAL V');
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const user = computed(() => page.props.auth.user);

/**
 * SISTEM NOTIFIKASI TAKTIS
 */
const isNotifOpen = ref(false);
const notifications = ref([
    { id: 1, title: 'Ulang Birthday', message: 'Hari ini ada personel yang berulang tahun. Silakan cek Periksa pada menu ucapan', time: 'Baru saja', icon: '🎂' },
    { id: 2, title: 'Sistem SINDEN', message: 'Pangkalan data UcapanConfig berhasil disinkronisasi dengan jalur utama.', time: '1 Jam yang lalu', icon: '✅' },
]);

const showNotifDetail = (notif) => {
    isNotifOpen.value = false; // Tutup dropdown
    Swal.fire({
        title: `<span class="uppercase font-black text-sm tracking-widest text-indigo-950">${notif.title}</span>`,
        html: `<p class="text-xs font-bold italic text-slate-600 leading-relaxed">${notif.message}</p>`,
        icon: 'info',
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#4f46e5',
        customClass: {
            popup: 'rounded-[2.5rem] border-4 border-indigo-50',
            confirmButton: 'rounded-xl text-[10px] font-black uppercase px-8 py-3'
        }
    });
};

/**
 * OTORITAS KAS: Khusus Admin/Komandan dan Personel bernama "Suma"
 */
const canAccessCash = computed(() => {
    return isAdmin.value || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom' || user.value.name === 'Suma Nurhasanah';
});

/**
 * OTORITAS STRATEGIS REKENING KOMANDAN (HANYA ADMIN, SUMA, DAN KOMANDAN MURNI)
 */
const canAccessCommanderAccount = computed(() => {
    return isAdmin.value || user.value.role === 'Suma Nurhasanah' || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

/**
 * Logika Deteksi Mobile (Layar < 1024px atau Domain m.)
 */
const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth < 1024 || window.location.hostname.startsWith('m.');
    if (isMobile.value) {
        isSidebarOpen.value = false;
    } else {
        isSidebarOpen.value = true;
    }
};

/**
 * Menutup sidebar otomatis saat menu diklik
 */
const handleMenuClick = () => {
    if (window.innerWidth < 1024) {
        isSidebarOpen.value = false;
    }
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
});

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};
</script>

<template>
    <div class="flex min-h-screen bg-[#F8F9FD] font-sans antialiased text-left">
        
        <aside 
            v-if="!isMobile"
            :class="isSidebarOpen ? 'w-72' : 'w-24'" 
            class="hidden lg:flex flex-col bg-white border-r border-gray-100 transition-all duration-500 ease-in-out sticky top-0 h-screen z-50 shadow-[4px_0_24px_rgba(0,0,0,0.02)]"
        >
            <div class="h-20 flex items-center px-6 border-b border-gray-50 mb-6 shrink-0">
                <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden">
                    <ApplicationLogo class="block h-9 w-auto shrink-0" />
                    <div v-if="isSidebarOpen" class="flex flex-col whitespace-nowrap transition-opacity duration-300">
                        <span class="text-[11px] font-black text-indigo-950 uppercase tracking-tighter leading-none">{{ agencyName }}</span>
                        <span class="text-[8px] text-gray-400 font-bold uppercase tracking-widest mt-1">Intelligence Digital System</span>
                    </div>
                </Link>
            </div>

            <nav class="flex-1 px-4 space-y-2 overflow-y-auto custom-scrollbar pb-6 text-left">
                <p v-if="isSidebarOpen" class="px-4 text-[9px] font-black text-gray-300 uppercase tracking-[0.2em] mb-4">Main Operational</p>
                
                <Link :href="route('dashboard')" @click="handleMenuClick"
                      :class="route().current('dashboard') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">🏠</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Dashboard</span>
                </Link>

                <Link :href="route('letter-logs.index')" @click="handleMenuClick"
                      :class="route().current('letter-logs.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">📖</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Buku Nomor</span>
                </Link>

                <Link v-if="isAdmin" :href="route('categories.index')" @click="handleMenuClick"
                      :class="route().current('categories.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">🗂️</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Manajemen Kategori</span>
                </Link>

                <Link v-if="isAdmin" :href="route('birthday.index')" @click="handleMenuClick"
                      :class="route().current('birthday.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden border-l-4 border-indigo-200">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">🎂</div>
                    <div v-if="isSidebarOpen" class="flex flex-col text-left ms-4">
                        <span class="text-[11px] font-black uppercase whitespace-nowrap">Ucapan Personel</span>
                    </div>
                </Link>

                <Link :href="route('letters.index')" @click="handleMenuClick"
                      :class="route().current('letters.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">📑</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Arsip Surat</span>
                </Link>

                <Link :href="route('signature.index')" @click="handleMenuClick"
                      :class="route().current('signature.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">✍️</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Req TTD Komandan</span>
                </Link>
                
                <Link :href="route('stamp.index')" 
                      :active="route().current('stamp.index')"
                      :class="route().current('stamp.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">🛡️</div>
                    <div v-if="isSidebarOpen" class="flex flex-col text-left ms-4">
                        <span class="text-[11px] font-black uppercase">Otoritas Stempel</span>
                        <span class="text-[7px] font-bold opacity-60 uppercase">Eksekusi Validasi</span>
                    </div>
                </Link>

                <Link :href="route('backup.index')" @click="handleMenuClick"
                      :class="route().current('backup.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">📂</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">PC File Backup</span>
                </Link>

                <Link v-if="canAccessCash" :href="route('cash.index')" @click="handleMenuClick"
                      :class="route().current('cash.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 overflow-hidden border-l-4"
                      :style="route().current('cash.*') ? '' : 'border-color: #10b981'">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">💰</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Buku Kas Denintel</span>
                </Link>

                <Link v-if="canAccessCommanderAccount" :href="route('commander.index')" @click="handleMenuClick"
                      :class="route().current('commander.*') ? 'bg-red-600 text-white shadow-lg shadow-red-100' : 'text-gray-400 hover:bg-gray-50 hover:text-red-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 overflow-hidden border-l-4"
                      :style="route().current('commander.*') ? '' : 'border-color: #ef4444'">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">💳</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Rekening Komandan</span>
                </Link>

                <Link :href="route('soldier-violations.index')" 
                      @click="handleMenuClick"
                      :class="route().current('soldier-violations.*') ? 'bg-red-600 text-white shadow-lg shadow-red-100' : 'text-gray-400 hover:bg-gray-50 hover:text-red-600'"
                      class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                    <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">⚖️</div>
                    <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Kasus Prajurit</span>
                </Link>

                <div class="pt-4 mt-4 border-t border-gray-50 text-left">
                    <p v-if="isSidebarOpen" class="px-4 text-[9px] font-black text-gray-300 uppercase tracking-[0.2em] mb-4">Intelijen Teritorial</p>
                    
                    <Link :href="route('activities.index')" @click="handleMenuClick"
                          :class="route().current('activities.index') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                          class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                        <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">📍</div>
                        <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Peta Rencana</span>
                    </Link>
                </div>

                <div v-if="isAdmin" class="pt-6 text-left">
                    <p v-if="isSidebarOpen" class="px-4 text-[9px] font-black text-gray-300 uppercase tracking-[0.2em] mb-4">Administration</p>
                    
                    <Link :href="route('admin.pess.dashboard')" @click="handleMenuClick"
                          :class="route().current('admin.pess.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50 hover:text-indigo-600'"
                          class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 border-l-4 border-purple-500 overflow-hidden">
                        <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">🎖️</div>
                        <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Antrean PESS Admin</span>
                    </Link>

                    <Link :href="route('users.index')" @click="handleMenuClick"
                          :class="route().current('users.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50'"
                          class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 overflow-hidden">
                        <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">👤</div>
                        <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Personel Akun</span>
                    </Link>
                    
                     <Link :href="route('visitor-logs.index')" @click="handleMenuClick"
                          :class="route().current('visitor-logs.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50'"
                          class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 overflow-hidden">
                        <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">🛡️</div>
                        <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Log Keamanan</span>
                    </Link>
                    
                    <Link :href="route('audit-logs.index')" @click="handleMenuClick"
                          :class="route().current('audit-logs.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50'"
                          class="flex items-center p-3.5 rounded-2xl transition-all group mb-2 overflow-hidden">
                        <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:rotate-12">🔍</div>
                        <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Audit Otoritas</span>
                    </Link>
                    
                    <Link :href="route('settings.index')" @click="handleMenuClick"
                          :class="route().current('settings.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'text-gray-400 hover:bg-gray-50'"
                          class="flex items-center p-3.5 rounded-2xl transition-all group overflow-hidden">
                        <div class="min-w-[32px] flex justify-center text-lg transition-transform group-hover:scale-110">⚙️</div>
                        <span v-if="isSidebarOpen" class="ms-4 text-[11px] font-black uppercase tracking-widest whitespace-nowrap">Konfigurasi</span>
                    </Link>
                </div>
            </nav>

            <div class="p-6 border-t border-gray-50 shrink-0 text-left">
                <div class="p-4 rounded-2xl border border-indigo-100/50" :class="isSidebarOpen ? 'bg-indigo-50/50' : ''">
                    <p v-if="isSidebarOpen" class="text-[8px] font-black text-indigo-400 uppercase tracking-[0.2em]">SiStem Intelijen Pro</p>
                    <p v-if="isSidebarOpen" class="text-[9px] font-black text-indigo-900 uppercase mt-1 italic">{{ user.role }} Mode</p>
                    <p class="text-center text-lg" v-else>🎖️</p>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <header class="h-20 bg-white/80 backdrop-blur-md border-b border-gray-100 flex items-center justify-between px-6 sm:px-8 sticky top-0 z-40 shrink-0">
                <div class="flex items-center gap-6">
                    <button v-if="!isMobile" @click="toggleSidebar" class="p-2.5 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl transition-all active:scale-90 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div v-else class="flex items-center gap-2">
                        <ApplicationLogo class="h-8 w-auto" />
                        <span class="text-[11px] font-black text-indigo-950 uppercase italic tracking-tighter leading-none">SISINDEN <span class="text-indigo-600">M</span></span>
                    </div>

                    <div class="hidden sm:block border-l border-gray-100 ps-6 text-indigo-950 font-black text-[10px] uppercase tracking-[0.4em]">
                        {{ route().current('dashboard') ? 'Overview' : (route().current('letter-logs.*') ? 'Registry' : 'Operational') }}
                    </div>
                </div>

                <div class="flex items-center gap-4 sm:gap-6">
                    <div class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-emerald-50 rounded-full border border-emerald-100">
                        <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest">Active</span>
                    </div>

                    <div class="relative">
                        <button @click="isNotifOpen = !isNotifOpen" class="h-10 w-10 bg-white border border-gray-100 rounded-2xl flex items-center justify-center hover:bg-indigo-50 transition-all shadow-sm group">
                            <span class="text-lg group-hover:scale-110 transition-transform">🔔</span>
                            <div v-if="notifications.length > 0" class="absolute top-2 right-2.5 h-2 w-2 bg-red-500 border-2 border-white rounded-full"></div>
                        </button>

                        <div v-if="isNotifOpen" class="absolute right-0 mt-4 w-72 sm:w-80 bg-white rounded-[2rem] shadow-2xl border border-gray-50 z-[100] overflow-hidden">
                            <div class="p-5 border-b border-gray-50 bg-slate-50/50">
                                <h3 class="font-black text-indigo-950 uppercase tracking-widest text-[10px]">Notifikasi Masuk</h3>
                            </div>
                            <div class="max-h-80 overflow-y-auto custom-scrollbar">
                                <div v-for="n in notifications" :key="n.id" 
                                     @click="showNotifDetail(n)"
                                     class="p-4 border-b border-gray-50 hover:bg-indigo-50/30 cursor-pointer transition flex gap-3">
                                    <div class="h-10 w-10 bg-white rounded-2xl flex items-center justify-center shadow-sm text-sm border border-gray-100 shrink-0">
                                        {{ n.icon }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-black text-indigo-900 uppercase truncate">{{ n.title }}</p>
                                        <p class="text-[9px] text-slate-500 font-bold truncate mt-0.5">{{ n.message }}</p>
                                        <p class="text-[8px] text-gray-300 font-black uppercase mt-1.5 tracking-tighter">{{ n.time }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="notifications.length === 0" class="p-10 text-center text-[10px] font-black text-slate-300 uppercase italic">
                                Belum ada laporan
                            </div>
                        </div>
                    </div>

                    <Dropdown align="right" width="64">
                        <template #trigger>
                            <button class="flex items-center gap-3 p-1.5 hover:bg-gray-50 rounded-2xl transition-all group border border-transparent hover:border-gray-100">
                                <div class="flex flex-col text-right hidden sm:flex">
                                    <span class="text-[10px] font-black text-indigo-950 uppercase tracking-tight leading-none truncate max-w-[150px]">{{ user.name }}</span>
                                    <span class="text-[8px] font-bold text-indigo-500 uppercase tracking-widest mt-1">NRP. {{ user.nrp || 'N/A' }}</span>
                                </div>
                                <div class="h-9 w-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-lg shadow-indigo-100 group-hover:scale-110 transition-transform uppercase">
                                    {{ user.name.charAt(0) }}
                                </div>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')"> Edit Profil </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button" class="text-red-600 font-bold uppercase text-[10px]"> 
                                Keluar Sistem 
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto px-4 sm:px-8 py-6 sm:py-10 custom-scrollbar pb-32 lg:pb-10">
                <div class="mx-auto max-w-7xl text-left">
                    <div v-if="$slots.header" class="mb-6 sm:mb-10 text-left">
                        <slot name="header" />
                    </div>
                    <div class="transition-all duration-500 ease-in-out">
                        <slot />
                    </div>
                </div>
                
                <footer class="mt-20 py-10 text-center border-t border-gray-100">
                    <p class="text-[10px] text-gray-300 font-black uppercase tracking-[0.3em]">
                        &copy; {{ new Date().getFullYear() }} {{ settings.copyright || agencyName }}
                    </p>
                </footer>
            </main>

            <nav 
                v-if="isMobile"
                class="lg:hidden fixed bottom-0 inset-x-0 bg-white/90 backdrop-blur-xl border-t border-gray-100 h-20 px-6 flex justify-around items-center z-[100] shadow-[0_-10px_30px_rgba(0,0,0,0.03)]"
            >
                <Link :href="route('dashboard')" class="flex flex-col items-center gap-1 group">
                    <div :class="route().current('dashboard') ? 'text-indigo-600 scale-110' : 'text-gray-300'" class="transition-all duration-300 text-lg">🏠</div>
                    <span :class="route().current('dashboard') ? 'text-indigo-600 font-black' : 'text-gray-400 font-bold'" class="text-[8px] uppercase tracking-widest">Home</span>
                </Link>
                <Link :href="route('signature.index')" class="flex flex-col items-center gap-1 group">
                    <div :class="route().current('signatures.*') ? 'text-indigo-600 scale-110' : 'text-gray-300'" class="transition-all duration-300 text-lg">🖋️</div>
                    <span :class="route().current('signatures.*') ? 'text-indigo-600 font-black' : 'text-gray-400 font-bold'" class="text-[8px] uppercase tracking-widest">TTE</span>
                </Link>

                <Link :href="route('letter-logs.index')" class="flex flex-col items-center gap-1 group">
                    <div :class="route().current('letter-logs.*') ? 'text-indigo-600 scale-110' : 'text-gray-300'" class="transition-all duration-300 text-lg">📖</div>
                    <span :class="route().current('letter-logs.*') ? 'text-indigo-600 font-black' : 'text-gray-400 font-bold'" class="text-[8px] uppercase tracking-widest">Buku</span>
                </Link>

                <Link v-if="canAccessCash" :href="route('cash.index')" class="flex flex-col items-center gap-1 group">
                    <div :class="route().current('cash.*') ? 'text-indigo-600 scale-110' : 'text-gray-300'" class="transition-all duration-300 text-lg">💰</div>
                    <span :class="route().current('cash.*') ? 'text-indigo-600 font-black' : 'text-gray-400 font-bold'" class="text-[8px] uppercase tracking-widest">Kas</span>
                </Link>

                <Link v-if="canAccessCommanderAccount" :href="route('commander.index')" class="flex flex-col items-center gap-1 group">
                    <div :class="route().current('commander.*') ? 'text-indigo-600 scale-110' : 'text-gray-300'" class="transition-all duration-300 text-lg">💳</div>
                    <span :class="route().current('commander.*') ? 'text-indigo-600 font-black' : 'text-gray-400 font-bold'" class="text-[8px] uppercase tracking-widest">Rekening</span>
                </Link>

                <Link :href="route('profile.edit')" class="flex flex-col items-center gap-1 group">
                    <div :class="route().current('profile.edit') ? 'text-indigo-600 scale-110' : 'text-gray-300'" class="transition-all duration-300 text-lg">👤</div>
                    <span :class="route().current('profile.edit') ? 'text-indigo-600 font-black' : 'text-gray-400 font-bold'" class="text-[8px] uppercase tracking-widest">Akun</span>
                </Link>
            </nav>

        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 0px;
    height: 0px;
}
</style>