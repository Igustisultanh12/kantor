<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Pengaturan Profil - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            <!-- Header Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Pengaturan Profil & Akun</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Manajemen Informasi Identitas Personel & Keamanan Sandi Akses</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0]">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0]">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0]">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
