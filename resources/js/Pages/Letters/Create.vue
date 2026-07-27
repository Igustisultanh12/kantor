<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    categories: Array,
    letterCategories: Array,
    telegramCategories: Array,
    selectedLog: Object
});

// State Management
const isScanning = ref(false);
const fileInput = ref(null);
const filteredSubCategories = ref([]);

// Load Scanner.js secara dinamis
onMounted(() => {
    if (!window.scanner) {
        const script = document.createElement('script');
        script.src = "https://cdn.asprise.com/scannerjs/scanner.js";
        script.type = "text/javascript";
        document.head.appendChild(script);
    }
});

const form = useForm({
    letter_log_id: props.selectedLog?.id || null,
    type: props.selectedLog ? 'keluar' : 'masuk', 
    security_level: props.selectedLog?.priority === 'R' ? 'rahasia' : 'biasa',
    letter_number: props.selectedLog?.full_number || '', 
    category_id: props.selectedLog?.category_id || '',
    sub_category_id: '', 
    subject: props.selectedLog?.subject || '', 
    date: props.selectedLog?.date || new Date().toISOString().substr(0, 10),
    issuer: '',
    file: null,
});

/**
 * LOGIKA SCANNER.JS
 */
const startScan = () => {
    if (!window.scanner) {
        Swal.fire('Error', 'Library Scanner belum siap. Pastikan koneksi internet aktif.', 'error');
        return;
    }
    isScanning.value = true;
    
    scanner.scan(displayServerResponse, {
        "output_settings": [
            {
                "type": "return-base64",
                "format": "pdf",
                "pdf_config": {
                    "name": "SCAN_" + Date.now()
                }
            }
        ]
    });
};

function displayServerResponse(successful, mesg, response) {
    if (!successful) {
        isScanning.value = false;
        Swal.fire('Gagal Scan', mesg, 'error');
        return;
    }

    const scannedFile = response.getScannedImage(0); 
    
    fetch(scannedFile.src)
        .then(res => res.blob())
        .then(blob => {
            // Berikan nama file yang jelas agar mudah dicari di storage Armbian
            const fileName = "SCAN_" + form.letter_number.replace(/[/\\?%*:|"<>]/g, '-') + ".pdf";
            const file = new File([blob], fileName, { type: "application/pdf" });
            
            form.file = file;
            isScanning.value = false;
            Swal.fire({
                title: 'Scan Berhasil',
                text: 'Dokumen dari printer telah terlampir.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });
}

const availableCategories = computed(() => {
    if (form.type === 'telegram') {
        return props.telegramCategories && props.telegramCategories.length > 0 
            ? props.telegramCategories 
            : (props.categories ? props.categories.filter(c => c.is_telegram) : []);
    }
    return props.letterCategories && props.letterCategories.length > 0 
        ? props.letterCategories 
        : (props.categories ? props.categories.filter(c => !c.is_telegram) : []);
});

watch(() => form.category_id, (newCategoryId) => {
    const allCats = [...(props.letterCategories || []), ...(props.categories || [])];
    const selectedCategory = allCats.find(cat => cat.id === newCategoryId);
    
    if (selectedCategory && selectedCategory.sub_categories && selectedCategory.sub_categories.length > 0) {
        filteredSubCategories.value = selectedCategory.sub_categories;
    } else {
        filteredSubCategories.value = [];
        form.sub_category_id = ''; 
    }
}, { immediate: true });

watch(() => form.type, () => {
    if (!props.selectedLog) {
        form.category_id = '';
        form.sub_category_id = '';
    }
});

const triggerFileInput = () => {
    fileInput.value.click();
};

const submit = () => {
    // Indikator Loading untuk antisipasi file PDF besar
    Swal.fire({
        title: 'Mengirim Arsip...',
        text: 'Mohon tunggu, berkas sedang diunggah ke server.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    form.post(route('letters.store'), {
        forceFormData: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Arsip dan berkas scan telah disimpan.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            }).then(() => {
                // Redirect ke daftar surat agar data terupdate
                router.visit(route('letters.index'));
            });
        },
        onError: (errors) => {
            Swal.fire('Gagal Simpan', 'Periksa kembali kelengkapan data atau ukuran file.', 'error');
        }
    });
};
</script>

<template>
    <Head title="Tambah Arsip" />

    <AuthenticatedLayout>
        <div class="max-w-3xl mx-auto py-6 font-sans">
            <!-- Header Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] mb-6 flex justify-between items-center">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">
                        {{ props.selectedLog ? 'Otomatisasi Arsip PDF' : 'Input Arsip Baru' }}
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Sistem Manajemen Pengarsipan & Penyimpanan Berkas Dokumen</p>
                </div>
                <Link :href="route('letters.index')" class="text-xs font-extrabold text-slate-400 hover:text-slate-700 uppercase tracking-wider">
                    ← Kembali
                </Link>
            </div>

            <form @submit.prevent="submit" class="bg-white p-8 shadow-xs rounded-3xl border border-[#E2E8F0] space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="md:col-span-2 space-y-2">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Tipe Dokumen</label>
                        <div class="grid grid-cols-3 gap-4" :class="{'opacity-50 pointer-events-none': props.selectedLog}">
                            <label v-for="t in ['masuk', 'keluar', 'telegram']" :key="t" 
                                :class="['flex items-center justify-center py-3.5 px-4 rounded-2xl border-2 cursor-pointer transition-all', form.type === t ? 'border-blue-600 bg-blue-50 text-blue-700 font-extrabold' : 'border-slate-100 text-slate-400 hover:bg-slate-50 font-semibold']">
                                <input type="radio" v-model="form.type" :value="t" class="hidden">
                                <span class="text-xs uppercase tracking-wider">{{ t === 'telegram' ? 'Telegram' : 'Surat ' + t }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Nomor Dokumen</label>
                        <input v-model="form.letter_number" type="text" :readonly="!!props.selectedLog" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold uppercase font-mono h-12 focus:ring-blue-500 focus:border-blue-600">
                    </div>

                    <div v-if="form.type !== 'telegram'" class="md:col-span-2 space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Sifat Surat</label>
                        <select v-model="form.security_level" :disabled="!!props.selectedLog" class="w-full rounded-2xl border-slate-200 bg-slate-50 h-12 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                            <option value="biasa">BIASA (B)</option>
                            <option value="rahasia">RAHASIA (R)</option>
                        </select>
                    </div>

                    <div :class="filteredSubCategories.length > 0 ? 'md:col-span-1' : 'md:col-span-2'" class="space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Kategori</label>
                        <select v-model="form.category_id" :disabled="!!props.selectedLog" class="w-full rounded-2xl border-slate-200 bg-slate-50 h-12 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                            <option value="" disabled>Pilih Kategori</option>
                            <option v-for="cat in availableCategories" :key="cat.id" :value="cat.id">{{ cat.name }} ({{ cat.code }})</option>
                        </select>
                    </div>

                    <div v-if="filteredSubCategories.length > 0" class="space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Sub-Jenis</label>
                        <select v-model="form.sub_category_id" class="w-full rounded-2xl border-slate-200 bg-slate-50 h-12 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                            <option value="" disabled>Pilih Sub-Jenis</option>
                            <option v-for="sub in filteredSubCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Perihal / Isi Ringkas</label>
                        <input v-model="form.subject" type="text" :readonly="!!props.selectedLog" class="w-full rounded-2xl border-slate-200 bg-slate-50 h-12 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Tanggal Dokumen</label>
                        <input v-model="form.date" type="date" :readonly="!!props.selectedLog" class="w-full rounded-2xl border-slate-200 bg-slate-50 h-12 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Asal / Instansi</label>
                        <input v-model="form.issuer" type="text" placeholder="MISAL: KODAM V/BRW" class="w-full rounded-2xl border-slate-200 bg-slate-50 uppercase h-12 text-xs font-bold focus:ring-blue-500 focus:border-blue-600">
                    </div>

                    <div class="md:col-span-2 space-y-3 pt-4 border-t border-slate-100">
                        <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest px-1">Lampiran Berkas (PDF)</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div @click="triggerFileInput" class="flex flex-col items-center justify-center p-6 border-2 border-slate-200 border-dashed rounded-3xl hover:border-blue-500 hover:bg-blue-50/40 transition cursor-pointer group">
                                <input ref="fileInput" type="file" class="hidden" accept=".pdf" @change="e => form.file = e.target.files[0]">
                                <span class="text-[20px] mb-1">📂</span>
                                <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider">Cari di Komputer</span>
                            </div>
                            <div @click="startScan" class="flex flex-col items-center justify-center p-6 border-2 border-emerald-200 border-dashed rounded-3xl hover:border-emerald-500 hover:bg-emerald-50/40 transition cursor-pointer group">
                                <span class="text-[20px] mb-1">{{ isScanning ? '⏳' : '🖨️' }}</span>
                                <span class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-wider">Tarik dari Printer</span>
                            </div>
                        </div>

                        <div v-if="form.file" class="bg-slate-50 border border-slate-200 p-4 rounded-2xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">📄</span>
                                <div class="flex flex-col leading-none">
                                    <span class="text-[10px] font-extrabold text-slate-900 uppercase truncate max-w-[200px]">{{ form.file.name }}</span>
                                    <span class="text-[8px] text-slate-400 font-bold uppercase mt-1 italic">{{ (form.file.size / 1024 / 1024).toFixed(2) }} MB</span>
                                </div>
                            </div>
                            <button @click="form.file = null" type="button" class="text-[10px] font-extrabold text-rose-500 uppercase hover:underline">Hapus</button>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end space-x-4 border-t border-slate-100 pt-6">
                    <Link :href="route('letters.index')" class="text-xs font-extrabold text-slate-400 uppercase tracking-wider hover:text-slate-800 transition">Batal</Link>
                    <button type="submit" :disabled="form.processing || !form.file"
                            class="bg-blue-600 text-white px-8 py-3.5 rounded-2xl font-extrabold uppercase text-xs tracking-wider hover:bg-blue-700 shadow-md shadow-blue-500/20 disabled:opacity-50 transition-all active:scale-95">
                        Simpan Arsip
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>