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
        <template #header>
            <div class="flex justify-between items-center text-left">
                <h2 class="font-black text-2xl text-gray-800 leading-tight">LOGISTIK REKENING KOMANDAN</h2>
                <div class="flex gap-2" v-if="canEdit">
                    <button @click="openCreateModal" class="bg-red-600 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow">📝 Catat Mutasi</button>
                    <a :href="route('commander.pdf')" target="_blank" class="bg-gray-800 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow">🖨 Cetak Laporan</a>
                </div>
                <div v-else>
                    <a :href="route('commander.pdf')" target="_blank" class="bg-gray-800 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow">🖨 Unduh Rekap</a>
                </div>
            </div>
        </template>

        <div class="py-8 px-4 md:px-6 space-y-6 text-left">
            <!-- Papan Radar Monitor Saldo -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-emerald-100">
                    <p class="text-[10px] font-black text-gray-400 uppercase">Total Uang Masuk</p>
                    <p class="text-xl font-black text-emerald-600 mt-1">{{ formatRupiah(stats.total_masuk) }}</p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-red-100">
                    <p class="text-[10px] font-black text-gray-400 uppercase">Total Uang Keluar</p>
                    <p class="text-xl font-black text-red-600 mt-1">{{ formatRupiah(stats.total_keluar) }}</p>
                </div>
                <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-indigo-100 bg-indigo-50/20">
                    <p class="text-[10px] font-black text-indigo-400 uppercase">Saldo Akhir Komandan</p>
                    <p class="text-xl font-black text-indigo-700 mt-1">{{ formatRupiah(stats.saldo_akhir) }}</p>
                </div>
            </div>

            <!-- Tabel Transaksi -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 uppercase text-[10px] font-black tracking-wider text-gray-400">
                        <tr>
                            <th class="p-5">Tanggal</th>
                            <th class="p-5">Keterangan Mekanisme</th>
                            <th class="p-5 text-center">Jenis</th>
                            <th class="p-5 text-right">Jumlah Nominal</th>
                            <th class="p-5 text-center" v-if="canEdit">Otoritas Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        <tr v-for="item in logs" :key="item.id" class="hover:bg-slate-50/50">
                            <td class="p-5 font-bold text-gray-500">{{ item.tanggal }}</td>
                            <td class="p-5">
                                <p class="font-black text-gray-800 uppercase">{{ item.keterangan }}</p>
                                <p class="text-[9px] text-gray-400 font-semibold">Operator: {{ item.petugas_input }}</p>
                            </td>
                            <td class="p-5 text-center">
                                <span :class="item.jenis === 'MASUK' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'" class="px-2.5 py-0.5 rounded-full text-[9px] font-black border">
                                    {{ item.jenis }}
                                </span>
                            </td>
                            <td class="p-5 text-right font-mono font-bold text-sm" :class="item.jenis === 'MASUK' ? 'text-emerald-600' : 'text-red-600'">
                                {{ item.jenis === 'MASUK' ? '+' : '-' }} {{ formatRupiah(item.jumlah) }}
                            </td>
                            <td class="p-5 text-center flex justify-center gap-2" v-if="canEdit">
                                <button @click="openEditModal(item)" class="bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg font-black uppercase text-[10px]">Ubah</button>
                                <button @click="deleteItem(item.id)" class="bg-red-50 text-red-600 px-3 py-1.5 rounded-lg font-black uppercase text-[10px]">Hapus</button>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="5" class="p-10 text-center italic text-gray-300">Belum ada mutasi finansial tercatat.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL TRANSAKSI -->
        <div v-if="showModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 text-left">
            <div class="bg-white w-full max-w-md rounded-[2rem] p-6 shadow-2xl space-y-4">
                <h3 class="font-black uppercase text-sm tracking-widest text-gray-700">{{ isEdit ? 'Ubah Catatan Mutasi' : 'Catat Mutasi Finansial' }}</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Tanggal Transaksi</label>
                        <input v-model="form.tanggal" type="date" class="w-full rounded-xl border-gray-200 mt-1 text-xs" required />
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Keterangan</label>
                        <input v-model="form.keterangan" type="text" placeholder="CONTOH: DUKUNGAN OPS SATGAS SUMATRA" class="w-full rounded-xl border-gray-200 mt-1 text-xs uppercase" required />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Jenis Mutasi</label>
                            <select v-model="form.jenis" class="w-full rounded-xl border-gray-200 mt-1 text-xs">
                                <option value="MASUK">UANG MASUK</option>
                                <option value="KELUAR">UANG KELUAR</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-1">Nominal (Rupiah)</label>
                            <input v-model="form.jumlah" type="number" class="w-full rounded-xl border-gray-200 mt-1 text-xs font-mono" required />
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showModal = false" class="w-1/2 py-2 bg-gray-100 text-gray-500 rounded-xl font-bold uppercase text-[10px]">Batal</button>
                        <button type="submit" class="w-1/2 py-2 bg-red-600 text-white rounded-xl font-black uppercase text-[10px] shadow">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>