<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3'; 
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import html2pdf from 'html2pdf.js';

const props = defineProps({
    cashes: Array,
    totalSaldo: Number
});

const user = computed(() => usePage().props.auth.user);

// --- STATE MANAGER ---
const showPreview = ref(false);
const previewImage = ref(null);
const filterMonth = ref(new Date().getMonth() + 1); 
const filterYear = ref(new Date().getFullYear());
const searchQuery = ref(''); 

// PENYESUAIAN MULTI-UPLOAD: State penampung kumpulan objek URL pratinjau
const receiptPreviews = ref([]); 
const editPreviews = ref([]);

// State Modal Detail & Berkas Nota
const showDetailModal = ref(false);
const selectedTransaction = ref(null);

// State Modal Koreksi/Edit Transaksi Baru
const showEditModal = ref(false);

const months = [
    { id: 1, name: 'Januari' }, { id: 2, name: 'Februari' }, { id: 3, name: 'Maret' },
    { id: 4, name: 'April' }, { id: 5, name: 'Mei' }, { id: 6, name: 'Juni' },
    { id: 7, name: 'Juli' }, { id: 8, name: 'Agustus' }, { id: 9, name: 'September' },
    { id: 10, name: 'Oktober' }, { id: 11, name: 'November' }, { id: 12, name: 'Desember' }
];

const formatRupiah = (angka) => {
    if (angka === null || angka === undefined || isNaN(angka)) return 'Rp. 0,00';
    const nominal = typeof angka === 'string' ? parseFloat(angka) : angka;
    const formatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    return formatter.format(nominal).replace('Rp', 'Rp.');
};

const filteredCashes = computed(() => {
    return props.cashes.filter(cash => {
        if (!cash.date) return false;
        const cleanDate = typeof cash.date === 'string' ? cash.date.split('T')[0] : '';
        const parts = cleanDate.split('-');
        let year, month;
        if (parts.length === 3) {
            year = parseInt(parts[0], 10);
            month = parseInt(parts[1], 10);
        } else {
            const d = new Date(cash.date);
            year = d.getFullYear();
            month = d.getMonth() + 1;
        }
        const matchDate = month == filterMonth.value && year == filterYear.value;
        const query = searchQuery.value.toLowerCase().trim();
        if (!query) return matchDate;

        return matchDate && (
            cash.description.toLowerCase().includes(query) ||
            cash.date.toLowerCase().includes(query) ||
            cash.debit.toString().includes(query) ||
            cash.credit.toString().includes(query)
        );
    });
});

const totalDebit = computed(() => filteredCashes.value.reduce((acc, curr) => acc + parseFloat(curr.debit), 0));
const totalCredit = computed(() => filteredCashes.value.reduce((acc, curr) => acc + parseFloat(curr.credit), 0));
const selectedMonthName = computed(() => months.find(m => m.id == filterMonth.value)?.name.toUpperCase());

const getTodayLocalDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Form Tambah Transaksi Baru (Multi-Files Array)
const form = useForm({
    date: getTodayLocalDate(),
    description: '',
    debit: 0,
    credit: 0,
    receipt_files: [] 
});

// Form Khusus Koreksi Data (Mendukung Spoofing Method PUT dengan Multipart Multi-Berkas)
const editForm = useForm({
    _method: 'PUT',
    id: null,
    date: '',
    description: '',
    debit: 0,
    credit: 0,
    receipt_files: []
});

// PENYESUAIAN MULTI-UPLOAD: Koleksi loop pembentukan objek penampil pratinjau nota baru
const handleReceiptChange = (e) => {
    const files = Array.from(e.target.files);
    form.receipt_files = files;
    receiptPreviews.value = [];

    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            receiptPreviews.value.push({ url: URL.createObjectURL(file), is_pdf: false });
        } else if (file.type === 'application/pdf') {
            receiptPreviews.value.push({ url: null, is_pdf: true, name: file.name });
        }
    });
};

// PENYESUAIAN MULTI-UPLOAD: Koleksi loop pembentukan objek penampil pratinjau nota koreksi
const handleEditReceiptChange = (e) => {
    const files = Array.from(e.target.files);
    editForm.receipt_files = files;
    editPreviews.value = [];

    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            editPreviews.value.push({ url: URL.createObjectURL(file), is_pdf: false });
        } else if (file.type === 'application/pdf') {
            editPreviews.value.push({ url: null, is_pdf: true, name: file.name });
        }
    });
};

const submit = () => {
    form.post(route('cash.store'), {
        onSuccess: () => {
            form.reset('description', 'debit', 'credit', 'receipt_files');
            receiptPreviews.value = []; 
            Swal.fire('Berhasil', 'Catatan Kas beserta Lampiran Multi-Nota telah diamankan.', 'success');
        }
    });
};

// Pemicu Modal Detail Transaksi & Pratinjau Nota
const openDetail = (cash) => {
    selectedTransaction.value = cash;
    showDetailModal.value = true;
};

// Membuka Modal Kustom Vue murni
const editTransaction = (cash) => {
    editForm.id = cash.id;
    editForm.date = cash.date ? cash.date.substring(0, 10) : '';
    editForm.description = cash.description;
    editForm.debit = cash.debit;
    editForm.credit = cash.credit;
    editForm.receipt_files = [];
    
    // Sinkronisasi data multi-url lampiran yang sudah tersimpan ke preview edit modal
    editPreviews.value = cash.receipt_urls ? [...cash.receipt_urls] : [];
    showEditModal.value = true;
};

const submitUpdate = () => {
    // Memaksa pengiriman dengan forceFormData agar data biner multi-upload diproses multipart murni oleh Laravel
    editForm.post(route('cash.update', editForm.id), {
        forceFormData: true,
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            editPreviews.value = [];
            Swal.fire('Tersimpan', 'Data transaksi & berkas nota berhasil dikalibrasi ulang.', 'success');
        }
    });
};

const deleteTransaction = (id) => {
    Swal.fire({
        title: 'Hapus?',
        text: "Log transaksi akan dihapus !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('cash.destroy', id), {
                onSuccess: () => Swal.fire('Terhapus', 'Log transaksi telah dihapus.', 'success')
            });
        }
    });
};

const openPreview = () => {
    const element = document.getElementById('area-kas-cetak');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        html2canvas: { scale: 2, useCORS: true, width: 790 },
    };
    html2pdf().set(opt).from(element).outputImg().then((img) => {
        previewImage.value = img.src;
        showPreview.value = true;
    });
};

const downloadPDF = () => {
    const element = document.getElementById('area-kas-cetak');
    const opt = {
        margin: 0.5,
        filename: `BUKU_KAS_${selectedMonthName.value}_${filterYear.value}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
};

const formatLongDate = (dateStr) => {
    if (!dateStr) return '';
    if (dateStr instanceof Date) {
        return dateStr.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }
    const cleanDateStr = typeof dateStr === 'string' ? dateStr.split('T')[0] : dateStr;
    const parts = cleanDateStr.split('-');
    if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        return new Date(year, month, day).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    }
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};
</script>

<template>
    <Head title="Pembukuan Kas" />
    <AuthenticatedLayout>
        <div class="py-12 px-4 md:px-0 font-sans">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div>
                        <h2 class="font-black text-indigo-900 uppercase tracking-widest text-lg">Pembukuan Kas Unit</h2>
                        <p class="text-[10px] text-gray-400 font-bold uppercase">Sistem Intelijen Digital - Denintel</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 bg-gray-50 p-3 rounded-xl border border-gray-100 font-bold w-full lg:w-auto">
                        <div class="flex flex-col flex-1 sm:flex-initial">
                            <label class="text-[8px] text-indigo-700 ml-1 mb-0.5 uppercase font-black tracking-wider">🔎 Cari Transaksi</label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    v-model="searchQuery" 
                                    placeholder="Ketik uraian / nominal..." 
                                    class="text-xs font-bold rounded-lg border-gray-200 py-1.5 w-full sm:w-56 focus:ring-indigo-500 pl-7"
                                />
                                <span class="absolute left-2.5 top-2 text-xs text-gray-400">🔍</span>
                                <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1.5 text-gray-400 hover:text-red-500 text-sm">×</button>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="flex flex-col">
                                <label class="text-[8px] text-gray-400 ml-1 mb-0.5">FILTER BULAN</label>
                                <select v-model="filterMonth" class="text-xs font-bold rounded-lg border-gray-200 uppercase py-1.5">
                                    <option v-for="m in months" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>
                            <div class="flex flex-col">
                                <label class="text-[8px] text-gray-400 ml-1 mb-0.5">TAHUN</label>
                                <input type="number" v-model="filterYear" class="w-20 text-xs font-bold rounded-lg border-gray-200 py-1.5" />
                            </div>
                            <button @click="openPreview" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2.5 rounded-lg text-[10px] font-black uppercase shadow-lg flex items-center gap-2 mt-3.5 whitespace-nowrap">
                                <span>📄</span> Pratinjau PDF
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-lg border-t-4 border-green-600">
                    <h3 class="font-black mb-6 uppercase text-xs tracking-widest text-slate-500 italic">➕ Tambah Log Transaksi</h3>
                    <form @submit.prevent="submit" class="space-y-6 italic font-bold">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="flex flex-col">
                                <label class="text-[10px] text-gray-400 mb-1 uppercase tracking-widest ml-1">📅 Tanggal</label>
                                <input type="date" v-model="form.date" class="rounded-xl border-gray-200 text-sm focus:ring-green-500" required />
                            </div>
                            <div class="flex flex-col">
                                <label class="text-[10px] text-gray-400 mb-1 uppercase tracking-widest ml-1">📝 Uraian / Keterangan</label>
                                <input type="text" v-model="form.description" placeholder="Masukkan keterangan..." class="rounded-xl border-gray-200 text-sm focus:ring-green-500" required />
                            </div>
                            <div class="flex flex-col p-2 bg-green-50 rounded-xl border border-green-100">
                                <label class="text-[10px] text-green-700 mb-1 uppercase font-black tracking-widest ml-1">📥 Debit (Masuk)</label>
                                <input type="number" v-model="form.debit" class="w-full bg-transparent border-none text-sm focus:ring-0 font-mono" />
                            </div>
                            <div class="flex flex-col p-2 bg-red-50 rounded-xl border border-red-100">
                                <label class="text-[10px] text-red-700 mb-1 uppercase font-black tracking-widest ml-1">📤 Kredit (Keluar)</label>
                                <input type="number" v-model="form.credit" class="w-full bg-transparent border-none text-sm focus:ring-0 font-mono" />
                            </div>
                        </div>

                        <div class="p-5 bg-slate-50/80 rounded-2xl border border-dashed border-slate-200 flex flex-col md:flex-row items-center gap-6 group transition-all hover:border-indigo-300">
                            <div class="flex gap-2 flex-wrap items-center justify-center min-w-[6rem] max-w-md shrink-0">
                                <template v-if="receiptPreviews.length > 0">
                                    <div v-for="(prev, index) in receiptPreviews" :key="index" class="w-14 h-14 rounded-lg bg-white border border-slate-200 shadow-sm flex items-center justify-center overflow-hidden">
                                        <img v-if="!prev.is_pdf" :src="prev.url" class="w-full h-full object-cover" />
                                        <span v-else class="text-[8px] text-red-500 font-black uppercase text-center p-0.5 leading-none">📄 PDF</span>
                                    </div>
                                </template>
                                <span v-else class="text-[10px] text-slate-300 font-black uppercase tracking-tighter text-center px-1">Belum Ada Nota</span>
                            </div>
                            <div class="flex-1 text-center md:text-left">
                                <label class="cursor-pointer bg-white border border-slate-200 px-5 py-2 rounded-xl text-[10px] font-black uppercase text-indigo-600 hover:bg-indigo-600 hover:text-white transition shadow-sm inline-block">
                                    📁 Unggah Lampiran Multi-Nota Bukti
                                    <input type="file" @change="handleReceiptChange" class="hidden" accept="image/jpeg,image/jpg,image/png,application/pdf" multiple />
                                </label>
                                <p class="text-[9px] text-gray-400 mt-2 font-bold uppercase tracking-tight">Mendukung unggah banyak gambar sekaligus (Maksimal Kapasitas Gabungan: 150MB)</p>
                                <p v-if="form.receipt_files.length > 0" class="text-[9px] text-emerald-600 mt-1 uppercase font-black">✓ Terpilih: {{ form.receipt_files.length }} File Berkas Berhasil Disiapkan</p>
                            </div>
                        </div>

                        <button :disabled="form.processing" class="w-full bg-green-700 text-white font-black py-4 rounded-2xl uppercase hover:bg-green-800 transition shadow-lg active:scale-95 disabled:opacity-50">
                            💾 Simpan ke Buku Kas Digital
                        </button>
                    </form>
                </div>

                <div class="bg-white overflow-hidden shadow-xl rounded-2xl border border-gray-200">
                    <div class="p-4 bg-slate-50 border-b flex justify-between items-center flex-wrap gap-2">
                        <h3 class="font-black uppercase text-[10px] text-slate-400 tracking-[0.2em]">Log Transaksi Keseluruhan</h3>
                        <span v-if="searchQuery" class="text-[10px] font-black bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full uppercase">
                            Ditemukan: {{ filteredCashes.length }} Hasil
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="bg-slate-800 text-white text-[10px] uppercase font-black tracking-widest">
                                <tr>
                                    <th class="p-4 text-center">Tgl</th>
                                    <th class="p-4">Keterangan</th>
                                    <th class="p-4 text-right">Debit (In)</th>
                                    <th class="p-4 text-right">Kredit (Out)</th>
                                    <th class="p-4 text-right bg-slate-700">Saldo</th>
                                    <th class="p-4 text-center bg-slate-600">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="font-bold">
                                <tr v-for="cash in filteredCashes" :key="cash.id" class="border-b hover:bg-green-50/30 transition-colors group">
                                    <td class="p-4 text-gray-500 text-center text-xs">
                                        {{ cash.date ? cash.date.substring(0, 10) : '' }}
                                    </td>
                                    
                                    <td class="p-4 uppercase text-xs tracking-tight">
                                        <div class="flex flex-col gap-0.5">
                                            <span @click="openDetail(cash)" class="text-slate-900 cursor-pointer hover:text-indigo-600 hover:underline transition-all">
                                                {{ cash.description }}
                                            </span>
                                            <span v-if="cash.receipt_urls && cash.receipt_urls.length > 0" @click="openDetail(cash)" class="text-[9px] text-emerald-600 font-extrabold tracking-wider w-max cursor-pointer">
                                                📎 Terlampir {{ cash.receipt_urls.length }} Nota Bukti Fisik
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="p-4 text-right text-green-600 font-mono">+{{ formatRupiah(cash.debit) }}</td>
                                    <td class="p-4 text-right text-red-600 font-mono">-{{ formatRupiah(cash.credit) }}</td>
                                    <td class="p-4 text-right bg-slate-50 font-black text-indigo-900 font-mono">{{ formatRupiah(cash.balance) }}</td>
                                    <td class="p-4 text-center bg-slate-100/50">
                                        <div class="flex justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click="editTransaction(cash)" class="p-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-600 hover:text-white transition shadow-sm" title="Edit Transaksi">✏️</button>
                                            <button @click="deleteTransaction(cash.id)" class="p-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-600 hover:text-white transition shadow-sm" title="Hapus Transaksi">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredCashes.length === 0">
                                    <td colspan="6" class="p-10 text-center text-gray-400 font-black uppercase italic">Radar Data Kosong / Tidak Ditemukan</td>
                                </tr>
                            </tbody>
                            <thead class="bg-slate-900 text-white font-black">
                                <tr>
                                    <td colspan="4" class="p-5 text-right uppercase italic text-xs tracking-widest border-r border-slate-800">Total Saldo Saat Ini :</td>
                                    <td colspan="2" class="p-5 text-right text-lg font-mono text-yellow-400">{{ formatRupiah(totalSaldo) }}</td>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div style="position: absolute; left: -9999px;">
            <div id="area-kas-cetak" class="p-10 bg-white text-black" style="width: 790px; font-family: 'Times New Roman', serif;">
                <div class="kop-container" style="text-align: left; margin-bottom: 30px;">
                    <div style="display: inline-block; text-align: center;">
                        <p style="margin: 0; font-size: 14px; font-weight: bold; line-height: 1.2; text-transform: uppercase;">KOMANDO DAERAH TNI ANGKATAN LAUT</p>
                        <p style="margin: 0; font-size: 14px; font-weight: bold; line-height: 1.2; text-transform: uppercase;">DETASEMEN INTELIJEN</p>
                        <div style="margin-top: 5px; border-top: 1px solid #000; border-bottom: 3.5px solid #000; height: 2px; width: 320px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                </div>

                <div class="text-center mb-8 uppercase">
                    <h3 style="margin: 0; font-size: 16px; font-weight: bold; text-decoration: underline;">REKENING KORAN BUKU KAS</h3>
                    <p style="margin: 8px 0 0 0; font-size: 11px; font-weight: bold;">BULAN: {{ selectedMonthName }} {{ filterYear }}</p>
                </div>

                <table class="w-full border-collapse border-[1.5px] border-black text-[10px]">
                    <thead>
                        <tr style="background-color: #f2f2f2; text-transform: uppercase; font-weight: bold; text-align: center;">
                            <th style="border: 1px solid #000; padding: 10px 4px; width: 5%;">NO</th>
                            <th style="border: 1px solid #000; padding: 10px 4px; width: 15%;">TANGGAL</th>
                            <th style="border: 1px solid #000; padding: 10px 4px; width: 35%; text-align: left;">KETERANGAN</th>
                            <th style="border: 1px solid #000; padding: 10px 4px; width: 15%;">DEBIT</th>
                            <th style="border: 1px solid #000; padding: 10px 4px; width: 15%;">KREDIT</th>
                            <th style="border: 1px solid #000; padding: 10px 4px; width: 15%;">SALDO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(cash, index) in filteredCashes" :key="cash.id">
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: center;">{{ index + 1 }}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: center;">{{ cash.date ? cash.date.substring(0, 10) : '' }}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-transform: uppercase;">{{ cash.description }}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: right;">{{ cash.debit > 0 ? formatRupiah(cash.debit) : '-' }}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: right;">{{ cash.credit > 0 ? formatRupiah(cash.credit) : '-' }}</td>
                            <td style="border: 1px solid #000; padding: 6px 4px; text-align: right; font-weight: bold;">{{ formatRupiah(cash.balance) }}</td>
                        </tr>
                        <tr v-if="filteredCashes.length === 0">
                            <td colspan="6" style="border: 1px solid #000; padding: 40px; text-align: center; font-weight: bold; color: #9ca3af;">DATA BULAN {{ selectedMonthName }} TIDAK DITEMUKAN</td>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-top: 15px; padding: 10px; border-left: 4px solid #000; background-color: #f9fafb; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                    <p style="margin: 0;">TOTAL DEBIT BULAN INI: {{ formatRupiah(totalDebit) }}</p>
                    <p style="margin: 4px 0 0 0;">TOTAL KREDIT BULAN INI: {{ formatRupiah(totalCredit) }}</p>
                    <div style="margin-top: 8px; border-top: 1px dashed #ccc; padding-top: 5px;">
                        <p style="margin: 0; font-size: 12px;">SALDO AKHIR (TOTAL): {{ formatRupiah(props.totalSaldo) }}</p>
                    </div>
                </div>

                <div style="margin-top: 50px; display: flex; justify-content: flex-end;">
                    <div style="width: 300px; text-align: left; font-size: 11px;">
                        <p style="margin-bottom: 2px;">Dikeluarkan di: Surabaya</p>
                        <p style="margin-bottom: 15px;">Pada tanggal: {{ formatLongDate(new Date()) }}</p>
                        <div style="text-align: center;">
                            <p style="font-weight: bold; margin: 0;">a.n. Komandan Datasemen Intelijen Kodaeral V</p>
                            <p style="font-weight: bold; margin: 0 0 70px 0;">PJ Kas Denintel Kodaeral V,</p>
                            <p style="font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 0;">{{ user.name }}</p>
                            <p style="margin: 2px 0 0 0;">{{ user.pangkat || 'Letnan Dua' }} NRP. {{ user.nrp || 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showPreview" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-white rounded-[2rem] shadow-2xl max-w-5xl w-full h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b flex justify-between items-center bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="bg-indigo-600 h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-black">KAS</div>
                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Pratinjau Buku Kas ({{ selectedMonthName }})</h3>
                    </div>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-red-500 font-black text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto bg-gray-700 p-4 md:p-10 flex justify-center items-start">
                    <img :src="previewImage" class="shadow-2xl border bg-white max-w-[800px] h-auto" />
                </div>
                <div class="p-6 border-t bg-gray-50 flex justify-end gap-3 font-black">
                    <button @click="showPreview = false" class="px-6 py-2.5 text-[10px] uppercase text-gray-500">Batal</button>
                    <button @click="downloadPDF(); showPreview = false" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-[10px] uppercase tracking-widest shadow-lg active:scale-95 transition-transform">Download Sekarang</button>
                </div>
            </div>
        </div>

        <div v-if="showDetailModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] shadow-2xl max-w-6xl w-full h-[85vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b flex justify-between items-center bg-slate-900 text-white">
                    <div class="flex items-center gap-3">
                        <div class="bg-indigo-600 h-7 w-7 rounded-lg flex items-center justify-center text-white text-[10px] font-black">INFO</div>
                        <h3 class="font-black uppercase text-xs tracking-wider">Detail Transaksi & Kumpulan Lampiran Dokumen</h3>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-white text-2xl font-bold leading-none">&times;</button>
                </div>

                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 overflow-hidden bg-slate-50">
                    <div class="p-6 border-r border-slate-200 bg-white space-y-5 overflow-y-auto">
                        <h4 class="text-[10px] font-black text-indigo-900 uppercase tracking-widest border-b pb-2">📋 Identitas Catatan</h4>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase">Keterangan / Uraian:</label>
                            <p class="text-sm font-black text-slate-800 uppercase leading-relaxed mt-0.5">{{ selectedTransaction?.description }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase">Tanggal Buku:</label>
                                <p class="text-xs font-bold text-slate-700 mt-0.5">📅 {{ selectedTransaction?.date ? selectedTransaction.date.substring(0, 10) : '' }}</p>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-gray-400 uppercase">ID Log:</label>
                                <p class="text-xs font-mono text-indigo-600 mt-0.5">#{{ selectedTransaction?.id }}</p>
                            </div>
                        </div>

                        <hr class="border-slate-100" />
                        <h4 class="text-[10px] font-black text-indigo-900 uppercase tracking-widest border-b pb-2">💰 Rincian Nominal</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-green-50 rounded-xl border border-green-100">
                                <span class="text-[10px] text-green-700 font-black uppercase">Debit (Masuk)</span>
                                <span class="text-xs font-black text-green-700 font-mono">+{{ formatRupiah(selectedTransaction?.debit) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-red-50 rounded-xl border border-red-100">
                                <span class="text-[10px] text-red-700 font-black uppercase">Kredit (Keluar)</span>
                                <span class="text-xs font-black text-red-700 font-mono">-{{ formatRupiah(selectedTransaction?.credit) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-slate-100 rounded-xl border border-slate-200">
                                <span class="text-[10px] text-slate-600 font-black uppercase">Saldo Terkalibrasi</span>
                                <span class="text-xs font-black text-slate-800 font-mono">{{ formatRupiah(selectedTransaction?.balance) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 p-6 flex flex-col overflow-hidden">
                        <h4 class="text-[10px] font-black text-indigo-900 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <span>📸</span> Kumpulan Bukti Lampiran Fisik (Nota Berkas)
                        </h4>
                        <div class="flex-1 bg-slate-200/60 rounded-2xl border border-slate-300 overflow-y-auto p-4">
                            <div v-if="selectedTransaction?.receipt_urls && selectedTransaction.receipt_urls.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div v-for="(file, idx) in selectedTransaction.receipt_urls" :key="idx" class="bg-white rounded-xl p-3 shadow-sm border border-slate-200 flex flex-col items-stretch h-72">
                                    <div class="flex-1 overflow-hidden flex items-center justify-center bg-slate-50 rounded-lg">
                                        <iframe v-if="file.is_pdf" :src="file.url" class="w-full h-full rounded-md" frameborder="0"></iframe>
                                        <img v-else :src="file.url" class="max-w-full max-h-full object-contain rounded-md" />
                                    </div>
                                    <div class="mt-2 flex justify-between items-center text-[10px] font-black text-gray-500 uppercase">
                                        <span>Berkas #{{ idx + 1 }}</span>
                                        <a :href="file.url" target="_blank" class="text-indigo-600 hover:underline">↗ Buka Penuh</a>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="h-full flex flex-col items-center justify-center text-center space-y-2 text-gray-400">
                                <span class="text-4xl">⚠️</span>
                                <p class="text-[10px] font-black uppercase italic">Tidak Ada Berkas Nota Terlampir Pada Transaksi Ini</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t bg-slate-50 flex justify-end gap-2 font-black">
                    <button @click="showDetailModal = false" class="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-[10px] uppercase tracking-widest shadow-md">Tutup Informasi</button>
                </div>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-white rounded-[2rem] shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col font-bold">
                <div class="p-6 border-b flex justify-between items-center bg-blue-900 text-white">
                    <h3 class="font-black uppercase text-xs tracking-wider">✏️ Koreksi Log Transaksi #{{ editForm.id }}</h3>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-white text-2xl font-bold leading-none">&times;</button>
                </div>
                
                <form @submit.prevent="submitUpdate" class="flex-1 overflow-y-auto p-6 space-y-5 bg-slate-50">
                    <div class="flex flex-col">
                        <label class="text-[10px] text-gray-400 mb-1 uppercase tracking-widest ml-1">📅 Tanggal Buku</label>
                        <input type="date" v-model="editForm.date" class="rounded-xl border-gray-200 text-sm focus:ring-blue-500" required />
                    </div>

                    <div class="flex flex-col">
                        <label class="text-[10px] text-gray-400 mb-1 uppercase tracking-widest ml-1">📝 Uraian / Keterangan</label>
                        <input type="text" v-model="editForm.description" class="rounded-xl border-gray-200 text-sm focus:ring-blue-500" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col p-2 bg-green-50 rounded-xl border border-green-100">
                            <label class="text-[10px] text-green-700 mb-1 uppercase font-black tracking-widest ml-1">📥 Debit (Masuk)</label>
                            <input type="number" v-model="editForm.debit" class="w-full bg-transparent border-none text-sm focus:ring-0 font-mono" />
                        </div>
                        <div class="flex flex-col p-2 bg-red-50 rounded-xl border border-red-100">
                            <label class="text-[10px] text-red-700 mb-1 uppercase font-black tracking-widest ml-1">📤 Kredit (Keluar)</label>
                            <input type="number" v-model="editForm.credit" class="w-full bg-transparent border-none text-sm focus:ring-0 font-mono" />
                        </div>
                    </div>

                    <div class="p-4 bg-white rounded-xl border border-slate-200 flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <label class="cursor-pointer bg-slate-100 border px-3 py-1.5 rounded-lg text-[9px] font-black uppercase text-blue-700 hover:bg-blue-600 hover:text-white transition inline-block">
                                📁 Ganti / Unggah Kumpulan Nota Baru
                                <input type="file" @change="handleEditReceiptChange" class="hidden" accept="image/jpeg,image/jpg,image/png,application/pdf" multiple />
                            </label>
                            <span v-if="editForm.receipt_files.length > 0" class="text-[9px] text-emerald-600 uppercase font-black">✓ Siap Ganti: {{ editForm.receipt_files.length }} File</span>
                        </div>
                        
                        <div class="flex gap-2 flex-wrap max-h-24 overflow-y-auto p-1 bg-slate-50 rounded-lg">
                            <div v-for="(prev, i) in editPreviews" :key="i" class="w-12 h-12 rounded bg-white border border-slate-200 shadow-xs flex items-center justify-center overflow-hidden">
                                <img v-if="!prev.is_pdf" :src="prev.url" class="w-full h-full object-cover" />
                                <span v-else class="text-[7px] text-red-500 font-black text-center leading-none">📄 PDF</span>
                            </div>
                            <span v-if="editPreviews.length === 0" class="text-[9px] text-gray-300 uppercase italic p-1">Tidak ada berkas yang dipilih</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t flex justify-end gap-2 font-black">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2 text-[10px] uppercase text-gray-500">Batal</button>
                        <button type="submit" :disabled="editForm.processing" class="px-6 py-2 bg-blue-700 text-white rounded-xl text-[10px] uppercase tracking-widest shadow-md">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
</style>