<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isUnlinking = ref(false);

const isGoogleLinked = computed(() => {
    return !!(user.value && user.value.google_id);
});

const unlinkGoogleAccount = () => {
    Swal.fire({
        title: 'Lepas Tautan Akun Google?',
        text: `Tautan ke akun Google (${user.value.google_email || 'Google'}) akan dilepas. Anda tetap bisa masuk menggunakan NRP/Email dan kata sandi Anda.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Lepas Tautan',
        cancelButtonText: 'Batal',
        customClass: {
            popup: 'rounded-2xl shadow-2xl',
            confirmButton: 'font-bold text-xs uppercase px-5 py-2.5 rounded-xl',
            cancelButton: 'font-bold text-xs uppercase px-5 py-2.5 rounded-xl',
        }
    }).then((result) => {
        if (result.isConfirmed) {
            isUnlinking.value = true;
            router.post(route('profile.google.unlink'), {}, {
                onFinish: () => {
                    isUnlinking.value = false;
                },
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Tautan Dilepas',
                        text: 'Tautan akun Google berhasil dilepas.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                },
                onError: () => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat melepas tautan akun.', 'error');
                }
            });
        }
    });
};
</script>

<template>
    <section>
        <header class="flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-white border border-slate-200 shadow-xs flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900">
                            Tautan Akun Google
                        </h2>
                    </div>
                </div>
                <p class="mt-1 text-xs text-slate-500 max-w-xl leading-relaxed">
                    Tautkan akun Google Anda ke akun dinas SINDEN untuk kemudahan masuk sistem dengan 1-klik tanpa perlu mengetik kata sandi.
                </p>
            </div>
        </header>

        <div class="mt-6">
            <!-- STATUS SUDAH TERTAUT -->
            <div v-if="isGoogleLinked" 
                 class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-emerald-50/70 to-slate-50 border border-emerald-200/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <img v-if="user.google_avatar" 
                         :src="user.google_avatar" 
                         alt="Avatar Google" 
                         class="w-12 h-12 rounded-full border-2 border-white shadow-sm shrink-0 object-cover"
                         referrerpolicy="no-referrer" />
                    <div v-else 
                         class="w-12 h-12 rounded-full bg-emerald-600 text-white font-black text-lg flex items-center justify-center shrink-0 shadow-sm">
                        {{ (user.google_email || user.name || 'G').charAt(0).toUpperCase() }}
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-slate-900 truncate max-w-xs sm:max-w-sm">
                                {{ user.google_email }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300 shrink-0">
                                Tertaut
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5 font-medium">
                            Akun Google aktif dan dapat digunakan untuk masuk langsung ke sistem.
                        </p>
                    </div>
                </div>

                <button 
                    type="button"
                    @click="unlinkGoogleAccount"
                    :disabled="isUnlinking"
                    class="px-3.5 py-2 rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 text-xs font-bold transition shadow-2xs hover:shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shrink-0"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                    <span>{{ isUnlinking ? 'Melepas...' : 'Lepas Tautan' }}</span>
                </button>
            </div>

            <!-- STATUS BELUM TERTAUT -->
            <div v-else 
                 class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-slate-200/80 text-slate-500 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-800">
                                Akun Google Belum Tertaut
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-200 text-slate-700 shrink-0">
                                Belum Aktif
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Hubungkan akun Google Anda untuk mengaktifkan fitur masuk cepat (Single Sign-On).
                        </p>
                    </div>
                </div>

                <a 
                    :href="route('auth.google.redirect', { action: 'link' })"
                    class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition shadow-sm hover:shadow-md flex items-center gap-2.5 cursor-pointer shrink-0 select-none"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                    </svg>
                    <span>Tautkan Akun Google</span>
                </a>
            </div>
        </div>
    </section>
</template>
