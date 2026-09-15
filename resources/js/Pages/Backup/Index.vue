<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
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

// --- OPERASI ADMIN: MONITORING REALTIME LOG AKSES SHARE FOLDER ---
const isShareLogsModalOpen = ref(false);
const shareLogs = ref([]);
const shareLogsStats = ref({
    total_accessors: 0,
    total_personel: 0,
    total_tamu: 0,
    total_hits: 0,
});
const isLoadingShareLogs = ref(false);
const isRealtimeActive = ref(true);
const shareLogsSearch = ref('');
const shareLogsTypeFilter = ref('all'); // 'all', 'personel', 'tamu'
const lastUpdatedTime = ref('');
let shareLogsPollTimer = null;

const fetchShareLogs = async (isBackground = false) => {
    if (!isBackground) {
        isLoadingShareLogs.value = true;
    }
    try {
        const response = await axios.get(route('admin.backup.share-access-logs'));
        if (response.data && response.data.status === 'success') {
            shareLogs.value = response.data.logs || [];
            shareLogsStats.value = response.data.stats || {
                total_accessors: 0,
                total_personel: 0,
                total_tamu: 0,
                total_hits: 0,
            };
            lastUpdatedTime.value = response.data.server_time || new Date().toLocaleTimeString();
        }
    } catch (err) {
        console.error('Gagal memuat log akses share folder:', err);
    } finally {
        if (!isBackground) {
            isLoadingShareLogs.value = false;
        }
    }
};

const startRealtimePolling = () => {
    stopRealtimePolling();
    isRealtimeActive.value = true;
    shareLogsPollTimer = setInterval(() => {
        if (isShareLogsModalOpen.value && isRealtimeActive.value) {
            fetchShareLogs(true);
        }
    }, 3000);
};

const stopRealtimePolling = () => {
    if (shareLogsPollTimer) {
        clearInterval(shareLogsPollTimer);
        shareLogsPollTimer = null;
    }
};

const toggleRealtime = () => {
    isRealtimeActive.value = !isRealtimeActive.value;
    if (isRealtimeActive.value) {
        fetchShareLogs(true);
    }
};

const openShareLogsModal = () => {
    isShareLogsModalOpen.value = true;
    fetchShareLogs(false);
    startRealtimePolling();
};

const closeShareLogsModal = () => {
    isShareLogsModalOpen.value = false;
    stopRealtimePolling();
};

const filteredShareLogs = computed(() => {
    return shareLogs.value.filter(item => {
        // Filter tipe
        if (shareLogsTypeFilter.value !== 'all' && item.access_type !== shareLogsTypeFilter.value) {
            return false;
        }
        // Filter search
        if (shareLogsSearch.value.trim()) {
            const q = shareLogsSearch.value.toLowerCase().trim();
            const matchName = item.nama?.toLowerCase().includes(q);
            const matchPangkat = item.pangkat?.toLowerCase().includes(q);
            const matchNrp = item.nrp?.toLowerCase().includes(q);
            const matchSatuan = item.satuan?.toLowerCase().includes(q);
            const matchIp = item.ip_address?.toLowerCase().includes(q);
            const matchFolder = item.share_name?.toLowerCase().includes(q);
            const matchPc = item.pc_name?.toLowerCase().includes(q);
            return matchName || matchPangkat || matchNrp || matchSatuan || matchIp || matchFolder || matchPc;
        }
        return true;
    });
});

const copyToClipboard = (text, label = 'Data') => {
    if (!text || text === '-') return;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `${label} berhasil disalin!`,
            showConfirmButton: false,
            timer: 1500,
        });
    });
};

onMounted(() => {
    checkAksesStatus();
});

onUnmounted(() => {
    stopRealtimePolling();
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
                        <div v-if="isAdmin" class="flex items-center gap-2.5 flex-wrap">
                            <button 
                                @click="openShareLogsModal" 
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-sm flex items-center gap-2 cursor-pointer border border-indigo-500 hover:shadow-md"
                                title="Pantau siapa saja yang mengakses folder berbagi secara realtime">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                                </span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Cek Akses Share Folder</span>
                            </button>

                            <button @click="openCreatePcModal" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-sm flex items-center gap-2 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                <span>Buat Pangkalan Backup Baru</span>
                            </button>
                        </div>
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

        <!-- MODAL CEK AKSES SHARE FOLDER (KHUSUS ADMIN - REALTIME) -->
        <Teleport to="body">
            <div v-if="isShareLogsModalOpen" class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-3 sm:p-5 animate-fade-in overflow-y-auto">
                <div class="bg-white w-full max-w-5xl rounded-3xl shadow-2xl overflow-hidden border border-slate-200 my-auto max-h-[92vh] flex flex-col">
                    
                    <!-- Modal Header -->
                    <div class="p-4 sm:p-6 bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-900 text-white flex justify-between items-center shrink-0 border-b border-indigo-800/50">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <div class="w-8 h-8 rounded-xl bg-indigo-500/30 border border-indigo-400/40 flex items-center justify-center text-indigo-300">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <h3 class="text-base sm:text-lg font-black tracking-tight uppercase">Monitoring Akses Share Folder</h3>
                                
                                <!-- Realtime Live Badge Toggle -->
                                <button 
                                    type="button" 
                                    @click="toggleRealtime" 
                                    :class="isRealtimeActive ? 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40' : 'bg-slate-700/60 text-slate-300 border-slate-600'" 
                                    class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border flex items-center gap-1.5 transition cursor-pointer"
                                    :title="isRealtimeActive ? 'Klik untuk jeda pembaruan realtime' : 'Klik untuk mengaktifkan pembaruan realtime'">
                                    <span class="relative flex h-2 w-2">
                                        <span v-if="isRealtimeActive" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span :class="isRealtimeActive ? 'bg-emerald-400' : 'bg-slate-400'" class="relative inline-flex rounded-full h-2 w-2"></span>
                                    </span>
                                    <span>{{ isRealtimeActive ? 'Realtime (3s)' : 'Jeda' }}</span>
                                </button>
                            </div>
                            <p class="text-xs text-indigo-200/80 font-medium">
                                Memantau seluruh personel & pengunjung tamu yang mengakses folder berbagi secara langsung.
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button 
                                type="button" 
                                @click="fetchShareLogs(false)" 
                                :disabled="isLoadingShareLogs"
                                class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs transition cursor-pointer flex items-center gap-1" 
                                title="Segarkan data sekarang">
                                <svg :class="isLoadingShareLogs ? 'animate-spin' : ''" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                            <button 
                                type="button" 
                                @click="closeShareLogsModal" 
                                class="text-white/70 hover:text-white text-2xl font-bold p-1 leading-none transition cursor-pointer">
                                &times;
                            </button>
                        </div>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1 bg-slate-50/50">
                        
                        <!-- Stat Cards Row -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Pengakses</p>
                                <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">{{ shareLogsStats.total_accessors }} <span class="text-xs font-semibold text-slate-500 font-normal">Orang</span></p>
                            </div>
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs">
                                <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider">Total Frekuensi Akses</p>
                                <p class="text-xl sm:text-2xl font-black text-indigo-900 mt-0.5">{{ shareLogsStats.total_hits }} <span class="text-xs font-semibold text-indigo-600 font-normal">Kali</span></p>
                            </div>
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs">
                                <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider">Personel Internal</p>
                                <p class="text-xl sm:text-2xl font-black text-blue-900 mt-0.5">{{ shareLogsStats.total_personel }} <span class="text-xs font-semibold text-blue-600 font-normal">Akun</span></p>
                            </div>
                            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-2xs">
                                <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Pengunjung Tamu</p>
                                <p class="text-xl sm:text-2xl font-black text-amber-900 mt-0.5">{{ shareLogsStats.total_tamu }} <span class="text-xs font-semibold text-amber-600 font-normal">Tamu</span></p>
                            </div>
                        </div>

                        <!-- Filter & Search Toolbar -->
                        <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-2xs flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <!-- Search Input -->
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </span>
                                <input 
                                    v-model="shareLogsSearch" 
                                    type="text" 
                                    placeholder="Cari Pangkat, Nama, NRP, Satuan, IP, atau Folder..." 
                                    class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:border-indigo-600 focus:outline-none transition"
                                />
                            </div>

                            <!-- Type Filter Pills -->
                            <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 shrink-0">
                                <button 
                                    type="button" 
                                    @click="shareLogsTypeFilter = 'all'" 
                                    :class="shareLogsTypeFilter === 'all' ? 'bg-white text-slate-900 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800 font-bold'" 
                                    class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer">
                                    Semua ({{ shareLogs.length }})
                                </button>
                                <button 
                                    type="button" 
                                    @click="shareLogsTypeFilter = 'personel'" 
                                    :class="shareLogsTypeFilter === 'personel' ? 'bg-white text-indigo-700 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800 font-bold'" 
                                    class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1">
                                    <span>🔒 Personel</span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="shareLogsTypeFilter = 'tamu'" 
                                    :class="shareLogsTypeFilter === 'tamu' ? 'bg-white text-amber-700 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-800 font-bold'" 
                                    class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer flex items-center gap-1">
                                    <span>👥 Tamu</span>
                                </button>
                            </div>
                        </div>

                        <!-- Table Content -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
                            <div v-if="isLoadingShareLogs && shareLogs.length === 0" class="p-12 text-center text-slate-400 space-y-2">
                                <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
                                <p class="text-xs font-bold">Memuat log akses folder berbagi...</p>
                            </div>

                            <div v-else-if="filteredShareLogs.length === 0" class="p-12 text-center text-slate-400 space-y-2">
                                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-2xl text-slate-400">
                                    🔍
                                </div>
                                <p class="text-xs font-bold text-slate-600">Tidak ada data akses yang sesuai filter.</p>
                                <p class="text-[11px] text-slate-400">Belum ada aktivitas akses atau kata kunci pencarian tidak ditemukan.</p>
                            </div>

                            <!-- Responsive Table -->
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-black tracking-wider">
                                            <th class="py-3 px-4">Pengakses (Pangkat / Nama / NRP)</th>
                                            <th class="py-3 px-3">Tipe</th>
                                            <th class="py-3 px-3">Folder Yang Diakses</th>
                                            <th class="py-3 px-3">Pukul Berapa Mengakses</th>
                                            <th class="py-3 px-3">Alamat IP</th>
                                            <th class="py-3 px-3 text-center">Frekuensi</th>
                                            <th class="py-3 px-3">Status Sesi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="log in filteredShareLogs" :key="log.id" class="hover:bg-slate-50/70 transition">
                                            
                                            <!-- Pengakses (Pangkat, Nama, NRP, Satuan, WhatsApp) -->
                                            <td class="py-3 px-4">
                                                <div class="space-y-0.5">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span v-if="log.pangkat && log.pangkat !== '-'" class="px-1.5 py-0.5 bg-indigo-50 border border-indigo-200 text-indigo-800 text-[10px] font-extrabold rounded-md uppercase">
                                                            {{ log.pangkat }}
                                                        </span>
                                                        <span class="font-black text-slate-900 uppercase text-xs">{{ log.nama }}</span>
                                                    </div>
                                                    <div class="text-[11px] text-slate-500 font-semibold flex items-center gap-2 flex-wrap">
                                                        <span>NRP/NIP: <strong class="text-slate-700 font-mono">{{ log.nrp }}</strong></span>
                                                        <span>&bull;</span>
                                                        <span>{{ log.satuan }}</span>
                                                    </div>
                                                    <div v-if="log.whatsapp" class="text-[10.5px] text-emerald-700 font-bold flex items-center gap-1">
                                                        <span>📱 WA:</span>
                                                        <a :href="'https://wa.me/' + log.whatsapp" target="_blank" class="hover:underline font-mono">{{ log.whatsapp }}</a>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Tipe Akses -->
                                            <td class="py-3 px-3 whitespace-nowrap">
                                                <span v-if="log.access_type === 'personel'" class="px-2 py-1 bg-blue-100 text-blue-800 border border-blue-200 rounded-lg text-[10px] font-black uppercase flex items-center gap-1 w-fit">
                                                    <span>🔒 Personel</span>
                                                </span>
                                                <span v-else class="px-2 py-1 bg-amber-100 text-amber-900 border border-amber-300 rounded-lg text-[10px] font-black uppercase flex items-center gap-1 w-fit">
                                                    <span>👥 Tamu Luar</span>
                                                </span>
                                            </td>

                                            <!-- Folder yang Diakses -->
                                            <td class="py-3 px-3">
                                                <div class="space-y-0.5 max-w-[200px]">
                                                    <p class="font-extrabold text-slate-800 uppercase truncate" :title="log.share_name">
                                                        📁 {{ log.share_name }}
                                                    </p>
                                                    <p class="text-[10px] text-slate-400 font-semibold truncate">
                                                        PC: {{ log.pc_name }}
                                                    </p>
                                                </div>
                                            </td>

                                            <!-- Pukul Berapa Mengakses (Timestamp) -->
                                            <td class="py-3 px-3 whitespace-nowrap">
                                                <div class="space-y-0.5">
                                                    <p class="font-black text-slate-900 text-xs flex items-center gap-1 font-mono">
                                                        <span>⏱️</span>
                                                        <span>{{ log.last_accessed_time }}</span>
                                                    </p>
                                                    <p class="text-[10px] text-slate-400 font-semibold">
                                                        {{ log.last_accessed_date }} ({{ log.time_ago }})
                                                    </p>
                                                </div>
                                            </td>

                                            <!-- Alamat IP -->
                                            <td class="py-3 px-3 whitespace-nowrap">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2 py-1 rounded-md border border-slate-200">
                                                        {{ log.ip_address }}
                                                    </span>
                                                    <button 
                                                        type="button" 
                                                        @click="copyToClipboard(log.ip_address, 'IP Address')" 
                                                        class="text-slate-400 hover:text-slate-700 p-1 rounded transition cursor-pointer" 
                                                        title="Salin IP">
                                                        📋
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- Berapa Kali Akses -->
                                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-black bg-indigo-50 border border-indigo-200 text-indigo-800 shadow-2xs">
                                                    {{ log.access_count }}x Akses
                                                </span>
                                            </td>

                                            <!-- Status Sesi -->
                                            <td class="py-3 px-3 whitespace-nowrap">
                                                <div v-if="log.access_type === 'tamu'">
                                                    <span v-if="log.is_expired" class="px-2 py-0.5 bg-rose-100 text-rose-700 rounded-md text-[10px] font-bold">
                                                        Kadaluarsa
                                                    </span>
                                                    <span v-else class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md text-[10px] font-bold">
                                                        Sesi Aktif
                                                    </span>
                                                    <p v-if="log.expires_at" class="text-[9px] text-slate-400 mt-0.5">
                                                        s/d {{ log.expires_at }}
                                                    </p>
                                                </div>
                                                <div v-else>
                                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md text-[10px] font-bold">
                                                        Terdaftar
                                                    </span>
                                                </div>
                                            </td>

                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 bg-white border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-2 shrink-0 text-xs text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" :class="isRealtimeActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                            <span>Status: <strong>{{ isRealtimeActive ? 'Pembaruan Realtime Aktif (Tiap 3 Detik)' : 'Jeda Pembaruan' }}</strong></span>
                            <span v-if="lastUpdatedTime">&bull; Terakhir dicek: <strong>{{ lastUpdatedTime }}</strong></span>
                        </div>
                        <button 
                            type="button" 
                            @click="closeShareLogsModal" 
                            class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs uppercase tracking-wider transition cursor-pointer">
                            Tutup
                        </button>
                    </div>
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