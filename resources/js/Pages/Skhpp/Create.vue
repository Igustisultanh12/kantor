<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import SkhppForm from './Partials/SkhppForm.vue';

defineProps({
    agendaSurat: Object,
});
</script>

<template>
    <Head title="Terbitkan SKHPP Baru - SINDEN" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center justify-between bg-white p-6 rounded-3xl shadow-xs border border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 uppercase tracking-tight">Formulir Penerbitan SKHPP</h1>
                        <p class="text-xs text-slate-500 font-medium">Input data permohonan Surat Keterangan Hasil Penelitian Personel</p>
                    </div>
                </div>

                <Link :href="route('skhpp.index')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold uppercase rounded-xl transition-all">
                    Kembali
                </Link>
            </div>

            <SkhppForm :agenda-surat="agendaSurat" />
        </div>
    </AuthenticatedLayout>
</template>
