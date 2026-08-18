<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue'; 
import axios from 'axios';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({
    settings: Object
});

const form = useForm({
    agency_name: props.settings.agency_name || '',
    copyright: props.settings.copyright || '',
    start_number: props.settings.start_number || 1,
    wa_notifications_enabled: props.settings.wa_notifications_enabled !== undefined ? String(props.settings.wa_notifications_enabled) : '1',
    logo: null,
    login_background: null,
    signature_file: null,
    favicon: null,
    _method: 'PUT' 
});

const logoPreview = ref(props.settings.agency_logo ? '/storage/' + props.settings.agency_logo : null);
const bgPreview = ref(props.settings.login_background ? '/storage/' + props.settings.login_background : null);
const signaturePreview = ref(props.settings.commander_signature ? '/storage/' + props.settings.commander_signature : null);
const faviconPreview = ref(props.settings.favicon ? '/storage/' + props.settings.favicon : null);

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const handleBgChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.login_background = file;
        bgPreview.value = URL.createObjectURL(file);
    }
};

const handleSignatureChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.signature_file = file;
        signaturePreview.value = URL.createObjectURL(file);
    }
};

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
            // Success updated
        }
    });
};

// --- LOGIKA WHATSAPP GATEWAY ---
const waData = ref({ status: 'LOADING', qr: null, pairingCode: null });
let waInterval = null;

const checkStatus = async () => {
    try {
        const res = await axios.get(route('settings.wa-status'));
        waData.value = {
            status: res.data.status === 'ONLINE' ? 'CONNECTED' : (res.data.status === 'WAITING_SCAN' ? 'WAITING' : 'OFFLINE'),
            qr: res.data.qr,
            pairingCode: res.data.pairing_code
        };
    } catch (error) {
        waData.value = { status: 'OFFLINE', qr: null, pairingCode: null };
    }
};

const generateNewCode = async () => {
    waData.value.status = 'LOADING';
    await checkStatus();
};

onMounted(() => {
    checkStatus();
    waInterval = setInterval(checkStatus, 5000); 
});

onUnmounted(() => { if (waInterval) clearInterval(waInterval); });
</script>

<template>
    <Head title="Pengaturan Instansi" />
    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto py-6 space-y-8 font-sans">
            
            <div class="flex justify-between items-center bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Pengaturan Sistem SINDEN</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-1">Konfigurasi Identitas, Background Login, Logo, dan Integrasi</p>
                </div>
                <span class="text-[10px] font-extrabold bg-blue-50 text-blue-600 px-3 py-1 rounded-full uppercase tracking-wider">Admin Control</span>
            </div>

            <!-- Status WA Gateway Card -->
            <div class="bg-white p-8 rounded-3xl shadow-xs border border-slate-200 overflow-hidden space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-4">
                        <div :class="waData.status === 'CONNECTED' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"class="w-14 h-14 rounded-2xl flex items-center justify-center transition-colors duration-500 shadow-xs">
                            <span v-if="waData.status === 'CONNECTED'" class="text-2xl animate-pulse"></span>
                            <span v-else class="text-2xl"></span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-slate-900 uppercase text-base tracking-tight">STATUS WA GATEWAY</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <div :class="waData.status === 'CONNECTED' ? 'bg-emerald-500 animate-ping' : 'bg-rose-500'" class="w-2 h-2 rounded-full"></div>
                                <span class="text-[11px] font-extrabold uppercase" :class="waData.status === 'CONNECTED' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ waData.status === 'CONNECTED' ? 'TERHUBUNG / LIVE' : 'TERPUTUS / OFFLINE' }}
                                </span>
                            </div>
                            <button @click="generateNewCode" v-if="waData.status !== 'CONNECTED'" class="mt-2 text-[10px] font-extrabold text-blue-600 uppercase border-b border-dashed border-blue-300 hover:text-blue-800 transition"> Refresh Status Koneksi
                            </button>
                        </div>
                    </div>

                    <div v-if="waData.status !== 'CONNECTED' && waData.qr" class="bg-white p-4 rounded-3xl shadow-xl border-4 border-slate-900">
                        <p class="text-[9px] text-slate-900 font-extrabold uppercase mb-2 text-center tracking-wider">SCAN QR AKTIVASI</p>
                        <qrcode-vue :value="waData.qr" :size="150" level="H" class="mx-auto" />
                        <p class="mt-2 text-[8px] text-slate-400 font-bold text-center uppercase">Gunakan WhatsApp Web di HP</p>
                    </div>

                    <div v-else-if="waData.status !== 'CONNECTED' && waData.pairingCode" class="bg-slate-900 p-6 rounded-3xl shadow-xl">
                        <p class="text-[9px] text-slate-300 font-extrabold uppercase mb-3 text-center tracking-wider">KODE PAIRING ANDA</p>
                        <div class="flex gap-2">
                            <span v-for="(char, index) in waData.pairingCode" :key="index" class="bg-white text-slate-900 px-3 py-2 rounded-lg font-black text-xl font-mono shadow-inner border-b-4 border-slate-300">
                                {{ char }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sakelar Kontrol Master Notifikasi Otomatis WhatsApp -->
                <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="text-xs font-black uppercase text-slate-900 tracking-wider">MODUL NOTIFIKASI OTOMATIS WHATSAPP</h4>
                        <p class="text-[10px] text-slate-500 font-semibold mt-0.5">Aktifkan atau nonaktifkan pengiriman pesan WhatsApp otomatis ke HP pemohon saat ada pengajuan, pengesahan, atau revisi.</p>
                    </div>
                    <div class="flex items-center gap-3 bg-slate-50 p-2.5 px-4 rounded-2xl border border-slate-200">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" :checked="form.wa_notifications_enabled === '1'" @change="form.wa_notifications_enabled = $event.target.checked ? '1' : '0'" class="sr-only peer" />
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                        <span class="text-xs font-black uppercase tracking-wider" :class="form.wa_notifications_enabled === '1' ? 'text-emerald-600' : 'text-rose-600'">
                            {{ form.wa_notifications_enabled === '1' ? 'NOTIFIKASI WA AKTIF' : 'NOTIFIKASI WA NONAKTIF' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form Configuration -->
            <div class="bg-white p-8 rounded-3xl shadow-xs border border-slate-200">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- File Uploads Grid: Logo, Background Login, Favicon -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Logo Instansi -->
                        <div class="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 group hover:border-blue-400 transition">
                            <div class="relative w-28 h-28 rounded-2xl border-4 border-white shadow-md flex items-center justify-center overflow-hidden bg-white mb-4">
                                <img v-if="logoPreview" :src="logoPreview" class="object-contain w-full h-full p-2" />
                                <span v-else class="text-slate-300 font-black text-3xl uppercase">
                                    {{ form.agency_name ? form.agency_name.charAt(0) : '?' }}
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-xs inline-block"> Ganti Logo Instansi
                                    <input type="file" @change="handleLogoChange" class="hidden" accept="image/*" />
                                </label>
                                <p class="text-[9px] text-slate-400 mt-2 uppercase font-bold">Maksimal: 2MB (JPG/PNG)</p>
                            </div>
                        </div>

                        <!-- Background Halaman Login -->
                        <div class="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 group hover:border-blue-400 transition">
                            <div class="relative w-28 h-28 rounded-2xl border-4 border-white shadow-md flex items-center justify-center overflow-hidden bg-slate-900 mb-4">
                                <img v-if="bgPreview" :src="bgPreview" class="object-cover w-full h-full opacity-80" />
                                <span v-else class="text-slate-400 font-black text-xs uppercase text-center p-2"> BG LOGIN
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-xs inline-block"> Background Login
                                    <input type="file" @change="handleBgChange" class="hidden" accept="image/*" />
                                </label>
                                <p class="text-[9px] text-slate-400 mt-2 uppercase font-bold">Maksimal: 5MB (Full HD)</p>
                            </div>
                        </div>

                        <!-- Favicon Web -->
                        <div class="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 group hover:border-blue-400 transition">
                            <div class="relative w-28 h-28 rounded-2xl border-4 border-white shadow-md flex items-center justify-center overflow-hidden bg-white mb-4">
                                <img v-if="faviconPreview" :src="faviconPreview" class="object-contain w-14 h-14" />
                                <span v-else class="text-slate-300 font-black text-3xl uppercase">
                                    
                                </span>
                            </div>
                            <div class="text-center">
                                <label class="cursor-pointer bg-white px-4 py-2 rounded-xl border border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-xs inline-block"> Ganti Favicon Web
                                    <input type="file" @change="handleFaviconChange" class="hidden" accept="image/x-icon,image/png,image/jpeg" />
                                </label>
                                <p class="text-[9px] text-slate-400 mt-2 uppercase font-bold">ICO/PNG. Maks: 1MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Text Input Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nama Instansi / Aplikasi</label>
                            <input v-model="form.agency_name" type="text" class="w-full rounded-2xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-600 uppercase text-xs font-bold px-4 py-3 transition" placeholder="CONTOH: DENINTEL KODAERAL V">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Teks Copyright</label>
                            <input v-model="form.copyright" type="text" class="w-full rounded-2xl border-slate-200 bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-600 text-xs font-semibold px-4 py-3 transition">
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider ms-1 flex items-center gap-1"> Nomor Urut Surat Awal
                            </label>
                            <input v-model="form.start_number" type="number" min="1" class="w-full rounded-2xl border-blue-200 bg-blue-50/50 focus:ring-blue-500 focus:border-blue-600 text-xs font-extrabold px-4 py-3 text-blue-700">
                        </div>

                        <!-- TTD Komandan Specimen -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-extrabold text-purple-600 uppercase tracking-wider ms-1 flex items-center gap-1"> Spesimen Tanda Tangan Komandan
                            </label>
                            <div class="flex items-center gap-4 p-3 bg-purple-50/40 rounded-2xl border border-purple-100">
                                <div class="w-14 h-14 bg-white rounded-xl border border-purple-200 flex items-center justify-center overflow-hidden shrink-0">
                                    <img v-if="signaturePreview" :src="signaturePreview" class="max-w-full max-h-full object-contain p-1" />
                                    <span v-else class="text-[8px] text-purple-300 font-bold uppercase text-center">KOSONG</span>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-[10px] font-extrabold text-purple-600 uppercase cursor-pointer hover:underline"> Pilih File PNG Transparan
                                        <input type="file" @change="handleSignatureChange" class="hidden" accept="image/png" />
                                    </label>
                                    <p class="text-[8px] text-slate-400 leading-tight mt-0.5">Wajib PNG Tanpa Background agar tidak menutupi teks surat.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 flex justify-end items-center gap-4">
                        <transition leave-active-class="transition ease-in duration-1000" leave-from-class="opacity-100" leave-to-class="opacity-0">
                            <p v-if="form.recentlySuccessful" class="text-xs font-extrabold text-emerald-600 uppercase tracking-wider"> Konfigurasi Berhasil Disimpan
                            </p>
                        </transition>
                        
                        <button :disabled="form.processing"class="bg-blue-600 text-white px-8 py-3.5 rounded-2xl font-extrabold uppercase text-xs tracking-wider hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 disabled:opacity-50 active:scale-95">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Konfigurasi System' }}
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Quick Link Stamp Setting -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs flex items-center justify-between group hover:border-blue-500 transition cursor-pointer" 
                 @click="router.get(route('stamp.setting'))">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-xl group-hover:scale-105 transition-transform">
                        
                    </div>
                    <div class="flex flex-col text-left">
                        <span class="text-xs font-extrabold text-slate-900 uppercase tracking-wider">Konfigurasi Stempel Digital</span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase mt-0.5">Update Master Stempel PNG Komandan</span>
                    </div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400 group-hover:text-blue-600 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </div>

        </div>
    </AuthenticatedLayout>
</template>