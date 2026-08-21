<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    myPcs: Array,
    publicPcs: Array,
    networks: Array,
    isAdmin: Boolean,
    globalStats: Object,
    authRequest: Object,       // Data pengajuan user yang sedang login
    pendingRequests: Array     // Daftar pengajuan untuk Admin
});

const networkForm = useForm({
    location_name: '',
    ip_address: '',
});

// --- LOGIKA OTORITAS AKSES (SWEETALERT) ---
// --- FUNGSI PEMERIKSAAN OTORITAS SEBELUM BUKA PC ---
const openPcStorage = (pcId) => {
    if (props.myPcs.length === 0 && !props.isAdmin) {
        checkAksesStatus();
        return;
    }

    router.visit(route('backup.explore', pcId));
};

const checkAksesStatus = () => {
    // Jika personel belum punya PC sama sekali dan bukan Admin bypass
    if (props.myPcs.length === 0 && !props.isAdmin) {
        
        // 1. Jika belum pernah mengajukan akses
        if (!props.authRequest) {
            Swal.fire({
                title: 'Akses Ditolak',
                text: "Anda belum memiliki pangkalan penyimpanan. Ajukan akses ke Komandan sekarang?",
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
    if (!checkMobileDevice()) {
        checkAksesStatus();
    }
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
                    <h3 class="text-lg font-bold mb-4 text-blue-800 border-b pb-2 uppercase flex items-center gap-2">
                        <span> Penyimpanan PC Saya</span>
                    </h3>
                    <div v-if="myPcs.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="pc in myPcs" :key="pc.id" class="border rounded-xl p-5 bg-slate-50 relative overflow-hidden group hover:shadow-lg transition">
                            <div class="absolute top-0 right-0 p-2">
                                <span class="text-[10px] bg-blue-200 text-blue-800 px-2 py-0.5 rounded-full font-bold uppercase">{{ pc.hardware_id }}</span>
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

                            <Link :href="route('backup.explore', pc.id)" class="mt-6 block w-full text-center bg-blue-700 hover:bg-blue-800 text-white py-3 rounded-lg font-bold transition shadow-md uppercase text-sm"> MASUK DAFTAR PC
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-gray-50 rounded-xl border-2 border-dashed">
                        <p class="text-gray-400 italic">Belum ada PC yang terdeteksi.</p>
                        <button @click="checkAksesStatus" class="mt-4 bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-xs font-bold hover:bg-blue-200 transition"> Ajukan Otoritas Akses
                        </button>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-bold mb-4 text-green-800 border-b pb-2 uppercase"> Penyimpanan Publik (Anggota)</h3>
                    <div v-if="publicPcs.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="pc in publicPcs" :key="pc.id" class="border rounded-lg p-4 hover:border-green-500 transition-all bg-white shadow-sm">
                            <p class="text-[9px] font-bold text-gray-400 uppercase mb-1">{{ pc.pc_name }}</p>
                            <p class="font-bold text-gray-800 leading-tight">{{ pc.user.name }}</p>
                            <p class="text-[10px] text-gray-500 mb-3">{{ pc.user.pangkat }} / {{ pc.user.nrp }}</p>
                            
                            <button @click="openPcStorage(pc.id)" class="block w-full text-center bg-gray-50 hover:bg-green-600 hover:text-white text-gray-600 py-2 rounded font-bold text-[10px] transition uppercase border"> Buka Penyimpanan
                            </button>
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
    </AuthenticatedLayout>
</template>

<style scoped>
/* Transisi halus untuk Progress Bar */
.transition-all {
    transition: width 1.5s ease-in-out;
}
</style>