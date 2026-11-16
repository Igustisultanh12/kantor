<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import SkhppForm from './Partials/SkhppForm.vue';

defineProps({
    skhpp: Object,
    agendaSurat: Object,
});
</script>

<template>
    <Head :title="`Revisi SKHPP - ${skhpp.nama}`" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center justify-between bg-white p-6 rounded-3xl shadow-xs border border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight">Revisi Data SKHPP Operator</h1>
                        <p class="text-xs text-slate-500 font-medium">Perbarui data yang salah lalu ajukan ulang ke Komandan</p>
                    </div>
                </div>

                <Link :href="route('skhpp.index')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold uppercase rounded-xl transition-all">
                    Batal / Kembali
                </Link>
            </div>

            <!-- Catatan revisi dari Komandan -->
            <div v-if="skhpp.catatan_revisi" class="p-5 bg-rose-50 border border-rose-200 rounded-3xl space-y-1">
                <div class="text-xs font-black uppercase text-rose-700 tracking-wider">Catatan Revisi dari Komandan:</div>
                <p class="text-xs font-semibold text-rose-900 leading-relaxed">"{{ skhpp.catatan_revisi }}"</p>
            </div>

            <SkhppForm :skhpp="skhpp" :agenda-surat="agendaSurat" />
        </div>
    </AuthenticatedLayout>
</template>
