<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';

const page = usePage();

const props = defineProps({
    myPcs: Array,
    publicPcs: Array,
    networks: Array,
    isAdmin: Boolean,
    globalStats: Object,
    authRequest: Object,       // Data pengajuan user yang sedang login
    pendingRequests: Array,    // Daftar pengajuan untuk Admin
    allUsers: {
        type: Array,
        default: () => []
    }
});

const networkForm = useForm({
    location_name: '',
    ip_address: '',
});

// --- OPERASI ADMIN: BUAT PANGKALAN PC DENGAN KUOTA GB KUSTOM ---
const isCreatePcModalOpen = ref(false);
const createPcForm = useForm({
    pc_name: '',
    user_id: '',
    quota_gb: 200,
});

const openCreatePcModal = () => {
    createPcForm.pc_name = '';
    createPcForm.user_id = page.props.auth?.user?.id || '';
    createPcForm.quota_gb = 200;
    isCreatePcModalOpen.value = true;
};

const submitCreatePc = () => {
    createPcForm.post(route('admin.backup.create-pc'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreatePcModalOpen.value = false;
            createPcForm.reset();
            Swal.fire({
                title: 'Pangkalan Siap!',
                text: 'Pangkalan Backup baru berhasil dibangun sesuai jatah kuota.',
                icon: 'success',
                confirmButtonColor: '#2563eb'
            });
        },
        onError: (err) => {
            Swal.fire('Gagal Membuat PC', Object.values(err)[0] || 'Terjadi kesalahan.', 'error');
        }
    });
};

// --- OPERASI ADMIN: SESUAIKAN KUOTA GB PANGKALAN PC ---
const isEditQuotaModalOpen = ref(false);
const activePcForQuota = ref(null);
const editQuotaForm = useForm({
    quota_gb: 200,
});

const openEditQuotaModal = (pc) => {
    activePcForQuota.value = pc;
    const currentGb = Math.round(pc.max_quota / (1024 * 1024 * 1024)) || 200;
    editQuotaForm.quota_gb = currentGb;
    isEditQuotaModalOpen.value = true;
};

const submitEditQuota = () => {
    if (!activePcForQuota.value) return;
    editQuotaForm.post(route('admin.backup.update-quota', activePcForQuota.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditQuotaModalOpen.value = false;
            Swal.fire({
                title: 'Kuota Disesuaikan!',
                text: `Kapasitas kuota ${activePcForQuota.value.pc_name} berhasil diperbarui.`,
                icon: 'success',
                confirmButtonColor: '#2563eb'
            });
        },
        onError: (err) => {
            Swal.fire('Gagal Mengubah Kuota', Object.values(err)[0] || 'Terjadi kesalahan.', 'error');
        }
    });
};

// --- LOGIKA OTORITAS AKSES (SWEETALERT) ---
// --- FUNGSI PEMERIKSAAN OTORITAS SEBELUM BUKA PC ---
const openPcStorage = (pcId) => {
    router.visit(route('backup.explore', pcId));
};

const checkAksesStatus = () => {
    // Jika Admin belum punya PC, Admin bisa langsung buat PC
    if (props.isAdmin && props.myPcs.length === 0) {
        return;
    }

    // Jika personel belum punya PC sama sekali dan bukan Admin bypass
    if (props.myPcs.length === 0 && !props.isAdmin) {
        
        // 1. Jika belum pernah mengajukan akses
        if (!props.authRequest) {
            Swal.fire({
                title: 'Akses Ditolak',
                text: "Anda belum memiliki pangkalan penyimpanan. Ajukan akses sekarang?",
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Ajukan Akses!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    useForm({}).post(route('backup.request-access'), {
                        onSuccess: () => Swal.fire('Berhasil', 'Laporan pengajuan telah dikirim.', 'success')
                    });
                }
            });
        } 
        
        // 2. Jika sedang menunggu persetujuan (Pending)
        else if (props.authRequest.status === 'pending') {
            Swal.fire({
                title: 'Dalam Antrean',
                text: 'Pengajuan anda sedang ditinjau. Mohon menunggu kode verifikasi melalui WhatsApp.',
                icon: 'info'
            });
        } 
        
        // 3. Jika sudah disetujui Admin (Input Kode Verifikasi)
        else if (props.authRequest.status === 'approved') {
            Swal.fire({
                title: 'Verifikasi Otoritas',
                text: 'Masukkan 6 digit kode akses yang dikirim ke WhatsApp anda:',
                input: 'text',
                inputAttributes: { autocapitalize: 'off', maxlength: 6 },
                showCancelButton: true,
                confirmButtonText: 'Verifikasi & Bangun PC',
                showLoaderOnConfirm: true,
                preConfirm: (code) => {
                    return new Promise((resolve) => {
                        useForm({ code: code }).post(route('backup.verify'), {
                            onSuccess: () => {
                                Swal.fire('Berhasil', 'Pangkalan PC 200GB telah siap!', 'success');
                                resolve();
                            },
                            onError: (errors) => {
                                Swal.showValidationMessage(errors.code || 'Kode salah atau kadaluarsa!');
                                resolve();
                            }
                        });
                    });
                }
            });
        }
    }
};

// --- FUNGSI ADMIN: PERSETUJUAN ---
const approvePersonel = (id) => {
    Swal.fire({
        title: 'Berikan Otoritas?',
        text: "Sistem akan mengirimkan kode akses unik ke WhatsApp personel.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui!'
    }).then((result) => {
        if (result.isConfirmed) {
            useForm({}).post(route('admin.backup.approve', id), {
                onSuccess: (page) => {
                    // MENGAMBIL KODE DARI FLASH SESSION (DIBERIKAN OLEH CONTROLLER)
                    const code = page.props.flash.generatedCode;
                    
                    Swal.fire({
                        title: 'Otoritas Berhasil!',
                        html: `Kode telah terkirim via WA.<br><br>KODE AKSES PERSONEL:<br><b style="font-size: 32px; color: #d32f2f; letter-spacing: 5px;">${code || '------'}</b>`,
                        icon: 'success'
                    });
                }
            });
        }
    });
};

// --- FUNGSI ADMIN: HAPUS AKSES & SELURUH BERKAS PC PERMANEN ---
const revokePcAccess = (pc) => {
    const ownerName = pc.user?.name || pc.pc_name;
    Swal.fire({
        title: 'HAPUS OTORITAS & AKSES PC?',
        html: `Peringatan: Seluruh berkas, folder, dan jatah penyimpanan <b>${pc.pc_name}</b> (Milik: <b>${ownerName}</b>) akan <b>DIHAPUS PERMANEN</b> dari server!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus Permanen!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            useForm({}).delete(route('admin.backup.revoke', pc.id), {
                onSuccess: () => {
                    Swal.fire({
                        title: 'Telah Dihapus!',
                        text: 'Otoritas akses dan seluruh berkas PC berhasil dihapus permanen.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    });
                }
            });
        }
    });
};

// --- FUNGSI ADMIN: RADAR IP ---
const submitNetwork = () => {
    networkForm.post(route('admin.backup.network.store'), {
        onSuccess: () => {
            networkForm.reset();
            Swal.fire('Berhasil', 'Keamanan radar IP telah diperbarui.', 'success');
        }
    });
};

const deleteNetwork = (id) => {
    Swal.fire({
        title: 'Hapus Radar?',
        text: "Lokasi ini tidak akan lagi masuk dalam zona aman kantor.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            useForm({}).delete(route('admin.backup.network.destroy', id));
        }
    });
};

onMounted(() => {
    checkAksesStatus();
});
</script>

<template>
    <Head title="SINDEN Backup" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight uppercase font-black"> Pusat Backup & Otoritas Akses</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div v-if="isAdmin && pendingRequests.length > 0" class="bg-amber-50 border-l-4 border-amber-400 p-6 shadow-sm rounded-lg">
                    <h3 class="text-lg font-bold text-amber-800 mb-4 uppercase flex items-center gap-2">
                        <span> Antrean Pengajuan Akses</span>
                    </h3>
                    <div class="grid grid-cols-1 gap-3">
                        <div v-for="req in pendingRequests" :key="req.id" class="bg-white p-4 rounded border flex justify-between items-center shadow-sm">
                            <div>
                                <p class="font-bold text-gray-800">{{ req.user.name }} <span class="text-xs font-normal text-gray-500">({{ req.user.nrp }})</span></p>
                                <p class="text-xs text-gray-400 italic">Meminta Akses: {{ req.pc_name }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button @click="approvePersonel(req.id)" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-xs font-bold transition flex items-center gap-1"> Setujui
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isAdmin && globalStats" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-6 rounded-lg shadow border-b-4 border-blue-600">
                        <p class="text-gray-500 text-sm font-bold uppercase">Total PC Aktif</p>
                        <p class="text-3xl font-black text-blue-900">{{ globalStats.total_pcs }} Unit</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow border-b-4 border-green-600">
                        <p class="text-gray-500 text-sm font-bold uppercase">Total Kapasitas Terpakai</p>
                        <p class="text-3xl font-black text-green-900">{{ globalStats.total_storage }}</p>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-blue-800">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4 border-b pb-3">
                        <h3 class="text-lg font-bold text-blue-800 uppercase flex items-center gap-2">
                            <span> Penyimpanan PC Saya</span>
                        </h3>
                        <button v-if="isAdmin" @click="openCreatePcModal" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-sm flex items-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                            <span>Buat Pangkalan Backup Baru</span>
                        </button>
                    </div>

                    <div v-if="myPcs.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="pc in myPcs" :key="pc.id" class="border rounded-xl p-5 bg-slate-50 relative overflow-hidden group hover:shadow-lg transition">
                            <div class="absolute top-0 right-0 p-2 flex items-center gap-1.5">
                                <span class="text-[10px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-bold uppercase">{{ pc.hardware_id }}</span>
                                <span v-if="isAdmin" class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full font-bold uppercase">{{ pc.quota_human }}</span>
                            </div>
                            <h4 class="font-black text-blue-900 mb-4 uppercase">{{ pc.pc_name }}</h4>
                            
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs font-bold">
                                    <span>Pemakaian Penyimpanan</span>
                                    <span :class="pc.usage_percentage > 90 ? 'text-red-600' : 'text-blue-600'">{{ pc.usage_percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-700" :style="{ width: pc.usage_percentage + '%' }"></div>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-500 font-mono">
                                    <span>{{ pc.usage_human }} Terpakai</span>
                                    <span>Limit: {{ pc.quota_human }}</span>
                                </div>
                            </div>

                            <div class="mt-6 space-y-2">
                                <Link :href="route('backup.explore', pc.id)" class="block w-full text-center bg-blue-700 hover:bg-blue-800 text-white py-3 rounded-lg font-bold transition shadow-md uppercase text-sm"> MASUK DAFTAR PC
                                </Link>
                                <div v-if="isAdmin" class="grid grid-cols-2 gap-2">
                                    <button @click="openEditQuotaModal(pc)" class="w-full text-center bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white py-2 rounded-lg font-bold text-xs transition uppercase border border-blue-200 flex items-center justify-center gap-1.5 cursor-pointer" title="Ubah kapasitas penyimpanan (GB)">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Sesuaikan Kuota</span>
                                    </button>
                                    <button @click="revokePcAccess(pc)" class="w-full text-center bg-red-50 hover:bg-red-600 text-red-600 hover:text-white py-2 rounded-lg font-bold text-xs transition uppercase border border-red-200 flex items-center justify-center gap-1.5 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        <span>Hapus PC</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-gray-50 rounded-xl border-2 border-dashed">
                        <p class="text-gray-400 italic">Belum ada PC yang terdeteksi.</p>
                        <div class="mt-4 flex justify-center gap-3">
                            <button v-if="isAdmin" @click="openCreatePcModal" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-sm cursor-pointer flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Buat Pangkalan Backup Mandiri (Atur Kuota GB)</span>
                            </button>
                            <button v-else @click="checkAksesStatus" class="bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-xs font-bold hover:bg-blue-200 transition">
                                Ajukan Otoritas Akses
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold mb-4 text-green-800 border-b pb-2 uppercase"> Penyimpanan Publik (Anggota)</h3>
                    <div v-if="publicPcs.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="pc in publicPcs" :key="pc.id" class="border rounded-lg p-4 hover:border-green-500 transition-all bg-white shadow-sm relative">
                            <div class="flex justify-between items-start mb-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase">{{ pc.pc_name }}</p>
                                <span v-if="isAdmin" class="text-[9px] bg-emerald-50 border border-emerald-200 text-emerald-700 px-1.5 py-0.5 rounded font-bold">{{ pc.quota_human }}</span>
                            </div>
                            <p class="font-bold text-gray-800 leading-tight">{{ pc.user.name }}</p>
                            <p class="text-[10px] text-gray-500 mb-3">{{ pc.user.pangkat }} / {{ pc.user.nrp }}</p>
                            
                            <div class="space-y-1.5">
                                <button @click="openPcStorage(pc.id)" class="block w-full text-center bg-gray-50 hover:bg-green-600 hover:text-white text-gray-600 py-2 rounded font-bold text-[10px] transition uppercase border cursor-pointer"> Buka Penyimpanan
                                </button>
                                <div v-if="isAdmin" class="grid grid-cols-2 gap-1.5">
                                    <button @click="openEditQuotaModal(pc)" class="block w-full text-center bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white py-1.5 rounded font-bold text-[10px] transition uppercase border border-blue-200 flex items-center justify-center gap-1 cursor-pointer" title="Sesuaikan Kuota GB">
                                        <span>⚙️ Kuota</span>
                                    </button>
                                    <button @click="revokePcAccess(pc)" class="block w-full text-center bg-red-50 hover:bg-red-600 text-red-600 hover:text-white py-1.5 rounded font-bold text-[10px] transition uppercase border border-red-200 flex items-center justify-center gap-1 cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        <span>Hapus</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-center text-gray-400 py-4 text-sm italic"> Belum ada personel lain dalam sistem.</p>
                </div>

                <div v-if="isAdmin" class="bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg p-6 text-white border-b-8 border-red-600">
                    <h3 class="text-lg font-bold mb-4 text-red-400 border-b border-slate-700 pb-2 uppercase flex items-center gap-2">
                        <span> Keamanan IP Kantor</span>
                    </h3>
                    <form @submit.prevent="submitNetwork" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] uppercase text-slate-500 ml-1 font-black">Nama Lokasi</label>
                            <input v-model="networkForm.location_name" placeholder="Contoh: Markas Pusat" class="rounded bg-slate-800 border-slate-700 text-white text-sm focus:ring-red-500" required />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] uppercase text-slate-500 ml-1 font-black">Alamat IP / IPv6</label>
                            <input v-model="networkForm.ip_address" placeholder="Contoh: 114.xxx atau 2001:xxx" class="rounded bg-slate-800 border-slate-700 text-white text-sm font-mono focus:ring-red-500" required />
                        </div>
                        <div class="flex flex-col justify-end">
                            <button type="submit" :disabled="networkForm.processing" class="bg-red-600 hover:bg-red-700 text-white font-bold rounded py-2 transition shadow-lg text-sm uppercase">
                                {{ networkForm.processing ? 'Memproses...' : ' Tambah Radar' }}
                            </button>
                        </div>
                    </form>
                    <div class="overflow-x-auto rounded border border-slate-800">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-800 text-slate-400 uppercase text-[10px]">
                                <tr>
                                    <th class="p-4">Keamanan</th>
                                    <th class="p-4 font-mono text-center">Titik Koordinat IP</th>
                                    <th class="p-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="net in networks" :key="net.id" class="border-b border-slate-800 hover:bg-slate-800/50 transition">
                                    <td class="p-4 font-bold text-slate-300">{{ net.location_name }}</td>
                                    <td class="p-4 text-blue-400 font-mono text-center">{{ net.ip_address }}</td>
                                    <td class="p-4 text-right">
                                        <button @click="deleteNetwork(net.id)" class="text-red-500 hover:text-red-400 font-bold uppercase text-[10px] border border-red-500/30 px-2 py-1 rounded">Hapus</button>
                                    </td>
                                </tr>
                                <tr v-if="networks.length === 0">
                                    <td colspan="3" class="p-10 text-center text-slate-600 italic">IP belum dikonfigurasi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL ADMIN: BUAT PANGKALAN PC BARU -->
        <Teleport to="body">
            <div v-if="isCreatePcModalOpen" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Otoritas Administrator</span>
                            <h3 class="text-base font-extrabold text-slate-900">Buat Pangkalan Backup Baru</h3>
                        </div>
                        <button @click="isCreatePcModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitCreatePc" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nama Pangkalan / PC *</label>
                            <input 
                                type="text" 
                                v-model="createPcForm.pc_name" 
                                required
                                placeholder="Contoh: PC Admin Utama, Arsip Komando, dsb."
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Pemilik Pangkalan (Personel / Admin) *</label>
                            <select 
                                v-model="createPcForm.user_id" 
                                required
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="" disabled>-- Pilih Pemilik Pangkalan --</option>
                                <option v-for="u in allUsers" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.pangkat ? u.pangkat + ' - ' : '' }}{{ u.nrp || 'Staff' }})
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Jatah Batas Kuota (Dalam Gigabyte / GB) *</label>
                            <div class="relative">
                                <input 
                                    type="number" 
                                    v-model.number="createPcForm.quota_gb" 
                                    required
                                    min="1"
                                    max="100000"
                                    class="w-full text-sm font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500 pr-12"
                                />
                                <span class="absolute right-3.5 top-3 text-xs font-bold text-slate-400">GB</span>
                            </div>
                            <!-- Quick GB Pills -->
                            <div class="flex items-center gap-1.5 pt-1 flex-wrap">
                                <span class="text-[10px] text-slate-400 font-bold">Pilihan Cepat:</span>
                                <button type="button" @click="createPcForm.quota_gb = 50" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">50 GB</button>
                                <button type="button" @click="createPcForm.quota_gb = 100" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">100 GB</button>
                                <button type="button" @click="createPcForm.quota_gb = 200" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">200 GB</button>
                                <button type="button" @click="createPcForm.quota_gb = 500" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">500 GB</button>
                                <button type="button" @click="createPcForm.quota_gb = 1000" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">1 TB (1000 GB)</button>
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isCreatePcModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="createPcForm.processing"
                                class="px-6 py-2.5 bg-blue-700 hover:bg-blue-800 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-700/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ createPcForm.processing ? 'Membangun...' : 'Bangun Pangkalan PC' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- MODAL ADMIN: SESUAIKAN KUOTA PC -->
        <Teleport to="body">
            <div v-if="isEditQuotaModalOpen && activePcForQuota" class="fixed inset-0 z-[160] bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                        <div>
                            <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Otoritas Kuota</span>
                            <h3 class="text-base font-extrabold text-slate-900">Sesuaikan Kuota Penyimpanan</h3>
                        </div>
                        <button @click="isEditQuotaModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitEditQuota" class="p-5 sm:p-6 space-y-4 text-xs font-semibold">
                        <div class="p-3 bg-slate-50 border border-slate-200/70 rounded-2xl space-y-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Pangkalan PC Terpilih:</p>
                            <p class="font-extrabold text-slate-800 text-sm">{{ activePcForQuota.pc_name }}</p>
                            <div class="flex justify-between text-[11px] font-mono pt-1 text-slate-500">
                                <span>Terpakai Saat Ini:</span>
                                <span class="font-bold text-slate-700">{{ activePcForQuota.usage_human }}</span>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Kapasitas Baru (Dalam GB) *</label>
                            <div class="relative">
                                <input 
                                    type="number" 
                                    v-model.number="editQuotaForm.quota_gb" 
                                    required
                                    min="1"
                                    max="100000"
                                    class="w-full text-sm font-mono font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500 pr-12"
                                />
                                <span class="absolute right-3.5 top-3 text-xs font-bold text-slate-400">GB</span>
                            </div>
                            <!-- Quick GB Pills -->
                            <div class="flex items-center gap-1.5 pt-1 flex-wrap">
                                <span class="text-[10px] text-slate-400 font-bold">Pilihan Cepat:</span>
                                <button type="button" @click="editQuotaForm.quota_gb = 100" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">100 GB</button>
                                <button type="button" @click="editQuotaForm.quota_gb = 200" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">200 GB</button>
                                <button type="button" @click="editQuotaForm.quota_gb = 500" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">500 GB</button>
                                <button type="button" @click="editQuotaForm.quota_gb = 1000" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">1 TB</button>
                                <button type="button" @click="editQuotaForm.quota_gb = 2000" class="px-2 py-0.5 rounded-lg border text-[10px] font-bold bg-slate-100 hover:bg-blue-100 text-slate-700">2 TB</button>
                            </div>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isEditQuotaModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="editQuotaForm.processing"
                                class="px-6 py-2.5 bg-blue-700 hover:bg-blue-800 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-700/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ editQuotaForm.processing ? 'Menyimpan...' : 'Simpan Kuota Baru' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Transisi halus untuk Progress Bar */
.transition-all {
    transition: width 1.5s ease-in-out;
}
</style>