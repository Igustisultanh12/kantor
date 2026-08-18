<script setup> import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({ users: Array, title: String, unit: String, date: String });

onMounted(() => {
    setTimeout(() => { window.print(); }, 1000); // Otomatis buka jendela print
});
</script>

<template>
    <Head :title="title" />
    <div class="p-10 bg-white min-h-screen text-black font-serif uppercase">
        <div class="text-center border-b-4 border-black pb-4 mb-8">
            <h1 class="text-2xl font-bold">{{ title }}</h1>
            <h2 class="text-xl font-bold">{{ unit }}</h2>
            <p class="text-xs mt-2 italic">Dicetak otomatis oleh sistem: {{ date }}</p>
        </div>

        <table class="w-full border-collapse border-2 border-black">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border-2 border-black p-2">NO</th>
                    <th class="border-2 border-black p-2 text-left">NAMA PERSONEL</th>
                    <th class="border-2 border-black p-2">NRP</th>
                    <th class="border-2 border-black p-2">ROLE</th>
                    <th class="border-2 border-black p-2">STATUS</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(u, i) in users" :key="u.id" class="border-b border-black">
                    <td class="border-2 border-black p-2 text-center">{{ i + 1 }}</td>
                    <td class="border-2 border-black p-2 text-left">{{ u.name }} ({{ u.pangkat }})</td>
                    <td class="border-2 border-black p-2 text-center">{{ u.nrp }}</td>
                    <td class="border-2 border-black p-2 text-center">{{ u.role }}</td>
                    <td class="border-2 border-black p-2 text-center font-bold">{{ u.is_active ? 'AKTIF' : 'SUSPEND' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style>
@media print { @page { size: landscape; margin: 1cm; } body { background: white; } }
</style>