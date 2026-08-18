<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router, Link } from '@inertiajs/vue3';
// PERBAIKAN: Menambahkan onMounted dan onUnmounted agar tidak error
import { ref, computed, onMounted, onUnmounted } from 'vue'; 
import Swal from 'sweetalert2';
import html2pdf from 'html2pdf.js'; // Library Sakti untuk Cetak Tanpa RAM Server

const props = defineProps({
    users: Object, // Data pagination (10 orang)
    allUsers: Array, // Data lengkap untuk Rekap PDF
});

const user = computed(() => usePage().props.auth.user);

// Mapping Role Satuan untuk tampilan yang lebih rapi di PDF
const roleLabels = {
    admin: 'ADMINISTRATOR SISTEM',
    komandan: 'KOMANDAN (APPROVER)',
    wadan: 'WAKIL KOMANDAN',
    pasops: 'PASOPS',
    danunit1: 'DAN UNIT I / LID',
    danunit2: 'DAN UNIT II / PAMGAL',
    danunitteknis: 'DAN UNIT TEKNIS',
    kaurmintel: 'KAUR MINTEL',
    paurset: 'PAUR SET',
    staf: 'STAF ADMINISTRASI',
    personel: 'PERSONEL SATUAN'
};

// State Management
const showPasswordModal = ref(false);
const showEditModal = ref(false);
const showAddModal = ref(false); 
const showPreview = ref(false);
const previewImage = ref(null);
const selectedUser = ref(null);

// Form untuk verifikasi password admin (Suspend/Verifikasi)
const form = useForm({
    password: '',
});

// Form untuk Edit Data
const editForm = useForm({
    id: '',
    name: '',
    email: '',
    pangkat: '',
    nrp: '',
    phone: '',
});

// Form untuk Tambah Personel Baru (Otomatis)
const addForm = useForm({
    name: '',
    pangkat: '',
    nrp: '',
    email: '',
    phone: '',
    role: 'personel',
});

/**
 * FITUR BARU: LOGIKA CETAK METODE SULTAN (HTML2PDF)
 */
const formatLongDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const openPreview = () => {
    const element = document.getElementById('area-rekap-personel');
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        html2canvas: { scale: 2, useCORS: true, width: 1100 },
    };

    html2pdf().set(opt).from(element).outputImg().then((img) => {
        previewImage.value = img.src;
        showPreview.value = true;
    });
};

const downloadPDF = () => {
    const element = document.getElementById('area-rekap-personel');
    const opt = {
        margin:       [0.5, 0.5, 0.5, 0.5],
        filename:      `Rekap_Personel_Sinden_${new Date().getTime()}.pdf`,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, width: 1100, windowWidth: 1100 },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
};

/**
 * FITUR: TAMBAH PERSONEL BARU
 */
const submitAdd = () => {
    addForm.post(route('users.store'), {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
            Swal.fire({
                title: 'BERHASIL',
                text: 'Akun dibuat. Detail login dikirim via WhatsApp ke personel.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            });
        },
        onError: (err) => {
            Swal.fire('GAGAL', 'Cek kembali data. Pastikan NRP/Email/WA belum terdaftar.', 'error');
        }
    });
};

/**
 * RESET PASSWORD: Request Token
 */
const requestToken = (user) => {
    Swal.fire({
        title: 'REQUEST TOKEN?',
        text: `Generate token reset password untuk ${user.name}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'YA, GENERATE'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('users.generate-token', user.id), {}, {
                onSuccess: (page) => {
                    const token = page.props.flash.token;
                    if(token) {
                        Swal.fire({
                            title: 'TOKEN BERHASIL DIBUAT',
                            html: `
                                <div class="bg-indigo-50 p-6 rounded-3xl border-2 border-dashed border-indigo-200 my-4">
                                    <div class="text-4xl font-black tracking-[0.3em] text-indigo-600">${token}</div>
                                </div>
                                <p class="text-[10px] uppercase font-bold text-gray-400">Berikan kode ini kepada personel.<br>Berlaku selama 5 menit.</p>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#4f46e5'
                        });
                    }
                }
            });
        }
    });
};

/**
 * EDIT DATA
 */
const startEdit = (user) => {
    selectedUser.value = user;
    editForm.id = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.pangkat = user.pangkat;
    editForm.nrp = user.nrp;
    editForm.phone = user.phone;
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('users.update', editForm.id), {
        onSuccess: () => {
            showEditModal.value = false;
            Swal.fire('BERHASIL', 'Data personel telah diperbarui.', 'success');
        }
    });
};

/**
 * KONFIRMASI: Verifikasi Password Admin
 */
const startConfirmation = (user) => {
    selectedUser.value = user;
    showPasswordModal.value = true;
};

const submitConfirmation = () => {
    form.patch(route('users.toggle', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPasswordModal.value = false;
            form.reset();
            Swal.fire('SUKSES', 'Status akses diperbarui.', 'success');
        },
        onError: (errors) => {
            Swal.fire('GAGAL', errors.password || 'Otoritas ditolak.', 'error');
        }
    });
};

const deleteUser = (user) => {
    Swal.fire({
        title: 'HAPUS PERSONEL?',
        text: "Akun akan dihapus permanen dari sistem!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'HAPUS AKUN'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('users.destroy', user.id), {
                onSuccess: () => Swal.fire('Dihapus', 'Data telah dibersihkan.', 'success')
            });
        }
    });
};

const toggleMitraAccess = (user) => {
    router.post(route('users.toggle-mitra-access', user.id), {}, {
        preserveScroll: true,
        onSuccess: () => Swal.fire('SUKSES', `Hak Akses Modul Mitra untuk ${user.name} berhasil diperbarui.`, 'success')
    });
};

const toggleTechnicalCashAccess = (user) => {
    router.post(route('users.toggle-technical-cash-access', user.id), {}, {
        preserveScroll: true,
        onSuccess: () => Swal.fire('SUKSES', `Hak Akses Buku Kas Dan Unit Teknis untuk ${user.name} berhasil diperbarui.`, 'success')
    });
};

const getStatusClass = (status) => {
    return status ? 'bg-emerald-50 text-emerald-600 border-emerald-100 shadow-sm' : 'bg-rose-50 text-rose-600 border-rose-100 shadow-sm';
};

// --- AUTO REFRESH (Opsional) ---
let interval = null;
onMounted(() => {
    // interval = setInterval(() => router.reload({ only: ['users'] }), 10000);
});
onUnmounted(() => {
    if (interval) clearInterval(interval);
});
</script>

<template>
    <Head title="Otoritas Akses Personel" />

    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-4 sm:p-6 lg:p-8 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 uppercase tracking-tight">Otoritas Akses Personel</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Manajemen Pengguna, Peran Otoritas, & Verifikasi Akses Sistem</p>
                </div>
                
                <div class="flex gap-2 sm:gap-3 w-full md:w-auto">
                    <button @click="openPreview" class="flex-1 md:flex-none px-4 sm:px-5 py-2.5 sm:py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-2xl font-extrabold text-xs uppercase shadow-xs hover:bg-slate-100 transition tracking-wider"> Pratinjau Rekap
                    </button>
                    <button @click="showAddModal = true" class="flex-1 md:flex-none px-4 sm:px-5 py-2.5 sm:py-3 bg-blue-600 text-white rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-blue-500/20 hover:bg-blue-700 transition tracking-wider">
                        + Tambah Personel
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-[#E2E8F0] shadow-xs overflow-hidden p-3 sm:p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="pb-4 px-4">Personel</th>
                                <th class="pb-4 px-4 text-center">Identitas (NRP)</th>
                                <th class="pb-4 px-4 text-center">Status</th>
                                <th class="pb-4 px-4 text-right">Opsi Kelola</th>
                            </tr>
                        </thead>
                    <tbody class="divide-y divide-gray-50 text-[11px]">
                        <tr v-for="user in users.data" :key="user.id" class="group hover:bg-indigo-50/30 transition-all">
                            <td class="py-6 px-4">
                                <div class="flex flex-col leading-tight">
                                    <span class="font-black text-indigo-950 uppercase">{{ user.name }}</span>
                                    <span class="text-[9px] text-gray-400 font-bold tracking-tighter">{{ user.email }}</span>
                                    <span v-if="user.phone" class="text-[8px] text-indigo-500 font-bold mt-1">WA: {{ user.phone }}</span>
                                </div>
                            </td>
                            <td class="py-6 px-4 text-center">
                                <div class="flex flex-col leading-tight">
                                    <span class="font-black text-gray-800 uppercase italic">{{ user.pangkat || 'BELUM DIISI' }}</span>
                                    <span class="text-[10px] text-indigo-500 font-black">NRP. {{ user.nrp || '-' }}</span>
                                </div>
                            </td>
                            <td class="py-6 px-4 text-center">
                                <span :class="getStatusClass(user.is_active)" class="px-4 py-1.5 rounded-full text-[8px] font-black uppercase tracking-widest border">
                                    {{ user.is_active ? 'TERVERIFIKASI' : 'SUSPEND' }}
                                </span>
                            </td>
                            <td class="py-6 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="startEdit(user)" class="p-2 bg-gray-50 text-gray-400 hover:text-indigo-600 rounded-lg transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button @click="toggleMitraAccess(user)" 
                                        :class="user.can_access_mitra ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-600 hover:text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-700 hover:text-white'"class="px-2.5 py-1.5 text-[8px] font-black uppercase rounded-lg transition-all shadow-xs"title="Toggle Hak Akses Modul Mitra"> Mitra: {{ user.can_access_mitra ? 'AKTIF' : 'OFF' }}
                                    </button>
                                    <button @click="toggleTechnicalCashAccess(user)" 
                                        :class="user.can_access_technical_cash ? 'bg-cyan-100 text-cyan-800 hover:bg-cyan-600 hover:text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-700 hover:text-white'"class="px-2.5 py-1.5 text-[8px] font-black uppercase rounded-lg transition-all shadow-xs"title="Toggle Hak Akses Buku Kas Dan Unit Teknis"> Kas Teknis: {{ user.can_access_technical_cash ? 'AKTIF' : 'OFF' }}
                                    </button>
                                    <button @click="requestToken(user)" class="px-3 py-2 bg-amber-100 text-amber-700 text-[8px] font-black uppercase rounded-lg hover:bg-amber-500 hover:text-white transition-all shadow-sm"> Req Token
                                    </button>
                                    <button @click="startConfirmation(user)" 
                                        :class="user.is_active ? 'border-rose-500 text-rose-500 hover:bg-rose-500 hover:text-white' : 'bg-indigo-600 text-white hover:bg-indigo-700'"class="px-5 py-2 font-black text-[9px] uppercase rounded-xl shadow-lg transition-all active:scale-95 border-2 border-transparent">
                                        {{ user.is_active ? 'SUSPEND' : 'AKTIFKAN' }}
                                    </button>
                                    <button @click="deleteUser(user)" class="p-2 text-gray-300 hover:text-rose-600 transition-colors text-lg">
                                        &times;
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 flex items-center justify-between px-4">
                <div class="text-[10px] font-bold text-gray-400 uppercase italic"> Showing {{ users.from }} to {{ users.to }} of {{ users.total }} Personnels
                </div>
                <div class="flex gap-1">
                    <Link v-for="(link, index) in users.links" :key="index"
                        :href="link.url || '#'"v-html="link.label"
                        :class="[
                            'px-4 py-2 rounded-xl text-[10px] font-black transition-all border',
                            link.active ? 'bg-indigo-600 text-white border-indigo-600 shadow-md' : 'bg-white text-gray-400 border-gray-100 hover:bg-gray-50',
                            !link.url ? 'opacity-30 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>
        </div>

        <div style="position: absolute; left: -9999px;">
            <div id="area-rekap-personel" class="p-10 bg-white text-black" style="width: 1050px; font-family: Arial, sans-serif;">
                <div class="inline-table uppercase mb-10 text-left text-base font-bold">
                    <p class="leading-none tracking-tight">KOMANDO DAERAH TNI ANGKATAN LAUT</p>
                    <p class="leading-none tracking-tight text-center mt-1">DETASEMEN INTELIJEN</p>
                    <div class="border-b-[2px] border-black mt-2 w-full"></div>
                </div>

                <div class="text-center mb-8 uppercase text-base">
                    <h3 class="font-bold underline tracking-widest leading-none">REKAPITULASI OTORITAS AKSES PERSONEL DIGITAL</h3>
                    <p class="font-normal mt-2 text-sm">Status Data: Per {{ formatLongDate(new Date()) }}</p>
                </div>

                <table class="w-full border-collapse border-[1.5px] border-black text-[10px]">
                    <thead>
                        <tr class="bg-gray-100 uppercase font-bold text-center">
                            <th class="border border-black px-2 py-3 w-[5%]">NO</th>
                            <th class="border border-black px-3 py-3 text-left w-[25%]">NAMA LENGKAP</th>
                            <th class="border border-black px-3 py-3 text-center w-[15%]">PANGKAT</th>
                            <th class="border border-black px-3 py-3 text-center w-[15%]">NRP/NIP</th>
                            <th class="border border-black px-3 py-3 text-left w-[20%]">EMAIL / AKSES</th>
                            <th class="border border-black px-3 py-3 w-[10%]">ROLE</th>
                            <th class="border border-black px-3 py-3 w-[10%]">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(u, index) in allUsers" :key="u.id">
                            <td class="border border-black px-2 py-2 text-center">{{ index + 1 }}</td>
                            <td class="border border-black px-3 py-2 font-bold uppercase">{{ u.name }}</td>
                            <td class="border border-black px-3 py-2 text-center uppercase">{{ u.pangkat || '-' }}</td>
                            <td class="border border-black px-3 py-2 text-center font-mono">{{ u.nrp || '-' }}</td>
                            <td class="border border-black px-3 py-2" style="text-transform: none;">{{ u.email }}</td>
                            <td class="border border-black px-3 py-2 text-center uppercase">{{ roleLabels[u.role] || u.role }}</td>
                            <td class="border border-black px-3 py-2 text-center font-bold">{{ u.is_active ? 'TERVERIFIKASI' : 'SUSPEND' }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-16 flex justify-end text-center text-sm">
                    <div class="w-72">
                        <p class="mb-20 uppercase">Surabaya, {{ formatLongDate(new Date()) }}<br>Admin Otoritas,</p>
                        <p class="font-bold underline leading-none uppercase">{{ $page.props.auth.user.name }}</p>
                        <p class="font-normal mt-1 uppercase">{{ $page.props.auth.user.pangkat || 'STAF' }}</p>
                        <p class="font-normal mt-0.5 uppercase">{{ $page.props.auth.user.nrp ? 'NRP. ' + $page.props.auth.user.nrp : '' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showPreview" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-6xl w-full h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b flex justify-between items-center bg-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-black italic">PDF</div>
                        <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest">Personnel Report Preview (Landscape)</h3>
                    </div>
                    <button @click="showPreview = false" class="text-gray-400 hover:text-red-500 font-black text-2xl">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto bg-gray-700 p-4 md:p-10 flex justify-center items-start">
                    <img :src="previewImage" class="shadow-2xl border border-gray-400 bg-white max-w-full h-auto" />
                </div>
                <div class="p-6 border-t bg-gray-50 flex justify-end gap-3">
                    <button @click="showPreview = false" class="px-6 py-2.5 text-[10px] font-black uppercase text-gray-500">Batal</button>
                    <button @click="downloadPDF(); showPreview = false" class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg">Download PDF Rekap</button>
                </div>
            </div>
        </div>

        <div v-if="showAddModal" class="fixed inset-0 bg-indigo-950/20 backdrop-blur-md flex items-center justify-center z-[100] p-6">
            <div class="bg-white w-full max-w-xl rounded-[2.5rem] shadow-2xl p-10 animate-in zoom-in duration-200">
                <h3 class="text-sm font-black text-indigo-950 uppercase mb-8 border-l-4 border-indigo-600 pl-4 italic">Registrasi Akun Personel Baru</h3>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Nama Lengkap</label>
                            <input v-model="addForm.name" type="text" placeholder="MASUKKAN NAMA" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Pangkat</label>
                            <input v-model="addForm.pangkat" type="text" placeholder="SERDA" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">NRP</label>
                            <input v-model="addForm.nrp" type="text" placeholder="IDENTITAS" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-600" required />
                        </div>

                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Otoritas Akses (Role)</label>
                            <select v-model="addForm.role" class="w-full bg-indigo-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-600">
                                <option value="personel">PERSONEL SATUAN</option>
                                <option value="staf">STAF ADMINISTRASI</option>
                                <option value="komandan">KOMANDAN (APPROVER)</option>
                                <option value="admin">ADMINISTRATOR SISTEM</option>
                                <option value="wadan">WAKIL KOMANDAN</option>
                                <option value="pasops">PASOPS</option>
                                <option value="danunit1">DAN UNIT I / LID</option>
                                <option value="danunit2">DAN UNIT II / PAMGAL</option>
                                <option value="danunitteknis">DAN UNIT TEKNIS</option>
                                <option value="kaurmintel">KAUR MINTEL</option>
                                <option value="paurset">PAUR SET</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Email</label>
                            <input v-model="addForm.email" type="email" placeholder="email@contoh.com" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">WhatsApp</label>
                            <input v-model="addForm.phone" type="text" placeholder="0812XXXXXXXX" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-600" required />
                        </div>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 mt-4 text-center">
                        <p class="text-[9px] text-indigo-600 font-bold uppercase leading-relaxed italic">Sistem akan otomatis mengirimkan password dan detail jabatan ke WhatsApp personel.</p>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" @click="showAddModal = false" class="flex-1 py-4 text-[9px] font-black text-gray-400 uppercase transition-all hover:text-indigo-600">Batal</button>
                        <button type="submit" :disabled="addForm.processing" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl hover:bg-indigo-700 active:scale-95 transition-all">BUAT AKUN & KIRIM WA</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 bg-indigo-950/20 backdrop-blur-md flex items-center justify-center z-[100] p-6">
            <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl p-10 animate-in zoom-in duration-200">
                <h3 class="text-sm font-black text-indigo-950 uppercase mb-8 border-l-4 border-indigo-600 pl-4 italic">Update Data Personel</h3>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Nama Lengkap</label>
                            <input v-model="editForm.name" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Pangkat</label>
                            <input v-model="editForm.pangkat" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black uppercase focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">NRP</label>
                            <input v-model="editForm.nrp" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-black focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Email</label>
                            <input v-model="editForm.email" type="email" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2">WhatsApp</label>
                            <input v-model="editForm.phone" type="text" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[11px] font-bold focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="button" @click="showEditModal = false" class="flex-1 py-4 text-[9px] font-black text-gray-400 uppercase">Tutup</button>
                        <button type="submit" :disabled="editForm.processing" class="flex-[2] py-4 bg-gray-900 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl hover:bg-indigo-600 transition-all">Update Personel</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="showPasswordModal" class="fixed inset-0 bg-indigo-950/20 backdrop-blur-md flex items-center justify-center z-[100] p-6">
            <div class="bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl border border-indigo-50 p-10 animate-in fade-in zoom-in duration-300">
                <div class="text-center mb-8">
                    <div class="h-16 w-16 bg-rose-50 text-rose-600 rounded-3xl flex items-center justify-center text-2xl mx-auto mb-4 animate-bounce"></div>
                    <h3 class="text-sm font-black text-indigo-950 uppercase tracking-[0.1em]">Otoritas Admin</h3>
                    <p class="text-[9px] text-gray-400 font-bold uppercase mt-2 italic">Konfirmasi password untuk merubah izin akses</p>
                </div>
                <div class="space-y-6">
                    <input v-model="form.password" type="password" placeholder="PASSWORD ADMIN" @keyup.enter="submitConfirmation" class="w-full bg-gray-50 border-none rounded-2xl p-4 text-[10px] font-black text-center uppercase tracking-widest focus:ring-2 focus:ring-indigo-500" autofocus />
                    <div class="flex gap-4">
                        <button @click="showPasswordModal = false" class="flex-1 py-4 text-[9px] font-black text-gray-400 uppercase">Batal</button>
                        <button @click="submitConfirmation" :disabled="form.processing" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl active:scale-95">LANJUTKAN</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
/* Style untuk PDF Rekap agar font mono NRP terlihat jelas */
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>