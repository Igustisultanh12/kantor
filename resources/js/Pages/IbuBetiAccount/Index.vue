<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    logs: Array,
    stats: Object,
    filters: Object,
});

const showModal = ref(false);
const isEdit = ref(false);
const previewBuktiUrl = ref(null);
const isPreviewPdf = ref(false);
const showPreviewModal = ref(false);

const getTodayLocalDate = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const form = useForm({
    id: null,
    tanggal: getTodayLocalDate(),
    keterangan: '',
    jenis: 'MASUK',
    jumlah: 0,
    bukti: null,
});

const filterSearch = ref(props.filters?.search || '');
const filterJenis = ref(props.filters?.jenis || '');
const filterMonth = ref(props.filters?.month || '');

const applyFilters = () => {
    router.get(route('ibu-beti.index'), {
        search: filterSearch.value,
        jenis: filterJenis.value,
        month: filterMonth.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterSearch.value = '';
    filterJenis.value = '';
    filterMonth.value = '';
    applyFilters();
};

const openCreateModal = () => {
    isEdit.value = false;
    form.reset();
    form.tanggal = getTodayLocalDate();
    form.jenis = 'MASUK';
    form.jumlah = 0;
    form.bukti = null;
    showModal.value = true;
};

const openEditModal = (item) => {
    isEdit.value = true;
    form.id = item.id;
    form.tanggal = item.tanggal;
    form.keterangan = item.keterangan;
    form.jenis = item.jenis;
    form.jumlah = item.jumlah;
    form.bukti = null;
    showModal.value = true;
};

const handleFileUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.bukti = file;
    }
};

const submit = () => {
    if (isEdit.value) {
        form.post(route('ibu-beti.update', form.id), {
            forceFormData: true,
            onSuccess: () => {
                showModal.value = false;
                Swal.fire({
                    icon: 'success',
                    title: 'BERHASIL DIPERBARUI',
                    text: 'Catatan mutasi Rekening Ibu Beti berhasil diperbarui.',
                    confirmButtonColor: '#ec4899',
                });
            }
        });
    } else {
        form.post(route('ibu-beti.store'), {
            forceFormData: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({
                    icon: 'success',
                    title: 'MUTASI DICATAT',
                    text: 'Mutasi transaksi Rekening Ibu Beti berhasil dicatat ke dalam log.',
                    confirmButtonColor: '#ec4899',
                });
            }
        });
    }
};

const deleteItem = (item) => {
    Swal.fire({
        title: 'HAPUS CATATAN MUTASI?',
        html: `Apakah Anda yakin ingin menghapus catatan <strong>"${item.keterangan}"</strong> sebesar <strong>${formatRupiah(item.jumlah)}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'YA, HAPUS SEKARANG',
        cancelButtonText: 'BATAL'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('ibu-beti.destroy', item.id), {
                onSuccess: () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'BERHASIL DIHAPUS',
                        text: 'Catatan mutasi telah dibersihkan dari log.',
                        confirmButtonColor: '#ec4899',
                    });
                }
            });
        }
    });
};

const viewBukti = (item) => {
    if (!item.bukti) return;
    previewBuktiUrl.value = item.bukti;
    isPreviewPdf.value = item.is_pdf;
    showPreviewModal.value = true;
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};
</script>

<template>
    <Head title="Rekening Ibu Beti - SINDEN" />
    
    <AuthenticatedLayout>
        <div class="space-y-6 font-sans pb-12">
            
            <!-- Page Header Card -->
            <div class="bg-white p-5 sm:p-7 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 text-white flex items-center justify-center shadow-lg shadow-pink-500/20 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 tracking-tight">Rekening Ibu Beti</h2>
                            <span class="px-2 py-0.5 text-[9px] font-black uppercase rounded-md bg-pink-100 text-pink-700">Khusus</span>
                        </div>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Sistem Pembukuan, Arus Kas Mutasi & Rekapitulasi Alokasi Dana Ibu Beti</p>
                    </div>
                </div>
                
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 w-full md:w-auto">
                    <button 
                        @click="openCreateModal" 
                        class="flex-1 md:flex-none justify-center bg-pink-600 hover:bg-pink-700 text-white px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase shadow-sm shadow-pink-600/20 transition tracking-wider flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Catat Mutasi</span>
                    </button>
                    
                    <a 
                        :href="route('ibu-beti.pdf', { month: filterMonth })" 
                        target="_blank" 
                        class="flex-1 md:flex-none justify-center bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-extrabold uppercase shadow-sm transition tracking-wider flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        <span>Cetak Laporan PDF</span>
                    </a>
                </div>
            </div>

            <!-- Stats Cards Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                <!-- Total Masuk -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs border border-emerald-100 flex items-center justify-between relative overflow-hidden">
                    <div class="space-y-1">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Uang Masuk</span>
                        <p class="text-xl sm:text-2xl font-black text-emerald-600 font-mono">{{ formatRupiah(stats?.total_masuk) }}</p>
                        <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md inline-block">Debet / Penerimaan</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                        </svg>
                    </div>
                </div>

                <!-- Total Keluar -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs border border-rose-100 flex items-center justify-between relative overflow-hidden">
                    <div class="space-y-1">
                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Uang Keluar</span>
                        <p class="text-xl sm:text-2xl font-black text-rose-600 font-mono">{{ formatRupiah(stats?.total_keluar) }}</p>
                        <span class="text-[10px] text-rose-700 font-semibold bg-rose-50 px-2 py-0.5 rounded-md inline-block">Kredit / Pengeluaran</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                        </svg>
                    </div>
                </div>

                <!-- Saldo Akhir -->
                <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-lg flex items-center justify-between relative overflow-hidden">
                    <div class="space-y-1 z-10">
                        <span class="text-[10px] font-extrabold text-slate-300 uppercase tracking-wider block">Saldo Akhir Rekening</span>
                        <p class="text-xl sm:text-2xl font-black text-white font-mono">{{ formatRupiah(stats?.saldo_akhir) }}</p>
                        <span class="text-[10px] text-pink-300 font-bold bg-white/10 px-2 py-0.5 rounded-md inline-block">Sisa Kas Tersedia</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/10 text-pink-400 flex items-center justify-center shrink-0 z-10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Section -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex flex-wrap md:flex-nowrap items-center gap-3 w-full md:w-auto">
                    <!-- Search Input -->
                    <div class="relative flex-1 md:w-64">
                        <input 
                            v-model="filterSearch"
                            @keyup.enter="applyFilters"
                            type="text" 
                            placeholder="Cari keterangan mutasi..." 
                            class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>

                    <!-- Jenis Filter -->
                    <select 
                        v-model="filterJenis" 
                        @change="applyFilters"
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold px-3 py-2 focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition cursor-pointer"
                    >
                        <option value="">Semua Jenis</option>
                        <option value="MASUK">Uang Masuk (Debet)</option>
                        <option value="KELUAR">Uang Keluar (Kredit)</option>
                    </select>

                    <!-- Month Filter -->
                    <input 
                        type="month" 
                        v-model="filterMonth" 
                        @change="applyFilters"
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold px-3 py-2 focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition cursor-pointer"
                    />

                    <button 
                        @click="resetFilters" 
                        class="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                        title="Reset Filter"
                    >
                        Reset
                    </button>
                </div>

                <div class="text-xs font-bold text-slate-500 self-end md:self-center">
                    Total: <span class="text-slate-900 font-extrabold">{{ logs.length }}</span> Transaksi
                </div>
            </div>

            <!-- Ledger Table Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] uppercase font-black tracking-wider text-slate-500">
                                <th class="py-4 px-4 text-center w-12">No</th>
                                <th class="py-4 px-4 w-28">Tanggal</th>
                                <th class="py-4 px-4 min-w-[200px]">Keterangan Transaksi</th>
                                <th class="py-4 px-4 text-center w-24">Bukti</th>
                                <th class="py-4 px-4 text-right w-36">Masuk (Debet)</th>
                                <th class="py-4 px-4 text-right w-36">Keluar (Kredit)</th>
                                <th class="py-4 px-4 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <tr v-if="logs.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-400 italic">
                                    Belum ada catatan transaksi pada Rekening Ibu Beti.
                                </td>
                            </tr>
                            <tr 
                                v-for="(item, index) in logs" 
                                :key="item.id" 
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="py-4 px-4 text-center font-bold text-slate-400">
                                    {{ index + 1 }}
                                </td>
                                <td class="py-4 px-4 whitespace-nowrap text-slate-700 font-semibold">
                                    {{ formatDateIndo(item.tanggal) }}
                                </td>
                                <td class="py-4 px-4">
                                    <p class="font-extrabold text-slate-900 uppercase tracking-tight text-xs">{{ item.keterangan }}</p>
                                    <span class="text-[10px] text-slate-400 font-medium mt-0.5 block">
                                        Petugas: {{ item.petugas_input || 'Sistem' }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <button 
                                        v-if="item.bukti" 
                                        @click="viewBukti(item)"
                                        class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition shadow-2xs flex items-center gap-1 mx-auto cursor-pointer"
                                        title="Buka Berkas Bukti Transaksi"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <span>Bukti</span>
                                    </button>
                                    <span v-else class="text-slate-300 font-mono text-[11px]">-</span>
                                </td>
                                <td class="py-4 px-4 text-right whitespace-nowrap font-mono font-bold text-emerald-600">
                                    {{ item.jenis === 'MASUK' ? formatRupiah(item.jumlah) : '-' }}
                                </td>
                                <td class="py-4 px-4 text-right whitespace-nowrap font-mono font-bold text-rose-600">
                                    {{ item.jenis === 'KELUAR' ? formatRupiah(item.jumlah) : '-' }}
                                </td>
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button 
                                            @click="openEditModal(item)" 
                                            class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition cursor-pointer"
                                            title="Edit Transaksi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteItem(item)" 
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                            title="Hapus Transaksi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Catat / Edit Mutasi -->
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="showModal = false"></div>
                
                <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 sm:p-8 space-y-6 z-10 border border-slate-100">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 uppercase tracking-tight">
                                    {{ isEdit ? 'Edit Mutasi Transaksi' : 'Catat Mutasi Rekening' }}
                                </h3>
                                <p class="text-[11px] text-slate-400 font-semibold">Rekening Ibu Beti SINDEN</p>
                            </div>
                        </div>
                        <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Tanggal -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tanggal Transaksi</label>
                            <input 
                                type="date" 
                                v-model="form.tanggal" 
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                                required 
                            />
                            <p v-if="form.errors.tanggal" class="text-rose-500 text-[10px] mt-1 font-semibold">{{ form.errors.tanggal }}</p>
                        </div>

                        <!-- Jenis Mutasi -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Jenis Mutasi</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label 
                                    :class="form.jenis === 'MASUK' ? 'border-emerald-500 bg-emerald-50/60 text-emerald-700 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                    class="p-3 border rounded-xl flex items-center gap-2.5 cursor-pointer transition select-none font-bold text-xs"
                                >
                                    <input type="radio" v-model="form.jenis" value="MASUK" class="hidden" />
                                    <span class="w-3.5 h-3.5 rounded-full border-2 border-emerald-500 flex items-center justify-center">
                                        <span v-if="form.jenis === 'MASUK'" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    </span>
                                    <span>UANG MASUK (DEBET)</span>
                                </label>

                                <label 
                                    :class="form.jenis === 'KELUAR' ? 'border-rose-500 bg-rose-50/60 text-rose-700 ring-2 ring-rose-500/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                    class="p-3 border rounded-xl flex items-center gap-2.5 cursor-pointer transition select-none font-bold text-xs"
                                >
                                    <input type="radio" v-model="form.jenis" value="KELUAR" class="hidden" />
                                    <span class="w-3.5 h-3.5 rounded-full border-2 border-rose-500 flex items-center justify-center">
                                        <span v-if="form.jenis === 'KELUAR'" class="w-2 h-2 rounded-full bg-rose-500"></span>
                                    </span>
                                    <span>UANG KELUAR (KREDIT)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Nominal Jumlah -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nominal Transaksi (Rp)</label>
                            <input 
                                type="number" 
                                v-model="form.jumlah" 
                                min="0" 
                                step="any"
                                placeholder="Contoh: 1500000"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-mono font-extrabold focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                                required 
                            />
                            <p v-if="form.errors.jumlah" class="text-rose-500 text-[10px] mt-1 font-semibold">{{ form.errors.jumlah }}</p>
                        </div>

                        <!-- Keterangan -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Keterangan / Uraian</label>
                            <textarea 
                                v-model="form.keterangan" 
                                rows="3"
                                placeholder="Tuliskan rincian uraian alokasi transaksi..."
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition"
                                required
                            ></textarea>
                            <p v-if="form.errors.keterangan" class="text-rose-500 text-[10px] mt-1 font-semibold">{{ form.errors.keterangan }}</p>
                        </div>

                        <!-- Upload Bukti Transaksi (Opsional) -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Bukti Transfer / Nota (Opsional)</label>
                            <input 
                                type="file" 
                                @change="handleFileUpload" 
                                accept="image/*,application/pdf"
                                class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100 transition cursor-pointer"
                            />
                            <p class="text-[10px] text-slate-400 mt-1">Format: JPG, PNG, atau PDF. Maksimal 5 MB.</p>
                            <p v-if="form.errors.bukti" class="text-rose-500 text-[10px] mt-1 font-semibold">{{ form.errors.bukti }}</p>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="showModal = false" 
                                class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider bg-pink-600 hover:bg-pink-700 text-white shadow-md shadow-pink-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Catat Transaksi') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Pratinjau Bukti -->
            <div v-if="showPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs transition-opacity" @click="showPreviewModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 space-y-4 z-10 max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h4 class="text-xs font-black uppercase text-slate-800 tracking-wider">Berkas Bukti Transaksi</h4>
                        <div class="flex items-center gap-2">
                            <a :href="previewBuktiUrl" target="_blank" download class="text-xs text-indigo-600 hover:underline font-bold mr-2">Unduh Asli</a>
                            <button @click="showPreviewModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-auto flex items-center justify-center bg-slate-50 rounded-2xl p-4 min-h-[300px]">
                        <iframe v-if="isPreviewPdf" :src="previewBuktiUrl" class="w-full h-[60vh] rounded-xl border border-slate-200"></iframe>
                        <img v-else :src="previewBuktiUrl" class="max-h-[60vh] object-contain rounded-xl shadow-sm" alt="Bukti Transaksi" />
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
