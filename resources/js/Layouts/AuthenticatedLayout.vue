<script setup>
// Saklar tampilan: Nova (baru) atau Classic (lama, tidak diubah).
// Ditentukan oleh Pengaturan admin `ui_nova`, bisa ditimpa per perangkat
// lewat ?nova=1 / ?nova=0 / ?nova=auto (lihat resources/js/nova/flag.js).
import { computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ClassicLayout from '@/Layouts/ClassicLayout.vue';
import NovaShell from '@/Layouts/NovaShell.vue';
import { isNovaOn } from '@/nova/flag';
import { reportDevice } from '@/Utils/deviceReport';
import { startRealtime } from '@/Utils/realtime';
import LoginWelcome from '@/Components/LoginWelcome.vue';

const page = usePage();
const nova = computed(() => (typeof window !== 'undefined') && isNovaOn(page.props.settings));

// Info perangkat untuk Profil > Perangkat & sesi (maks. tiap 5 menit, tanpa meminta izin baru).
onMounted(() => {
    reportDevice();
    // Data halaman ikut berubah otomatis saat ada perubahan di server (Laravel Reverb).
    startRealtime(page);
});
</script>

<template>
    <NovaShell v-if="nova"><slot /></NovaShell>
    <ClassicLayout v-else><slot /></ClassicLayout>
    <LoginWelcome />
</template>
