<script setup>
// Saklar tampilan: Nova (baru) atau Classic (lama, tidak diubah).
// Ditentukan oleh Pengaturan admin `ui_nova`, bisa ditimpa per perangkat
// lewat ?nova=1 / ?nova=0 / ?nova=auto (lihat resources/js/nova/flag.js).
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ClassicLayout from '@/Layouts/ClassicLayout.vue';
import NovaShell from '@/Layouts/NovaShell.vue';
import { isNovaOn } from '@/nova/flag';

const page = usePage();
const nova = computed(() => (typeof window !== 'undefined') && isNovaOn(page.props.settings));
</script>

<template>
    <NovaShell v-if="nova"><slot /></NovaShell>
    <ClassicLayout v-else><slot /></ClassicLayout>
</template>
