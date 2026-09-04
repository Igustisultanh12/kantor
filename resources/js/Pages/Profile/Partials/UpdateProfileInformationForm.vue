<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const photoInput = ref(null);
const photoPreview = ref(null);

const form = useForm({
    _method: 'PATCH',
    name: user.name,
    email: user.email,
    pangkat: user.pangkat || '',
    nrp: user.nrp || '',
    phone: user.phone || '',
    avatar: null,
    remove_avatar: false,
});

const selectNewPhoto = () => {
    photoInput.value?.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value?.files[0];
    if (!photo) return;

    if (photo.size > 3 * 1024 * 1024) {
        alert('Ukuran file foto maksimal 3 MB');
        return;
    }

    form.avatar = photo;
    form.remove_avatar = false;

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    form.avatar = null;
    form.remove_avatar = true;
    photoPreview.value = null;
    if (photoInput.value) {
        photoInput.value.value = null;
    }
};

const submit = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            photoPreview.value = null;
            if (photoInput.value) {
                photoInput.value.value = null;
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-bold text-gray-900 uppercase tracking-tight"> Informasi Personel
            </h2>

            <p class="mt-1 text-xs text-gray-500 font-semibold"> Perbarui Foto Profil, Nama, Pangkat, NRP, dan Nomor WhatsApp Anda. Data akan tersimpan sesuai dengan format kedinasan resmi.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <!-- Komponen Upload Foto Profil Personel -->
            <div class="p-4 sm:p-5 bg-slate-50 border border-slate-200/80 rounded-2xl flex flex-col sm:flex-row items-center gap-5">
                <div class="relative group shrink-0">
                    <div class="w-24 h-24 rounded-full overflow-hidden border-3 border-white bg-blue-600 text-white flex items-center justify-center font-black text-2xl shadow-md">
                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            alt="Pratinjau Foto Profil"
                            class="w-full h-full object-cover"
                        />
                        <img
                            v-else-if="user.avatar && !form.remove_avatar"
                            :src="'/storage/' + user.avatar"
                            alt="Foto Profil Personel"
                            class="w-full h-full object-cover"
                        />
                        <span v-else class="text-white font-black tracking-wider select-none">
                            {{ user.name ? user.name.substring(0, 2).toUpperCase() : 'US' }}
                        </span>
                    </div>

                    <button
                        type="button"
                        @click="selectNewPhoto"
                        class="absolute inset-0 rounded-full bg-black/40 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                        title="Klik untuk memilih foto baru"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-[9px] font-extrabold uppercase mt-1">Ubah</span>
                    </button>
                </div>

                <div class="flex-1 text-center sm:text-left space-y-2">
                    <div>
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Foto Profil Personel</h3>
                        <p class="text-[11px] text-slate-500 font-medium mt-0.5">Unggah foto dinas resmi Anda. Format yang didukung: JPG, PNG, atau WEBP (Maksimal 3 MB).</p>
                    </div>

                    <input
                        ref="photoInput"
                        type="file"
                        class="hidden"
                        accept="image/png, image/jpeg, image/jpg, image/webp"
                        @change="updatePhotoPreview"
                    />

                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-1">
                        <button
                            type="button"
                            @click="selectNewPhoto"
                            class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-extrabold uppercase tracking-wider rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            Pilih Foto Profil
                        </button>

                        <button
                            v-if="(user.avatar && !form.remove_avatar) || photoPreview"
                            type="button"
                            @click="deletePhoto"
                            class="px-3.5 py-2 bg-slate-200 hover:bg-rose-100 hover:text-rose-700 text-slate-700 text-[11px] font-extrabold uppercase tracking-wider rounded-xl transition flex items-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Hapus Foto
                        </button>
                    </div>

                    <InputError :message="form.errors.avatar" class="mt-1" />
                </div>
            </div>

            <div>
                <InputLabel for="name" value="Nama Lengkap" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full font-bold"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="pangkat" value="Pangkat" />

                <TextInput
                    id="pangkat"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.pangkat"
                    placeholder="Contoh: Letnan Dua Laut (E)"
                    required
                />

                <InputError class="mt-2" :message="form.errors.pangkat" />
            </div>

            <div>
                <InputLabel for="nrp" value="NRP" />

                <TextInput
                    id="nrp"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.nrp"
                    placeholder="SILAHKAN ISIKAN NRP"
                    required
                />

                <InputError class="mt-2" :message="form.errors.nrp" />
            </div>

            <div>
                <InputLabel for="phone" value="Nomor WhatsApp (Aktif)" />

                <TextInput
                    id="phone"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    placeholder="Contoh: 08123456789"
                    required
                />

                <InputError class="mt-2" :message="form.errors.phone" />
                
                <p class="mt-2 text-[10px] text-amber-600 font-bold italic uppercase">
                    * Digunakan untuk pengiriman notifikasi pengesahan berkas secara otomatis.
                </p>
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Email Anda belum terverifikasi.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Kirim ulang tautan verifikasi.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Tautan verifikasi baru telah dikirim ke alamat email Anda.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton :disabled="form.processing">Simpan Perubahan</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-emerald-600 font-bold"
                    >
                        Berhasil Disimpan.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>