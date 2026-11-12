<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const enabled = computed(() => Boolean(user.value.mfa_enabled));
const hasPhone = computed(() => Boolean(user.value.has_phone));
const busy = ref(false);

const toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2400, timerProgressBar: true });

const toggle = async () => {
    if (busy.value) return;
    const enable = !enabled.value;

    if (enable && !hasPhone.value) {
        await Swal.fire({
            icon: 'info',
            title: 'Nomor WhatsApp belum diisi',
            text: 'Isi nomor WhatsApp di bagian Informasi Profil terlebih dahulu. Kode OTP akan dikirim ke nomor tersebut.',
        });
        return;
    }

    const res = await Swal.fire({
        icon: enable ? 'question' : 'warning',
        title: enable ? 'Aktifkan OTP WhatsApp?' : 'Nonaktifkan OTP WhatsApp?',
        html: enable
            ? 'Setiap kali masuk (web & aplikasi), Anda akan diminta kode OTP yang dikirim ke WhatsApp Anda.'
            : 'Akun Anda hanya dilindungi kata sandi. Disarankan tetap aktif untuk akun dinas.',
        input: 'password',
        inputLabel: 'Konfirmasi dengan kata sandi Anda',
        inputPlaceholder: 'Kata sandi',
        inputAttributes: { autocomplete: 'current-password' },
        showCancelButton: true,
        confirmButtonText: enable ? 'Aktifkan' : 'Nonaktifkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: enable ? '#2563eb' : '#e11d48',
        reverseButtons: true,
        inputValidator: (v) => (!v ? 'Kata sandi wajib diisi' : undefined),
    });
    if (!res.isConfirmed) return;

    busy.value = true;
    router.post(route('profile.mfa'), { enabled: enable, password: res.value }, {
        preserveScroll: true,
        onSuccess: () => toast.fire({ icon: 'success', title: enable ? 'OTP WhatsApp diaktifkan' : 'OTP WhatsApp dinonaktifkan' }),
        onError: (errors) => Swal.fire({ icon: 'error', title: 'Gagal', text: errors.password || errors.mfa || 'Pengaturan tidak dapat disimpan.' }),
        onFinish: () => { busy.value = false; },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Verifikasi 2 Langkah (OTP WhatsApp)</h2>
            <p class="mt-1 text-sm text-gray-600">
                Saat aktif, setiap kali masuk Anda diminta kode OTP 6 digit yang dikirim ke WhatsApp Anda.
            </p>
        </header>

        <div class="mt-6 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 p-4">
            <div class="min-w-0">
                <div class="text-sm font-bold text-slate-900">Status: {{ enabled ? 'AKTIF' : 'NONAKTIF' }}</div>
                <p class="text-xs text-slate-500 mt-0.5">
                    <template v-if="hasPhone">Kode dikirim ke nomor WhatsApp di profil Anda.</template>
                    <template v-else>Nomor WhatsApp belum diisi: lengkapi dulu untuk dapat mengaktifkan.</template>
                </p>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="enabled"
                :disabled="busy"
                @click="toggle"
                :class="enabled ? 'bg-blue-600' : 'bg-slate-300'"
                class="relative w-12 h-7 shrink-0 rounded-full p-0.5 transition-colors disabled:opacity-60"
                :title="enabled ? 'Nonaktifkan OTP WhatsApp' : 'Aktifkan OTP WhatsApp'"
            >
                <span :class="enabled ? 'translate-x-5' : 'translate-x-0'" class="block w-6 h-6 bg-white rounded-full shadow-md transform transition-transform"></span>
            </button>
        </div>
    </section>
</template>
