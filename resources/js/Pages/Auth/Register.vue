<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    nrp: '',      // Penambahan field NRP
    pangkat: '',  // Penambahan field Pangkat
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register Personel" />

        <div class="mb-8 text-center">
            <h1 class="text-xl font-black text-indigo-900 uppercase tracking-tight">Registrasi Personel</h1>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1 italic">Input Data Identitas Militer Secara Benar</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="name" value="Nama Lengkap" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Ketikkan Nama beserta Gelar anda"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="nrp" value="NRP" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput
                    id="nrp"
                    type="text"
                    class="mt-1 block w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm uppercase font-bold"
                    v-model="form.nrp"
                    required
                    placeholder="Silahkan isi NRP anda"
                />
                <p class="text-[9px] text-gray-400 italic px-1 mt-1">NRP dapat berisi kombinasi angka dan huruf.</p>
                <InputError class="mt-2" :message="form.errors.nrp" />
            </div>

            <div class="mt-4">
                <InputLabel for="pangkat" value="Pangkat" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput
                    id="pangkat"
                    type="text"
                    class="mt-1 block w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm"
                    v-model="form.pangkat"
                    required
                    placeholder="Contoh: Letnan Dua Laut (E)"
                />
                <InputError class="mt-2" :message="form.errors.pangkat" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email Dinas" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="personel@instansi.id"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Konfirmasi Password" class="text-[10px] font-black uppercase text-gray-400 ms-1" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="mt-8 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-[11px] font-bold text-gray-500 uppercase tracking-tighter hover:text-indigo-600 transition"
                >
                    Sudah Terdaftar?
                </Link>

                <PrimaryButton
                    class="ms-4 bg-gray-900 py-3 px-8 rounded-2xl font-black uppercase text-[11px] tracking-widest shadow-xl hover:bg-indigo-600 transition-all active:scale-95"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Daftar Akun
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>