<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    submissions: Object,
    stats: Object,
    filters: Object,
    stages: Array,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');

const searchQuery = ref(props.filters?.search || '');
const selectedStage = ref(props.filters?.stage || 'all');
const selectedStatus = ref(props.filters?.status || 'all');

const handleFilter = () => {
    router.get(route('sc-submissions.index'), {
        search: searchQuery.value,
        stage: selectedStage.value,
        status: selectedStatus.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilter = () => {
    searchQuery.value = '';
    selectedStage.value = 'all';
    selectedStatus.value = 'all';
    handleFilter();
};

// Modal Tambah Pengajuan Baru
const isCreateModalOpen = ref(false);
const isSubmittingCreate = ref(false);
const createForm = ref({
    nama: '',
    pangkat_korps: '',
    identifier_type: 'nrp',
    identifier_number: '',
    kesatuan: '',
    jabatan: '',
    phone: '',
    keperluan: '',
    current_stage: 1,
    status: 'proses',
    catatan_petugas: '',
    nomor_surat_rh: '',
    nomor_skhpp: '',
    nomor_sc: '',
});

const openCreateModal = () => {
    createForm.value = {
        nama: '',
        pangkat_korps: '',
        identifier_type: 'nrp',
        identifier_number: '',
        kesatuan: '',
        jabatan: '',
        phone: '',
        keperluan: '',
        current_stage: 1,
        status: 'proses',
        catatan_petugas: '',
        nomor_surat_rh: '',
        nomor_skhpp: '',
        nomor_sc: '',
    };
    isCreateModalOpen.value = true;
};

const submitCreate = () => {
    isSubmittingCreate.value = true;
    router.post(route('sc-submissions.store'), createForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            isSubmittingCreate.value = false;
        },
        onError: (err) => {
            alert('Gagal mendaftarkan berkas: ' + Object.values(err)[0]);
            isSubmittingCreate.value = false;
        }
    });
};

// Modal Pembaruan Tahapan Cepat (Quick Stage Update)
const isUpdateStageModalOpen = ref(false);
const isSubmittingStage = ref(false);
const activeSubmissionForStage = ref(null);
const stageForm = ref({
    stage: 1,
    notes: '',
    status: 'proses',
    nomor_skhpp: '',
    nomor_sc: '',
});

const openUpdateStageModal = (sub) => {
    activeSubmissionForStage.value = sub;
    stageForm.value = {
        stage: sub.current_stage,
        notes: '',
        status: sub.status || 'proses',
        nomor_skhpp: sub.nomor_skhpp || '',
        nomor_sc: sub.nomor_sc || '',
    };
    isUpdateStageModalOpen.value = true;
};

const advanceToNextStage = () => {
    if (stageForm.value.stage < 10) {
        stageForm.value.stage++;
        if (stageForm.value.stage === 10) {
            stageForm.value.status = 'selesai';
        }
    }
};

const submitUpdateStage = () => {
    if (!activeSubmissionForStage.value) return;
    isSubmittingStage.value = true;

    router.post(route('sc-submissions.update-stage', activeSubmissionForStage.value.id), stageForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isUpdateStageModalOpen.value = false;
            isSubmittingStage.value = false;
        },
        onError: (err) => {
            alert('Gagal memperbarui tahapan: ' + Object.values(err)[0]);
            isSubmittingStage.value = false;
        }
    });
};

// Modal Detail / Riwayat Logs
const isLogsModalOpen = ref(false);
const activeSubmissionForLogs = ref(null);

const openLogsModal = (sub) => {
    activeSubmissionForLogs.value = sub;
    isLogsModalOpen.value = true;
};

// Modal Edit Detail Pemohon
const isEditModalOpen = ref(false);
const isSubmittingEdit = ref(false);
const editForm = ref({});

const openEditModal = (sub) => {
    editForm.value = {
        id: sub.id,
        nama: sub.nama,
        pangkat_korps: sub.pangkat_korps || '',
        identifier_type: sub.identifier_type,
        identifier_number: sub.identifier_number,
        kesatuan: sub.kesatuan || '',
        jabatan: sub.jabatan || '',
        phone: sub.phone || '',
        keperluan: sub.keperluan || '',
        status: sub.status,
        nomor_surat_rh: sub.nomor_surat_rh || '',
        nomor_skhpp: sub.nomor_skhpp || '',
        nomor_sc: sub.nomor_sc || '',
    };
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    isSubmittingEdit.value = true;
    router.put(route('sc-submissions.update', editForm.value.id), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            isSubmittingEdit.value = false;
        },
        onError: (err) => {
            alert('Gagal mengubah data: ' + Object.values(err)[0]);
            isSubmittingEdit.value = false;
        }
    });
};

// Hapus Berkas
const deleteSubmission = (sub) => {
    if (confirm(`Peringatan Kedinasan: Yakin ingin menghapus seluruh data pengajuan SC atas nama ${sub.nama} (${sub.tracking_code})? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(route('sc-submissions.destroy', sub.id), {
            preserveScroll: true,
        });
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) + ' WIB';
};
</script>

<template>
    <Head title="Manajemen Pengajuan Security Clearance (SC) - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 sm:space-y-8 font-sans">
            
            <!-- Hero Header Banner -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-[#E2E8F0] shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 font-extrabold text-[10px] uppercase rounded-full tracking-wider">Layanan Dokumen Intelijen</span>
                        <span class="text-slate-400 text-xs font-semibold">10 Tahapan Operasional SC</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Manajemen Pengajuan Security Clearance (SC)
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed max-w-2xl">
                        Pengelolaan tahapan berkas SC mulai dari Pengisian RH, Verifikasi Denintel, Proses Sintel, hingga Penerbitan SC di Mako Kodaeral V. Dapat dilacak langsung oleh personel secara publik tanpa login.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <a 
                        :href="route('tracking-sc.index')" 
                        target="_blank"
                        class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition flex items-center gap-2"
                        title="Buka halaman tracking publik tanpa login"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        <span>Halaman Publik</span>
                    </a>
                    <button 
                        @click="openCreateModal"
                        class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider transition shadow-md shadow-blue-500/20 flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Daftarkan Berkas SC</span>
                    </button>
                </div>
            </div>

            <!-- Kartu Statistik Alur Berkas -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Total Pengajuan</span>
                    <h3 class="text-2xl font-black text-slate-900">{{ stats.total || 0 }}</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Seluruh Berkas Terdaftar</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-500 block">Sedang Berproses</span>
                    <h3 class="text-2xl font-black text-blue-600">{{ stats.in_progress || 0 }}</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Dalam Rangkaian Tahapan</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-500 block">Di Denintel (Tahap 1-5)</span>
                    <h3 class="text-2xl font-black text-amber-600">{{ stats.at_denintel || 0 }}</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Pemeriksaan & SKHPP</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-500 block">Di Sintel (Tahap 6-9)</span>
                    <h3 class="text-2xl font-black text-indigo-600">{{ stats.at_sintel || 0 }}</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Validasi & TTD Asintel</p>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-[#E2E8F0] shadow-xs space-y-1 col-span-2 lg:col-span-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-500 block">SC Selesai (Tahap 10)</span>
                    <h3 class="text-2xl font-black text-emerald-600">{{ stats.completed || 0 }}</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Siap Diambil di Mako</p>
                </div>
            </div>

            <!-- Filter & Pencarian Bar -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-[#E2E8F0] shadow-xs flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="flex flex-col sm:flex-row gap-2 flex-1">
                    <div class="relative flex-1">
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            @keyup.enter="handleFilter"
                            placeholder="Cari Nama, NRP/NIP/NIK, Kode Tracking, Kesatuan..." 
                            class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>

                    <select 
                        v-model="selectedStage" 
                        @change="handleFilter"
                        class="text-xs font-semibold py-2.5 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white"
                    >
                        <option value="all">Semua Tahapan (1-10)</option>
                        <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                            Tahap {{ stg.id }}: {{ stg.title }}
                        </option>
                    </select>

                    <select 
                        v-model="selectedStatus" 
                        @change="handleFilter"
                        class="text-xs font-semibold py-2.5 px-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white"
                    >
                        <option value="all">Semua Status</option>
                        <option value="proses">Dalam Proses</option>
                        <option value="selesai">Selesai Terbit</option>
                        <option value="perbaikan">Perbaikan</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button 
                        @click="handleFilter"
                        class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition cursor-pointer"
                    >
                        Cari
                    </button>
                    <button 
                        @click="resetFilter"
                        class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-extrabold transition cursor-pointer"
                        title="Reset Filter"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Tabel Daftar Berkas Pengajuan SC -->
            <div class="bg-white rounded-3xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">
                        Daftar Berkas Pengajuan Security Clearance
                    </h3>
                    <span class="text-xs font-mono font-bold text-slate-400">
                        Total: {{ submissions.total || 0 }} Berkas
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                                <th class="p-3.5 pl-5">Kode / Pemohon</th>
                                <th class="p-3.5">Kesatuan & Keperluan</th>
                                <th class="p-3.5">Tahapan Terkini (1-10)</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5">Tanggal Daftar</th>
                                <th class="p-3.5 pr-5 text-right">Aksi Kedinasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            <tr v-for="sub in submissions.data" :key="sub.id" class="hover:bg-slate-50/70 transition">
                                <!-- Kode & Pemohon -->
                                <td class="p-3.5 pl-5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-[10px] font-bold px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md">
                                            {{ sub.tracking_code }}
                                        </span>
                                    </div>
                                    <div class="font-extrabold text-slate-900 text-sm mt-0.5">
                                        {{ sub.nama }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        {{ sub.pangkat_korps ? sub.pangkat_korps + ' - ' : '' }}{{ sub.identifier_type.toUpperCase() }}. {{ sub.identifier_number }}
                                    </div>
                                </td>

                                <!-- Kesatuan & Keperluan -->
                                <td class="p-3.5">
                                    <span class="font-extrabold text-slate-800 block">{{ sub.kesatuan || '-' }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate max-w-[160px]">{{ sub.keperluan || 'Kedinasan' }}</span>
                                </td>

                                <!-- Tahapan Terkini -->
                                <td class="p-3.5">
                                    <div class="space-y-1 min-w-[200px]">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-extrabold" :class="sub.current_stage === 10 ? 'text-emerald-700' : 'text-blue-700'">
                                                Tahap {{ sub.current_stage }}: {{ sub.stage_title }}
                                            </span>
                                            <span class="font-mono font-bold text-slate-400">{{ sub.current_stage }}/10</span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                            <div 
                                                :style="{ width: sub.progress_percentage + '%' }"
                                                :class="sub.current_stage === 10 ? 'bg-emerald-500' : 'bg-blue-600'"
                                                class="h-full rounded-full transition-all duration-300"
                                            ></div>
                                        </div>
                                        <span v-if="sub.catatan_petugas" class="text-[9px] text-slate-400 italic truncate block max-w-xs">
                                            "{{ sub.catatan_petugas }}"
                                        </span>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="p-3.5">
                                    <span :class="{
                                        'bg-emerald-100 text-emerald-800': sub.status === 'selesai' || sub.current_stage === 10,
                                        'bg-blue-100 text-blue-800': sub.status === 'proses' && sub.current_stage < 10,
                                        'bg-amber-100 text-amber-800': sub.status === 'perbaikan',
                                        'bg-rose-100 text-rose-800': sub.status === 'ditolak',
                                    }" class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider inline-block">
                                        {{ (sub.status || 'proses').toUpperCase() }}
                                    </span>
                                </td>

                                <!-- Tanggal -->
                                <td class="p-3.5 font-mono text-slate-500 text-[11px]">
                                    {{ formatDate(sub.created_at) }}
                                </td>

                                <!-- Tombol Aksi -->
                                <td class="p-3.5 pr-5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Update Tahapan Cepat -->
                                        <button 
                                            @click="openUpdateStageModal(sub)"
                                            class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-200 rounded-xl text-[10px] font-extrabold uppercase tracking-wider transition cursor-pointer"
                                            title="Perbarui tahapan berkas"
                                        >
                                            Update Tahap
                                        </button>

                                        <!-- Riwayat Log -->
                                        <button 
                                            @click="openLogsModal(sub)"
                                            class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition cursor-pointer"
                                            title="Lihat riwayat linimasa berkas"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>

                                        <!-- Edit Data -->
                                        <button 
                                            @click="openEditModal(sub)"
                                            class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl transition cursor-pointer"
                                            title="Edit data pemohon"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>

                                        <!-- Hapus (Admin Only) -->
                                        <button 
                                            v-if="isAdmin"
                                            @click="deleteSubmission(sub)"
                                            class="p-1.5 bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 rounded-xl transition cursor-pointer"
                                            title="Hapus berkas"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!submissions.data || submissions.data.length === 0">
                                <td colspan="6" class="p-12 text-center text-xs text-slate-400 font-semibold italic">
                                    Belum ada berkas pengajuan Security Clearance yang terdaftar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <div v-if="submissions.links && submissions.links.length > 3" class="p-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500 font-medium">
                        Menampilkan {{ submissions.from || 0 }} - {{ submissions.to || 0 }} dari {{ submissions.total || 0 }} berkas
                    </span>
                    <div class="flex items-center gap-1">
                        <Link 
                            v-for="(lnk, lIdx) in submissions.links" 
                            :key="lIdx"
                            :href="lnk.url || '#'"
                            :class="[
                                lnk.active ? 'bg-blue-600 text-white font-black' : 'bg-slate-50 text-slate-700 hover:bg-slate-100',
                                !lnk.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs transition"
                            v-html="lnk.label"
                        ></Link>
                    </div>
                </div>
            </div>

        </div>

        <!-- MODAL DAFTARKAN PENGAJUAN SC BARU -->
        <Teleport to="body">
            <div v-if="isCreateModalOpen" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-black shadow-xs">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Registrasi Berkas Baru</span>
                                <h3 class="text-base font-extrabold text-slate-900">Pendaftaran Pengajuan SC</h3>
                            </div>
                        </div>
                        <button @click="isCreateModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Nama Lengkap -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nama Lengkap Pemohon *</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.nama" 
                                    required 
                                    placeholder="Contoh: Raden Surya Wicaksana"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Pangkat / Korps -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pangkat & Korps</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.pangkat_korps" 
                                    placeholder="Contoh: Kapten Laut (E), Serka Kom, PNS III/b"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Tipe Identitas -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jenis Nomor Identitas *</label>
                                <select 
                                    v-model="createForm.identifier_type"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="nrp">NRP (Militer)</option>
                                    <option value="nip">NIP (PNS/ASN)</option>
                                    <option value="nik">NIK (KTP)</option>
                                </select>
                            </div>

                            <!-- Nomor Identitas -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor NRP / NIP / NIK (Kunci Pelacakan) *</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.identifier_number" 
                                    required 
                                    placeholder="Masukkan nomor identitas untuk tracking publik..."
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                                <span class="text-[10px] text-slate-400 block px-1">*Nomor ini digunakan pemohon untuk mengecek status berkas di portal publik tanpa login.</span>
                            </div>

                            <!-- Kesatuan -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Satuan / Kesatuan</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.kesatuan" 
                                    placeholder="Contoh: Denintel Kodaeral V, KRI ..., dsb."
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Jabatan -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jabatan</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.jabatan" 
                                    placeholder="Contoh: Paur Lidik, Ba Intel, dll."
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- No. WhatsApp / HP -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor WhatsApp / HP</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.phone" 
                                    placeholder="Contoh: 081234567890"
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Keperluan -->
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Keperluan Pengajuan SC</label>
                                <input 
                                    type="text" 
                                    v-model="createForm.keperluan" 
                                    placeholder="Contoh: Satgas Luar Negeri, Dikreg Seskoal, UKP, dsb."
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <!-- Tahapan Awal -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tahapan Awal Berkas Saat Ini</label>
                                <select 
                                    v-model="createForm.current_stage"
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                >
                                    <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                                        Tahap {{ stg.id }}: {{ stg.title }} ({{ stg.category }})
                                    </option>
                                </select>
                            </div>

                            <!-- Catatan Awal Petugas -->
                            <div class="space-y-1 sm:col-span-2">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Catatan Awal Petugas</label>
                                <textarea 
                                    v-model="createForm.catatan_petugas"
                                    rows="2"
                                    placeholder="Keterangan awal penyerahan berkas atau catatan operasional..."
                                    class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isCreateModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingCreate"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingCreate ? 'Menyimpan...' : 'Daftarkan Pengajuan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL PEMBARUAN TAHAPAN CEPAT (QUICK STAGE UPDATE) -->
        <Teleport to="body">
            <div v-if="isUpdateStageModalOpen && activeSubmissionForStage" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Pembaruan Tahapan Berkas</span>
                            <h3 class="text-base font-extrabold text-slate-900">
                                {{ activeSubmissionForStage.nama }}
                            </h3>
                            <span class="text-[10px] font-mono text-slate-400 block">{{ activeSubmissionForStage.tracking_code }} - {{ activeSubmissionForStage.identifier_number }}</span>
                        </div>
                        <button @click="isUpdateStageModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitUpdateStage" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <!-- Quick Advance Button -->
                        <div v-if="stageForm.stage < 10" class="p-3 bg-blue-50 border border-blue-200/70 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-blue-600 uppercase block">Lompat Cepat:</span>
                                <span class="text-xs font-extrabold text-blue-900">Maju ke Tahap {{ stageForm.stage + 1 }}: {{ stages[stageForm.stage]?.title }}</span>
                            </div>
                            <button 
                                type="button" 
                                @click="advanceToNextStage"
                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-[10px] uppercase tracking-wider transition cursor-pointer shadow-xs"
                            >
                                +1 Maju Tahap
                            </button>
                        </div>

                        <!-- Pilih Tahapan Berkas -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pilih Tahapan Saat Ini (1-10) *</label>
                            <select 
                                v-model="stageForm.stage"
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                                    Tahap {{ stg.id }}: {{ stg.title }} ({{ stg.category }})
                                </option>
                            </select>
                        </div>

                        <!-- Status Berkas -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Status Operasional Berkas</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label :class="stageForm.status === 'proses' ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-700 border-slate-200'" class="p-2 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="stageForm.status" value="proses" class="hidden" />
                                    Proses
                                </label>
                                <label :class="stageForm.status === 'selesai' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-slate-50 text-slate-700 border-slate-200'" class="p-2 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="stageForm.status" value="selesai" class="hidden" />
                                    Selesai
                                </label>
                                <label :class="stageForm.status === 'perbaikan' ? 'bg-amber-600 text-white border-amber-600' : 'bg-slate-50 text-slate-700 border-slate-200'" class="p-2 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="stageForm.status" value="perbaikan" class="hidden" />
                                    Perbaikan
                                </label>
                            </div>
                        </div>

                        <!-- Input Nomor SKHPP jika tahap >= 5 -->
                        <div v-if="stageForm.stage >= 5" class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor SKHPP (Denintel)</label>
                            <input 
                                type="text"
                                v-model="stageForm.nomor_skhpp"
                                placeholder="Contoh: SKHPP/123/IX/2026"
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Input Nomor SC jika tahap >= 8 -->
                        <div v-if="stageForm.stage >= 8" class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor Naskah Security Clearance (SC)</label>
                            <input 
                                type="text"
                                v-model="stageForm.nomor_sc"
                                placeholder="Contoh: SC/456/IX/2026/Sintel"
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Catatan Petugas untuk Tahap Ini -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Catatan Pembaruan (Terlihat oleh Pemohon)</label>
                            <textarea 
                                v-model="stageForm.notes"
                                rows="3"
                                placeholder="Tuliskan keterangan detail posisi berkas untuk informasi personel..."
                                class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isUpdateStageModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingStage"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingStage ? 'Menyimpan...' : 'Simpan Tahapan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL RIWAYAT LINIMASA LOGS -->
        <Teleport to="body">
            <div v-if="isLogsModalOpen && activeSubmissionForLogs" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Riwayat Alur Berkas</span>
                            <h3 class="text-base font-extrabold text-slate-900">
                                {{ activeSubmissionForLogs.nama }}
                            </h3>
                            <span class="text-[10px] font-mono text-slate-400 block">{{ activeSubmissionForLogs.tracking_code }}</span>
                        </div>
                        <button @click="isLogsModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <div class="p-5 sm:p-6 space-y-4 text-xs">
                        <div v-if="activeSubmissionForLogs.logs && activeSubmissionForLogs.logs.length > 0" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <div 
                                v-for="(lg, lgIdx) in activeSubmissionForLogs.logs" 
                                :key="lg.id"
                                class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-2xl space-y-1"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-extrabold text-blue-700">Tahap {{ lg.stage }}: {{ lg.stage_title }}</span>
                                    <span class="font-mono text-[10px] text-slate-400">{{ formatDateTime(lg.created_at) }}</span>
                                </div>
                                <p class="text-slate-600 font-medium leading-relaxed">{{ lg.notes || '-' }}</p>
                                <div class="text-[10px] text-slate-400 font-bold pt-1 border-t border-slate-200/60">
                                    Petugas: {{ lg.user_name || 'Petugas Kedinasan' }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-slate-400 font-medium italic">
                            Belum ada riwayat pembaruan yang tercatat.
                        </div>

                        <div class="pt-2 text-right">
                            <button 
                                type="button" 
                                @click="isLogsModalOpen = false" 
                                class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL EDIT DATA PEMOHON -->
        <Teleport to="body">
            <div v-if="isEditModalOpen" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Koreksi Data Berkas</span>
                            <h3 class="text-base font-extrabold text-slate-900">Edit Data Pemohon</h3>
                        </div>
                        <button @click="isEditModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitEdit" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nama Lengkap *</label>
                            <input 
                                type="text" 
                                v-model="editForm.nama" 
                                required
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pangkat & Korps</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.pangkat_korps" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor Identitas *</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.identifier_number" 
                                    required
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Satuan / Kesatuan</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.kesatuan" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">No. WhatsApp / HP</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.phone" 
                                    class="w-full text-xs font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Keperluan SC</label>
                            <input 
                                type="text" 
                                v-model="editForm.keperluan" 
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor SKHPP</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.nomor_skhpp" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nomor SC Resmi</label>
                                <input 
                                    type="text" 
                                    v-model="editForm.nomor_sc" 
                                    class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white"
                                />
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isEditModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingEdit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingEdit ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
