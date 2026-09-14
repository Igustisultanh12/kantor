<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    logs: Array,
    canEdit: Boolean,
    stats: Object,
    available_months: Array,
    filters: Object,
});

const showModal = ref(false);
const isEdit = ref(false);

const filterMonth = ref(props.filters?.month || '');

const applyMonthFilter = () => {
    router.get(route('commander.index'), {
        month: filterMonth.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilter = () => {
    filterMonth.value = '';
    applyMonthFilter();
};

const formatMonthName = (ym) => {
    if (!ym) return '';
    const [year, month] = ym.split('-');
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const idx = parseInt(month, 10) - 1;
    return `${months[idx] || month} ${year}`;
};

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
    jumlah: 0
});

const openCreateModal = () => {
    isEdit.value = false;
    form.reset();
    form.tanggal = getTodayLocalDate();
    form.jenis = 'MASUK';
    form.jumlah = 0;
    showModal.value = true;
};

const openEditModal = (item) => {
    isEdit.value = true;
    form.id = item.id;
    form.tanggal = item.tanggal;
    form.keterangan = item.keterangan;
    form.jenis = item.jenis;
    form.jumlah = item.jumlah;
    showModal.value = true;
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('commander.update', form.id), {
            onSuccess: () => { showModal.value = false; Swal.fire('Sukses', 'Data mutasi diperbarui.', 'success'); }
        });
    } else {
        form.post(route('commander.store'), {
            onSuccess: () => { showModal.value = false; form.reset(); Swal.fire('Sukses', 'Data mutasi dicatat.', 'success'); }
        });
    }
};

const deleteItem = (id) => {
    if (confirm('Lapor! Hapus catatan transaksi ini dari pangkalan secara permanen?')) {
        form.delete(route('commander.destroy', id), {
            onSuccess: () => Swal.fire('Terhapus', 'Catatan dibersihkan.', 'success')
        });
    }
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
    <Head title="Rekening Komandan - SINDEN" />
    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center shadow-lg shadow-blue-500/20 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 uppercase tracking-tight">Rekening Komandan</h2>
                            <span class="px-2 py-0.5 text-[9px] font-black uppercase rounded-md bg-blue-100 text-blue-700">Khusus</span>
                        </div>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Manajemen Logistik Finansial, Mutasi Bulanan & Rekening Koran Komandan</p>
                    </div>
                </div>
                
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 w-full md:w-auto">
                    <button v-if="canEdit" @click="openCreateModal" class="flex-1 md:flex-none justify-center bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm shadow-blue-500/20 transition tracking-wider flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Catat Mutasi</span>
                    </button>
                    <a :href="route('commander.pdf', { month: filterMonth })" target="_blank" class="flex-1 md:flex-none justify-center bg-slate-900 hover:bg-slate-800 text-white px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm transition tracking-wider flex items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        <span>Cetak Rekening Koran (PDF)</span>
                    </a>
                </div>
            </div>

            <!-- Filter Bulan / Periode Mutasi Bar -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-xs font-black uppercase text-slate-700 tracking-wider">Periode Mutasi:</span>
                    </div>

                    <!-- Dropdown Pilihan Bulan Cepat -->
                    <select 
                        v-model="filterMonth" 
                        @change="applyMonthFilter"
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-extrabold px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition cursor-pointer min-w-[180px]"
                    >
                        <option value="">Semua Periode (All Time)</option>
                        <option v-for="ym in available_months" :key="ym" :value="ym">
                            {{ formatMonthName(ym) }}
                        </option>
                    </select>

                    <!-- Pemilih Bulan Tambahan -->
                    <input 
                        type="month" 
                        v-model="filterMonth" 
                        @change="applyMonthFilter"
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold px-3 py-2 text-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition cursor-pointer"
                    />

                    <button 
                        v-if="filterMonth" 
                        @click="resetFilter" 
                        class="px-3 py-2 text-xs font-extrabold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl transition cursor-pointer"
                        title="Tampilkan Seluruh Riwayat"
                    >
                        Reset Filter
                    </button>
                </div>

                <div class="text-xs font-bold text-slate-500 flex items-center gap-2">
                    <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">
                        {{ filterMonth ? 'Periode: ' + formatMonthName(filterMonth) : 'Semua Transaksi' }}
                    </span>
                    <span>Total: <b class="text-slate-900">{{ logs.length }}</b> Mutasi</span>
                </div>
            </div>

            <!-- Stats Cards Row (4 Cards jika Bulanan, 3 Cards jika Semua Periode) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <!-- Saldo Awal (Khusus Mode Bulanan) -->
                <div v-if="stats.is_monthly" class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-xs border border-amber-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider">Saldo Awal Bulan</p>
                        <p class="text-lg sm:text-xl font-black text-amber-600 mt-1 font-mono">{{ formatRupiah(stats.saldo_awal) }}</p>
                        <span class="text-[9px] text-amber-700/80 font-bold">Bawaan dari bulan lalu</span>
                    </div>
                    <div class="w-10 sm:w-11 h-10 sm:h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Total Masuk -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-xs border border-emerald-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">{{ stats.is_monthly ? 'Uang Masuk Bulan Ini' : 'Total Uang Masuk' }}</p>
                        <p class="text-lg sm:text-xl font-black text-emerald-600 mt-1 font-mono">{{ formatRupiah(stats.total_masuk) }}</p>
                        <span class="text-[9px] text-emerald-700/80 font-bold">Penerimaan (Debet)</span>
                    </div>
                    <div class="w-10 sm:w-11 h-10 sm:h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                        </svg>
                    </div>
                </div>

                <!-- Total Keluar -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-xs border border-rose-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">{{ stats.is_monthly ? 'Uang Keluar Bulan Ini' : 'Total Uang Keluar' }}</p>
                        <p class="text-lg sm:text-xl font-black text-rose-600 mt-1 font-mono">{{ formatRupiah(stats.total_keluar) }}</p>
                        <span class="text-[9px] text-rose-700/80 font-bold">Pengeluaran (Kredit)</span>
                    </div>
                    <div class="w-10 sm:w-11 h-10 sm:h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                        </svg>
                    </div>
                </div>

                <!-- Saldo Akhir -->
                <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl shadow-lg flex items-center justify-between relative overflow-hidden">
                    <div class="z-10">
                        <p class="text-[10px] font-extrabold text-blue-300 uppercase tracking-wider">{{ stats.is_monthly ? 'Saldo Akhir Bulan' : 'Saldo Akhir Komandan' }}</p>
                        <p class="text-lg sm:text-xl font-black text-white mt-1 font-mono">{{ formatRupiah(stats.saldo_akhir) }}</p>
                        <span class="text-[9px] text-slate-300 font-bold">Sisa kas periode ini</span>
                    </div>
                    <div class="w-10 sm:w-11 h-10 sm:h-11 rounded-2xl bg-white/10 text-blue-400 flex items-center justify-center text-lg font-bold z-10">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                    <div>
                        <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-800">Buku Mutasi & Rekening Koran</h3>
                        <p class="text-[10px] text-slate-400 font-semibold mt-0.5">Daftar mutasi dengan perhitungan saldo berjalan (running balance) real-time</p>
                    </div>
                    <span class="text-[10px] font-extrabold bg-slate-100 text-slate-600 px-3 py-1 rounded-full uppercase">
                        {{ filterMonth ? formatMonthName(filterMonth) : 'Seluruh Riwayat' }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-extrabold tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="p-4 w-28">Tanggal</th>
                                <th class="p-4 min-w-[200px]">Keterangan Mekanisme</th>
                                <th class="p-4 text-center w-24">Jenis</th>
                                <th class="p-4 text-right w-36">Nominal</th>
                                <th class="p-4 text-right w-40 bg-blue-50/50 text-blue-900">Saldo Berjalan</th>
                                <th class="p-4 text-center w-24" v-if="canEdit">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold">
                            <tr v-for="item in logs" :key="item.id" class="hover:bg-slate-50/70 transition">
                                <td class="p-4 font-bold text-slate-600 whitespace-nowrap">{{ item.tanggal }}</td>
                                <td class="p-4">
                                    <p class="font-extrabold text-slate-900 uppercase">{{ item.keterangan }}</p>
                                    <p class="text-[9px] text-slate-400 font-bold mt-0.5">Operator: {{ item.petugas_input }}</p>
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <span :class="item.jenis === 'MASUK' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'" class="px-3 py-1 rounded-full text-[9px] font-extrabold border uppercase">
                                        {{ item.jenis }}
                                    </span>
                                </td>
                                <td class="p-4 text-right font-mono font-bold text-sm whitespace-nowrap" :class="item.jenis === 'MASUK' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ item.jenis === 'MASUK' ? '+' : '-' }} {{ formatRupiah(item.jumlah) }}
                                </td>
                                <td class="p-4 text-right font-mono font-bold text-sm text-slate-900 bg-blue-50/20 whitespace-nowrap">
                                    {{ formatRupiah(item.saldo_berjalan) }}
                                </td>
                                <td class="p-4 text-center whitespace-nowrap" v-if="canEdit">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="openEditModal(item)" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-xl font-extrabold uppercase text-[10px] transition cursor-pointer">Ubah</button>
                                        <button @click="deleteItem(item.id)" class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-3 py-1.5 rounded-xl font-extrabold uppercase text-[10px] transition cursor-pointer">Hapus</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Baris Saldo Awal jika memfilter bulan tertentu -->
                            <tr v-if="stats.is_monthly && stats.saldo_awal !== 0" class="bg-amber-50/40 border-t-2 border-amber-200/60">
                                <td class="p-4 font-bold text-amber-900 whitespace-nowrap">01/{{ filterMonth.split('-')[1] }}/{{ filterMonth.split('-')[0] }}</td>
                                <td class="p-4 font-extrabold text-amber-900 uppercase">
                                    SALDO AWAL BULAN (BEGINNING BALANCE)
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-100 text-amber-800">
                                        SALDO AWAL
                                    </span>
                                </td>
                                <td class="p-4 text-right text-slate-400 font-mono">-</td>
                                <td class="p-4 text-right font-mono font-black text-sm text-blue-700 bg-blue-50/30 whitespace-nowrap">
                                    {{ formatRupiah(stats.saldo_awal) }}
                                </td>
                                <td v-if="canEdit" class="p-4 text-center text-slate-400 text-[10px]">-</td>
                            </tr>

                            <tr v-if="logs.length === 0 && (!stats.is_monthly || stats.saldo_awal === 0)">
                                <td :colspan="canEdit ? 6 : 5" class="p-12 text-center italic text-slate-400 font-medium">
                                    Belum ada mutasi finansial tercatat pada periode ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL TRANSAKSI -->
        <div v-if="showModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 text-left">
            <div class="bg-white w-full max-w-md rounded-3xl p-8 shadow-2xl space-y-6 border border-slate-100">
                <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                    <h3 class="font-extrabold uppercase text-sm tracking-wider text-slate-900">{{ isEdit ? 'Ubah Catatan Mutasi' : 'Catat Mutasi Finansial' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
                </div>
                
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Tanggal Transaksi</label>
                        <input v-model="form.tanggal" type="date" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 focus:ring-blue-500 focus:border-blue-600" required />
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Keterangan</label>
                        <input v-model="form.keterangan" type="text" placeholder="CONTOH: DUKUNGAN OPS SATGAS SUMATRA" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 uppercase focus:ring-blue-500 focus:border-blue-600" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Jenis Mutasi</label>
                            <select v-model="form.jenis" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 focus:ring-blue-500 focus:border-blue-600">
                                <option value="MASUK">UANG MASUK</option>
                                <option value="KELUAR">UANG KELUAR</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nominal (Rupiah)</label>
                            <input v-model="form.jumlah" type="number" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-mono font-bold py-3 mt-1 focus:ring-blue-500 focus:border-blue-600" required />
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="showModal = false" class="w-1/2 py-3 bg-slate-100 text-slate-600 rounded-2xl font-extrabold uppercase text-xs hover:bg-slate-200 transition">Batal</button>
                        <button type="submit" class="w-1/2 py-3 bg-blue-600 text-white rounded-2xl font-extrabold uppercase text-xs shadow-md shadow-blue-500/20 hover:bg-blue-700 transition">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>