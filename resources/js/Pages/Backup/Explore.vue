<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3'; 
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'; 
import Swal from 'sweetalert2';
import axios from 'axios'; 

const props = defineProps({
    pc: Object,
    contents: Array,        
    currentFolderId: Number, 
    breadcrumbs: Array,
    searchQuery: String 
});

// --- FORMULIR TAKTIS ---
const uploadForm = useForm({
    pc_id: props.pc.id,
    file: null,
    parent_id: props.currentFolderId 
});

const folderForm = useForm({
    pc_id: props.pc.id,
    folder_name: '',
    parent_id: props.currentFolderId
});

// --- STATE RADAR & MENU ---
const searchQuery = ref(props.searchQuery || ''); 
const contextMenu = ref({ show: false, x: 0, y: 0, item: null });
const previewUrl = ref(null);
const previewType = ref(null);

// --- STATE PROGRES TRANSMISI UPLOAD ---
const uploadProgress = ref(0);
const uploadSpeed = ref('');
const isUploading = ref(false);
let startTime = 0;

// --- STATE RADAR EKSTRAKSI ---
const isExtracting = ref(false);
const extractProgress = ref(0);
const extractLog = ref('Menginisialisasi ...'); 
const extractionDestination = ref('');
const activeExtractItem = ref(null);
let radarInterval = null;

// --- LOGIKA NAVIGASI BACK ---
const parentFolderId = computed(() => {
    if (props.breadcrumbs && props.breadcrumbs.length > 0) {
        const currentIndex = props.breadcrumbs.findIndex(b => b.id === props.currentFolderId);
        if (currentIndex > 0) {
            return props.breadcrumbs[currentIndex - 1].id;
        }
    }
    return null; 
});

// --- PROTOKOL PEMANCARAN RADAR GLOBAL ---
watch(searchQuery, (value) => {
    router.get(route('backup.explore', props.pc.id), { 
        search: value,
        folder: value ? null : props.currentFolderId 
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
});

const filteredContents = computed(() => {
    if (!searchQuery.value) return props.contents;
    return props.contents.filter(item => item.file_name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// --- LOGIKA FILE & FOLDER ---
const handleFileUpload = (event) => {
    uploadForm.file = event.target.files[0];
};

const submitUpload = () => {
    if(!uploadForm.file) return Swal.fire('Error', 'Pilih berkas terlebih dahulu!', 'error');
    
    isUploading.value = true;
    uploadProgress.value = 0;
    startTime = Date.now();

    uploadForm.post(route('backup.store'), {
        forceFormData: true,
        onProgress: (progress) => {
            uploadProgress.value = progress.percentage;
            const duration = (Date.now() - startTime) / 1000; 
            if (duration > 0) {
                const bps = progress.loaded / duration; 
                if (bps > 1024 * 1024) {
                    uploadSpeed.value = (bps / (1024 * 1024)).toFixed(2) + ' MB/s';
                } else {
                    uploadSpeed.value = (bps / 1024).toFixed(2) + ' KB/s';
                }
            }
        },
        onSuccess: () => {
            isUploading.value = false;
            uploadProgress.value = 0;
            uploadForm.reset('file');
            document.getElementById('file-input').value = "";
            Swal.fire('Berhasil!', 'Berkas berhasil di unggah.', 'success');
        },
        onError: (err) => {
            isUploading.value = false;
            uploadProgress.value = 0;
            Swal.fire('Gagal', Object.values(err)[0], 'error');
        }
    });
};

// --- LOGIKA EKSTRAKSI CERDAS ---
const handleExtract = (item) => {
    activeExtractItem.value = item;
    extractionDestination.value = 'EXTRACTED_' + item.file_name.split('.')[0].toUpperCase();
    
    Swal.fire({
        title: 'Konfigurasi Ekstraksi',
        html: `
            <div class="text-left">
                <label class="text-[10px] font-black uppercase text-gray-500">Nama Folder Tujuan:</label>
                <input id="swal-destination" class="swal2-input !m-0 !w-full !text-sm font-bold uppercase" value="${extractionDestination.value}">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Mulai Bongkar Muatan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            return document.getElementById('swal-destination').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            startExtractionRadar(item, result.value);
        }
    });
};

const startExtractionRadar = async (item, destName) => {
    isExtracting.value = true;
    extractProgress.value = 0;
    extractLog.value = 'Menghubungkan ke Server...';

    radarInterval = setInterval(async () => {
        try {
            const res = await axios.get(route('backup.extract-progress'));
            extractProgress.value = res.data.progress;
            extractLog.value = res.data.log || 'Sedang memproses...'; 
        } catch (e) {
            console.error("Radar gangguan...");
        }
    }, 1000);

    try {
        const response = await axios.post(route('backup.start-extract', item.id), {
            destination: destName
        });

        if (response.data.status === 'success') {
            Swal.fire('Sukses', response.data.message, 'success').then(() => {
                window.location.reload();
            });
        } else if (response.data.status === 'cancelled') {
            Swal.fire('Informasi', 'Operasi dihentikan paksa oleh personel.', 'info');
        }
    } catch (error) {
        Swal.fire('Gagal', 'Terjadi sabotase sistem atau timeout pada file raksasa.', 'error');
    } finally {
        stopRadar();
    }
};

const cancelExtraction = async () => {
    try {
        await axios.post(route('backup.cancel-extract'));
        Swal.fire('Sinyal Dikirim', 'Perintah pembatalan sedang diproses server...', 'warning');
    } catch (e) {
        stopRadar();
    }
};

const stopRadar = () => {
    isExtracting.value = false;
    clearInterval(radarInterval);
    radarInterval = null;
};

// --- LOGIKA DASAR EXPLORER ---
const createFolder = () => {
    Swal.fire({
        title: 'Buat Folder Baru',
        input: 'text',
        inputLabel: 'Nama Folder ',
        showCancelButton: true,
        confirmButtonText: 'Tambahkan Folder ?',
        preConfirm: (name) => {
            if (!name) return Swal.showValidationMessage('Nama folder wajib diisi!');
            folderForm.folder_name = name;
            folderForm.post(route('backup.create-folder'), {
                onSuccess: () => Swal.fire('Berhasil', 'Folder baru telah dibuat.', 'success')
            });
        }
    });
};

const deleteItem = (item) => {
    Swal.fire({
        title: 'Hapus ?',
        text: `Apakah anda yakin ingin menghapus ${item.file_name}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya'
    }).then((result) => {
        if (result.isConfirmed) {
            useForm({}).delete(route('backup.destroy-backup', item.id));
        }
    });
};

const openContextMenu = (e, item) => {
    e.preventDefault();
    contextMenu.value = {
        show: true,
        x: e.clientX,
        y: e.clientY,
        item: item
    };
};

const closeContextMenu = () => {
    contextMenu.value.show = false;
};

const handleRename = (item) => {
    Swal.fire({
        title: 'Ubah Nama',
        input: 'text',
        inputValue: item.file_name,
        showCancelButton: true,
        preConfirm: (newName) => {
            if (!newName) return Swal.showValidationMessage('Nama baru wajib diisi!');
            useForm({ new_name: newName }).post(route('backup.rename', item.id));
        }
    });
};

const showProperties = (item) => {
    Swal.fire({
        title: 'Informasi Berkas',
        html: `
            <div class="text-left text-xs font-mono p-2 bg-slate-100 rounded border">
                <p class="mb-1"><b>NAMA:</b> ${item.file_name}</p>
                <p class="mb-1"><b>TIPE:</b> ${item.is_folder ? 'FOLDER STRATEGIS' : item.file_type.toUpperCase()}</p>
                <p class="mb-1"><b>UKURAN:</b> ${item.size_human}</p> 
                <p class="mb-1"><b>MODIFIKASI:</b> ${item.date_human}</p>
                <p><b>ID PC:</b> ${props.pc.hardware_id}</p>
            </div>
        `,
        icon: 'info'
    });
};

const openPreview = (item) => {
    if (item.is_folder) return; 
    const ext = item.file_type.toLowerCase();
    const officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        previewType.value = 'image';
        previewUrl.value = item.preview_url;
    } else if (ext === 'pdf') {
        previewType.value = 'pdf';
        previewUrl.value = item.preview_url;
    } else if (officeExts.includes(ext)) {
        previewType.value = 'office';
        previewUrl.value = route('backup.view-office', item.id);
    } else {
        return window.location.href = route('backup.download', item.id);
    }
};

const closePreview = () => {
    previewUrl.value = null;
};

// --- PROTOKOL DETEKSI PERANGKAT SELULER (HP) ---
const checkMobileDevice = () => {
    const isMobile = window.innerWidth < 1024 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    if (isMobile) {
        Swal.fire({
            title: 'Akses Terbatas',
            text: 'Modul Explorer Backup memerlukan layar standar Komputer / PC. Silakan buka halaman ini melalui Komputer atau Perangkat Desktop.',
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Kembali ke Dashboard',
            allowOutsideClick: false,
            allowEscapeKey: false,
        }).then(() => {
            router.visit(route('dashboard'));
        });
        return true;
    }
    return false;
};

onMounted(() => {
    checkMobileDevice();
    window.addEventListener('click', closeContextMenu);
});

onUnmounted(() => {
    window.removeEventListener('click', closeContextMenu);
});
</script>

<template>
    <Head :title="'Explorer - ' + pc.pc_name" />

    <AuthenticatedLayout>
        <div class="space-y-6 font-sans" @contextmenu.prevent="">
            
            <!-- Page Header Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4">
                    <Link v-if="currentFolderId" 
                          :href="route('backup.explore', { id: pc.id, folder: parentFolderId })"class="bg-slate-100 hover:bg-blue-600 hover:text-white text-slate-800 p-2.5 rounded-2xl transition shadow-xs flex items-center justify-center w-11 h-11"title="Kembali Mundur">
                        <span class="font-black text-lg"></span>
                    </Link>

                    <div>
                        <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight"> Explorer: {{ pc.pc_name }}
                        </h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Sistem Manajemen File & Penyimpanan Cadangan Logistik SINDEN</p>
                    </div>
                </div>
                
                <Link :href="route('backup.index')" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm transition tracking-wider"> Kembali ke Radar
                </Link>
            </div>

            <!-- Deep Scan Search Bar & Breadcrumbs -->
            <div class="bg-white p-4 rounded-3xl shadow-xs border border-[#E2E8F0] space-y-4">
                <div class="relative">
                    <input v-model="searchQuery" type="text" placeholder="Cari Berkas (Deep Scan Sub-Folder)..."class="w-full rounded-2xl border-slate-200 bg-slate-50 font-bold text-xs py-3.5 pl-10 focus:ring-blue-500 focus:border-blue-600 placeholder:text-slate-400" />
                    <span class="absolute left-3.5 top-3 text-sm text-slate-400"></span>
                </div>

                <div v-if="searchQuery" class="px-4 py-2.5 bg-blue-50 border-l-4 border-blue-600 text-blue-700 text-[10px] font-extrabold uppercase rounded-r-xl flex items-center gap-2 animate-pulse">
                    <span></span> Menampilkan hasil pencarian di seluruh Folder...
                </div>

                <nav class="flex bg-slate-50 px-5 py-3 rounded-2xl border border-slate-200">
                    <ol class="flex items-center space-x-2 text-[10px] font-extrabold uppercase tracking-wider">
                        <li>
                            <Link :href="route('backup.explore', { id: pc.id })" class="text-blue-600 hover:underline">HOME</Link>
                        </li>
                        <li v-for="crumb in breadcrumbs" :key="crumb.id" class="flex items-center space-x-2">
                            <span class="text-gray-400">/</span>
                            <Link :href="route('backup.explore', { id: pc.id, folder: crumb.id })" class="text-blue-600 hover:underline">{{ crumb.name }}</Link>
                        </li>
                    </ol>
                </nav>

                <div class="bg-white p-4 rounded-lg shadow space-y-4">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex gap-2">
                            <button @click="createFolder" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-xs font-black uppercase flex items-center gap-2 transition shadow">
                                <span> Folder Baru</span>
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-2 bg-slate-100 p-2 rounded-xl border border-slate-200">
                            <input id="file-input" type="file" @change="handleFileUpload" :disabled="isUploading" class="text-[10px] font-bold" />
                            <button @click="submitUpload" :disabled="uploadForm.processing || isUploading" class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2 rounded-lg text-xs font-black uppercase transition flex items-center gap-2">
                                {{ isUploading ? 'MENGIRIM...' : ' Unggah Berkas' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="isUploading" class="bg-blue-50 p-4 rounded-xl border border-blue-200 animate-pulse">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-blue-600 font-black text-xs uppercase italic"> Progres : {{ uploadProgress }}%</span>
                            <span class="text-blue-800 font-mono text-[10px] font-bold">Speed: {{ uploadSpeed }}</span>
                        </div>
                        <div class="w-full bg-blue-200 rounded-full h-3 overflow-hidden shadow-inner">
                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-300 ease-out" :style="{ width: uploadProgress + '%' }"></div>
                        </div>
                    </div>
                </div>

                <div v-if="isExtracting" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/90 backdrop-blur-sm p-4">
                    <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl p-8 border-t-8 border-indigo-600">
                        <div class="text-center space-y-4">
                            <div class="inline-block p-4 bg-indigo-50 rounded-full animate-bounce"></div>
                            <h3 class="font-black uppercase tracking-tighter text-lg text-indigo-900">Membongkar ZIP</h3>
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Target: {{ activeExtractItem?.file_name }}</p>
                            
                            <div class="relative pt-4">
                                <div class="flex justify-between mb-2">
                                    <span class="text-[10px] font-black text-indigo-600 uppercase">Progres:</span>
                                    <span class="text-[10px] font-black text-indigo-600">{{ extractProgress }}%</span>
                                </div>
                                <div class="w-full bg-indigo-100 rounded-full h-4 overflow-hidden shadow-inner border border-indigo-200">
                                    <div class="bg-indigo-600 h-4 rounded-full transition-all duration-500 ease-out shadow-[0_0_10px_rgba(79,70,229,0.5)]" :style="{ width: extractProgress + '%' }"></div>
                                </div>
                            </div>

                            <div class="mt-4 bg-slate-900 rounded-2xl p-4 border border-slate-700 shadow-inner overflow-hidden">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">Log:</span>
                                </div>
                                <div class="h-6 overflow-hidden">
                                    <p class="text-[11px] font-mono text-green-400 truncate tracking-tighter text-left italic">
                                        > {{ extractLog }}
                                    </p>
                                </div>
                            </div>

                            <p class="text-[9px] text-slate-400 italic font-medium">SINDEN sedang melakukan dekompresi file, mohon jangan keluar dari halaman...</p>

                            <button @click="cancelExtraction" class="w-full mt-4 bg-red-50 hover:bg-red-100 text-red-600 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all"> Batalkan Operasi
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 min-h-[400px]">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50 text-[10px] uppercase font-black text-gray-500 border-b">
                                <th class="p-4">Nama Item</th>
                                <th class="p-4 text-center">Ukuran</th>
                                <th class="p-4">Tgl Modifikasi</th>
                                <th class="p-4 text-right">Otoritas</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr v-for="item in filteredContents" :key="item.id" 
                                @dblclick="item.is_folder ? $inertia.get(route('backup.explore', { id: pc.id, folder: item.id })) : openPreview(item)"
                                @contextmenu.stop="openContextMenu($event, item)"class="border-b hover:bg-blue-50 cursor-pointer transition select-none group">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <span v-if="item.is_folder" class="text-2xl"></span>
                                        <span v-else class="text-2xl"></span>
                                        <div>
                                            <p class="font-black text-gray-800 uppercase tracking-tighter">{{ item.file_name }}</p>
                                            <p class="text-[10px] text-gray-400 font-bold uppercase">{{ item.is_folder ? 'Folder Strategis' : item.file_type }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center font-mono text-xs font-bold text-gray-500">
                                    {{ item.is_folder ? '--' : item.size_human }}
                                </td>
                                <td class="p-4 text-xs font-bold text-gray-400">
                                    {{ item.date_human }}
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                                        <button v-if="!item.is_folder && (item.file_type?.toLowerCase() === 'zip' || item.file_name?.toLowerCase().endsWith('.zip'))" 
                                                @click="handleExtract(item)"class="bg-indigo-100 text-indigo-700 p-2 rounded-lg hover:bg-indigo-200"title="Ekstrak Paket ZIP">
                                            
                                        </button>
                                        <button v-if="!item.is_folder" @click="openPreview(item)" class="bg-blue-100 text-blue-700 p-2 rounded-lg hover:bg-blue-200" title="Preview"></button>
                                        <a v-if="!item.is_folder" :href="route('backup.download', item.id)" class="bg-green-100 text-green-700 p-2 rounded-lg hover:bg-green-200" title="Download"></a>
                                        <button @click="deleteItem(item)" class="bg-red-100 text-red-700 p-2 rounded-lg hover:bg-red-200" title="Hapus"></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredContents.length === 0">
                                <td colspan="4" class="p-20 text-center">
                                    <div class="flex flex-col items-center opacity-30">
                                        <span class="text-6xl mb-4"></span>
                                        <p class="font-black uppercase italic"> Files tidak ditemukan</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white p-4 rounded-xl shadow border-b-4 border-blue-900">
                    <div class="flex justify-between text-[10px] font-black uppercase mb-1">
                        <span>Status Storage {{ pc.pc_name }}</span>
                        <span>{{ pc.usage_percentage }}% Terpakai</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-900 h-2 rounded-full transition-all duration-1000" :style="{ width: pc.usage_percentage + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="contextMenu.show" 
             :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"class="fixed z-[100] bg-white border border-slate-200 shadow-2xl rounded-xl w-52 py-2 text-[11px] font-black text-gray-700 uppercase tracking-tighter">
            
            <div @click="contextMenu.item.is_folder ? $inertia.get(route('backup.explore', { id: pc.id, folder: contextMenu.item.id })) : openPreview(contextMenu.item)"class="px-4 py-2 hover:bg-blue-600 hover:text-white cursor-pointer flex items-center gap-3 transition">
                <span></span> BUKA ITEM
            </div>
            
            <div v-if="!contextMenu.item.is_folder && (contextMenu.item.file_type?.toLowerCase() === 'zip' || contextMenu.item.file_name?.toLowerCase().endsWith('.zip'))"
                 @click="handleExtract(contextMenu.item)"class="px-4 py-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white cursor-pointer flex items-center gap-3 transition font-black">
                <span></span> EKSTRAK BERKAS (ZIP)
            </div>
            
            <div class="border-t my-1 border-slate-100"></div>
            <div @click="handleRename(contextMenu.item)"class="px-4 py-2 hover:bg-blue-600 hover:text-white cursor-pointer flex items-center gap-3 transition">
                <span></span> UBAH NAMA
            </div>
            <div @click="showProperties(contextMenu.item)"class="px-4 py-2 hover:bg-blue-600 hover:text-white cursor-pointer flex items-center gap-3 transition">
                <span></span> PROPERTIES
            </div>
            <div class="border-t my-1 border-slate-100"></div>
            <div @click="deleteItem(contextMenu.item)"class="px-4 py-2 hover:bg-red-600 hover:text-white cursor-pointer flex items-center gap-3 transition text-red-600">
                <span></span> Hapus
            </div>
        </div>

        <div v-if="previewUrl" class="fixed inset-0 z-[250] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="bg-white w-full max-w-6xl h-[90vh] rounded-[2rem] flex flex-col relative overflow-hidden shadow-2xl border-t-8 border-blue-600">
                <div class="p-5 border-b flex justify-between items-center bg-slate-50">
                    <div class="flex items-center gap-3">
                        <span class="text-xl"></span>
                        <h3 class="font-black text-sm uppercase tracking-tighter">Preview Dokumen Strategis</h3>
                    </div>
                    <button @click="closePreview" class="bg-red-500 text-white px-6 py-2 rounded-xl font-black text-xs hover:bg-red-600 transition shadow-lg">TUTUP</button>
                </div>
                
                <div class="flex-1 overflow-auto p-0 bg-slate-200 flex justify-center items-center">
                    <img v-if="previewType === 'image'" :src="previewUrl" class="max-h-full shadow-2xl rounded-lg" />
                    
                    <iframe v-if="previewType === 'pdf' || previewType === 'office'" :src="previewUrl" class="w-full h-full border-none"></iframe>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.select-none {
    -webkit-user-select: none;
    user-select: none;
}
.transition-all {
    transition: all 0.5s ease-in-out;
}
</style>