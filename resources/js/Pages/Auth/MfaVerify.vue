<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';

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

onMounted(() => {
    if (props.expiresAt) {
        const remaining = Math.max(0, props.expiresAt - Math.floor(Date.now() / 1000));
        timeLeft.value = remaining;
    }

    timerInterval = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            clearInterval(timerInterval);
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

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
</script>

<template>
    <Head title="Verifikasi 2FA WhatsApp Kedinasan" />

    <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 flex flex-col justify-center items-center p-4 sm:p-6 select-none font-sans relative overflow-hidden">
        
        <!-- Background Ambient Glow -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Main Card -->
        <div class="w-full max-w-md bg-slate-900/90 border border-slate-700/60 backdrop-blur-xl rounded-[2.5rem] shadow-2xl p-6 sm:p-8 space-y-6 relative z-10 text-white animate-in zoom-in duration-200">
            
            <!-- Shield Header -->
            <div class="flex flex-col items-center text-center space-y-3">
                <div class="w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/40 flex items-center justify-center text-indigo-400 shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <div>
                    <span class="text-[10px] font-black tracking-widest text-indigo-400 uppercase block">Protokol Keamanan Pimpinan</span>
                    <h2 class="text-lg font-black uppercase tracking-tight text-white mt-1">Autentikasi Dua Langkah (2FA)</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Personel Terdeteksi: <b class="text-slate-200">{{ userPangkat }} {{ userName }}</b>
                    </p>
                </div>
            </div>

            <!-- Status Alert -->
            <div v-if="status" class="p-3 bg-emerald-500/20 border border-emerald-500/40 rounded-2xl text-xs text-emerald-300 font-bold text-center">
                {{ status }}
            </div>

            <!-- Warning Notice -->
            <div class="p-4 bg-slate-800/80 border border-slate-700/50 rounded-2xl space-y-1.5 text-xs text-slate-300">
                <div class="flex items-center gap-2 text-emerald-400 font-bold">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Kode OTP Terkirim via WhatsApp</span>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    Kode verifikasi 6 digit telah dikirimkan ke nomor: <span class="font-mono font-bold text-slate-200">{{ maskedPhone }}</span>.
                </p>
            </div>

            <!-- Form OTP -->
            <form @submit.prevent="submitVerify" class="space-y-5">
                <div class="space-y-2">
                    <label class="block text-center text-xs font-bold uppercase tracking-wider text-slate-300">
                        Masukkan 6-Digit Kode OTP
                    </label>
                    <input 
                        type="text" 
                        v-model="form.otp"
                        maxlength="6"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autofocus
                        placeholder="······"
                        class="w-full text-center text-3xl font-mono font-black tracking-[0.4em] bg-slate-950/80 border-2 border-slate-700 focus:border-indigo-500 rounded-2xl py-3 text-white placeholder-slate-600 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/30 transition"
                        required
                    />
                    <p v-if="form.errors.otp" class="text-xs text-rose-400 text-center font-bold">
                        {{ form.errors.otp }}
                    </p>
                </div>

                <!-- Countdown Info -->
                <div class="flex items-center justify-between text-xs px-2 text-slate-400">
                    <span>Masa Berlaku Kode:</span>
                    <span :class="timeLeft < 60 ? 'text-rose-400 font-black' : 'text-indigo-400 font-bold'" class="font-mono">
                        {{ formatTime }}
                    </span>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    :disabled="form.processing || form.otp.length < 6"
                    class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 active:scale-98 text-white font-black text-xs uppercase tracking-widest rounded-2xl shadow-lg shadow-indigo-600/30 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ form.processing ? 'MEMVERIFIKASI OTORITAS...' : 'VERIFIKASI & MASUK SISTEM' }}
                </button>
            </form>

            <!-- Footer Action -->
            <div class="pt-4 border-t border-slate-800 flex items-center justify-between text-xs">
                <Link :href="route('login')" class="text-slate-400 hover:text-slate-200 transition font-bold">
                    &larr; Batalkan & Keluar
                </Link>
                <button 
                    type="button" 
                    @click="resendOtp"
                    :disabled="!canResend || isResending"
                    class="text-indigo-400 hover:text-indigo-300 font-bold transition disabled:text-slate-600 cursor-pointer disabled:cursor-not-allowed"
                >
                    {{ isResending ? 'Mengirim Ulang...' : 'Kirim Ulang Kode OTP' }}
                </button>
            </div>

        </div>

        <!-- Footer Seal -->
        <p class="mt-6 text-center text-[10px] uppercase font-bold tracking-widest text-slate-500 relative z-10">
            Sistem Informasi Detasemen Intelijen &bull; Dokumen Intelijen Rahasia
        </p>

    </div>
</template>
