<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import html2pdf from 'html2pdf.js';

const props = defineProps({
    mitras: Array,
    currentYear: Number,
    availableYears: Array,
    canEdit: Boolean
});

// State Manager
const searchQuery = ref('');
const selectedYear = ref(props.currentYear);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showPreviewModal = ref(false);
const previewImage = ref(null);

const months = [
    { id: 1, full: 'Januari', short: 'Jan' },
    { id: 2, full: 'Februari', short: 'Feb' },
    { id: 3, full: 'Maret', short: 'Mar' },
    { id: 4, full: 'April', short: 'Apr' },
    { id: 5, full: 'Mei', short: 'Mei' },
    { id: 6, full: 'Juni', short: 'Jun' },
    { id: 7, full: 'Juli', short: 'Jul' },
    { id: 8, full: 'Agustus', short: 'Agu' },
    { id: 9, full: 'September', short: 'Sep' },
    { id: 10, full: 'Oktober', short: 'Okt' },
    { id: 11, full: 'November', short: 'Nov' },
    { id: 12, full: 'Desember', short: 'Des' }
];

// Form Tambah Mitra Baru
const createForm = useForm({
    nama: '',
    pt: '',
    no_tlp: '',
    keterangan: ''
});

// Form Edit Mitra
const editForm = useForm({
    id: null,
    nama: '',
    pt: '',
    no_tlp: '',
    keterangan: ''
});

// Filter Mitra berdasarkan Pencarian Search Input
const filteredMitras = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return props.mitras;
    return props.mitras.filter(m => 
        (m.nama && m.nama.toLowerCase().includes(q)) ||
        (m.pt && m.pt.toLowerCase().includes(q)) ||
        (m.no_tlp && m.no_tlp.toLowerCase().includes(q))
    );
});

// Pergantian Tahun Filter Matriks
const changeYear = () => {
    router.get(route('mitras.index'), { tahun: selectedYear.value }, { preserveState: true, preserveScroll: true });
};

// Toggle Checkbox Pembayaran Mitra per Bulan (Instant Response)
const togglePayment = (mitra, monthId) => {
    const currentStatus = mitra.payment_matrix[monthId] || false;
    const newStatus = !currentStatus;

    // Optimistic UI update
    mitra.payment_matrix[monthId] = newStatus;

    router.post(route('mitras.toggle-payment'), {
        mitra_id: mitra.id,
        tahun: selectedYear.value,
        bulan: monthId,
        is_paid: newStatus
    }, {
        preserveScroll: true,
        onError: () => {
            // Revert state if error occurs
            mitra.payment_matrix[monthId] = currentStatus;
            Swal.fire('Error', 'Gagal memperbarui status pembayaran.', 'error');
        }
    });
};

// Geser Posisi Mitra (Naik / Turun 1 Langkah)
const moveMitra = (mitraId, direction) => {
    router.post(route('mitras.move', mitraId), { direction }, {
        preserveScroll: true
    });
};

// Submit Tambah Mitra
const submitCreate = () => {
    createForm.post(route('mitras.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            Swal.fire('Berhasil', 'Data Mitra berhasil ditambahkan!', 'success');
        }
    });
};

// Open Edit Modal
const openEdit = (mitra) => {
    editForm.id = mitra.id;
    editForm.nama = mitra.nama;
    editForm.pt = mitra.pt || '';
    editForm.no_tlp = mitra.no_tlp || '';
    editForm.keterangan = mitra.keterangan || '';
    showEditModal.value = true;
};

// Submit Edit Mitra
const submitEdit = () => {
    editForm.put(route('mitras.update', editForm.id), {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            Swal.fire('Tersimpan', 'Data Mitra berhasil diperbarui.', 'success');
        }
    });
};

// Hapus Data Mitra
const deleteMitra = (id, nama) => {
    Swal.fire({
        title: 'Hapus Data Mitra?',
        text: `Apakah Anda yakin ingin menghapus "${nama}" beserta seluruh riwayat pembayarannya?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('mitras.destroy', id), {
                onSuccess: () => Swal.fire('Terhapus', 'Data Mitra telah dihapus.', 'success')
            });
        }
    });
};

// Open Pratinjau PDF Mode Landscape
const openPreview = () => {
    const element = document.getElementById('area-mitra-cetak');
    const opt = {
        margin: [0.3, 0.3, 0.3, 0.3],
        html2canvas: { scale: 2, useCORS: true, width: 1100 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).outputImg().then((img) => {
        previewImage.value = img.src;
        showPreviewModal.value = true;
    });
};

// Download File PDF Resmi Format Gambar Lampiran User
const downloadPDF = () => {
    const element = document.getElementById('area-mitra-cetak');
    const opt = {
        margin: 0.3,
        filename: `DAFTAR_NAMA_MITRA_${selectedYear.value}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
};
</script>

<template>
    <Head title="Pencatatan Pembayaran Mitra" />
    <AuthenticatedLayout>
        <div class="py-4 sm:py-8 font-sans">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <!-- Page Header Card -->
                <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div>
                        <h2 class="font-extrabold text-slate-900 uppercase tracking-tight text-lg sm:text-xl flex items-center gap-2">
                            <span></span> Pencatatan Pembayaran Mitra
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Matriks Monitoring Setoran / Pembayaran Mitra Bulanan</p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                        <!-- Select Tahun -->
                        <div class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-xl border border-slate-200">
                            <span class="text-[10px] font-extrabold text-slate-500 uppercase">Tahun:</span>
                            <select v-model="selectedYear" @change="changeYear" class="text-xs font-black rounded-lg border-slate-200 py-1 px-3 bg-white focus:ring-blue-500">
                                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>

                        <!-- Tombol Tambah Mitra -->
                        <button @click="showCreateModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-xs font-extrabold uppercase shadow-sm flex items-center gap-2 transition active:scale-95">
                            <span></span> Tambah Mitra
                        </button>

                        <!-- Tombol Pratinjau PDF -->
                        <button @click="openPreview" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2.5 rounded-xl text-xs font-extrabold uppercase shadow-sm flex items-center gap-2 transition active:scale-95">
                            <span></span> Cetak PDF Matriks
                        </button>
                    </div>
                </div>

                <!-- Searching & Filter Bar -->
                <div class="bg-white p-4 rounded-2xl shadow-xs border border-[#E2E8F0] flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="relative w-full sm:w-80">
                        <input 
                            type="text"v-model="searchQuery"placeholder="Cari Nama Mitra, PT, No Tlp..."class="w-full text-xs font-bold rounded-xl border-slate-200 py-2.5 pl-9 pr-8 bg-slate-50 focus:bg-white focus:ring-blue-500 focus:border-blue-600"
                        />
                        <span class="absolute left-3 top-2.5 text-xs text-slate-400"></span>
                        <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2.5 text-slate-400 hover:text-rose-500 text-xs font-bold">×</button>
                    </div>

                    <div class="text-[11px] font-bold text-slate-500 flex items-center gap-2">
                        <span>Total Terdaftar: <strong class="text-blue-600 font-extrabold">{{ filteredMitras.length }}</strong> Mitra</span>
                    </div>
                </div>

                <!-- Interactive Matrix Table -->
                <div class="bg-white overflow-hidden shadow-xl rounded-2xl border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-wider">
                                <tr>
                                    <th class="p-3 text-center w-12 border-r border-slate-700">Urutan</th>
                                    <th class="p-3 text-center w-10 border-r border-slate-700">No</th>
                                    <th class="p-3 min-w-[140px] border-r border-slate-700">Nama</th>
                                    <th class="p-3 min-w-[160px] border-r border-slate-700">PT / Perusahaan</th>
                                    <th class="p-3 min-w-[120px] border-r border-slate-700">No. Tlp</th>
                                    
                                    <!-- 12 Bulan Column Headers -->
                                    <th v-for="m in months" :key="m.id" class="p-2 text-center w-11 border-r border-slate-700 bg-slate-800">
                                        {{ m.short }}
                                    </th>

                                    <th class="p-3 text-center w-20">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="font-bold divide-y divide-slate-100">
                                <tr v-for="(mitra, index) in filteredMitras" :key="mitra.id" class="hover:bg-blue-50/40 transition-colors group">
                                    
                                    <!-- Tombol Geser Ke Atas / Ke Bawah -->
                                    <td class="p-2 text-center border-r border-slate-100 bg-slate-50/50">
                                        <div class="flex items-center justify-center gap-1">
                                            <button 
                                                @click="moveMitra(mitra.id, 'up')" 
                                                :disabled="index === 0"class="p-1 text-slate-400 hover:text-blue-600 disabled:opacity-20 hover:bg-white rounded transition"title="Geser Ke Atas"
                                            >
                                                ▲
                                            </button>
                                            <button 
                                                @click="moveMitra(mitra.id, 'down')" 
                                                :disabled="index === filteredMitras.length - 1"class="p-1 text-slate-400 hover:text-blue-600 disabled:opacity-20 hover:bg-white rounded transition"title="Geser Ke Bawah"
                                            >
                                                ▼
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Nomor Urut -->
                                    <td class="p-3 text-center font-mono text-slate-500 border-r border-slate-100">
                                        {{ index + 1 }}
                                    </td>

                                    <!-- Nama Mitra -->
                                    <td class="p-3 border-r border-slate-100 text-slate-900 font-extrabold uppercase">
                                        {{ mitra.nama }}
                                    </td>

                                    <!-- PT / Perusahaan -->
                                    <td class="p-3 border-r border-slate-100 text-slate-700 uppercase">
                                        {{ mitra.pt || '-' }}
                                    </td>

                                    <!-- No Tlp -->
                                    <td class="p-3 border-r border-slate-100 font-mono text-slate-600">
                                        {{ mitra.no_tlp || '-' }}
                                    </td>

                                    <!-- 12 Checkbox Bulan -->
                                    <td v-for="m in months" :key="m.id" class="p-2 text-center border-r border-slate-100 select-none">
                                        <button 
                                            @click="togglePayment(mitra, m.id)"class="w-7 h-7 rounded-lg flex items-center justify-center transition-all transform active:scale-90 font-black text-sm mx-auto shadow-xs"
                                            :class="mitra.payment_matrix[m.id] ? 'bg-emerald-500 text-white border border-emerald-600 shadow-emerald-200' : 'bg-slate-100 text-slate-300 border border-slate-200 hover:bg-slate-200 hover:text-slate-500'"
                                            :title="`${mitra.nama} - Bulan ${m.full} ${selectedYear}: ${mitra.payment_matrix[m.id] ? 'Sudah Bayar (Klik untuk batal)' : 'Belum Bayar (Klik untuk lunas)'}`"
                                        >
                                            <span v-if="mitra.payment_matrix[m.id]"></span>
                                            <span v-else class="text-xs opacity-40">-</span>
                                        </button>
                                    </td>

                                    <!-- Tombol Aksi -->
                                    <td class="p-3 text-center">
                                        <div class="flex justify-center gap-1.5 opacity-90 group-hover:opacity-100">
                                            <button @click="openEdit(mitra)" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition" title="Ubah Data">
                                                
                                            </button>
                                            <button @click="deleteMitra(mitra.id, mitra.nama)" class="p-1.5 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-600 hover:text-white transition" title="Hapus Data">
                                                
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredMitras.length === 0">
                                    <td colspan="18" class="p-12 text-center text-slate-400 font-extrabold uppercase italic"> Belum Ada Data Mitra Terdaftar / Tidak Ditemukan.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL TAMBAH MITRA BARU -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="font-extrabold uppercase text-xs tracking-wider text-slate-800"> Tambah Mitra Baru</h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4 font-bold text-xs">
                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">Nama Mitra / Kontak PJ *</label>
                        <input type="text" v-model="createForm.nama" placeholder="Contoh: Pak Jery" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2.5" required />
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">Nama PT / Perusahaan</label>
                        <input type="text" v-model="createForm.pt" placeholder="Contoh: PT Lomy Harapan Sejahtera" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2.5" />
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">No. Telepon / WA</label>
                        <input type="text" v-model="createForm.no_tlp" placeholder="Contoh: 08113069898" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2.5" />
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">Keterangan Catatan Tambahan</label>
                        <textarea v-model="createForm.keterangan" placeholder="Catatan opsional..." class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2" rows="2"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2.5 text-xs text-slate-500 uppercase font-extrabold">Batal</button>
                        <button type="submit" :disabled="createForm.processing" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl uppercase font-extrabold shadow-md hover:bg-blue-700 transition">Simpan Mitra</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL EDIT MITRA -->
        <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="font-extrabold uppercase text-xs tracking-wider text-slate-800"> Ubah Data Mitra</h3>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4 font-bold text-xs">
                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">Nama Mitra / Kontak PJ *</label>
                        <input type="text" v-model="editForm.nama" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2.5" required />
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">Nama PT / Perusahaan</label>
                        <input type="text" v-model="editForm.pt" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2.5" />
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">No. Telepon / WA</label>
                        <input type="text" v-model="editForm.no_tlp" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2.5" />
                    </div>

                    <div>
                        <label class="block text-[10px] text-slate-400 uppercase mb-1">Keterangan Catatan Tambahan</label>
                        <textarea v-model="editForm.keterangan" class="w-full rounded-xl border-slate-200 text-xs font-bold focus:ring-blue-500 focus:border-blue-600 py-2" rows="2"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2.5 text-xs text-slate-500 uppercase font-extrabold">Batal</button>
                        <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl uppercase font-extrabold shadow-md hover:bg-blue-700 transition">Update Mitra</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- HIDDEN CETAK LANDSCAPE PDF TEMPLATE (SESUAI GAMBAR LAMPIRAN) -->
        <div style="position: absolute; left: -9999px;">
            <div id="area-mitra-cetak" class="p-8 bg-white text-black" style="width: 1080px; font-family: 'Times New Roman', serif;">
                
                <!-- Kop / Judul Utama Sesuai Gambar Foto Lampiran -->
                <div class="text-center mb-6 uppercase">
                    <h2 style="margin: 0; font-size: 18px; font-weight: bold; letter-spacing: 1px;">DAFTAR NAMA – NAMA MITRA</h2>
                    <p style="margin: 4px 0 0 0; font-size: 11px; font-weight: bold;">TAHUN {{ selectedYear }}</p>
                </div>

                <!-- Tabel Matriks 12 Bulan Format Gambar -->
                <table class="w-full border-collapse border-[1.5px] border-black text-[10px]">
                    <thead>
                        <tr style="background-color: #f8fafc; text-transform: uppercase; font-weight: bold; text-align: center;">
                            <th style="border: 1px solid #000; padding: 6px 2px; width: 4%;">NO</th>
                            <th style="border: 1px solid #000; padding: 6px 4px; width: 14%; text-align: left;">NAMA</th>
                            <th style="border: 1px solid #000; padding: 6px 4px; width: 24%; text-align: left;">PT</th>
                            <th style="border: 1px solid #000; padding: 6px 4px; width: 12%;">NO. TLP</th>
                            
                            <!-- 12 Bulan Headers ditulis vertikal / ringkas -->
                            <th v-for="m in months" :key="m.id" style="border: 1px solid #000; padding: 4px 1px; width: 3.8%; font-size: 9px;">
                                {{ m.full }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(mitra, index) in filteredMitras" :key="mitra.id">
                            <td style="border: 1px solid #000; padding: 5px 2px; text-align: center; font-weight: bold;">{{ index + 1 }}.</td>
                            <td style="border: 1px solid #000; padding: 5px 4px; font-weight: bold;">{{ mitra.nama }}</td>
                            <td style="border: 1px solid #000; padding: 5px 4px; text-transform: uppercase;">{{ mitra.pt || '-' }}</td>
                            <td style="border: 1px solid #000; padding: 5px 4px; text-align: center; font-family: monospace;">{{ mitra.no_tlp || '-' }}</td>

                            <!-- Value 12 Bulan Checkmarks -->
                            <td v-for="m in months" :key="m.id" style="border: 1px solid #000; padding: 5px 1px; text-align: center; font-weight: bold;">
                                <span v-if="mitra.payment_matrix[m.id]" style="font-size: 12px;"></span>
                                <span v-else style="color: #666;">-</span>
                            </td>
                        </tr>
                        <tr v-if="filteredMitras.length === 0">
                            <td colspan="16" style="border: 1px solid #000; padding: 30px; text-align: center; font-weight: bold; color: #9ca3af;"> BELUM ADA DATA MITRA TERDAFTAR
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>
        </div>

        <!-- MODAL PRATINJAU PDF LANDSCAPE -->
        <div v-if="showPreviewModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-white rounded-3xl shadow-2xl max-w-6xl w-full h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b flex justify-between items-center bg-slate-900 text-white">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-600 h-8 w-8 rounded-xl flex items-center justify-center text-white text-xs font-black">PDF</div>
                        <h3 class="font-extrabold uppercase text-xs tracking-wider">Pratinjau Cetak Matriks Pembayaran Mitra {{ selectedYear }}</h3>
                    </div>
                    <button @click="showPreviewModal = false" class="text-slate-400 hover:text-white font-bold text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto bg-slate-800 p-4 md:p-8 flex justify-center items-start">
                    <img :src="previewImage" class="shadow-2xl border bg-white max-w-[1050px] w-full h-auto" />
                </div>
                <div class="p-6 border-t bg-slate-50 flex justify-end gap-3 font-extrabold">
                    <button @click="showPreviewModal = false" class="px-6 py-2.5 text-xs uppercase text-slate-500">Batal</button>
                    <button @click="downloadPDF(); showPreviewModal = false" class="px-8 py-2.5 bg-blue-600 text-white rounded-xl text-xs uppercase tracking-wider shadow-md hover:bg-blue-700 transition"> Download PDF Resmi
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
