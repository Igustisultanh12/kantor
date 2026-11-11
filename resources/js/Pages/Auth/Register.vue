<script setup>
import AuthScene from '@/Components/AuthScene.vue';
import AuthField from '@/Components/AuthField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    nrp: '',
    pangkat: '',
    password: '',
    password_confirmation: '',
});

const scene = ref(null);

const submit = () => {
    form.post(route('register'), {
        onError: () => scene.value?.shake(),
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registrasi Personel - SINDEN" />

    <AuthScene ref="scene" card-class="max-w-lg" tagline="Pendaftaran Akun Personel — Sistem Informasi Detasemen Intelijen SINDEN.">
        <div data-field class="mb-7">
            <h1 class="text-[1.65rem] leading-tight font-extrabold tracking-tight text-white">Daftar Akun</h1>
            <p class="text-sm mt-1.5 text-slate-300/90">
                Isi data identitas dengan benar untuk mendaftar akun dinas.
            </p>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4" novalidate>
            <AuthField data-field class="sm:col-span-2" v-model="form.name" label="Nama Lengkap & Gelar" icon="user" :error="form.errors.name" autocomplete="name" required autofocus />
            <AuthField data-field v-model="form.nrp" label="NRP" icon="badge" :error="form.errors.nrp" input-class="uppercase font-semibold" autocapitalize="characters" spellcheck="false" required />
            <AuthField data-field v-model="form.pangkat" label="Pangkat" icon="star" :error="form.errors.pangkat" required />
            <AuthField data-field class="sm:col-span-2" v-model="form.email" type="email" label="Email Dinas" icon="mail" :error="form.errors.email" autocomplete="email" required />
            <AuthField data-field class="sm:col-span-2" v-model="form.password" type="password" label="Kata Kunci" icon="lock" :error="form.errors.password" autocomplete="new-password" required />
            <AuthField data-field class="sm:col-span-2" v-model="form.password_confirmation" type="password" label="Ulangi Kata Kunci" icon="lock" autocomplete="new-password" required />

            <div data-field class="sm:col-span-2 pt-1">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="auth-submit w-full h-12 px-4 text-white text-sm font-bold rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait cursor-pointer flex justify-center items-center gap-2"
                >
                    {{ form.processing ? 'Mendaftarkan...' : 'Daftar Akun Baru' }}
                    <svg v-if="!form.processing" class="w-4 h-4 arrow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </form>

        <div data-field class="mt-6 border-t border-white/10 pt-5 flex flex-wrap gap-2 justify-between text-sm text-slate-300">
            <Link href="/aktivasi" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Aktivasi Akun</Link>
            <span>Sudah punya akun? <Link :href="route('login')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Masuk</Link></span>
        </div>
    </AuthScene>
</template>
