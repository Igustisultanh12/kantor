<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    logs: Array,
    canEdit: Boolean,
    stats: Object
});

const showModal = ref(false);
const isEdit = ref(false);

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
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(val);
};
</script>

<template>
    <Head title="Rekening Komandan" />
    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 uppercase tracking-tight">Rekening Komandan</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Manajemen Logistik & Alokasi Dana Rekening Komandan</p>
                </div>
                
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 w-full md:w-auto">
                    <button v-if="canEdit" @click="openCreateModal" class="flex-1 md:flex-none justify-center bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm transition tracking-wider flex items-center gap-2">
                        <span></span> Catat Mutasi
                    </button>
                    <a :href="route('commander.pdf')" target="_blank" class="flex-1 md:flex-none justify-center bg-slate-900 hover:bg-slate-800 text-white px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm transition tracking-wider flex items-center gap-2">
                        <span></span> Cetak Laporan
                    </a>
                </div>
            </div>

            <!-- Stats Cards Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs border border-emerald-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Total Uang Masuk</p>
                        <p class="text-xl sm:text-2xl font-black text-emerald-600 mt-1 font-mono">{{ formatRupiah(stats.total_masuk) }}</p>
                    </div>
                    <div class="w-10 sm:w-12 h-10 sm:h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg sm:text-xl font-bold">
                        
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs border border-rose-100 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Total Uang Keluar</p>
                        <p class="text-xl sm:text-2xl font-black text-rose-600 mt-1 font-mono">{{ formatRupiah(stats.total_keluar) }}</p>
                    </div>
                    <div class="w-10 sm:w-12 h-10 sm:h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg sm:text-xl font-bold">
                        
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs border border-blue-100 bg-blue-50/20 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider">Saldo Akhir Komandan</p>
                        <p class="text-xl sm:text-2xl font-black text-blue-700 mt-1 font-mono">{{ formatRupiah(stats.saldo_akhir) }}</p>
                    </div>
                    <div class="w-10 sm:w-12 h-10 sm:h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg sm:text-xl font-bold">
                        
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-extrabold text-xs uppercase tracking-wider text-slate-800">Riwayat Mutasi & Transaksi</h3>
                    <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-3 py-1 rounded-full uppercase">Total Log: {{ logs.length }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-extrabold tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="p-4">Tanggal</th>
                                <th class="p-4">Keterangan Mekanisme</th>
                                <th class="p-4 text-center">Jenis</th>
                                <th class="p-4 text-right">Nominal</th>
                                <th class="p-4 text-center" v-if="canEdit">Aksi</th>
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
                                <td class="p-4 text-center whitespace-nowrap" v-if="canEdit">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="openEditModal(item)" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-xl font-extrabold uppercase text-[10px] transition">Ubah</button>
                                        <button @click="deleteItem(item.id)" class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-3 py-1.5 rounded-xl font-extrabold uppercase text-[10px] transition">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="logs.length === 0">
                                <td colspan="5" class="p-12 text-center italic text-slate-400 font-medium">Belum ada mutasi finansial tercatat.</td>
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