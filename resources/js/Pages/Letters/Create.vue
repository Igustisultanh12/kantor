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
        <template #header>
            <h2 class="font-bold text-xl text-gray-800 leading-tight uppercase italic">
                {{ props.selectedLog ? 'Otomatisasi Arsip PDF' : 'Input Arsip Baru' }}
            </h2>
        </template>

        <div class="max-w-3xl mx-auto py-8">
            <form @submit.prevent="submit" class="bg-white p-8 shadow-sm rounded-[2.5rem] border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="md:col-span-2 space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Tipe Dokumen</label>
                        <div class="grid grid-cols-3 gap-4" :class="{'opacity-50 pointer-events-none': props.selectedLog}">
                            <label v-for="t in ['masuk', 'keluar', 'telegram']" :key="t" 
                                :class="['flex items-center justify-center py-3 px-4 rounded-2xl border-2 cursor-pointer transition-all', form.type === t ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-50 text-gray-400 hover:bg-gray-50']">
                                <input type="radio" v-model="form.type" :value="t" class="hidden">
                                <span class="text-xs font-black uppercase tracking-widest">{{ t === 'telegram' ? 'Telegram' : 'Surat ' + t }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Nomor Dokumen</label>
                        <input v-model="form.letter_number" type="text" :readonly="!!props.selectedLog" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm uppercase font-mono h-12">
                    </div>

                    <div v-if="form.type !== 'telegram'" class="md:col-span-2 space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Sifat Surat</label>
                        <select v-model="form.security_level" :disabled="!!props.selectedLog" class="w-full rounded-2xl border-gray-200 h-12 text-sm font-bold">
                            <option value="biasa">BIASA (B)</option>
                            <option value="rahasia">RAHASIA (R)</option>
                        </select>
                    </div>

                    <div :class="filteredSubCategories.length > 0 ? 'md:col-span-1' : 'md:col-span-2'" class="space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Kategori</label>
                        <select v-model="form.category_id" :disabled="!!props.selectedLog" class="w-full rounded-2xl border-gray-200 h-12 text-sm font-bold">
                            <option value="" disabled>Pilih Kategori</option>
                            <option v-for="cat in availableCategories" :key="cat.id" :value="cat.id">{{ cat.name }} ({{ cat.code }})</option>
                        </select>
                    </div>

                    <div v-if="filteredSubCategories.length > 0" class="space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Sub-Jenis</label>
                        <select v-model="form.sub_category_id" class="w-full rounded-2xl border-gray-200 h-12 text-sm font-bold">
                            <option value="" disabled>Pilih Sub-Jenis</option>
                            <option v-for="sub in filteredSubCategories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                        </select>
                    </div>

                    <div class="md:col-span-2 space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Perihal / Isi Ringkas</label>
                        <input v-model="form.subject" type="text" :readonly="!!props.selectedLog" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500 shadow-sm h-12 text-sm font-bold italic">
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Tanggal Dokumen</label>
                        <input v-model="form.date" type="date" :readonly="!!props.selectedLog" class="w-full rounded-2xl border-gray-200 h-12 text-sm font-bold">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Asal / Instansi</label>
                        <input v-model="form.issuer" type="text" placeholder="MISAL: KODAM V/BRW" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500 uppercase h-12 text-sm font-bold">
                    </div>

                    <div class="md:col-span-2 space-y-3 pt-4 border-t border-gray-50">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">Lampiran Berkas (PDF)</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div @click="triggerFileInput" class="flex flex-col items-center justify-center p-6 border-2 border-gray-100 border-dashed rounded-[2rem] hover:border-indigo-400 hover:bg-indigo-50/30 transition cursor-pointer group">
                                <input ref="fileInput" type="file" class="hidden" accept=".pdf" @change="e => form.file = e.target.files[0]">
                                <span class="text-[20px] mb-1">📂</span>
                                <span class="text-[9px] font-black text-indigo-600 uppercase tracking-tighter">Cari di Komputer</span>
                            </div>
                            <div @click="startScan" class="flex flex-col items-center justify-center p-6 border-2 border-emerald-100 border-dashed rounded-[2rem] hover:border-emerald-400 hover:bg-emerald-50/30 transition cursor-pointer group">
                                <span class="text-[20px] mb-1">{{ isScanning ? '⏳' : '🖨️' }}</span>
                                <span class="text-[9px] font-black text-emerald-600 uppercase tracking-tighter">Tarik dari Printer</span>
                            </div>
                        </div>

                        <div v-if="form.file" class="bg-gray-50 border border-gray-100 p-4 rounded-2xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">📄</span>
                                <div class="flex flex-col leading-none">
                                    <span class="text-[10px] font-black text-gray-950 uppercase truncate max-w-[200px]">{{ form.file.name }}</span>
                                    <span class="text-[8px] text-gray-400 font-bold uppercase mt-1 italic">{{ (form.file.size / 1024 / 1024).toFixed(2) }} MB</span>
                                </div>
                            </div>
                            <button @click="form.file = null" type="button" class="text-[10px] font-black text-rose-500 uppercase hover:underline">Hapus</button>
                        </div>
                    </div>
                </div>

                <div class="mt-10 flex items-center justify-end space-x-6 border-t border-gray-50 pt-8">
                    <Link :href="route('letters.index')" class="text-xs font-black text-gray-400 uppercase tracking-widest hover:text-gray-900 transition">Batal</Link>
                    <button type="submit" :disabled="form.processing || !form.file"
                            class="bg-indigo-600 text-white px-10 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-700 shadow-xl disabled:opacity-50 transition-all active:scale-95">
                        Simpan Arsip
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>