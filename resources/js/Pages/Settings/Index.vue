<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
// PERBAIKAN: Menambahkan 'router' ke dalam impor agar navigasi taktis berfungsi
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue'; 
import axios from 'axios';
import QrcodeVue from 'qrcode.vue'; // Tambahan untuk render QR Code

const props = defineProps({
    settings: Object
});

const form = useForm({
    agency_name: props.settings.agency_name || '',
    copyright: props.settings.copyright || '',
    start_number: props.settings.start_number || 1,
    logo: null,
    signature_file: null, // Field baru untuk TTD
    favicon: null, // TAMBAHAN: Field baru untuk Favicon
    _method: 'PUT' 
});

const logoPreview = ref(props.settings.agency_logo ? '/storage/' + props.settings.agency_logo : null);
const signaturePreview = ref(props.settings.commander_signature ? '/storage/' + props.settings.commander_signature : null);
const faviconPreview = ref(props.settings.favicon ? '/storage/' + props.settings.favicon : null); // TAMBAHAN: Preview Favicon

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handleSignatureChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.signature_file = file;
        signaturePreview.value = URL.createObjectURL(file);
    }
};

// TAMBAHAN: Handler input file Favicon
const handleFaviconChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.favicon = file;
        faviconPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Menggunakan tampilan sukses baru
        }
    });
};

// --- LOGIKA WHATSAPP GATEWAY (SYNC WITH server.js PORT 3000 VIA LARAVEL BRIDGE) ---
const waData = ref({ status: 'LOADING', qr: null, pairingCode: null });
let waInterval = null;

const checkStatus = async () => {
    try {
        // Mengakses bridge rute Laravel yang baru kita buat di web.php
        const res = await axios.get(route('settings.wa-status'));
        
        waData.value = {
            status: res.data.status === 'ONLINE' ? 'CONNECTED' : (res.data.status === 'WAITING_SCAN' ? 'WAITING' : 'OFFLINE'),
            qr: res.data.qr, // Menarik data QR dari Node.js
            pairingCode: res.data.pairing_code
        };
    } catch (error) {
        waData.value = { status: 'OFFLINE', qr: null, pairingCode: null };
    }
};

// Fungsi taktis untuk memicu refresh status secara manual
const generateNewCode = async () => {
    waData.value.status = 'LOADING';
    await checkStatus();
};

onMounted(() => {
    checkStatus();
    // PERBAIKAN: Dikembalikan ke 5000ms agar monitoring bersifat real-time
    waInterval = setInterval(checkStatus, 5000); 
});

onUnmounted(() => { if (waInterval) clearInterval(waInterval); });

</script>

<template>
    <Head title="Pengaturan Instansi" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-indigo-950 uppercase italic tracking-widest">Pengaturan Sistem</h2>
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Konfigurasi Instansi</span>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-10 px-4">
            
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 mb-8 overflow-hidden transition-all hover:shadow-lg">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-4">
                        <div :class="waData.status === 'CONNECTED' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'" 
                             class="w-14 h-14 rounded-2xl flex items-center justify-center transition-colors duration-500 shadow-sm">
                            <span v-if="waData.status === 'CONNECTED'" class="text-2xl animate-pulse">🟢</span>
                            <span v-else class="text-2xl">🔴</span>
                        </div>
                        <div>
                            <h3 class="font-black text-indigo-950 uppercase italic text-lg tracking-tight">STATUS WA GATEWAY</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <div :class="waData.status === 'CONNECTED' ? 'bg-emerald-500 animate-ping' : 'bg-rose-500'" class="w-2 h-2 rounded-full"></div>
                                <span class="text-[10px] font-black uppercase italic" :class="waData.status === 'CONNECTED' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ waData.status === 'CONNECTED' ? 'TERHUBUNG / LIVE' : 'TERPUTUS / OFFLINE' }}
                                </span>
                            </div>
                            <button @click="generateNewCode" v-if="waData.status !== 'CONNECTED'" class="mt-2 text-[9px] font-black text-indigo-600 uppercase border-b border-dashed border-indigo-300 hover:text-indigo-800 transition-all">
                                🔄 Refresh Status Koneksi
                            </button>
                        </div>
                    </div>

                    <div v-if="waData.status !== 'CONNECTED' && waData.qr" class="bg-white p-4 rounded-3xl shadow-xl border-4 border-indigo-950 animate-in zoom-in duration-300">
                        <p class="text-[8px] text-indigo-950 font-black uppercase mb-3 text-center tracking-[0.2em]">SCAN QR AKTIVASI</p>
                        <qrcode-vue :value="waData.qr" :size="160" level="H" class="mx-auto" />
                        <p class="mt-2 text-[7px] text-gray-400 font-bold text-center uppercase tracking-tighter">Gunakan menu WhatsApp Web di HP</p>
                    </div>

                    <div v-else-if="waData.status !== 'CONNECTED' && waData.pairingCode" class="bg-indigo-950 p-6 rounded-3xl shadow-xl animate-in zoom-in duration-300">
                        <p class="text-[8px] text-indigo-300 font-black uppercase mb-3 text-center tracking-[0.2em]">KODE PAIRING ANDA</p>
                        <div class="flex gap-2">
                            <span v-for="(char, index) in waData.pairingCode" :key="index" class="bg-white text-indigo-950 px-3 py-2 rounded-lg font-black text-xl font-mono shadow-inner border-b-4 border-indigo-200 transition-all active:scale-95">
                                {{ char }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="waData.status !== 'CONNECTED'" class="mt-8 p-4 bg-rose-50 border border-rose-100 rounded-2xl animate-in slide-in-from-bottom duration-500">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📢</span>
                        <p class="text-[10px] text-rose-700 font-bold uppercase italic leading-relaxed">
                            LAPOR! WHATSAPP LOGOUT. SILAKAN SCAN QR DI ATAS ATAU GUNAKAN KODE PAIRING UNTUK MENGAKTIFKAN KEMBALI GATEWAY SI SINDEN.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 transition-all hover:shadow-md">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col items-center p-8 bg-gray-50/50 rounded-[2rem] border-2 border-dashed border-gray-200 group transition-all hover:border-indigo-300">
                            <div class="relative w-32 h-32 rounded-full border-4 border-white shadow-xl flex items-center justify-center overflow-hidden bg-white mb-4 group-hover:scale-105 transition-transform duration-300">
                                <img v-if="logoPreview" :src="logoPreview" class="object-cover w-full h-full" />
                                <span v-else class="text-gray-300 font-black text-4xl uppercase">
                                    {{ form.agency_name ? form.agency_name.charAt(0) : '?' }}
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-6 py-2.5 rounded-xl border border-gray-200 text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                    Ganti Logo Instansi
                                    <input type="file" @change="handleLogoChange" class="hidden" accept="image/*" />
                                </label>
                                <p class="text-[10px] text-gray-400 mt-3 italic uppercase font-bold">Format: JPG, PNG. Maksimal: 2MB</p>
                            </div>
                        </div>

                        <div class="flex flex-col items-center p-8 bg-gray-50/50 rounded-[2rem] border-2 border-dashed border-gray-200 group transition-all hover:border-indigo-300">
                            <div class="relative w-32 h-32 rounded-2xl border-4 border-white shadow-xl flex items-center justify-center overflow-hidden bg-white mb-4 group-hover:scale-105 transition-transform duration-300">
                                <img v-if="faviconPreview" :src="faviconPreview" class="object-contain w-16 h-16" />
                                <span v-else class="text-gray-300 font-black text-4xl uppercase">
                                    🌐
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-6 py-2.5 rounded-xl border border-gray-200 text-[10px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                    Ganti Favicon Web
                                    <input type="file" @change="handleFaviconChange" class="hidden" accept="image/x-icon,image/png,image/jpeg" />
                                </label>
                                <p class="text-[10px] text-gray-400 mt-3 italic uppercase font-bold">Format: ICO, PNG. Rekomendasi: 32x32 px</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ms-1 italic">Nama Instansi</label>
                            <input v-model="form.agency_name" type="text" class="w-full rounded-2xl border-gray-100 bg-gray-50/30 focus:ring-indigo-500 focus:border-indigo-500 uppercase text-sm font-bold shadow-sm px-5 py-4 transition-all" placeholder="CONTOH: DENINTEL">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ms-1 italic">Teks Copyright</label>
                            <input v-model="form.copyright" type="text" class="w-full rounded-2xl border-gray-100 bg-gray-50/30 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-semibold shadow-sm px-5 py-4 transition-all">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest ms-1 flex items-center gap-1">
                                🔢 Kontrol Nomor Urut Surat
                            </label>
                            <div class="relative">
                                <input v-model="form.start_number" type="number" min="1" class="w-full rounded-2xl border-indigo-100 bg-indigo-50/30 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-black shadow-sm px-5 py-4 text-indigo-700">
                                <span class="absolute right-5 top-4 text-[10px] text-indigo-300 font-black uppercase italic">Start</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-amber-500 uppercase tracking-widest ms-1 flex items-center gap-1 italic">
                                ✍️ Spesimen Tanda Tangan Komandan
                            </label>
                            <div class="flex items-start gap-4 p-4 bg-amber-50/30 rounded-[2rem] border border-amber-100 shadow-inner">
                                <div class="w-20 h-20 bg-white rounded-2xl border border-amber-200 flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="signaturePreview" :src="signaturePreview" class="max-w-full max-h-full object-contain p-1" />
                                    <span v-else class="text-[8px] text-amber-300 font-black uppercase text-center px-2">KOSONG</span>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[9px] font-black text-amber-600 mb-2 uppercase cursor-pointer hover:underline">
                                        Pilih File PNG Transparan
                                        <input type="file" @change="handleSignatureChange" class="hidden" accept="image/png" />
                                    </label>
                                    <p class="text-[8px] text-gray-400 leading-tight">Wajib PNG Tanpa Background agar tidak menutupi teks surat di SI SINDEN.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8 border-t border-gray-50 flex justify-end items-center gap-4">
                        <transition leave-active-class="transition ease-in duration-1000" leave-from-class="opacity-100" leave-to-class="opacity-0">
                            <p v-if="form.recentlySuccessful" class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">
                                ✓ Pengaturan Berhasil Disimpan
                            </p>
                        </transition>
                        
                        <button :disabled="form.processing" 
                                class="flex items-center gap-3 bg-gray-950 text-white px-12 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-600 transition-all shadow-xl shadow-gray-200 disabled:opacity-50 active:scale-95">
                            <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ form.processing ? 'Menyimpan...' : 'Update Konfigurasi Sistem' }}
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm flex items-center justify-between group hover:border-indigo-500 transition-all cursor-pointer mt-8" 
                 @click="router.get(route('stamp.setting'))">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                        🛡️
                    </div>
                    <div class="flex flex-col text-left">
                        <span class="text-[12px] font-black text-indigo-950 uppercase tracking-widest leading-none">Konfigurasi Stempel Digital</span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase mt-2 tracking-tighter">Update Master Stempel PNG Komandan</span>
                    </div>
                </div>
                <div class="text-indigo-200 group-hover:text-indigo-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <p class="text-[10px] text-gray-400 font-medium uppercase tracking-[0.3em]">
                    {{ form.copyright }} &copy; {{ new Date().getFullYear() }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.shadow-inner {
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);
}
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
button:hover {
    transform: translateY(-2px);
}
button:active {
    transform: translateY(0);
}
</style>