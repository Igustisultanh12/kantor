<script setup>
import AuthScene from '@/Components/AuthScene.vue';
import AuthField from '@/Components/AuthField.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const form = useForm({
    email: '',
    token: '',
    password: '',
    password_confirmation: '',
});

const scene = ref(null);
watch(() => [form.errors.email, form.errors.token, form.errors.password], (e) => { if (e.some(Boolean)) scene.value?.shake(); });

const submit = () => {
    form.post(route('password.update.custom'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password Token 6-Digit - SINDEN" />

    <AuthScene ref="scene" tagline="Verifikasi Token Otoritas Reset Kata Kunci — Sistem Informasi Detasemen Intelijen SINDEN.">
        <div data-field class="mb-7">
            <h1 class="text-[1.65rem] leading-tight font-extrabold tracking-tight text-white">Lupa Kata Kunci</h1>
            <p class="text-sm mt-1.5 text-slate-300/90">
                Minta Token 6-Digit ke Admin Sistem, lalu buat kata kunci baru Anda.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4" novalidate>
            <AuthField data-field v-model="form.email" type="email" label="Email Dinas" icon="mail" :error="form.errors.email" autocomplete="email" required autofocus />

            <!-- Token 6 digit -->
            <div data-field class="auth-panel rounded-2xl p-4 text-center">
                <label for="reset-token" class="block text-[11px] font-bold uppercase tracking-[0.16em] mb-2.5 text-sky-300">Token 6-Digit dari Admin</label>
                <input
                    id="reset-token"
                    type="text"
                    v-model="form.token"
                    maxlength="6"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="••••••"
                    required
                    class="w-full h-14 rounded-2xl text-2xl font-black tracking-[0.55em] text-center text-white bg-white/[0.06] border border-white/15 placeholder-slate-500 outline-none focus:border-sky-400/80 focus:bg-white/[0.09] focus:ring-4 focus:ring-blue-500/20 transition tabular-nums"
                    @input="form.token = $event.target.value.replace(/[^0-9]/g, '')"
                />
                <p v-if="form.errors.token" class="mt-1.5 text-xs text-rose-300 font-medium">{{ form.errors.token }}</p>
            </div>

            <AuthField data-field v-model="form.password" type="password" label="Kata Kunci Baru" icon="lock" :error="form.errors.password" autocomplete="new-password" required />
            <AuthField data-field v-model="form.password_confirmation" type="password" label="Ulangi Kata Kunci Baru" icon="lock" autocomplete="new-password" required />

            <div data-field>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="auth-submit w-full h-12 px-4 text-white text-sm font-bold rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-60 disabled:cursor-wait cursor-pointer flex justify-center items-center gap-2"
                >
                    {{ form.processing ? 'Memperbarui...' : 'Perbarui Kata Kunci' }}
                    <svg v-if="!form.processing" class="w-4 h-4 arrow" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </form>

        <div data-field class="mt-6 border-t border-white/10 pt-5 flex flex-wrap gap-2 justify-between text-sm">
            <Link :href="route('password.request')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Reset lewat Email</Link>
            <Link :href="route('login')" class="font-semibold text-sky-300 hover:text-sky-200 underline-offset-4 hover:underline">Kembali ke Login</Link>
        </div>
    </AuthScene>
</template>
