<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2'; // Pastikan sudah install: npm install sweetalert2

const props = defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
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
                confirmButtonColor: '#4f46e5',
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
                
                // POPUP PERINGATAN TEGAS
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
    <GuestLayout>
        <Head title="Otoritas Login" />

        <div class="mb-8 text-center">
            <h1 class="text-xl font-black text-indigo-900 uppercase tracking-tight italic">Sistem Informasi Denintel (SI SINDEN)</h1>
            <div class="h-1 w-12 bg-indigo-600 mx-auto mt-2 rounded-full"></div>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2 italic">Monitoring Keamanan & Lokasi Aktif</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Email Dinas" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput id="email" type="email" class="mt-1 block w-full rounded-2xl border-gray-100 bg-gray-50/50 focus:bg-white transition-all placeholder:text-gray-300" v-model="form.email" required autofocus autocomplete="username" placeholder="masukkan email dinas..." />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput id="password" type="password" class="mt-1 block w-full rounded-2xl border-gray-100 bg-gray-50/50 focus:bg-white transition-all placeholder:text-gray-300" v-model="form.password" required autocomplete="current-password" placeholder="••••••••" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between mt-4 px-1">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Ingat Perangkat</span>
                </label>
                
                <Link v-if="canResetPassword" :href="route('password.request.custom')" class="text-[9px] font-black text-indigo-600 uppercase tracking-tighter hover:text-indigo-800">
                    Lupa Password?
                </Link>
            </div>
            
            <div class="mt-4 text-center">
                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">
                        <Link href="/aktivasi" class="text-emerald-600 hover:underline">Aktivasi Akun Disini</Link>
                    </p>
                </div>

            <div class="pt-4">
                <PrimaryButton
                    class="w-full justify-center bg-gray-900 py-4 rounded-2xl font-black uppercase text-[11px] tracking-[0.2em] shadow-xl hover:bg-indigo-700 transition-all active:scale-95 disabled:opacity-50"
                    :disabled="form.processing || isLockingGPS"
                >
                    <template v-if="isLockingGPS">
                        <svg class="animate-spin h-4 w-4 mr-3 text-white inline" viewBox="0 0 24 24">
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
            
            <div class="mt-6 text-center border-t border-gray-50 pt-4">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                    Belum punya akun? 
                    <Link 
                        :href="route('register')" 
                        class="ms-1 text-indigo-600 hover:text-indigo-800 transition-colors underline decoration-2 underline-offset-4 font-black"
                    >
                        Silahkan Mendaftar
                    </Link>
                </p>
            </div>
            
            <!--<Link :href="route('password.request.custom')">Lupa Password?</Link>-->

            <div class="mt-6 p-4 rounded-2xl border transition-all duration-500 flex items-center gap-3"
                 :class="form.latitude ? 'bg-emerald-50 border-emerald-100' : 'bg-gray-50 border-gray-100'">
                
                <div class="h-2 w-2 rounded-full transition-all duration-500" 
                    :class="[
                        form.latitude ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'bg-red-500', 
                        isLockingGPS ? 'animate-ping' : ''
                    ]"
                ></div>

                <div class="flex flex-col">
                    <p class="text-[9px] font-black uppercase leading-tight" 
                       :class="form.latitude ? 'text-emerald-700' : 'text-gray-400'">
                        {{ form.latitude ? 'Koordinat Terkunci' : (gpsError ? 'Akses Terblokir' : 'Status Lokasi Aktif') }}
                    </p>
                    <p class="text-[8px] font-bold text-gray-400 uppercase mt-0.5 tracking-tight">
                        {{ gpsError || (form.latitude ? `Laporan lokasi terverifikasi: ${form.latitude.toFixed(4)}, ${form.longitude.toFixed(4)}` : 'GPS Wajib Aktif sebagai instrumen verifikasi keamanan.') }}
                    </p>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>