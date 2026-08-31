<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    settings: { type: Object },
});

const page = usePage();

const pageSettings = computed(() => props.settings || page.props.settings || {});
const appName = computed(() => pageSettings.value.agency_name || pageSettings.value.app_name || 'SINDEN');
const configuredLogo = computed(() => {
    const logo = pageSettings.value.agency_logo || pageSettings.value.logo || pageSettings.value.logo_tni;
    if (!logo) return '/images/logo.png';
    return (logo.startsWith('http') || logo.startsWith('/storage') || logo.startsWith('/images')) ? logo : '/storage/' + logo;
});

// Alur UI: 'welcome' -> 'login' -> 'session_check'
const currentStep = ref('welcome'); // default ke welcome screen persis video
const isDarkMode = ref(true);
const currentLang = ref('id');
const isLangMenuOpen = ref(false);
const showPassword = ref(false);
const isRobotVerified = ref(false);
const isVerifyingRobot = ref(false);

const toggleTheme = () => {
    isDarkMode.value = !isDarkMode.value;
};

const setLanguage = (lang) => {
    currentLang.value = lang;
    isLangMenuOpen.value = false;
};

const handleRobotCheck = () => {
    if (isRobotVerified.value) {
        isRobotVerified.value = false;
        return;
    }
    isVerifyingRobot.value = true;
    setTimeout(() => {
        isVerifyingRobot.value = false;
        isRobotVerified.value = true;
    }, 600);
};

const isLockingGPS = ref(false);
const gpsError = ref(null);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    latitude: null,
    longitude: null,
});

onMounted(() => {
    if (page.props.flash?.message) {
        Swal.fire({
            icon: 'success',
            title: 'AKTIVASI OTORITAS AKUN BERHASIL',
            text: page.props.flash.message,
            confirmButtonText: 'SIAP LOGIN',
            confirmButtonColor: '#f59e0b',
            background: 'rgba(15, 23, 42, 0.95)',
            color: '#ffffff',
            customClass: {
                popup: 'border border-amber-500/30 shadow-2xl rounded-2xl',
                confirmButton: 'bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-amber-500/20'
            },
        });
    }
});

const lockLocation = () => {
    return new Promise((resolve, reject) => {
        isLockingGPS.value = true;
        gpsError.value = null;

        if (!navigator.geolocation) {
            Swal.fire({
                icon: 'error',
                title: 'SISTEM TIDAK DIDUKUNG',
                text: 'Browser Anda tidak mendukung fitur GPS.',
                confirmButtonColor: '#2563eb',
            });
            isLockingGPS.value = false;
            reject();
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const accuracy = position.coords.accuracy;
                const isMocked = position.mocked || (position.coords && position.coords.isMocked);

                if (isMocked || accuracy > 250) {
                    Swal.fire({
                        icon: 'error',
                        title: 'DETEKSI MANIPULASI',
                        text: 'SISTEM MENDETEKSI PENGGUNAAN LOKASI PALSU. MATIKAN APLIKASI FAKE GPS ANDA!',
                        confirmButtonColor: '#dc2626',
                    });
                    gpsError.value = "MANIPULASI LOKASI TERDETEKSI.";
                    isLockingGPS.value = false;
                    reject();
                    return;
                }

                form.latitude = position.coords.latitude;
                form.longitude = position.coords.longitude;
                isLockingGPS.value = false;
                resolve();
            },
            (error) => {
                isLockingGPS.value = false;
                Swal.fire({
                    icon: 'warning',
                    title: 'AKSES DIBATALKAN',
                    text: 'MAAF ANDA TIDAK BISA LOGIN, SILAHKAN AKTIFKAN LOKASI ANDA!',
                    confirmButtonText: 'KEMBALI KE HALAMAN LOGIN',
                    confirmButtonColor: '#dc2626',
                    background: '#ffffff',
                    allowOutsideClick: false
                });
                gpsError.value = "IZIN LOKASI DITOLAK.";
                reject();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    });
};

const submit = async () => {
    if (!form.email) {
        return;
    }

    try {
        await lockLocation();

        // Tampilkan transisi splash "Memeriksa sesi" persis CORETAX DJP
        currentStep.value = 'session_check';

        form.post(route('login'), {
            onFinish: () => {
                form.reset('password');
            },
            onError: () => {
                currentStep.value = 'login';
            }
        });
    } catch (e) {
        console.warn("Autentikasi dihentikan: Koordinat GPS diperlukan.");
    }
};
</script>

<template>
    <Head :title="currentStep === 'welcome' ? 'Selamat Datang - SINDEN' : 'Masuk - SINDEN'" />

    <!-- LAYAR TRANSISI: MEMERIKSA SESI (PERSIS VIDEO CORETAX) -->
    <div 
        v-if="currentStep === 'session_check'" 
        class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-[#0B0F19] text-white p-6 transition-all duration-300"
    >
        <div class="flex flex-col items-center space-y-6 max-w-sm text-center animate-pulse">
            <!-- Logo SINDEN Emas -->
            <div class="flex items-center gap-2">
                <img v-if="configuredLogo" :src="configuredLogo" class="h-16 w-16 object-contain" alt="Logo" />
                <span class="text-3xl font-black tracking-wider text-white">
                    SIN<span class="text-[#FFC107]">DEN</span>
                </span>
            </div>

            <!-- Golden Circular Spinner -->
            <div class="relative w-16 h-16 flex items-center justify-center">
                <div class="w-12 h-12 rounded-full border-3 border-[#1E2638] border-t-[#FFC107] animate-spin"></div>
            </div>

            <!-- Text Status -->
            <div class="space-y-1">
                <h3 class="text-lg font-bold text-white tracking-wide">Memeriksa sesi</h3>
                <p class="text-xs text-slate-400">Mohon tunggu sebentar...</p>
            </div>
        </div>
    </div>

    <!-- WRAPPER UTAMA -->
    <div 
        v-else
        class="min-h-screen font-sans antialiased transition-colors duration-300 flex flex-col justify-between"
        :class="isDarkMode ? 'bg-[#0B0F19] text-slate-100' : 'bg-[#F4F6F9] text-slate-800'"
    >
        <!-- TOP HEADER BAR -->
        <header class="w-full px-6 py-4 flex items-center justify-between border-b transition-colors duration-300 z-20"
            :class="isDarkMode ? 'border-white/10 bg-[#0B0F19]/90 backdrop-blur-md' : 'border-slate-200 bg-white/90 backdrop-blur-md shadow-xs'"
        >
            <!-- Logo & Brand Header -->
            <div class="flex items-center gap-3">
                <img v-if="configuredLogo" :src="configuredLogo" class="h-9 sm:h-11 object-contain drop-shadow-md" alt="Logo SINDEN" />
                <div class="flex flex-col">
                    <span class="text-lg sm:text-xl font-black tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                        SIN<span class="text-[#FFC107]">DEN</span>
                    </span>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 hidden sm:block">
                        Detasemen Intelijen Kodaeral V
                    </span>
                </div>
            </div>

            <!-- Right Controls: Theme Toggle & Language Selector -->
            <div class="flex items-center gap-3">
                <!-- Theme Toggle Button -->
                <button 
                    @click="toggleTheme" 
                    type="button"
                    class="p-2 rounded-xl transition-all cursor-pointer flex items-center justify-center"
                    :class="isDarkMode ? 'bg-white/10 text-amber-400 hover:bg-white/20' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    :title="isDarkMode ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
                >
                    <!-- Sun Icon (Dark Mode) -->
                    <svg v-if="isDarkMode" class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0s-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41s-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41s-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/>
                    </svg>
                    <!-- Moon Icon (Light Mode) -->
                    <svg v-else class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M12.3 2a10 10 0 0 0-1.9 20 10 10 0 0 0 9.8-7.7 1 1 0 0 0-1.2-1.2A8 8 0 0 1 10.9 4a1 1 0 0 0-1.2-1.2 10 10 0 0 0 2.6-.8z"/>
                    </svg>
                </button>

                <!-- Language Selector Dropdown -->
                <div class="relative">
                    <button 
                        @click="isLangMenuOpen = !isLangMenuOpen"
                        type="button" 
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all cursor-pointer"
                        :class="isDarkMode ? 'border-white/15 bg-white/5 hover:bg-white/10 text-white' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-800 shadow-2xs'"
                    >
                        <!-- Flag Indicator -->
                        <span class="w-4 h-3 rounded-xs flex items-center justify-center text-[10px] overflow-hidden border border-black/20 font-bold">
                            {{ currentLang === 'id' ? 'ðŸ‡®ðŸ‡©' : 'ðŸ‡ºðŸ‡¸' }}
                        </span>
                        <span>{{ currentLang === 'id' ? 'ID' : 'EN' }}</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Language Menu Options -->
                    <div 
                        v-if="isLangMenuOpen" 
                        class="absolute right-0 mt-2 w-32 rounded-xl shadow-2xl border py-1 z-30 transition-all"
                        :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white' : 'bg-white border-slate-200 text-slate-800'"
                    >
                        <button 
                            @click="setLanguage('id')"
                            type="button"
                            class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 hover:bg-amber-500/10 hover:text-amber-400 transition"
                            :class="currentLang === 'id' ? 'font-bold text-amber-400' : ''"
                        >
                            <span>ðŸ‡®ðŸ‡©</span> Indonesia
                        </button>
                        <button 
                            @click="setLanguage('en')"
                            type="button"
                            class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 hover:bg-amber-500/10 hover:text-amber-400 transition"
                            :class="currentLang === 'en' ? 'font-bold text-amber-400' : ''"
                        >
                            <span>ðŸ‡ºðŸ‡¸</span> English
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- FASE 1: HALAMAN SELAMAT DATANG (PRA-LOGIN PERSIS VIDEO 1 & VIDEO 2)       -->
        <!-- ========================================================================= -->
        <main v-if="currentStep === 'welcome'" class="flex-1 flex items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-4xl flex flex-col items-center text-center space-y-8 my-auto animate-fade-in">
                
                <!-- SINDEN Hero Branding -->
                <div class="space-y-4 max-w-xl mx-auto">
                    <div class="flex items-center justify-center gap-2">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-14 sm:h-20 object-contain drop-shadow-lg" alt="Logo" />
                        <h1 class="text-4xl sm:text-6xl font-black tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                            SIN<span class="text-[#FFC107]">DEN</span>
                        </h1>
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                            Selamat Datang<br class="sm:hidden" /> Anda akan Masuk ke SINDEN
                        </h2>
                        <p class="text-xs sm:text-sm leading-relaxed max-w-md mx-auto" :class="isDarkMode ? 'text-slate-400' : 'text-slate-600'">
                            Pastikan Anda menggunakan perangkat dan jaringan yang aman sebelum melanjutkan proses login. Dengan melanjutkan, Anda menyetujui ketentuan penggunaan dan kebijakan keamanan SINDEN.
                        </p>
                    </div>

                    <!-- Call To Action: Lanjutkan ke Login -->
                    <div class="pt-2">
                        <button 
                            @click="currentStep = 'login'" 
                            type="button" 
                            class="inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-2xl font-black text-sm tracking-wide transition-all shadow-xl cursor-pointer bg-[#25324B] hover:bg-[#1E283D] text-white hover:scale-[1.02] active:scale-[0.98] border border-blue-400/20"
                        >
                            <span>Lanjutkan ke Login</span>
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Kartu Informasi Keamanan (Praktik Aman & Hal yang Dihindari) -->
                <div class="w-full grid grid-cols-1 md:grid-cols-2 gap-4 text-left pt-4">
                    
                    <!-- Kartu 1: Praktik Aman (Hijau) -->
                    <div 
                        class="p-5 sm:p-6 rounded-2xl border transition-all space-y-4"
                        :class="isDarkMode ? 'bg-[#121827]/80 border-white/10 shadow-lg' : 'bg-white border-slate-200 shadow-sm'"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                                <svg class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Praktik Aman</h3>
                                <p class="text-xs text-slate-400">Lindungi akun dengan kebiasaan digital yang aman.</p>
                            </div>
                        </div>

                        <div class="space-y-3 pt-1 text-xs">
                            <div class="flex items-start gap-2.5">
                                <span class="text-emerald-400 font-bold mt-0.5">ðŸ”’</span>
                                <div>
                                    <p class="font-bold" :class="isDarkMode ? 'text-slate-200' : 'text-slate-800'">Gunakan sandi kuat & aktifkan 2FA</p>
                                    <p class="text-[11px] text-slate-400">Buat sandi unik, panjang, dan sulit ditebak.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-emerald-400 font-bold mt-0.5">ðŸ”„</span>
                                <div>
                                    <p class="font-bold" :class="isDarkMode ? 'text-slate-200' : 'text-slate-800'">Perbarui sistem & peramban</p>
                                    <p class="text-[11px] text-slate-400">Pastikan OS dan browser selalu mutakhir.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-emerald-400 font-bold mt-0.5">ðŸŒ</span>
                                <div>
                                    <p class="font-bold" :class="isDarkMode ? 'text-slate-200' : 'text-slate-800'">Akses dari jaringan tepercaya</p>
                                    <p class="text-[11px] text-slate-400">Gunakan jaringan resmi atau VPN yang tepercaya.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kartu 2: Hal yang Dihindari (Merah) -->
                    <div 
                        class="p-5 sm:p-6 rounded-2xl border transition-all space-y-4"
                        :class="isDarkMode ? 'bg-[#121827]/80 border-white/10 shadow-lg' : 'bg-white border-slate-200 shadow-sm'"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-500/15 border border-red-500/30 flex items-center justify-center text-red-400 shrink-0">
                                <svg class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold" :class="isDarkMode ? 'text-white' : 'text-slate-900'">Hal yang Dihindari</h3>
                                <p class="text-xs text-slate-400">Waspadai tindakan yang membahayakan akun.</p>
                            </div>
                        </div>

                        <div class="space-y-3 pt-1 text-xs">
                            <div class="flex items-start gap-2.5">
                                <span class="text-red-400 font-bold mt-0.5">ðŸ‘¤</span>
                                <div>
                                    <p class="font-bold" :class="isDarkMode ? 'text-slate-200' : 'text-slate-800'">Membagikan OTP / Kode Akses</p>
                                    <p class="text-[11px] text-slate-400">Jangan berikan kode kepada siapa pun.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-red-400 font-bold mt-0.5">ðŸ”—</span>
                                <div>
                                    <p class="font-bold" :class="isDarkMode ? 'text-slate-200' : 'text-slate-800'">Mengklik tautan mencurigakan</p>
                                    <p class="text-[11px] text-slate-400">Hindari tautan asing dari WhatsApp atau Email.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <span class="text-red-400 font-bold mt-0.5">ðŸ’»</span>
                                <div>
                                    <p class="font-bold" :class="isDarkMode ? 'text-slate-200' : 'text-slate-800'">Meninggalkan perangkat terbuka</p>
                                    <p class="text-[11px] text-slate-400">Selalu lock perangkat Anda saat tidak digunakan.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </main>

        <!-- ========================================================================= -->
        <!-- FASE 2: FORMULIR LOGIN MODERN (GAYA RESMI CORETAX DJP)                     -->
        <!-- ========================================================================= -->
        <main v-else-if="currentStep === 'login'" class="flex-1 flex items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-center my-auto animate-fade-in">
                
                <!-- Sisi Kiri: Branding SINDEN (Desktop Only) -->
                <div class="hidden lg:flex lg:col-span-5 flex-col justify-center space-y-4 text-left pr-4">
                    <div class="flex items-center gap-3">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-16 w-16 object-contain drop-shadow-md" alt="Logo" />
                        <div>
                            <span class="text-3xl font-black tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                                SIN<span class="text-[#FFC107]">DEN</span>
                            </span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-xl font-bold uppercase tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                            Sistem Inti Administrasi Intelijen
                        </h2>
                        <p class="text-xs text-slate-400 leading-relaxed font-medium">
                            Komando Daerah TNI Angkatan Laut V. Tangguh, Akurat, dan Terintegrasi.
                        </p>
                    </div>
                </div>

                <!-- Sisi Kanan: Form Card Login -->
                <div class="lg:col-span-7 w-full max-w-md mx-auto">
                    <div 
                        class="p-6 sm:p-8 rounded-3xl border transition-all duration-300 space-y-6 shadow-2xl"
                        :class="isDarkMode ? 'bg-[#121827] border-white/10 text-white' : 'bg-white border-slate-200 text-slate-900'"
                    >
                        <!-- Form Header -->
                        <div class="space-y-1 text-left">
                            <h2 class="text-2xl font-black tracking-tight" :class="isDarkMode ? 'text-white' : 'text-slate-900'">
                                Selamat Datang!
                            </h2>
                            <p class="text-xs text-slate-400">
                                Masuk untuk mengakses Layanan SINDEN
                            </p>
                        </div>

                        <!-- Form Submission -->
                        <form @submit.prevent="submit" class="space-y-4 text-left">
                            
                            <!-- Input ID Pengguna (NRP/NIP/Email) -->
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold tracking-wide block" :class="isDarkMode ? 'text-slate-200' : 'text-slate-700'">
                                    ID Pengguna
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 text-slate-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </span>
                                    <input 
                                        id="email" 
                                        type="text" 
                                        v-model="form.email" 
                                        required
                                        autofocus
                                        placeholder="NIK/NPWP/NRP/Email identitas SINDEN"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-xs transition-all focus:outline-hidden focus:ring-2 focus:ring-amber-500/50"
                                        :class="isDarkMode ? 'bg-[#0B0F19] border-white/15 text-white placeholder-slate-500' : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'"
                                    />
                                </div>
                                <p v-if="form.errors.email" class="text-[11px] font-bold text-red-500 mt-1 flex items-center gap-1">
                                    <span>âš ï¸</span> {{ form.errors.email }}
                                </p>
                            </div>

                            <!-- Input Kata Sandi -->
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold tracking-wide block" :class="isDarkMode ? 'text-slate-200' : 'text-slate-700'">
                                    Kata Sandi
                                </label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-3.5 text-slate-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    </span>
                                    <input 
                                        id="password" 
                                        :type="showPassword ? 'text' : 'password'" 
                                        v-model="form.password" 
                                        required
                                        placeholder="Masukkan kata sandi Anda"
                                        class="w-full pl-10 pr-10 py-2.5 rounded-xl border text-xs transition-all focus:outline-hidden focus:ring-2 focus:ring-amber-500/50"
                                        :class="isDarkMode ? 'bg-[#0B0F19] border-white/15 text-white placeholder-slate-500' : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'"
                                    />
                                    <button 
                                        type="button" 
                                        @click="showPassword = !showPassword" 
                                        class="absolute right-3 text-slate-400 hover:text-slate-200 cursor-pointer p-1"
                                    >
                                        <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </button>
                                </div>
                                <p v-if="form.errors.password" class="text-[11px] font-bold text-red-500 mt-1 flex items-center gap-1">
                                    <span>âš ï¸</span> {{ form.errors.password }}
                                </p>
                            </div>

                            <!-- Kotak Verifikasi Saya Bukan Robot (Persis CORETAX DJP) -->
                            <div 
                                class="p-3 rounded-xl border flex items-center justify-between cursor-pointer transition-all select-none"
                                :class="isDarkMode ? 'bg-[#0B0F19] border-white/10 hover:border-white/20' : 'bg-slate-50 border-slate-200 hover:border-slate-300'"
                                @click="handleRobotCheck"
                            >
                                <div class="flex items-center gap-3">
                                    <div 
                                        class="w-5 h-5 rounded-md border flex items-center justify-center transition-all"
                                        :class="isRobotVerified ? 'bg-amber-500 border-amber-500 text-slate-950 font-bold' : (isDarkMode ? 'border-white/30 bg-white/5' : 'border-slate-400 bg-white')"
                                    >
                                        <svg v-if="isRobotVerified" class="w-3.5 h-3.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <div v-else-if="isVerifyingRobot" class="w-3 h-3 border-2 border-amber-400 border-t-transparent rounded-full animate-spin"></div>
                                    </div>
                                    <span class="text-xs font-semibold" :class="isDarkMode ? 'text-slate-300' : 'text-slate-700'">
                                        {{ isRobotVerified ? 'Terverifikasi' : (isVerifyingRobot ? 'Memverifikasi...' : 'Saya bukan robot') }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono">
                                    reCAPTCHA
                                </div>
                            </div>

                            <!-- Lupa Kata Sandi Link -->
                            <div class="flex justify-end pt-1">
                                <Link 
                                    v-if="canResetPassword" 
                                    :href="route('password.request')" 
                                    class="text-xs font-bold text-slate-400 hover:text-amber-400 hover:underline transition"
                                >
                                    Lupa Kata Sandi?
                                </Link>
                            </div>

                            <!-- Tombol Masuk Utama (Gold/Amber Khas CORETAX) -->
                            <div class="pt-2">
                                <button 
                                    type="submit" 
                                    :disabled="form.processing || isLockingGPS" 
                                    class="w-full py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all shadow-lg flex items-center justify-center gap-2 cursor-pointer"
                                    :class="form.processing || isLockingGPS 
                                        ? 'bg-amber-500/50 text-slate-800 cursor-not-allowed' 
                                        : 'bg-[#FFC107] hover:bg-[#F59E0B] text-slate-950 hover:shadow-amber-500/20 active:scale-[0.99]'"
                                >
                                    <div v-if="form.processing || isLockingGPS" class="w-4 h-4 border-2 border-slate-900 border-t-transparent rounded-full animate-spin"></div>
                                    <span>{{ form.processing || isLockingGPS ? 'Memproses...' : 'Masuk' }}</span>
                                </button>
                            </div>

                        </form>

                        <!-- Pembatas ATAU -->
                        <div class="relative flex py-1 items-center">
                            <div class="flex-grow border-t" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'"></div>
                            <span class="shrink-0 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">ATAU</span>
                            <div class="flex-grow border-t" :class="isDarkMode ? 'border-white/10' : 'border-slate-200'"></div>
                        </div>

                        <!-- Tombol Aksi Alternatif (Pengguna Baru & Aktivasi Akun) -->
                        <div class="space-y-2 text-xs font-bold">
                            <!-- Pengguna Baru? Daftar di sini -->
                            <Link 
                                :href="route('register')"
                                class="w-full p-3 rounded-xl border flex items-center justify-between transition group"
                                :class="isDarkMode ? 'bg-white/5 border-white/10 text-white hover:bg-white/10' : 'bg-slate-50 border-slate-200 text-slate-800 hover:bg-slate-100'"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="text-slate-400">ðŸ‘¤</span>
                                    <span>Pengguna Baru? <span class="text-amber-400 font-normal">Daftar di sini</span></span>
                                </div>
                                <svg class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </Link>

                            <!-- Belum Aktivasi? Aktivasi Akun Personel -->
                            <Link 
                                :href="route('aktivasi')"
                                class="w-full p-3 rounded-xl border flex items-center justify-between transition group"
                                :class="isDarkMode ? 'bg-white/5 border-white/10 text-white hover:bg-white/10' : 'bg-slate-50 border-slate-200 text-slate-800 hover:bg-slate-100'"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span class="text-slate-400">ðŸ”‘</span>
                                    <span>Belum Aktivasi? <span class="text-amber-400 font-normal">Aktivasi Akun Wajib Personel</span></span>
                                </div>
                                <svg class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </Link>
                        </div>

                    </div>
                </div>

            </div>
        </main>

        <!-- FOOTER RESMI (PERSIS VIDEO CORETAX) -->
        <footer class="w-full py-4 text-center text-[11px] text-slate-500 font-medium border-t transition-colors duration-300"
            :class="isDarkMode ? 'border-white/5 bg-[#0B0F19]' : 'border-slate-200 bg-[#F4F6F9]'"
        >
            Â© {{ new Date().getFullYear() }} Detasemen Intelijen Komando Daerah TNI Angkatan Laut V. Seluruh hak cipta dilindungi.
        </footer>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
</style>