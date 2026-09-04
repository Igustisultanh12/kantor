<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    target: String,
    title: { type: String, default: 'Jejak Audit & Kronologi Dokumen' },
});

const emit = defineEmits(['close']);

const trails = ref([]);
const isLoading = ref(false);

const fetchTrail = async (t) => {
    if (!t) return;
    try {
        isLoading.value = true;
        const res = await fetch(`/api/audit-trail?target=${encodeURIComponent(t)}`);
        if (res.ok) {
            const data = await res.json();
            trails.value = data.trails || [];
        }
    } catch (e) {
        console.error('Gagal mengambil audit trail:', e);
    } finally {
        isLoading.value = false;
    }
};

watch(() => props.show, (newVal) => {
    if (newVal && props.target) {
        fetchTrail(props.target);
    }
});

const getActionBadgeClass = (action) => {
    const act = (action || '').toUpperCase();
    if (act.includes('CREATE') || act.includes('TAMBAH') || act.includes('SETUJUI') || act.includes('APPROVE')) {
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    }
    if (act.includes('DELETE') || act.includes('HAPUS') || act.includes('TOLAK') || act.includes('REJECT')) {
        return 'bg-rose-100 text-rose-800 border-rose-200';
    }
    if (act.includes('UPDATE') || act.includes('EDIT') || act.includes('UBAH')) {
        return 'bg-amber-100 text-amber-800 border-amber-200';
    }
    return 'bg-indigo-100 text-indigo-800 border-indigo-200';
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[150] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto animate-in fade-in duration-150">
        <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden my-auto border border-slate-100 animate-in zoom-in-95 duration-150">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/60">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600/10 text-indigo-600 flex items-center justify-center font-black">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-indigo-600 tracking-wider block">Integritas Rekam Jejak Sistem</span>
                        <h3 class="text-base font-black text-slate-900">{{ title }}</h3>
                    </div>
                </div>
                <button @click="$emit('close')" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                    &times;
                </button>
            </div>

            <!-- Target Label -->
            <div class="px-6 py-2.5 bg-slate-100/70 border-b border-slate-200/70 flex items-center justify-between text-xs">
                <span class="font-bold text-slate-500 uppercase text-[10px]">Objek Berkas:</span>
                <span class="font-mono font-black text-slate-800">{{ target }}</span>
            </div>

            <!-- Scrollable Timeline -->
            <div class="p-6 overflow-y-auto flex-1 space-y-6 text-xs custom-scrollbar">
                
                <div v-if="isLoading" class="py-12 flex flex-col items-center justify-center gap-2 text-slate-400">
                    <svg class="animate-spin w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span class="font-bold text-[11px]">Memuat riwayat log...</span>
                </div>

                <div v-else-if="trails.length > 0" class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    
                    <div v-for="(trail, idx) in trails" :key="trail.id || idx" class="relative group">
                        
                        <!-- Timeline Dot -->
                        <div class="absolute -left-6 top-1 w-3 h-3 rounded-full bg-white border-2 border-indigo-600 group-hover:scale-125 transition-transform shadow-xs"></div>
                        
                        <!-- Timeline Content Card -->
                        <div class="p-3.5 bg-slate-50 hover:bg-slate-100/80 border border-slate-200/70 rounded-2xl transition space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-1.5">
                                <span :class="getActionBadgeClass(trail.action)" class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider border">
                                    {{ trail.action }}
                                </span>
                                <span class="font-mono text-[10px] text-slate-400 font-bold">
                                    {{ trail.created_at }}
                                </span>
                            </div>

                            <p class="font-bold text-slate-800 text-[11px] leading-snug">
                                {{ trail.description }}
                            </p>

                            <div class="pt-1 border-t border-slate-200/60 flex items-center justify-between text-[10px] text-slate-400">
                                <span>Petugas: <b class="text-slate-600 uppercase">{{ trail.admin_name }}</b></span>
                                <span v-if="trail.ip_address" class="font-mono">IP: {{ trail.ip_address }}</span>
                            </div>
                        </div>

                    </div>

                </div>

                <div v-else class="py-12 text-center text-slate-400 space-y-1">
                    <p class="font-bold">Belum ada catatan jejak audit untuk berkas ini.</p>
                    <p class="text-[11px]">Seluruh perubahan berkas di masa mendatang akan otomatis terekam di sini.</p>
                </div>

            </div>

            <!-- Footer -->
            <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-end shrink-0">
                <button 
                    @click="$emit('close')"
                    class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs uppercase rounded-xl transition cursor-pointer"
                >
                    Tutup
                </button>
            </div>

        </div>
    </div>
</template>
