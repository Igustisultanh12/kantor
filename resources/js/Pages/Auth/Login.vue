<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
    if (!logo) return null;
    return (logo.startsWith('http') || logo.startsWith('/storage')) ? logo : '/storage/' + logo;
});
const loginBg = computed(() => {
    const bg = pageSettings.value.login_background;
    if (!bg) return null;
    return (bg.startsWith('http') || bg.startsWith('/storage')) ? bg : '/storage/' + bg;
});

const isLockingGPS = ref(false);
const gpsError = ref(null);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    latitude: null,
    longitude: null,
});

/**
 * FUNGSI INTI: Validasi GPS Wajib & Deteksi Fake GPS
 */
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
                // DETEKSI FAKE GPS: Cek akurasi dan properti mocked
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
    try {
        await lockLocation();

        form.post(route('login'), {
            onFinish: () => form.reset('password'),
        });
    } catch (e) {
        console.warn("Autentikasi dihentikan: Koordinat GPS diperlukan.");
    }
};
</script>

<template>
    <Head title="Otoritas Login - SINDEN" />

    <div 
        class="min-h-screen flex text-slate-800 font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-950"
        :style="loginBg ? { backgroundImage: `url(${loginBg})` } : {}"
    >
        <!-- Dark overlay -->
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-[2px] z-0"></div>

        <!-- Main Wrapper -->
        <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
            
            <!-- Left Column: Single Floating Logo & App Info -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-16 text-white z-10">
                <div></div>

                <div class="my-auto max-w-lg space-y-6 flex flex-col items-center text-center mx-auto">
                    <!-- Single Main Logo (Efek Mentul-Mentul / Floating Animation) -->
                    <div class="flex justify-center animate-float-slow">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-36 lg:h-44 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)]" alt="Logo SINDEN" />
                        <div v-else class="w-32 h-32 rounded-3xl bg-blue-600/30 backdrop-blur-md border border-blue-500/40 flex items-center justify-center shadow-2xl">
                            <span class="font-black text-white text-5xl">S</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h2 class="text-3xl font-extrabold tracking-tight uppercase text-white drop-shadow-md">
                            {{ appName }}
                        </h2>
                        <p class="text-xs leading-relaxed max-w-md mx-auto text-slate-300 font-medium"> Sistem Informasi Detasemen Intelijen — Integrasi Otoritas Akses, Pengawasan Lokasi, Administrasi Surat & Logistik Terpadu.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 text-center font-medium">
                    © {{ new Date().getFullYear() }} {{ appName }}. All Rights Reserved.
                </p>
            </div>

            <!-- Right Column: Dark Floating Card Form Login -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 z-10 my-auto">
                <div class="w-full max-w-md p-8 sm:p-10 rounded-2xl shadow-2xl bg-slate-900/90 backdrop-blur-md border border-white/10 text-white animate-float-card">
                    <div class="mb-8">
                        <h3 class="text-xl font-bold text-white">Masuk Akun</h3>
                        <p class="text-xs mt-1 text-slate-300"> Gunakan akun internal Anda untuk mengakses sistem dashboard SINDEN.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider mb-2 text-slate-300"> Email / Username Dinas
                            </label>
                            <input 
                                type="email"v-model="form.email"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="Masukkan email / NRP"required 
                                autofocus
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.email" />
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300"> Kata Kunci
                                </label>
                                <Link v-if="canResetPassword" :href="route('password.request.custom')" class="text-xs font-medium text-orange-400 hover:underline"> Lupa Password?
                                </Link>
                            </div>
                            <input 
                                type="password"v-model="form.password"class="w-full px-4 py-2.5 rounded-lg border text-sm outline-none transition duration-150 bg-white/10 border-white/10 text-white placeholder-slate-400 focus:ring-2 focus:ring-white/20 focus:border-white"placeholder="••••••••"required 
                            />
                            <InputError class="mt-1 text-xs text-red-400" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-slate-300 select-none cursor-pointer">
                                <input 
                                    type="checkbox"v-model="form.remember"class="w-4 h-4 rounded bg-white/10 border-white/10 text-orange-500 focus:ring-0 cursor-pointer" 
                                /> Ingat Saya
                            </label>
                        </div>

                        <button 
                            type="submit" 
                            :disabled="form.processing || isLockingGPS"class="w-full py-3 px-4 text-white text-sm font-semibold rounded-lg shadow-lg bg-orange-500 hover:bg-orange-600 shadow-orange-500/20 transition duration-150 disabled:opacity-50 cursor-pointer flex justify-center items-center gap-2"
                        >
                            <template v-if="isLockingGPS">
                                <svg class="animate-spin h-4 w-4 mr-2 text-white inline" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Mengunci GPS...</span>
                            </template>
                            <template v-else-if="form.processing">
                                <span>Memverifikasi...</span>
                            </template>
                            <template v-else>
                                <span>Masuk Sistem</span>
                                <span></span>
                            </template>
                        </button>
                    </form>

                    <!-- Status GPS Verification Widget -->
                    <div class="mt-4 p-3 rounded-xl border transition-all flex items-center gap-3"
                         :class="form.latitude ? 'bg-emerald-950/40 border-emerald-500/40' : 'bg-slate-800/50 border-slate-700/50'">
                        <div class="h-2.5 w-2.5 rounded-full shrink-0 transition-all" 
                            :class="[
                                form.latitude ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]' : 'bg-rose-400', 
                                isLockingGPS ? 'animate-ping' : ''
                            ]"
                        ></div>
                        <div class="flex flex-col overflow-hidden text-left">
                            <p class="text-[10px] font-bold uppercase leading-tight" 
                               :class="form.latitude ? 'text-emerald-300' : 'text-slate-300'">
                                {{ form.latitude ? 'GPS Presisi Terkunci' : (gpsError ? 'Akses Terblokir' : 'Verifikasi GPS Sistem') }}
                            </p>
                            <p class="text-[9px] text-slate-400 uppercase mt-0.5 tracking-tight truncate">
                                {{ gpsError || (form.latitude ? `Koordinat: ${form.latitude.toFixed(4)}, ${form.longitude.toFixed(4)}` : 'Lokasi GPS Wajib Aktif') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 text-center border-t border-white/10 pt-6 flex justify-between text-xs text-slate-300">
                        <Link href="/aktivasi" class="text-orange-400 hover:underline">Aktivasi Akun</Link>
                        <span>Belum punya akun? <Link :href="route('register')" class="font-semibold text-orange-400 hover:underline">Daftar Sekarang</Link></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-float-slow {
  animation: float-logo 5s ease-in-out infinite;
}

.animate-float-card {
  animation: float-card 6s ease-in-out infinite;
}

@keyframes float-logo {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes float-card {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}
</style>