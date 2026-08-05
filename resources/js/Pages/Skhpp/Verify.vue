<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    skhpp: Object
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

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans flex flex-col justify-between p-4 sm:p-8">
        
        <!-- Header Brand -->
        <div class="max-w-2xl mx-auto w-full flex items-center justify-between border-b border-white/10 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 font-black text-xl">
                    ✓
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

        <!-- Main Card Verification -->
        <div class="max-w-2xl mx-auto w-full my-auto py-6">
            <div class="bg-slate-900/90 border border-emerald-500/30 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden backdrop-blur-md">
                
                <!-- Top Badge Status -->
                <div class="text-center space-y-2">
                    <div class="w-20 h-20 bg-emerald-500/20 rounded-full border border-emerald-500/40 flex items-center justify-center mx-auto text-emerald-400 shadow-[0_0_30px_rgba(16,185,129,0.3)] animate-pulse">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>

                    <h2 class="text-lg font-black text-white uppercase tracking-tight">DOKUMEN SKHPP ASLI & TERVERIFIKASI</h2>
                    <p class="text-xs text-emerald-400 font-semibold">
                        Surat Keterangan Hasil Penelitian Personel Ini Sah Ditandatangani Komandan Detasemen Intelijen Kodaeral V secara Digital.
                    </p>
                </div>

                <!-- Document Details -->
                <div class="bg-slate-950/80 rounded-2xl p-5 border border-white/5 space-y-4 text-xs">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-white/10">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Nomor Resmi SKHPP</span>
                            <span class="text-sm font-black text-orange-400">{{ skhpp.nomor_skhpp || 'Draft (Proses TTD)' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Tanggal Pengesahan</span>
                            <span class="text-sm font-bold text-white">{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at) }}</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Subjek Penelitian Personel</span>
                            <span class="text-sm font-black text-white">{{ skhpp.nama }}</span>
                            <div class="text-[11px] text-blue-400 font-semibold mt-0.5">
                                {{ skhpp.kategori_personel === 'militer' ? (skhpp.pangkat_korps_nrp || 'MILITER TNI AL') : ('NIK: ' + (skhpp.nik || '-')) }}
                            </div>
                        </div>

                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Jabatan / Pekerjaan</span>
                            <span class="text-xs font-semibold text-slate-200">{{ skhpp.jabatan_pekerjaan }}</span>
                        </div>

                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Maksud & Peruntukan</span>
                            <span class="text-xs font-medium text-slate-300 leading-relaxed">{{ skhpp.peruntukan }}</span>
                        </div>
                    </div>
                </div>

                <!-- Otoritas Penandatangan -->
                <div class="p-4 bg-emerald-950/40 rounded-2xl border border-emerald-500/30 flex items-center justify-between gap-4 text-xs">
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
        <div class="text-center text-xs text-slate-500 font-medium">
            © {{ new Date().getFullYear() }} Detasemen Intelijen Komando Daerah TNI Angkatan Laut V. All Rights Reserved.
        </div>
    </div>
</template>
