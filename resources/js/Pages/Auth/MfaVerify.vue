<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { animate, stagger } from 'framer-motion/dom';
import { reduced, T, applyMotionSetting } from '@/motion';

const props = defineProps({
    maskedPhone: String,
    expiresAt: Number,
    userName: String,
    userPangkat: String,
    errors: Object,
    status: String,
});

const form = useForm({
    otp: '',
});

const resendForm = useForm({});
const isResending = ref(false);

// Countdown timer
const timeLeft = ref(300); // 5 menit default
let timerInterval = null;

const formatTime = computed(() => {
    const minutes = Math.floor(timeLeft.value / 60);
    const seconds = timeLeft.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const canResend = computed(() => timeLeft.value <= 240); // Boleh kirim ulang setelah 1 menit

const submitVerify = () => {
    form.post(route('mfa.verify.submit'));
};

const resendOtp = () => {
    if (isResending.value) return;
    isResending.value = true;
    resendForm.post(route('mfa.resend'), {
        onFinish: () => {
            isResending.value = false;
            timeLeft.value = 300;
        }
    });
};

/* =====================================================================
   TAMPILAN & GERAK — disamakan dengan halaman Login (Auth/Login.vue):
   latar aurora, kolom identitas, kartu kaca. Logika OTP di atas tetap.
   ===================================================================== */
const page = usePage();
const pageSettings = computed(() => page.props.settings || {});
const appName = computed(() => pageSettings.value.agency_name || pageSettings.value.app_name || 'SINDEN');
const storageUrl = (path) => {
    if (!path) return null;
    return (path.startsWith('http') || path.startsWith('/storage')) ? path : '/storage/' + path;
};
const configuredLogo = computed(() => storageUrl(pageSettings.value.agency_logo || pageSettings.value.logo || pageSettings.value.logo_tni));
const loginBg = computed(() => storageUrl(pageSettings.value.login_background));

const cardEl = ref(null);
const tiltEl = ref(null);
const logoEl = ref(null);
const sceneEl = ref(null);

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

// Getar halus saat kode ditolak, sama seperti login.
watch(() => form.errors.otp, (err) => {
    if (err && cardEl.value && !reduced()) {
        animate(cardEl.value, { x: [0, -10, 9, -6, 4, 0] }, { duration: 0.45, ease: 'easeOut' })
            .then(() => cardEl.value?.style.removeProperty('transform'));
    }
});

onMounted(() => {
    if (props.expiresAt) {
        timeLeft.value = Math.max(0, props.expiresAt - Math.floor(Date.now() / 1000));
    }
    // Interval dibiarkan berjalan agar hitung mundur hidup lagi setelah kirim ulang.
    timerInterval = setInterval(() => {
        if (timeLeft.value > 0) timeLeft.value--;
    }, 1000);

    applyMotionSetting(String(pageSettings.value.ui_motion_enabled ?? '1') !== '0');
    playEntrance();
    window.addEventListener('pointermove', onPointerMove, { passive: true });
    document.addEventListener('pointerleave', onPointerLeave);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    window.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerleave', onPointerLeave);
    cancelAnimationFrame(rafId);
});
</script>

<template>
    <Head title="Verifikasi 2FA WhatsApp Kedinasan" />

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

            <!-- Kolom kanan: kartu verifikasi -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-5 sm:p-10 z-10 my-auto login-stage">
                <div ref="tiltEl" class="w-full max-w-md login-tilt">
                    <div ref="cardEl" class="login-card relative w-full p-7 sm:p-9 rounded-[1.75rem] text-white">

                        <!-- Logo kecil untuk layar sempit -->
                        <div data-field class="lg:hidden flex items-center gap-3 mb-6">
                            <img v-if="configuredLogo" :src="configuredLogo" class="h-11 w-11 object-contain" alt="" />
                            <div v-else class="h-11 w-11 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center font-black">S</div>
                            <span class="font-black tracking-tight uppercase text-sm truncate">{{ appName }}</span>
                        </div>

                        <div data-field class="mb-6">
                            <h1 class="text-[1.65rem] leading-tight font-extrabold tracking-tight text-white">Verifikasi Dua Langkah</h1>
                            <p class="text-sm mt-1.5 text-slate-300/90">
                                Masuk sebagai <span class="font-semibold text-white">{{ userPangkat }} {{ userName }}</span>. Satu langkah lagi untuk mengakses dashboard SINDEN.
                            </p>
                        </div>

                        <!-- Status (mis. kode berhasil dikirim ulang) -->
                        <div v-if="status" data-field class="mb-4 p-3 rounded-2xl bg-emerald-500/10 border border-emerald-400/30 text-xs text-emerald-200 font-medium" role="status">
                            {{ status }}
                        </div>

                        <!-- Info pengiriman kode -->
                        <div data-field class="mb-6 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-400/25 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/20 flex items-center justify-center shrink-0 text-emerald-300">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12.04 2a9.9 9.9 0 0 0-8.5 14.97L2 22l5.17-1.5A9.9 9.9 0 1 0 12.04 2zm0 1.8a8.1 8.1 0 1 1-4.2 15.03l-.3-.18-3 .87.9-2.9-.2-.31A8.1 8.1 0 0 1 12.04 3.8zM8.6 7.3c-.2 0-.5.07-.75.35-.26.28-.98.96-.98 2.34s1 2.71 1.15 2.9c.14.19 1.95 3.1 4.8 4.22 2.37.93 2.85.75 3.37.7.51-.05 1.66-.68 1.9-1.33.23-.66.23-1.22.16-1.34-.07-.11-.26-.18-.54-.32-.28-.14-1.66-.82-1.92-.91-.26-.1-.44-.14-.63.14-.19.28-.73.91-.9 1.1-.16.19-.33.21-.6.07-.29-.14-1.2-.44-2.28-1.4a8.5 8.5 0 0 1-1.58-1.97c-.16-.28-.02-.43.12-.57.13-.13.28-.33.42-.5.14-.16.19-.28.28-.47.1-.19.05-.35-.02-.5-.07-.13-.62-1.53-.87-2.09-.22-.5-.45-.42-.63-.42H8.6z"/>
                                </svg>
                            </div>
                            <div class="text-xs text-emerald-100/90 leading-relaxed">
                                <span class="font-semibold block text-white text-xs">Kode OTP terkirim via WhatsApp</span>
                                Kode 6 digit dikirim ke <span class="font-semibold text-white tabular-nums">{{ maskedPhone }}</span>
                            </div>
                        </div>

                        <form @submit.prevent="submitVerify" class="space-y-4" novalidate>
                            <div data-field>
                                <label for="mfa-otp" class="block mb-2 text-xs font-semibold text-slate-300">Kode OTP 6 digit</label>
                                <input
                                    id="mfa-otp"
                                    type="text"
                                    v-model="form.otp"
                                    maxlength="6"
                                    pattern="[0-9]*"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    autofocus
                                    placeholder="••••••"
                                    class="otp-input"
                                    :class="{ 'has-error': form.errors.otp }"
                                    :aria-invalid="!!form.errors.otp"
                                    required
                                />
                                <p v-if="form.errors.otp" class="mt-1.5 text-xs text-rose-300 font-medium" role="alert">
                                    {{ form.errors.otp }}
                                </p>
                            </div>

                            <!-- Masa berlaku -->
                            <div data-field class="p-3 rounded-2xl border transition-colors duration-500 flex items-center justify-between gap-3"
                                 :class="timeLeft < 60 ? 'bg-rose-500/10 border-rose-400/30' : 'bg-white/[0.04] border-white/10'"
                                 role="timer">
                                <span class="text-[11px] font-bold uppercase tracking-wide"
                                      :class="timeLeft < 60 ? 'text-rose-300' : 'text-slate-300'">
                                    {{ timeLeft > 0 ? 'Masa berlaku kode' : 'Kode kedaluwarsa' }}
                                </span>
                                <span class="text-sm font-bold tabular-nums" :class="timeLeft < 60 ? 'text-rose-300' : 'text-sky-300'">
                                    {{ formatTime }}
                                </span>
                            </div>

                            <div data-field>
                                <button
                                    type="submit"
                                    :disabled="form.processing || form.otp.length < 6"
                                    class="login-submit w-full h-12 px-4 text-white text-sm font-bold rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer flex justify-center items-center gap-2"
                                >
                                    <Transition name="swap" mode="out-in">
                                        <span v-if="form.processing" key="proc" class="flex items-center gap-2">
                                            <span class="dots" aria-hidden="true"><i></i><i></i><i></i></span>
                                            Memverifikasi...
                                        </span>
                                        <span v-else key="idle" class="flex items-center gap-2">
                                            Verifikasi &amp; Masuk
                                            <svg class="w-4 h-4 arrow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                                        </span>
                                    </Transition>
                                </button>
                            </div>
                        </form>

                        <div data-field class="mt-6 border-t border-white/10 pt-5 flex flex-wrap gap-2 items-center justify-between text-sm text-slate-300">
                            <Link :href="route('login')" class="font-semibold text-slate-300 hover:text-white underline-offset-4 hover:underline">
                                &larr; Batalkan
                            </Link>
                            <button
                                type="button"
                                @click="resendOtp"
                                :disabled="!canResend || isResending"
                                class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline cursor-pointer disabled:text-slate-500 disabled:no-underline disabled:cursor-not-allowed"
                            >
                                {{ isResending ? 'Mengirim ulang...' : 'Kirim Ulang Kode' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Gaya di bawah ini sengaja sama dengan Auth/Login.vue agar kedua layar seragam. */

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
.login-card::before {
    content: ''; position: absolute; left: 12%; right: 12%; top: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(147, 197, 253, .7), transparent);
}

/* ---------- Kolom OTP (permukaan sama dengan field login) ---------- */
.otp-input {
    width: 100%; height: 4rem; padding: 0 0 0 .5em; /* padding kiri mengimbangi letter-spacing agar tetap di tengah */
    border-radius: 1rem; text-align: center; color: #fff;
    font-size: 1.75rem; font-weight: 700; letter-spacing: .5em; font-variant-numeric: tabular-nums;
    background: rgba(255, 255, 255, .06);
    border: 1px solid rgba(255, 255, 255, .12);
    outline: none;
    transition: border-color .25s cubic-bezier(.22,1,.36,1), background-color .25s, box-shadow .3s cubic-bezier(.22,1,.36,1);
}
.otp-input::placeholder { color: rgba(148, 163, 184, .45); }
.otp-input:hover { background: rgba(255, 255, 255, .08); }
.otp-input:focus {
    border-color: rgba(96, 165, 250, .85);
    background: rgba(255, 255, 255, .09);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, .22);
}
.otp-input.has-error { border-color: rgba(251, 113, 133, .75); }

/* ---------- Tombol ---------- */
.login-submit {
    box-shadow: 0 10px 24px -12px rgba(0, 0, 0, .7), inset 0 1px 0 rgba(255, 255, 255, .25);
}
.login-submit .arrow { transition: transform .35s cubic-bezier(.16,1,.3,1); }
.login-submit:hover:not(:disabled) .arrow { transform: translateX(4px); }
.swap-enter-active, .swap-leave-active { transition: opacity .18s ease, transform .25s cubic-bezier(.22,1,.36,1), filter .2s; }
.swap-enter-from { opacity: 0; transform: translateY(8px); filter: blur(4px); }
.swap-leave-to { opacity: 0; transform: translateY(-8px); filter: blur(4px); }
.dots { display: inline-flex; gap: 3px; }
.dots i { width: 5px; height: 5px; border-radius: 50%; background: currentColor; animation: dot 1s ease-in-out infinite; }
.dots i:nth-child(2) { animation-delay: .15s; } .dots i:nth-child(3) { animation-delay: .3s; }
@keyframes dot { 0%, 80%, 100% { opacity: .35; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }

@media (prefers-reduced-motion: reduce) {
    .aurora, .logo-halo, .dots i { animation: none !important; }
    .login-spotlight { display: none; }
}
</style>
