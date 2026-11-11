<script setup>
import AuthScene from '@/Components/AuthScene.vue';
import AuthField from '@/Components/AuthField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const scene = ref(null);
watch(() => form.errors.email, (e) => { if (e) scene.value?.shake(); });

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Lupa Password - SINDEN" />

    <AuthScene ref="scene" tagline="Pemulihan Kata Kunci Akun Personel — Sistem Informasi Detasemen Intelijen SINDEN.">
        <div data-field class="mb-7">
            <h1 class="text-[1.65rem] leading-tight font-extrabold tracking-tight text-white">Lupa Kata Kunci</h1>
            <p class="text-sm mt-1.5 text-slate-300/90">
                Ketikkan email dinas Anda yang terdaftar untuk menerima tautan pemulihan kata kunci.
            </p>
        </div>

        <div v-if="status" data-field class="mb-5 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-400/30 text-sm font-semibold text-emerald-300" role="status">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4" novalidate>
            <AuthField data-field v-model="form.email" type="email" label="Email Dinas Terdaftar" icon="mail" :error="form.errors.email" autocomplete="email" required autofocus />

            <div data-field>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="auth-submit w-full h-12 px-4 text-white text-sm font-bold rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait cursor-pointer flex justify-center items-center gap-2"
                >
                    {{ form.processing ? 'Mengirim...' : 'Kirim Tautan Reset' }}
                    <svg v-if="!form.processing" class="w-4 h-4 arrow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </form>

        <div data-field class="mt-6 border-t border-white/10 pt-5 flex flex-wrap gap-2 justify-between text-sm">
            <Link :href="route('password.request.custom')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Reset Pakai Token 6-Digit</Link>
            <Link :href="route('login')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Kembali ke Login</Link>
        </div>
    </AuthScene>
</template>
