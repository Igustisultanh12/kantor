<script setup> import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    pangkat: user.pangkat || '', // Sinkron database
    nrp: user.nrp || '',         // Sinkron database
    phone: user.phone || '',     // Tambahan sinkron database untuk WA
});

// Fungsi submit normal tanpa paksaan huruf besar
const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Berhasil disimpan sesuai inputan user
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900"> Informasi Personel
            </h2>

            <p class="mt-1 text-sm text-gray-600"> Perbarui data Nama, Pangkat, NRP, dan Nomor WhatsApp Anda. Data akan tersimpan sesuai dengan besar-kecil huruf yang Anda ketikkan.
            </p>
        </header>

        <form
            @submit.prevent="submit"class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Nama Lengkap" />

                <TextInput
                    id="name"type="text"class="mt-1 block w-full font-bold"v-model="form.name"required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="pangkat" value="Pangkat" />

                <TextInput
                    id="pangkat"type="text"class="mt-1 block w-full"v-model="form.pangkat"placeholder="Contoh: Letnan Dua Laut (E)"required
                />

                <InputError class="mt-2" :message="form.errors.pangkat" />
            </div>

            <div>
                <InputLabel for="nrp" value="NRP" />

                <TextInput
                    id="nrp"type="text"class="mt-1 block w-full"v-model="form.nrp"placeholder="SILAHKAN ISIKAN NRP"required
                />

                <InputError class="mt-2" :message="form.errors.nrp" />
            </div>

            <div>
                <InputLabel for="phone" value="Nomor WhatsApp (Aktif)" />

                <TextInput
                    id="phone"type="text"class="mt-1 block w-full"v-model="form.phone"placeholder="Contoh: 08123456789"required
                />

                <InputError class="mt-2" :message="form.errors.phone" />
                
                <p class="mt-2 text-[10px] text-amber-600 font-bold italic uppercase">
                    * Digunakan untuk pengiriman notifikasi pengesahan berkas secara otomatis.
                </p>
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"type="email"class="mt-1 block w-full"v-model="form.email"required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800"> Your email address is unverified.
                    <Link
                        :href="route('verification.send')"method="post"as="button"class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    > Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"class="mt-2 text-sm font-medium text-green-600"
                > A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Simpan Perubahan</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"enter-from-class="opacity-0"leave-active-class="transition ease-in-out"leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"class="text-sm text-emerald-600 font-bold"
                    > Berhasil Disimpan.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>