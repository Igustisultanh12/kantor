<script setup>
// Sambutan sekali setelah berhasil masuk: kartu identitas berputar 3D menampilkan
// foto, nama, pangkat/NRP, lalu memudar. Dipicu flag `sinden_login_welcome` yang
// diset halaman Login (tetap bertahan melewati langkah OTP).
import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import IdentityCardLoader from '@/Components/IdentityCardLoader.vue';

const page = usePage();
const show = ref(false);
const verified = ref(false);

onMounted(() => {
    let flagged = false;
    try {
        flagged = sessionStorage.getItem('sinden_login_welcome') === '1';
        if (flagged) sessionStorage.removeItem('sinden_login_welcome');
    } catch (_) {}
    if (!flagged || !page.props.auth?.user) return;

    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    show.value = true;
    setTimeout(() => { verified.value = true; }, reduce ? 50 : 450);
    setTimeout(() => { show.value = false; }, reduce ? 900 : 2300);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="lw">
            <div v-if="show" class="fixed inset-0 z-[9999] grid place-items-center bg-slate-950/85 backdrop-blur-md" @click="show = false">
                <IdentityCardLoader :verified="verified" :user="page.props.auth?.user" :label="verified ? 'Selamat datang' : 'Memverifikasi identitas...'" />
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.lw-enter-active { transition: opacity .2s ease; }
.lw-leave-active { transition: opacity .45s ease, transform .45s ease; }
.lw-enter-from { opacity: 0; }
.lw-leave-to { opacity: 0; transform: scale(1.03); }
</style>
