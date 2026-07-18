<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ logs: Object });
</script>

<template>
    <Head title="Audit Log Keamanan" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col">
                <h2 class="text-xl font-black text-indigo-950 uppercase tracking-tight">Audit Log Keamanan</h2>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Rekam Jejak Otoritas & Tindakan Admin</p>
            </div>
        </template>

        <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden p-8">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[9px] font-black text-gray-300 uppercase tracking-[0.2em] border-b border-gray-50">
                        <th class="pb-6 px-4">Waktu</th>
                        <th class="pb-6 px-4">Pelaksana (Admin)</th>
                        <th class="pb-6 px-4 text-center">Tindakan</th>
                        <th class="pb-6 px-4">Deskripsi Aktivitas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-[10px]">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-5 px-4 font-bold text-gray-400 italic">{{ log.created_at }}</td>
                        <td class="py-5 px-4">
                            <div class="flex flex-col">
                                <span class="font-black text-indigo-900 uppercase">{{ log.admin_name }}</span>
                                <span class="text-[8px] text-indigo-400 font-bold">ID: #{{ log.user_id }}</span>
                            </div>
                        </td>
                        <td class="py-5 px-4 text-center">
                            <span :class="{
                                'bg-amber-50 text-amber-600 border-amber-200': log.action === 'VERIFIKASI',
                                'bg-rose-50 text-rose-600 border-rose-200': log.action === 'HAPUS'
                            }" class="px-3 py-1 rounded-lg font-black uppercase tracking-tighter border">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="py-5 px-4 font-bold text-gray-600 uppercase leading-relaxed">{{ log.description }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>