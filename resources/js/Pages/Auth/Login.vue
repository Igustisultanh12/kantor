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
        class="min-h-screen flex font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-900"
        :style="loginBg ? { backgroundImage: `url(${loginBg})` } : {}"
    >
        <!-- Overlay Gelap Jika Ada Gambar Background -->
        <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-[2px] z-0"></div>

        <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
            <!-- Sisi Kiri: Logo Instansi & Branding App SISFOPERSKC Style -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 lg:p-16 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <span class="font-black text-white text-xl">S</span>
                    </div>
                    <span class="font-black tracking-widest text-lg text-white uppercase">{{ appName }}</span>
                </div>

                <div class="my-auto max-w-lg space-y-6 text-center mx-auto">
                    <!-- Logo Utama (Dapat Diatur Lewat Pengaturan Admin) -->
                    <div v-if="configuredLogo" class="flex justify-center">
                        <img :src="configuredLogo" class="h-28 lg:h-36 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)] animate-pulse" />
                    </div>
                    <div v-else class="flex justify-center">
                        <div class="w-24 h-24 rounded-3xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center shadow-2xl">
                            <span class="font-black text-white text-4xl">S</span>
                        </div>
                    </div>

                    <div>
                        <h1 class="text-3xl lg:text-4xl font-black tracking-tight text-white uppercase drop-shadow-md">
                            {{ appName }}
                        </h1>
                        <p class="text-sm mt-3 text-slate-300 max-w-md mx-auto leading-relaxed font-medium">
                            Sistem Informasi Detasemen Intelijen — Integrasi Akses, Pengawasan, dan Logistik Terpadu.
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-400 text-center font-medium">
                    © {{ new Date().getFullYear() }} {{ appName }}. All Rights Reserved.
                </p>
            </div>

            <!-- Sisi Kanan: Card Form Login Clean Glassmorphism -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 my-auto">
                <div class="w-full max-w-md p-8 sm:p-10 rounded-3xl shadow-2xl bg-white/95 backdrop-blur-md border border-white/40 text-slate-800">
                    <div class="mb-6 text-center sm:text-left">
                        <div class="flex items-center justify-center sm:justify-start gap-2 mb-2 lg:hidden">
                            <img v-if="configuredLogo" :src="configuredLogo" class="h-10 object-contain" />
                            <span class="font-black text-slate-900 tracking-wider uppercase text-lg">{{ appName }}</span>
                        </div>
                        <h2 class="text-2xl font-black text-slate-900 uppercase tracking-tight">Masuk Akun</h2>
                        <p class="text-xs text-slate-500 font-semibold mt-1">Otoritas Login & Verifikasi Keamanan GPS SINDEN</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="email" value="Email Dinas" class="text-[10px] font-black uppercase text-slate-500 ms-1" />
                            <TextInput 
                                id="email" 
                                type="email" 
                                class="mt-1 block w-full rounded-2xl border-slate-200 bg-slate-50 focus:bg-white focus:border-blue-600 focus:ring-blue-500/20 text-sm font-semibold transition-all placeholder:text-slate-400 py-3" 
                                v-model="form.email" 
                                required 
                                autofocus 
                                autocomplete="username" 
                                placeholder="masukkan email dinas..." 
                            />
                            <InputError class="mt-1.5" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" class="text-[10px] font-black uppercase text-slate-500 ms-1" />
                            <TextInput 
                                id="password" 
                                type="password" 
                                class="mt-1 block w-full rounded-2xl border-slate-200 bg-slate-50 focus:bg-white focus:border-blue-600 focus:ring-blue-500/20 text-sm font-semibold transition-all placeholder:text-slate-400 py-3" 
                                v-model="form.password" 
                                required 
                                autocomplete="current-password" 
                                placeholder="••••••••" 
                            />
                            <InputError class="mt-1.5" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center cursor-pointer">
                                <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                <span class="ms-2 text-[11px] font-bold text-slate-600 uppercase tracking-wider">Ingat Perangkat</span>
                            </label>
                            
                            <Link v-if="canResetPassword" :href="route('password.request.custom')" class="text-[10px] font-extrabold text-blue-600 uppercase tracking-tight hover:text-blue-800">
                                Lupa Password?
                            </Link>
                        </div>

                        <div class="pt-2">
                            <PrimaryButton
                                class="w-full justify-center bg-blue-600 py-3.5 rounded-2xl font-black uppercase text-xs tracking-widest text-white shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition-all active:scale-95 disabled:opacity-50"
                                :disabled="form.processing || isLockingGPS"
                            >
                                <template v-if="isLockingGPS">
                                    <svg class="animate-spin h-4 w-4 mr-2 text-white inline" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    MENGUNCI GPS...
                                </template>
                                <template v-else-if="form.processing">
                                    MEREGISTER AKSES...
                                </template>
                                <template v-else>
                                    Masuk Ke Sistem
                                </template>
                            </PrimaryButton>
                        </div>

                        <div class="flex items-center justify-between text-xs pt-3 font-bold border-t border-slate-100 mt-4">
                            <Link href="/aktivasi" class="text-emerald-600 hover:underline uppercase text-[10px]">Aktivasi Akun Disini</Link>
                            <Link :href="route('register')" class="text-blue-600 hover:underline uppercase text-[10px]">Silahkan Mendaftar</Link>
                        </div>

                        <!-- Status GPS Verifikasi -->
                        <div class="mt-4 p-3.5 rounded-2xl border transition-all flex items-center gap-3"
                             :class="form.latitude ? 'bg-emerald-50/80 border-emerald-200' : 'bg-slate-50 border-slate-200'">
                            
                            <div class="h-2.5 w-2.5 rounded-full shrink-0 transition-all" 
                                :class="[
                                    form.latitude ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]' : 'bg-rose-500', 
                                    isLockingGPS ? 'animate-ping' : ''
                                ]"
                            ></div>

                            <div class="flex flex-col overflow-hidden">
                                <p class="text-[10px] font-black uppercase leading-tight" 
                                   :class="form.latitude ? 'text-emerald-800' : 'text-slate-600'">
                                    {{ form.latitude ? 'Koordinat Terkunci' : (gpsError ? 'Akses Terblokir' : 'Status Lokasi Aktif') }}
                                </p>
                                <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5 tracking-tight truncate">
                                    {{ gpsError || (form.latitude ? `Laporan lokasi: ${form.latitude.toFixed(4)}, ${form.longitude.toFixed(4)}` : 'GPS Wajib Aktif sebagai instrumen verifikasi.') }}
                                </p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>