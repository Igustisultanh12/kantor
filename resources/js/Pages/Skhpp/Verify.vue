<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    skhpp: Object,
    settings: Object
});

const page = usePage();
const pageSettings = computed(() => props.settings || page.props.settings || {});
const appName = computed(() => pageSettings.value.agency_name || pageSettings.value.app_name || 'SINDEN');

const configuredLogo = computed(() => {
    const logo = pageSettings.value.agency_logo || pageSettings.value.logo || pageSettings.value.logo_tni;
    if (!logo) return '/images/logo.png';
    return (logo.startsWith('http') || logo.startsWith('/storage') || logo.startsWith('/images')) ? logo : '/storage/' + logo;
});

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head :title="`Verifikasi Legalitas SKHPP - ${skhpp.nama}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans flex flex-col justify-between p-4 sm:p-8 relative overflow-hidden">
        
        <!-- Ambient Glow Background matching Login UI -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Header Brand -->
        <div class="max-w-2xl mx-auto w-full flex items-center justify-between border-b border-white/10 pb-4 relative z-10">
            <div class="flex items-center gap-3">
                <img v-if="configuredLogo" :src="configuredLogo" class="h-10 w-10 object-contain drop-shadow-md" alt="Logo SINDEN" />
                <div v-else class="w-10 h-10 rounded-xl bg-blue-600/30 border border-blue-500/40 flex items-center justify-center text-blue-400 font-black text-xl">
                    S
                </div>
                <div>
                    <h1 class="text-sm font-black uppercase tracking-wider text-white">SINDEN LEGALITAS DIGITAL</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Detasemen Intelijen Komando Daerah TNI AL V</p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-full text-[9px] font-black uppercase tracking-widest">
                    VERIFIED OFFICIAL
                </span>
            </div>
        </div>

        <!-- Main Card Verification (Login UI Aesthetic) -->
        <div class="max-w-2xl mx-auto w-full my-auto py-6 relative z-10">
            <div class="bg-slate-900/90 border border-white/10 rounded-2xl p-6 sm:p-10 shadow-2xl space-y-6 relative overflow-hidden backdrop-blur-md">
                
                <!-- Top Main Logo (Menggantikan Icon Centang / Shield) -->
                <div class="text-center space-y-3">
                    <div class="flex justify-center">
                        <img v-if="configuredLogo" :src="configuredLogo" class="h-24 sm:h-28 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)]" alt="Logo Denintel SINDEN" />
                        <div v-else class="w-20 h-20 rounded-2xl bg-blue-600/30 border border-blue-500/40 flex items-center justify-center text-white font-black text-3xl shadow-2xl mx-auto">
                            S
                        </div>
                    </div>

                    <h2 class="text-lg sm:text-xl font-black text-white uppercase tracking-tight">DOKUMEN SKHPP ASLI & TERVERIFIKASI</h2>
                    <p class="text-xs text-emerald-400 font-semibold max-w-md mx-auto leading-relaxed">
                        Surat Keterangan Hasil Penelitian Personel Ini Sah Ditandatangani Komandan Detasemen Intelijen Kodaeral V secara Digital.
                    </p>
                </div>

                <!-- Document Details -->
                <div class="bg-slate-950/80 rounded-xl p-5 border border-white/10 space-y-4 text-xs">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-white/10">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Nomor Resmi SKHPP</span>
                            <span class="text-sm font-black text-orange-400">{{ skhpp.nomor_skhpp || 'Draft (Proses TTD)' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Tanggal Pengesahan</span>
                            <span class="text-sm font-bold text-white">{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at) }}</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Subjek Penelitian Personel</span>
                            <span class="text-sm font-black text-white block">{{ skhpp.nama }}</span>
                            
                            <!-- Tampilkan NRP/NIP jika Militer/PNS, atau NIK jika Sipil -->
                            <div v-if="skhpp.pangkat_korps_nrp" class="text-xs text-blue-400 font-bold mt-1">
                                PANGKAT / KORPS / NRP: <span class="text-white">{{ skhpp.pangkat_korps_nrp }}</span>
                            </div>
                            <div v-else-if="skhpp.nik" class="text-xs text-blue-400 font-bold mt-1">
                                NIK: <span class="text-white">{{ skhpp.nik }}</span>
                            </div>
                            <div v-else class="text-xs text-slate-400 font-medium mt-1">
                                Kategori: {{ skhpp.kategori_personel ? skhpp.kategori_personel.toUpperCase() : 'SIPIL / KEDINASAN' }}
                            </div>
                        </div>

                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Jabatan / Pekerjaan</span>
                            <span class="text-xs font-semibold text-slate-200 block">{{ skhpp.jabatan_pekerjaan }}</span>
                        </div>

                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Maksud & Peruntukan</span>
                            <span class="text-xs font-medium text-slate-300 leading-relaxed block">{{ skhpp.peruntukan }}</span>
                        </div>
                    </div>
                </div>

                <!-- Otoritas Penandatangan -->
                <div class="p-4 bg-emerald-950/40 rounded-xl border border-emerald-500/30 flex items-center justify-between gap-4 text-xs">
                    <div>
                        <div class="text-[10px] text-emerald-400 font-black uppercase tracking-wider">Pejabat Penandatangan (TTE)</div>
                        <div class="font-bold text-white mt-0.5">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                        <div class="text-[10px] text-slate-300">Komandan Detasemen Intelijen Kodaeral V — Kolonel Laut (E) NRP 16085/P</div>
                    </div>
                    <div class="text-emerald-400 text-2xl font-black shrink-0">
                        🛡️
                    </div>
                </div>

                <!-- Operator Metadata -->
                <div class="text-[10px] text-slate-400 space-y-1 pt-2 border-t border-white/10 flex justify-between">
                    <div>Operator Pengaju: <span class="text-slate-200 font-bold">{{ skhpp.operator_name || 'Operator SINDEN' }}</span></div>
                    <div>Kode Unik: <span class="font-mono text-slate-200">{{ skhpp.verification_code }}</span></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-slate-500 font-medium relative z-10">
            © {{ new Date().getFullYear() }} {{ appName }} Detasemen Intelijen Komando Daerah TNI Angkatan Laut V. All Rights Reserved.
        </div>
    </div>
</template>
