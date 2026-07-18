<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    nrp: '',
    token: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('aktivasi.proses'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Aktivasi Akun Personel" />

    <div class="min-h-screen flex items-center justify-center bg-[#F8F9FD] px-4 font-sans antialiased">
        <div class="max-w-md w-full space-y-8 bg-white p-8 sm:p-10 rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.02)] border border-gray-100">
            
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <ApplicationLogo class="h-12 w-auto" />
                </div>
                <h2 class="text-2xl font-black text-indigo-950 uppercase tracking-tighter leading-none">
                    Aktivasi Personel
                </h2>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-[0.3em] mt-3">
                    Intelligence Digital System
                </p>
            </div>

            <form @submit.prevent="submit" class="mt-10 space-y-5">
                <div>
                    <label class="text-[10px] font-black text-indigo-900 uppercase ml-1 tracking-widest">NRP Personel</label>
                    <input v-model="form.nrp" type="text" required
                        class="mt-1 block w-full px-5 py-4 bg-gray-50 border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition-all text-sm font-bold"
                        placeholder="Masukkan NRP Anda">
                    <InputError class="mt-2" :message="form.errors.nrp" />
                </div>

                <div>
                    <label class="text-[10px] font-black text-indigo-900 uppercase ml-1 tracking-widest">Token Aktivasi (Cek WA)</label>
                    <input v-model="form.token" type="text" required
                        class="mt-1 block w-full px-5 py-4 bg-gray-50 border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition-all text-sm font-mono font-bold uppercase"
                        placeholder="SINDEN-XXXXXX">
                    <InputError class="mt-2" :message="form.errors.token" />
                </div>

                <div class="pt-2 border-t border-gray-50 mt-6">
                    <label class="text-[10px] font-black text-indigo-900 uppercase ml-1 tracking-widest">Buat Password Baru</label>
                    <input v-model="form.password" type="password" required
                        class="mt-1 block w-full px-5 py-4 bg-gray-50 border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition-all text-sm"
                        placeholder="Minimal 8 Karakter">
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <label class="text-[10px] font-black text-indigo-900 uppercase ml-1 tracking-widest">Konfirmasi Password</label>
                    <input v-model="form.password_confirmation" type="password" required
                        class="mt-1 block w-full px-5 py-4 bg-gray-50 border-transparent rounded-2xl focus:bg-white focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition-all text-sm"
                        placeholder="Ulangi Password Baru">
                </div>

                <div class="pt-4">
                    <button type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                        class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] shadow-xl shadow-indigo-100 hover:bg-indigo-700 active:scale-95 transition-all">
                        Aktifkan Otoritas Akun
                    </button>
                </div>
            </form>

            <div class="text-center pt-6">
                <Link :href="route('login')" class="text-[10px] font-black text-gray-400 hover:text-indigo-600 uppercase tracking-widest transition-colors">
                    Kembali ke Halaman Login
                </Link>
            </div>
        </div>
    </div>
</template>