<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Swal from 'sweetalert2';
import { animate, stagger } from 'framer-motion/dom';
import { reduced, T, applyMotionSetting } from '@/motion';

const props = defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    settings: { type: Object },
    redirect: { type: String, default: null },
});

const page = usePage();

const targetRedirect = computed(() => {
    if (props.redirect) return props.redirect;
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('redirect') || null;
    }
    return null;
});

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

onMounted(() => {
    if (page.props.flash?.error) {
        Swal.fire({
            icon: 'error',
            title: 'AUTENTIKASI GOOGLE GAGAL',
            text: page.props.flash.error,
            confirmButtonColor: '#dc2626',
            background: 'rgba(15, 23, 42, 0.92)',
            color: '#ffffff',
            customClass: {
                popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                confirmButton: 'bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-black/30'
            },
        });
    } else if (page.props.flash?.message) {
        Swal.fire({
            icon: 'success',
            title: 'AKTIVASI OTORITAS AKUN BERHASIL',
            text: page.props.flash.message,
            confirmButtonText: 'SIAP LOGIN',
            confirmButtonColor: '#2563eb',
            background: 'rgba(15, 23, 42, 0.92)',
            color: '#ffffff',
            customClass: {
                popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                confirmButton: 'bg-blue-600 hover:bg-blue-500 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-black/30'
            },
        });
    }
});

const isLockingGPS = ref(false);
const gpsWarning = ref(null);

const form = useForm({
    email: '',
    password: '',
    remember: false,
    latitude: null,
    longitude: null,
    redirect: '',
});

/**
 * FUNGSI: Deteksi GPS Kedinasan & Peringatan Lokasi
 */
const showLocationWarning = (resolve, lat = null, lng = null) => {
    isLockingGPS.value = false;
    form.latitude = lat;
    form.longitude = lng;
    gpsWarning.value = "Anda belum mengaktifkan lokasi";

    Swal.fire({
        icon: 'warning',
        title: 'PERINGATAN LOKASI',
        text: 'Anda belum mengaktifkan lokasi',
        showCancelButton: true,
        confirmButtonText: 'Lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#475569',
        background: 'rgba(15, 23, 42, 0.95)',
        color: '#ffffff',
        customClass: {
            popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
            confirmButton: 'bg-blue-600 hover:bg-blue-500 text-white font-black text-xs uppercase tracking-widest px-6 py-3 rounded-xl shadow-lg shadow-black/30',
            cancelButton: 'bg-slate-700 hover:bg-slate-600 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-xl'
        },
    }).then((result) => {
        if (result.isConfirmed) {
            resolve();
        } else {
            form.latitude = null;
            form.longitude = null;
        }
    });
};

const lockLocation = () => {
    return new Promise((resolve) => {
        isLockingGPS.value = true;
        gpsWarning.value = null;

        if (!navigator.geolocation) {
            showLocationWarning(resolve);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                const isMocked = position.mocked || (position.coords && position.coords.isMocked);

                // Jika terdeteksi mocked eksplisit atau akurasi perkiraan kasar (> 250m seperti di PC/laptop)
                if (isMocked || accuracy > 250 || !lat || !lng) {
                    showLocationWarning(resolve, lat, lng);
                    return;
                }

                form.latitude = lat;
                form.longitude = lng;
                isLockingGPS.value = false;
                resolve();
            },
            (error) => {
                showLocationWarning(resolve);
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );
    });
};

const submit = async () => {
    try {
        await lockLocation();

        const currentRedirect = targetRedirect.value;
        form.redirect = currentRedirect;

        const postRoute = currentRedirect 
            ? route('login', { redirect: currentRedirect })
            : route('login');

        form.post(postRoute, {
            onFinish: () => form.reset('password'),
        });
    } catch (e) {
        console.warn("Autentikasi dihentikan:", e);
    }
};

/* =====================================================================
   TAMPILAN & GERAK (Framer Motion) — hanya visual, logika login di atas
   tidak berubah.
   ===================================================================== */
const showPassword = ref(false);
const cardEl = ref(null);
const tiltEl = ref(null);
const logoEl = ref(null);
const sceneEl = ref(null);

// Entrance bergaya iOS: latar → logo → judul → kartu (blur-in + spring) → field.
const playEntrance = () => {
    if (reduced()) return;
    const blur = { filter: ['blur(12px)', 'blur(0px)'] };
    const hero = sceneEl.value?.querySelectorAll('[data-hero]') || [];
    const fields = cardEl.value?.querySelectorAll('[data-field]') || [];
    animate(sceneEl.value?.querySelectorAll('[data-aurora]') || [], { opacity: [0, 1] }, { duration: 1.2 });
    if (logoEl.value) {
        animate(logoEl.value, { opacity: [0, 1], scale: [0.6, 1], ...blur }, { type: 'spring', duration: 0.9, bounce: 0.3 });
    }
    animate(hero, { opacity: [0, 1], y: [16, 0], ...blur }, { ...T.enter, delay: stagger(0.08, { startDelay: 0.25 }) });
    if (cardEl.value) {
        animate(cardEl.value, { opacity: [0, 1], y: [48, 0], scale: [0.94, 1], ...blur }, { ...T.enter, duration: 0.8, delay: 0.15 });
    }
    animate(fields, { opacity: [0, 1], y: [14, 0] }, { ...T.enter, delay: stagger(0.05, { startDelay: 0.35 }) })
        .then(() => fields.forEach((f) => f.style.removeProperty('transform')));
};

// Kartu sedikit miring mengikuti kursor + logo paralaks (seperti efek kedalaman iOS).
let rafId = 0;
const onPointerMove = (e) => {
    if (reduced() || window.matchMedia('(pointer: coarse)').matches) return;
    cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(() => {
        const nx = e.clientX / window.innerWidth - 0.5;
        const ny = e.clientY / window.innerHeight - 0.5;
        if (tiltEl.value) {
            animate(tiltEl.value, { rotateY: nx * 5, rotateX: -ny * 5 }, { type: 'spring', duration: 0.8, bounce: 0 });
        }
        if (logoEl.value) {
            animate(logoEl.value, { x: nx * 22, y: ny * 18 }, { type: 'spring', duration: 1, bounce: 0 });
        }
        sceneEl.value?.style.setProperty('--px', `${(nx + 0.5) * 100}%`);
        sceneEl.value?.style.setProperty('--py', `${(ny + 0.5) * 100}%`);
    });
};
const onPointerLeave = () => {
    if (tiltEl.value) animate(tiltEl.value, { rotateX: 0, rotateY: 0 }, { type: 'spring', duration: 0.8, bounce: 0.1 });
};

// Getar halus saat login ditolak (umpan balik error yang jelas, bukan sekadar teks merah).
watch(() => [form.errors.email, form.errors.password], ([a, b]) => {
    if ((a || b) && cardEl.value && !reduced()) {
        animate(cardEl.value, { x: [0, -10, 9, -6, 4, 0] }, { duration: 0.45, ease: 'easeOut' })
            .then(() => cardEl.value?.style.removeProperty('transform'));
    }
});

onMounted(() => {
    // Hormati saklar Admin "Animasi Interaktif" (Pengaturan → Tampilan Web).
    applyMotionSetting(String(pageSettings.value.ui_motion_enabled ?? '1') !== '0');
    playEntrance();
    window.addEventListener('pointermove', onPointerMove, { passive: true });
    document.addEventListener('pointerleave', onPointerLeave);
});
onUnmounted(() => {
    window.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerleave', onPointerLeave);
    cancelAnimationFrame(rafId);
});
</script>

<template>
    <Head title="Otoritas Login - SINDEN" />

    <div
        ref="sceneEl"
        class="login-scene min-h-screen flex text-slate-100 font-sans bg-cover bg-center relative overflow-hidden bg-[#050b1a]"
        :style="loginBg ? { backgroundImage: `url(${loginBg})` } : {}"
    >
        <!-- Latar: aurora bergerak + sorotan mengikuti kursor -->
        <div class="absolute inset-0 z-0" aria-hidden="true">
            <div class="absolute inset-0" :class="loginBg ? 'bg-[#050b1a]/80 backdrop-blur-[3px]' : ''"></div>
            <div data-aurora class="aurora aurora-1"></div>
            <div data-aurora class="aurora aurora-2"></div>
            <div data-aurora class="aurora aurora-3"></div>
            <div class="login-spotlight"></div>
        </div>

        <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">

            <!-- Kolom kiri: identitas -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-16 z-10">
                <div></div>

                <div class="my-auto max-w-lg flex flex-col items-center text-center mx-auto">
                    <div ref="logoEl" class="relative mb-8">
                        <div class="logo-halo" aria-hidden="true"></div>
                        <img v-if="configuredLogo" :src="configuredLogo" class="relative h-40 lg:h-44 object-contain drop-shadow-[0_18px_30px_rgba(0,0,0,0.55)] select-none" alt="Logo SINDEN" draggable="false" />
                        <div v-else class="relative w-32 h-32 rounded-[2rem] bg-white/10 backdrop-blur-xl border border-white/20 flex items-center justify-center shadow-[0_20px_50px_-12px_rgba(0,0,0,0.6)]">
                            <span class="font-black text-white text-5xl">S</span>
                        </div>
                    </div>

                    <h2 data-hero class="text-[2.6rem] leading-[1.05] font-black tracking-tight uppercase text-white text-balance">
                        {{ appName }}
                    </h2>
                    <p data-hero class="mt-4 text-sm leading-relaxed max-w-md mx-auto text-slate-300/90 font-medium text-pretty">
                        Sistem Informasi Detasemen Intelijen Integrasi Otoritas Akses, Pengawasan Lokasi, Administrasi Surat &amp; Logistik Terpadu.
                    </p>
                </div>

                <p data-hero class="text-xs text-slate-400 text-center font-medium">
                    © {{ new Date().getFullYear() }} {{ appName }}. All Rights Reserved.
                </p>
            </div>

            <!-- Kolom kanan: kartu login -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-5 sm:p-10 z-10 my-auto login-stage">
                <div ref="tiltEl" class="w-full max-w-md login-tilt">
                    <div ref="cardEl" class="login-card relative w-full p-7 sm:p-9 rounded-[1.75rem] text-white">

                        <!-- Logo kecil untuk layar sempit -->
                        <div data-field class="lg:hidden flex items-center gap-3 mb-6">
                            <img v-if="configuredLogo" :src="configuredLogo" class="h-11 w-11 object-contain" alt="" />
                            <div v-else class="h-11 w-11 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center font-black">S</div>
                            <span class="font-black tracking-tight uppercase text-sm truncate">{{ appName }}</span>
                        </div>

                        <div data-field class="mb-7">
                            <h1 class="text-[1.65rem] leading-tight font-extrabold tracking-tight text-white">Masuk Akun</h1>
                            <p class="text-sm mt-1.5 text-slate-300/90">
                                Gunakan akun internal Anda untuk mengakses sistem dashboard SINDEN.
                            </p>
                        </div>

                        <!-- Indikator Akses Tautan Berbagi Terproteksi -->
                        <div v-if="targetRedirect && targetRedirect.includes('shared-folder')" data-field class="mb-6 p-3.5 rounded-2xl bg-blue-500/10 border border-blue-400/25 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0 text-blue-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div class="text-xs text-blue-100 leading-relaxed">
                                <span class="font-semibold block text-white text-xs">Akses Tautan Berbagi Terproteksi</span>
                                Silakan masuk dengan akun dinas Anda untuk membuka folder.
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4" novalidate>
                            <!-- Identitas (label mengambang) -->
                            <div data-field>
                                <div class="float-field" :class="{ 'has-error': form.errors.email }">
                                    <input
                                        id="login-identity"
                                        type="text"
                                        v-model="form.email"
                                        placeholder=" "
                                        autocomplete="username"
                                        autocapitalize="none"
                                        spellcheck="false"
                                        required
                                        autofocus
                                    />
                                    <label for="login-identity">NRP / Username / Email</label>
                                    <svg class="field-ico" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21a8 8 0 0 1 16 0"/></svg>
                                </div>
                                <InputError class="mt-1.5 text-xs text-rose-300 font-medium" :message="form.errors.email" />
                            </div>

                            <!-- Kata kunci -->
                            <div data-field>
                                <div class="float-field" :class="{ 'has-error': form.errors.password }">
                                    <input
                                        id="login-password"
                                        :type="showPassword ? 'text' : 'password'"
                                        v-model="form.password"
                                        placeholder=" "
                                        autocomplete="current-password"
                                        required
                                    />
                                    <label for="login-password">Kata Kunci</label>
                                    <svg class="field-ico" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                    <button
                                        type="button"
                                        class="pw-toggle"
                                        @click="showPassword = !showPassword"
                                        :aria-label="showPassword ? 'Sembunyikan kata kunci' : 'Tampilkan kata kunci'"
                                        :aria-pressed="showPassword"
                                    >
                                        <svg v-if="!showPassword" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg v-else class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/></svg>
                                    </button>
                                </div>
                                <InputError class="mt-1.5 text-xs text-rose-300 font-medium" :message="form.errors.password" />
                            </div>

                            <div data-field class="flex items-center justify-between pt-0.5">
                                <label class="ios-check flex items-center gap-2.5 text-sm text-slate-300 select-none cursor-pointer">
                                    <input type="checkbox" v-model="form.remember" class="sr-only peer" />
                                    <span class="ios-switch" aria-hidden="true"></span>
                                    Ingat Saya
                                </label>
                                <Link v-if="canResetPassword" :href="route('password.request.custom')" class="text-sm font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">
                                    Lupa Password?
                                </Link>
                            </div>

                            <div data-field>
                                <button
                                    type="submit"
                                    :disabled="form.processing || isLockingGPS"
                                    class="login-submit w-full h-12 px-4 text-white text-sm font-bold rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait cursor-pointer flex justify-center items-center gap-2"
                                >
                                    <Transition name="swap" mode="out-in">
                                        <span v-if="isLockingGPS" key="gps" class="flex items-center gap-2">
                                            <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            Mengunci GPS...
                                        </span>
                                        <span v-else-if="form.processing" key="proc" class="flex items-center gap-2">
                                            <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
                                            Memverifikasi...
                                        </span>
                                        <span v-else key="idle" class="flex items-center gap-2">
                                            Masuk Sistem
                                            <svg class="w-4 h-4 arrow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                                        </span>
                                    </Transition>
                                </button>
                            </div>

                            <!-- Opsi Masuk dengan Akun Google -->
                            <div v-if="pageSettings.google_login_enabled !== false" data-field>
                                <div class="relative my-1 flex items-center gap-3">
                                    <div class="h-px bg-white/12 flex-1"></div>
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 select-none">Atau</span>
                                    <div class="h-px bg-white/12 flex-1"></div>
                                </div>

                                <a
                                    :href="route('auth.google.redirect', { redirect: targetRedirect, action: 'login' })"
                                    class="mt-3 w-full h-12 px-4 bg-white hover:bg-slate-100 text-slate-800 text-sm font-semibold rounded-2xl shadow-[0_8px_24px_-12px_rgba(0,0,0,0.6)] transition flex justify-center items-center gap-2.5 cursor-pointer select-none"
                                >
                                    <svg class="w-[18px] h-[18px] shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                                    </svg>
                                    <span>Masuk dengan Akun Google</span>
                                </a>
                            </div>
                        </form>

                        <!-- Status verifikasi GPS -->
                        <div data-field class="mt-5 p-3 rounded-2xl border transition-colors duration-500 flex items-center gap-3"
                             :class="form.latitude ? 'bg-emerald-500/10 border-emerald-400/30' : (gpsWarning ? 'bg-amber-500/10 border-amber-400/30' : 'bg-white/[0.04] border-white/10')"
                             role="status" aria-live="polite">
                            <span class="gps-dot relative h-2.5 w-2.5 rounded-full shrink-0"
                                :class="[
                                    form.latitude ? 'bg-emerald-400 is-on' : (gpsWarning ? 'bg-amber-400' : 'bg-slate-400'),
                                    isLockingGPS ? 'is-scanning' : ''
                                ]"
                            ></span>
                            <div class="flex flex-col overflow-hidden text-left">
                                <p class="text-[11px] font-bold uppercase tracking-wide leading-tight"
                                   :class="form.latitude ? 'text-emerald-300' : (gpsWarning ? 'text-amber-300' : 'text-slate-300')">
                                    {{ form.latitude ? 'GPS Presisi Terkunci' : (gpsWarning ? 'Peringatan Lokasi' : 'Verifikasi GPS Sistem') }}
                                </p>
                                <p class="text-[11px] text-slate-400 mt-0.5 truncate tabular-nums">
                                    {{ gpsWarning || (form.latitude ? `Koordinat: ${form.latitude.toFixed(4)}, ${form.longitude.toFixed(4)}` : 'Disarankan mengaktifkan GPS') }}
                                </p>
                            </div>
                        </div>

                        <!-- Fitur Publik: Pelacakan Status Pengajuan SC (Tanpa Login) -->
                        <div data-field class="mt-3">
                            <Link
                                :href="route('tracking-sc.index')"
                                class="w-full p-3 bg-white/[0.04] hover:bg-white/[0.08] text-white rounded-2xl border border-white/10 hover:border-sky-400/40 transition flex items-center justify-between group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-sky-500/15 border border-sky-400/25 text-sky-300 flex items-center justify-center group-hover:bg-sky-500 group-hover:text-white transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                        </svg>
                                    </div>
                                    <div class="text-left">
                                        <span class="block text-white text-[13px] font-bold">Cek Riwayat Pengajuan SC</span>
                                        <span class="block text-[11px] text-slate-400 font-medium">Layanan publik mandiri via NRP / NIP / NIK</span>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-slate-400 group-hover:text-sky-300 group-hover:translate-x-1 transition duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </Link>
                        </div>

                        <div data-field class="mt-6 text-center border-t border-white/10 pt-5 flex flex-wrap gap-2 justify-between text-sm text-slate-300">
                            <Link href="/aktivasi" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Aktivasi Akun</Link>
                            <span>Belum punya akun? <Link :href="route('register')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Daftar Sekarang</Link></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ---------- Latar aurora ---------- */
.aurora {
    position: absolute; border-radius: 50%; filter: blur(90px); opacity: .55;
    animation: aurora-move 22s ease-in-out infinite alternate;
    will-change: transform;
}
.aurora-1 { width: 60vw; height: 60vw; left: -18vw; top: -24vw; background: radial-gradient(circle, #1d4ed8 0%, transparent 65%); }
.aurora-2 { width: 50vw; height: 50vw; right: -14vw; top: 10vh; background: radial-gradient(circle, #4f46e5 0%, transparent 65%); animation-delay: -7s; animation-duration: 26s; }
.aurora-3 { width: 46vw; height: 46vw; left: 28vw; bottom: -26vw; background: radial-gradient(circle, #0d9488 0%, transparent 65%); opacity: .4; animation-delay: -13s; animation-duration: 30s; }
@keyframes aurora-move {
    0%   { transform: translate3d(0, 0, 0) scale(1) rotate(0deg); }
    50%  { transform: translate3d(6vw, 4vh, 0) scale(1.12) rotate(20deg); }
    100% { transform: translate3d(-4vw, -3vh, 0) scale(.96) rotate(-10deg); }
}
.login-spotlight {
    position: absolute; inset: 0;
    background: radial-gradient(600px circle at var(--px, 70%) var(--py, 40%), rgba(96, 165, 250, .14), transparent 55%);
    transition: background .2s linear;
}

/* ---------- Logo ---------- */
.logo-halo {
    position: absolute; inset: -30%; border-radius: 50%;
    background: radial-gradient(circle, rgba(59, 130, 246, .35), transparent 65%);
    filter: blur(20px);
    animation: halo 6s ease-in-out infinite;
}
@keyframes halo { 0%, 100% { opacity: .55; transform: scale(.95); } 50% { opacity: .9; transform: scale(1.06); } }

/* ---------- Kartu kaca ---------- */
.login-stage { perspective: 1200px; }
.login-tilt { transform-style: preserve-3d; will-change: transform; }
.login-card {
    background: linear-gradient(160deg, rgba(30, 41, 70, .72), rgba(10, 17, 36, .78));
    -webkit-backdrop-filter: blur(28px) saturate(1.6);
    backdrop-filter: blur(28px) saturate(1.6);
    border: 1px solid rgba(255, 255, 255, .12);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, .12),
        0 30px 80px -24px rgba(0, 0, 0, .75),
        0 8px 24px -12px rgba(2, 6, 23, .6);
}
/* garis cahaya tipis di tepi atas kartu */
.login-card::before {
    content: ''; position: absolute; left: 12%; right: 12%; top: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(147, 197, 253, .7), transparent);
}

/* ---------- Field label mengambang ---------- */
.float-field { position: relative; }
.float-field input {
    width: 100%; height: 3.5rem; padding: 1.35rem 3rem .45rem 2.9rem;
    border-radius: 1rem; font-size: .95rem; color: #fff;
    background: rgba(255, 255, 255, .06);
    border: 1px solid rgba(255, 255, 255, .12);
    outline: none;
    transition: border-color .25s cubic-bezier(.22,1,.36,1), background-color .25s, box-shadow .3s cubic-bezier(.22,1,.36,1);
}
.float-field input:hover { background: rgba(255, 255, 255, .08); }
.float-field input:focus {
    border-color: rgba(96, 165, 250, .85);
    background: rgba(255, 255, 255, .09);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, .22);
}
.float-field label {
    position: absolute; left: 2.9rem; top: 50%; transform: translateY(-50%);
    font-size: .9rem; color: #94a3b8; pointer-events: none; transform-origin: left center;
    transition: transform .3s cubic-bezier(.22,1,.36,1), color .2s, top .3s cubic-bezier(.22,1,.36,1);
}
.float-field input:focus + label,
.float-field input:not(:placeholder-shown) + label {
    top: .95rem; transform: translateY(-50%) scale(.78); color: #93c5fd; font-weight: 600; letter-spacing: .01em;
}
.float-field .field-ico {
    position: absolute; left: 1rem; top: 50%; width: 18px; height: 18px; transform: translateY(-50%);
    color: #94a3b8; transition: color .2s, transform .35s cubic-bezier(.16,1,.3,1); pointer-events: none;
}
.float-field input:focus ~ .field-ico { color: #93c5fd; transform: translateY(-50%) scale(1.1); }
.float-field.has-error input { border-color: rgba(251, 113, 133, .75); }
.float-field input:-webkit-autofill {
    -webkit-text-fill-color: #fff;
    -webkit-box-shadow: 0 0 0 1000px #17213a inset;
    caret-color: #fff;
}
.pw-toggle {
    position: absolute; right: .55rem; top: 50%; transform: translateY(-50%);
    width: 2.5rem; height: 2.5rem; display: grid; place-items: center; border-radius: .8rem;
    color: #94a3b8; transition: color .2s, background-color .2s;
}
.pw-toggle:hover { color: #fff; background: rgba(255, 255, 255, .08); }

/* ---------- Saklar "Ingat Saya" bergaya iOS ---------- */
.ios-switch {
    position: relative; width: 2.6rem; height: 1.55rem; border-radius: 999px; flex-shrink: 0;
    background: rgba(255, 255, 255, .16); transition: background-color .3s cubic-bezier(.22,1,.36,1);
}
.ios-switch::after {
    content: ''; position: absolute; top: 2px; left: 2px; width: calc(1.55rem - 4px); height: calc(1.55rem - 4px);
    border-radius: 50%; background: #fff; box-shadow: 0 2px 6px rgba(0, 0, 0, .35);
    transition: transform .45s cubic-bezier(.16,1,.3,1), width .2s;
}
.ios-check:active .ios-switch::after { width: calc(1.55rem + 2px); }
.peer:checked + .ios-switch { background: #2563eb; }
.peer:checked + .ios-switch::after { transform: translateX(1.05rem); }
.ios-check:active .peer:checked + .ios-switch::after { transform: translateX(.95rem); }
.peer:focus-visible + .ios-switch { outline: 3px solid #60a5fa; outline-offset: 2px; }

/* ---------- Tombol masuk ---------- */
.login-submit {
    box-shadow: 0 10px 24px -12px rgba(0, 0, 0, .7), inset 0 1px 0 rgba(255, 255, 255, .25);
}
.login-submit .arrow { transition: transform .35s cubic-bezier(.16,1,.3,1); }
.login-submit:hover .arrow { transform: translateX(4px); }
.swap-enter-active, .swap-leave-active { transition: opacity .18s ease, transform .25s cubic-bezier(.22,1,.36,1), filter .2s; }
.swap-enter-from { opacity: 0; transform: translateY(8px); filter: blur(4px); }
.swap-leave-to { opacity: 0; transform: translateY(-8px); filter: blur(4px); }
.dots { display: inline-flex; gap: 3px; }
.dots i { width: 5px; height: 5px; border-radius: 50%; background: currentColor; animation: dot 1s ease-in-out infinite; }
.dots i:nth-child(2) { animation-delay: .15s; } .dots i:nth-child(3) { animation-delay: .3s; }
@keyframes dot { 0%, 80%, 100% { opacity: .35; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }

/* ---------- Titik GPS ---------- */
.gps-dot.is-on::after, .gps-dot.is-scanning::after {
    content: ''; position: absolute; inset: -5px; border-radius: 50%;
    border: 2px solid currentColor; color: inherit; animation: ring 1.6s ease-out infinite;
}
.gps-dot.is-on { color: #34d399; }
.gps-dot.is-scanning { color: #93c5fd; }
@keyframes ring { from { opacity: .8; transform: scale(.6); } to { opacity: 0; transform: scale(1.8); } }

@media (prefers-reduced-motion: reduce) {
    .aurora, .logo-halo, .dots i, .gps-dot::after { animation: none !important; }
    .login-spotlight { display: none; }
}
</style>
