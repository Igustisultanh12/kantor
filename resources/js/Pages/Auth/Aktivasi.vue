<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AuthScene from '@/Components/AuthScene.vue';
import AuthField from '@/Components/AuthField.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const scene = ref(null);

const form = useForm({
    nrp: '',
    token: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('aktivasi.proses'), {
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: 'AKTIVASI OTORITAS AKUN BERHASIL',
                text: 'Aktivasi Akun Berhasil! Akun Anda telah aktif. Silakan login menggunakan Email dan Password Anda.',
                confirmButtonText: 'MASUK KE SISTEM',
                confirmButtonColor: '#2563eb',
                background: 'rgba(15, 23, 42, 0.92)',
                color: '#ffffff',
                customClass: {
                    popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                    confirmButton: 'bg-blue-600 hover:bg-blue-500 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-blue-600/20'
                },
                allowOutsideClick: false,
            });
        },
        onError: (errors) => {
            scene.value?.shake();
            const errText = errors.token || errors.nrp || 'NRP / Email atau Token Aktivasi tidak valid.';
            Swal.fire({
                icon: 'error',
                title: 'AKTIVASI OTORITAS GAGAL',
                text: errText,
                confirmButtonText: 'PERIKSA KEMBALI',
                confirmButtonColor: '#dc2626',
                background: 'rgba(15, 23, 42, 0.92)',
                color: '#ffffff',
                customClass: {
                    popup: 'swal2-dark-glass border border-white/10 shadow-2xl rounded-[2rem]',
                    confirmButton: 'bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-red-600/20'
                },
            });
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>


<template>
    <Head title="Aktivasi Akun Personel - SINDEN" />

    <AuthScene ref="scene" tagline="Aktivasi Otoritas Akun Personel — Sistem Informasi Detasemen Intelijen SINDEN.">
        <div data-field class="mb-7">
            <h1 class="text-[1.65rem] leading-tight font-extrabold tracking-tight text-white">Aktivasi Akun</h1>
            <p class="text-sm mt-1.5 text-slate-300/90">
                Masukkan NRP atau Email beserta Token Aktivasi yang dikirim sistem ke WhatsApp Anda.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4" novalidate>
            <AuthField data-field v-model="form.nrp" label="NRP / Email Personel" icon="badge" :error="form.errors.nrp" input-class="uppercase font-semibold" autocomplete="username" autocapitalize="characters" spellcheck="false" required autofocus />
            <AuthField data-field v-model="form.token" label="Token Aktivasi (cek WhatsApp)" icon="key" :error="form.errors.token" input-class="uppercase font-mono font-semibold tracking-wider" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required />

            <div data-field class="flex items-center gap-3 pt-1">
                <div class="h-px bg-white/10 flex-1"></div>
                <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 select-none">Kata Kunci Baru</span>
                <div class="h-px bg-white/10 flex-1"></div>
            </div>

            <AuthField data-field v-model="form.password" type="password" label="Buat Kata Kunci (min. 8 karakter)" icon="lock" :error="form.errors.password" autocomplete="new-password" required />
            <AuthField data-field v-model="form.password_confirmation" type="password" label="Ulangi Kata Kunci" icon="lock" autocomplete="new-password" required />

            <div data-field>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="auth-submit w-full h-12 px-4 text-white text-sm font-bold rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait cursor-pointer flex justify-center items-center gap-2"
                >
                    {{ form.processing ? 'Memproses...' : 'Aktifkan Akun' }}
                    <svg v-if="!form.processing" class="w-4 h-4 arrow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </form>

        <div data-field class="mt-6 border-t border-white/10 pt-5 flex flex-wrap gap-2 justify-between text-sm text-slate-300">
            <Link :href="route('login')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Kembali ke Login</Link>
            <span>Belum terdaftar? <Link :href="route('register')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Daftar</Link></span>
        </div>
    </AuthScene>
</template>