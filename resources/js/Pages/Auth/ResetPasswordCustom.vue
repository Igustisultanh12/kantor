<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    email: '',
    token: '', // Akan dikirim sebagai number jika pakai .number
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update.custom'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Otoritas" />

        <div class="mb-8 text-center">
            <h1 class="text-lg font-black text-indigo-900 uppercase italic">Verifikasi Token Reset</h1>
            <p class="text-[9px] text-gray-400 font-bold uppercase mt-2">Minta Token 6-Digit ke Admin untuk Melanjutkan</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel value="Email Dinas" class="text-[9px] font-black uppercase" />
                <TextInput v-model="form.email" type="email" class="mt-1 block w-full rounded-2xl bg-gray-50 border-none" required />
                <InputError :message="form.errors.email" />
            </div>

            <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100 italic">
                <InputLabel value="Token 6-Digit (Dari Admin)" class="text-[9px] font-black text-indigo-700 uppercase" />
                <TextInput 
                    v-model.number="form.token" 
                    type="text" 
                    maxlength="6" 
                    class="mt-2 block w-full bg-white text-center text-xl font-black tracking-[0.5em] border-none rounded-xl" 
                    placeholder="000000" 
                    required 
                    @input="form.token = $event.target.value.replace(/[^0-9]/g, '')"
                />
                <InputError :message="form.errors.token" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel value="Password Baru" class="text-[9px] font-black uppercase" />
                    <TextInput v-model="form.password" type="password" class="mt-1 block w-full rounded-2xl bg-gray-50 border-none" required />
                </div>
                <div>
                    <InputLabel value="Konfirmasi" class="text-[9px] font-black uppercase" />
                    <TextInput v-model="form.password_confirmation" type="password" class="mt-1 block w-full rounded-2xl bg-gray-50 border-none" required />
                </div>
            </div>
            <InputError :message="form.errors.password" />

            <div class="pt-4">
                <PrimaryButton class="w-full justify-center bg-indigo-600 py-4 rounded-2xl font-black" :disabled="form.processing">
                    PERBARUI PASSWORD AKSES
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>