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
    searchQuery: String,
    currentShare: Object,
    isAdmin: Boolean,
});

// --- STATE FITUR BAGIKAN LINK (GOOGLE DRIVE STYLE DENGAN PIN) ---
const isShareModalOpen = ref(false);
const shareTarget = ref(null); // null jika membagikan folder saat ini / root pangkalan
const isSavingShare = ref(false);
const isCopiedLink = ref(false);
const shareForm = ref({
    is_active: true,
    pin: '',
    share_name: '',
    share_url: '',
    access_count: 0,
    last_accessed_at: null,
});

const openShareModal = (item = null) => {
    shareTarget.value = item;
    const existing = item ? item.share_info : props.currentShare;

    if (existing) {
        shareForm.value = {
            is_active: existing.is_active,
            pin: existing.pin || '',
            share_name: item ? item.file_name : (props.breadcrumbs?.[props.breadcrumbs.length - 1]?.name || props.pc?.pc_name || 'Folder Berkas'),
            share_url: existing.share_url,
            access_count: existing.access_count || 0,
            last_accessed_at: existing.last_accessed_at || null,
        };
    } else {
        const randomPin = Math.floor(100000 + Math.random() * 900000).toString();
        shareForm.value = {
            is_active: true,
            pin: randomPin,
            share_name: item ? item.file_name : (props.breadcrumbs?.[props.breadcrumbs.length - 1]?.name || props.pc?.pc_name || 'Folder Berkas'),
            share_url: '',
            access_count: 0,
            last_accessed_at: null,
        };
    }
    isShareModalOpen.value = true;
};

const generateRandomPin = () => {
    shareForm.value.pin = Math.floor(100000 + Math.random() * 900000).toString();
};

const copyShareLink = () => {
    if (!shareForm.value.share_url) return;
    navigator.clipboard.writeText(shareForm.value.share_url).then(() => {
        isCopiedLink.value = true;
        setTimeout(() => isCopiedLink.value = false, 2500);
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Tautan berhasil disalin!',
            showConfirmButton: false,
            timer: 2000,
        });
    });
};

const submitShareSettings = async () => {
    if (!shareForm.value.pin || shareForm.value.pin.length < 4) {
        return Swal.fire('Perhatian', 'PIN Keamanan minimal terdiri dari 4 digit!', 'warning');
    }

    isSavingShare.value = true;
    try {
        const res = await axios.post(route('backup.share.save'), {
            pc_id: props.pc.id,
            backup_id: shareTarget.value ? shareTarget.value.id : props.currentFolderId,
            pin: shareForm.value.pin,
            is_active: shareForm.value.is_active,
            share_name: shareForm.value.share_name,
        });

        if (res.data.status === 'success') {
            const data = res.data;
            shareForm.value.share_url = data.share_url;
            
            const updatedInfo = {
                id: data.share.id,
                is_active: Boolean(data.share.is_active),
                share_token: data.share.share_token,
                pin: data.share.pin,
                share_url: data.share_url,
                access_count: data.share.access_count,
                last_accessed_at: data.share.last_accessed_at,
            };

            if (shareTarget.value) {
                shareTarget.value.share_info = updatedInfo;
                const inList = props.contents.find(c => c.id === shareTarget.value.id);
                if (inList) inList.share_info = updatedInfo;
            } else if (props.currentShare) {
                Object.assign(props.currentShare, updatedInfo);
            }

            Swal.fire({
                title: 'Tersimpan!',
                text: 'Pengaturan tautan berbagi dan PIN keamanan telah aktif.',
                icon: 'success',
                confirmButtonColor: '#2563eb',
            });
        }
    } catch (err) {
        Swal.fire('Gagal Menyimpan', err.response?.data?.message || err.message || 'Terjadi kesalahan.', 'error');
    } finally {
        isSavingShare.value = false;
    }
};

const toggleDeactivateShare = async () => {
    shareForm.value.is_active = false;
    await submitShareSettings();
};

// --- FORMULIR TAKTIS ---
const folderForm = useForm({
    pc_id: props.pc.id,
    folder_name: '',
    parent_id: props.currentFolderId
});

// --- HELPER FORMAT UKURAN BERKAS ---
const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// --- STATE SISTEM ANTREAN UNGGAH BULK (RAMAH SERVER & TANPA BATAS) ---
const uploadQueue = ref([]);
const isQueueActive = ref(false);
const isQueueOpen = ref(false);
const isQueueMinimized = ref(false);
const currentUploadSpeed = ref('');
const currentUploadProgress = ref(0);
const isDraggingOver = ref(false);
let dragCounter = 0;
let uploadAbortController = null;

const totalQueueFiles = computed(() => uploadQueue.value.length);
const completedQueueFiles = computed(() => uploadQueue.value.filter(item => item.status === 'completed').length);
const failedQueueFiles = computed(() => uploadQueue.value.filter(item => item.status === 'error').length);
const pendingQueueFiles = computed(() => uploadQueue.value.filter(item => item.status === 'pending').length);

const overallProgressPercent = computed(() => {
    if (uploadQueue.value.length === 0) return 0;
    const totalSize = uploadQueue.value.reduce((acc, item) => acc + (item.size || 1), 0);
    const uploadedSize = uploadQueue.value.reduce((acc, item) => {
        if (item.status === 'completed') return acc + (item.size || 1);
        if (item.status === 'uploading') return acc + ((item.size || 1) * (item.progress / 100));
        return acc;
    }, 0);
    return Math.min(100, Math.round((uploadedSize / totalSize) * 100));
});

// --- STATE RADAR & MENU ---
const searchQuery = ref(props.searchQuery || ''); 
const contextMenu = ref({ show: false, x: 0, y: 0, item: null });
const previewUrl = ref(null);
const previewType = ref(null);
const activePreviewItem = ref(null);

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

// --- LOGIKA FILE & ANTREAN UNGGAH BULK (RAMAH SISTEM) ---
const triggerFileInput = () => {
    const input = document.getElementById('file-input');
    if (input) input.click();
};

const enqueueFiles = (fileList) => {
    if (!fileList || fileList.length === 0) return;

    const newItems = [];
    for (let i = 0; i < fileList.length; i++) {
        const file = fileList[i];
        newItems.push({
            id: 'task_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            file: file,
            name: file.name,
            size: file.size,
            size_formatted: formatFileSize(file.size),
            status: 'pending',
            progress: 0,
            error: null,
        });
    }

    uploadQueue.value.push(...newItems);
    isQueueOpen.value = true;
    isQueueMinimized.value = false;

    if (!isQueueActive.value) {
        processUploadQueue();
    }
};

const handleFileInputChange = (event) => {
    const files = event.target.files;
    if (files && files.length > 0) {
        enqueueFiles(files);
    }
    event.target.value = '';
};

// --- LOGIKA DRAG AND DROP (GLOBAL PER AREA EXPLORER) ---
const handleDragEnter = (e) => {
    dragCounter++;
    if (e.dataTransfer?.types?.includes('Files')) {
        isDraggingOver.value = true;
    }
};

const handleDragLeave = (e) => {
    dragCounter--;
    if (dragCounter <= 0) {
        isDraggingOver.value = false;
        dragCounter = 0;
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'copy';
    }
};

const handleDrop = (e) => {
    e.preventDefault();
    isDraggingOver.value = false;
    dragCounter = 0;
    if (e.dataTransfer?.files?.length > 0) {
        enqueueFiles(e.dataTransfer.files);
    }
};

// --- MOTOR PEMROSES ANTREAN UNGGAH (SEKUANSIAL 1 PER 1: RINGAN & ANTI SERVER OVERLOAD) ---
const processUploadQueue = async () => {
    if (isQueueActive.value) return;
    isQueueActive.value = true;

    let anySuccess = false;

    while (true) {
        const nextItem = uploadQueue.value.find(item => item.status === 'pending');
        if (!nextItem) break;

        nextItem.status = 'uploading';
        nextItem.progress = 0;
        currentUploadProgress.value = 0;
        currentUploadSpeed.value = 'Menyiapkan transmisi...';

        uploadAbortController = new AbortController();
        const startTime = Date.now();

        const formData = new FormData();
        formData.append('pc_id', props.pc.id);
        formData.append('file', nextItem.file);
        if (props.currentFolderId) {
            formData.append('parent_id', props.currentFolderId);
        }

        try {
            const res = await axios.post(route('backup.store'), formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'Accept': 'application/json',
                },
                signal: uploadAbortController.signal,
                onUploadProgress: (progressEvent) => {
                    if (progressEvent.total) {
                        const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        nextItem.progress = percent;
                        currentUploadProgress.value = percent;

                        const now = Date.now();
                        const elapsedSec = (now - startTime) / 1000;
                        if (elapsedSec > 0.3) {
                            const bytesPerSec = progressEvent.loaded / elapsedSec;
                            if (bytesPerSec > 1024 * 1024) {
                                currentUploadSpeed.value = (bytesPerSec / (1024 * 1024)).toFixed(2) + ' MB/s';
                            } else {
                                currentUploadSpeed.value = (bytesPerSec / 1024).toFixed(1) + ' KB/s';
                            }
                        }
                    }
                }
            });

            if (res.data?.status === 'success') {
                nextItem.status = 'completed';
                nextItem.progress = 100;
                anySuccess = true;
            } else {
                nextItem.status = 'error';
                nextItem.error = res.data?.message || 'Gagal menyimpan berkas.';
            }
        } catch (err) {
            if (axios.isCancel(err) || err.name === 'CanceledError' || err.name === 'AbortError') {
                nextItem.status = 'cancelled';
                nextItem.error = 'Dibatalkan pengguna';
            } else {
                nextItem.status = 'error';
                nextItem.error = err.response?.data?.message || err.message || 'Koneksi terputus.';
            }
        } finally {
            uploadAbortController = null;
        }

        // Jeda 50ms antar berkas agar garbage collection dan memory buffer server sangat santai
        await new Promise(r => setTimeout(r, 50));
    }

    isQueueActive.value = false;
    currentUploadSpeed.value = '';

    if (anySuccess) {
        // Muat ulang isi folder & status storage tanpa reload halaman browser penuh
        router.reload({
            only: ['contents', 'pc'],
            preserveScroll: true,
            preserveState: true,
        });

        const completedCount = completedQueueFiles.value;
        const failedCount = failedQueueFiles.value;

        if (failedCount === 0) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `${completedCount} berkas berhasil diunggah!`,
                showConfirmButton: false,
                timer: 3000,
            });
        } else {
            Swal.fire({
                title: 'Sebagian Berkas Selesai',
                text: `${completedCount} berhasil, ${failedCount} berkas terkendala. Silakan tinjau antrean.`,
                icon: 'warning',
                confirmButtonColor: '#2563eb',
            });
        }
    }
};

const cancelSingleUpload = (item) => {
    if (item.status === 'uploading' && uploadAbortController) {
        uploadAbortController.abort();
    } else if (item.status === 'pending') {
        item.status = 'cancelled';
        item.error = 'Dibatalkan';
    }
};

const cancelAllUploads = () => {
    if (uploadAbortController) {
        uploadAbortController.abort();
    }
    uploadQueue.value.forEach(item => {
        if (item.status === 'pending') {
            item.status = 'cancelled';
            item.error = 'Dibatalkan';
        }
    });
};

const clearFinishedQueue = () => {
    uploadQueue.value = uploadQueue.value.filter(item => item.status === 'uploading' || item.status === 'pending');
    if (uploadQueue.value.length === 0) {
        isQueueOpen.value = false;
    }
};

const closeUploadDrawer = () => {
    if (isQueueActive.value) {
        isQueueMinimized.value = true;
    } else {
        isQueueOpen.value = false;
        uploadQueue.value = [];
    }
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
    activePreviewItem.value = item;
    const ext = (item.file_type || '').toLowerCase();
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
    activePreviewItem.value = null;
};

// =========================================================================
// --- FITUR SPREADSHEET EDITOR EXCEL (.XLSX, .XLS, .CSV) ---
// =========================================================================
const isExcel = (item) => {
    if (!item || item.is_folder) return false;
    const ext = (item.file_type || '').toLowerCase();
    const name = (item.file_name || '').toLowerCase();
    return ['xlsx', 'xls', 'csv'].includes(ext) || /\.(xlsx|xls|csv)$/i.test(name);
};

const isExcelEditorOpen = ref(false);
const isExcelLoading = ref(false);
const isExcelSaving = ref(false);
const editingExcelItem = ref(null);
const currentWorkbook = ref(null);
const excelSheetNames = ref([]);
const activeSheetName = ref('');
const excelGrid = ref([]);
const selectedCell = ref({ row: 0, col: 0 });
const activeFormulaValue = ref('');
const isExcelDirty = ref(false);
const excelSearchQuery = ref('');

const loadSheetJs = () => {
    return new Promise((resolve, reject) => {
        if (window.XLSX) return resolve(window.XLSX);
        const existing = document.getElementById('sheetjs-script');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.XLSX));
            existing.addEventListener('error', reject);
            return;
        }
        const script = document.createElement('script');
        script.id = 'sheetjs-script';
        script.src = '/js/xlsx.full.min.js';
        script.onload = () => resolve(window.XLSX);
        script.onerror = () => {
            const cdn = document.createElement('script');
            cdn.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
            cdn.onload = () => resolve(window.XLSX);
            cdn.onerror = reject;
            document.head.appendChild(cdn);
        };
        document.head.appendChild(script);
    });
};

const getColumnLabel = (index) => {
    let label = '';
    let num = index;
    while (num >= 0) {
        label = String.fromCharCode((num % 26) + 65) + label;
        num = Math.floor(num / 26) - 1;
    }
    return label;
};

const selectedCellCoordinate = computed(() => {
    if (!excelGrid.value || excelGrid.value.length === 0) return 'A1';
    const col = getColumnLabel(selectedCell.value.col);
    const row = selectedCell.value.row + 1;
    return `${col}${row}`;
});

const loadWorksheetData = (sheetName) => {
    if (!currentWorkbook.value) return;
    const XLSX = window.XLSX;
    const ws = currentWorkbook.value.Sheets[sheetName] || {};
    const rawData = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

    const minRows = Math.max(rawData.length + 8, 30);
    let maxColLen = 0;
    for (let r = 0; r < rawData.length; r++) {
        if (rawData[r] && rawData[r].length > maxColLen) {
            maxColLen = rawData[r].length;
        }
    }
    const minCols = Math.max(maxColLen + 4, 12);

    const grid = [];
    for (let r = 0; r < minRows; r++) {
        const row = [];
        const srcRow = rawData[r] || [];
        for (let c = 0; c < minCols; c++) {
            row.push(srcRow[c] !== undefined && srcRow[c] !== null ? String(srcRow[c]) : '');
        }
        grid.push(row);
    }

    excelGrid.value = grid;
    selectedCell.value = { row: 0, col: 0 };
    activeFormulaValue.value = grid[0] && grid[0][0] !== undefined ? grid[0][0] : '';
};

const saveCurrentGridToWorkbook = () => {
    if (!currentWorkbook.value || !activeSheetName.value) return;
    const XLSX = window.XLSX;

    let lastRow = -1;
    for (let r = excelGrid.value.length - 1; r >= 0; r--) {
        if (excelGrid.value[r].some(val => val !== '' && val !== null && val !== undefined)) {
            lastRow = r;
            break;
        }
    }

    const rowsToExport = lastRow >= 0 ? excelGrid.value.slice(0, lastRow + 1) : [[]];

    const aoa = rowsToExport.map(row => row.map(cell => {
        if (cell === null || cell === undefined) return '';
        const strVal = String(cell).trim();
        if (strVal.startsWith('=')) {
            return { f: strVal.substring(1) };
        }
        if (strVal !== '' && !isNaN(strVal) && !isNaN(parseFloat(strVal))) {
            return Number(strVal);
        }
        return cell;
    }));

    const ws = XLSX.utils.aoa_to_sheet(aoa);
    currentWorkbook.value.Sheets[activeSheetName.value] = ws;
};

const openExcelEditor = async (item) => {
    if (!isExcel(item)) return;
    editingExcelItem.value = item;
    isExcelEditorOpen.value = true;
    isExcelLoading.value = true;
    isExcelDirty.value = false;

    try {
        const XLSX = await loadSheetJs();
        const res = await axios.get(route('backup.download', item.id), {
            responseType: 'arraybuffer'
        });

        const wb = XLSX.read(new Uint8Array(res.data), {
            type: 'array',
            cellDates: true,
            cellFormula: true,
        });

        currentWorkbook.value = wb;
        excelSheetNames.value = wb.SheetNames && wb.SheetNames.length > 0 ? [...wb.SheetNames] : ['Sheet1'];
        activeSheetName.value = excelSheetNames.value[0];
        loadWorksheetData(activeSheetName.value);
    } catch (err) {
        console.error('Gagal membuka berkas Excel:', err);
        Swal.fire({
            title: 'Gagal Memuat Excel',
            text: 'Tidak dapat membuka berkas Excel: ' + (err.response?.data?.message || err.message || 'Format berkas tidak valid'),
            icon: 'error'
        });
        isExcelEditorOpen.value = false;
        editingExcelItem.value = null;
    } finally {
        isExcelLoading.value = false;
    }
};

const selectSheet = (name) => {
    if (name === activeSheetName.value) return;
    saveCurrentGridToWorkbook();
    activeSheetName.value = name;
    loadWorksheetData(name);
};

const onCellSelect = (r, c) => {
    selectedCell.value = { row: r, col: c };
    activeFormulaValue.value = excelGrid.value[r]?.[c] ?? '';
};

const onCellChange = (r, c, val) => {
    if (excelGrid.value[r] !== undefined) {
        excelGrid.value[r][c] = val;
        if (selectedCell.value.row === r && selectedCell.value.col === c) {
            activeFormulaValue.value = val;
        }
        isExcelDirty.value = true;
    }
};

const onFormulaChange = () => {
    const { row, col } = selectedCell.value;
    if (excelGrid.value[row] !== undefined) {
        excelGrid.value[row][col] = activeFormulaValue.value;
        isExcelDirty.value = true;
    }
};

const navigateCell = (r, c, event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        const nextR = Math.min(r + 1, excelGrid.value.length - 1);
        onCellSelect(nextR, c);
        const nextEl = document.getElementById(`cell-${nextR}-${c}`);
        if (nextEl) nextEl.focus();
    } else if (event.key === 'Tab') {
        event.preventDefault();
        const nextC = Math.min(c + 1, (excelGrid.value[0]?.length || 1) - 1);
        onCellSelect(r, nextC);
        const nextEl = document.getElementById(`cell-${r}-${nextC}`);
        if (nextEl) nextEl.focus();
    }
};

const addRowBelow = () => {
    const colCount = excelGrid.value[0]?.length || 10;
    const insertIdx = selectedCell.value.row + 1;
    excelGrid.value.splice(insertIdx, 0, new Array(colCount).fill(''));
    selectedCell.value.row = insertIdx;
    isExcelDirty.value = true;
};

const deleteCurrentRow = () => {
    if (excelGrid.value.length <= 1) {
        return Swal.fire('Peringatan', 'Minimal harus terdapat 1 baris dalam lembar kerja.', 'warning');
    }
    excelGrid.value.splice(selectedCell.value.row, 1);
    if (selectedCell.value.row >= excelGrid.value.length) {
        selectedCell.value.row = excelGrid.value.length - 1;
    }
    activeFormulaValue.value = excelGrid.value[selectedCell.value.row]?.[selectedCell.value.col] || '';
    isExcelDirty.value = true;
};

const addColumnRight = () => {
    const insertIdx = selectedCell.value.col + 1;
    excelGrid.value.forEach(row => row.splice(insertIdx, 0, ''));
    selectedCell.value.col = insertIdx;
    isExcelDirty.value = true;
};

const deleteCurrentColumn = () => {
    const colCount = excelGrid.value[0]?.length || 0;
    if (colCount <= 1) {
        return Swal.fire('Peringatan', 'Minimal harus terdapat 1 kolom dalam lembar kerja.', 'warning');
    }
    const targetCol = selectedCell.value.col;
    excelGrid.value.forEach(row => row.splice(targetCol, 1));
    if (selectedCell.value.col >= (excelGrid.value[0]?.length || 1)) {
        selectedCell.value.col = (excelGrid.value[0]?.length || 1) - 1;
    }
    activeFormulaValue.value = excelGrid.value[selectedCell.value.row]?.[selectedCell.value.col] || '';
    isExcelDirty.value = true;
};

const addNewSheetPrompt = () => {
    Swal.fire({
        title: 'Tambah Lembar Baru (Sheet)',
        input: 'text',
        inputLabel: 'Nama Lembar Baru',
        inputValue: `Sheet${excelSheetNames.value.length + 1}`,
        showCancelButton: true,
        confirmButtonText: 'Buat Lembar',
        confirmButtonColor: '#059669',
        preConfirm: (name) => {
            if (!name) return Swal.showValidationMessage('Nama lembar kerja wajib diisi!');
            if (excelSheetNames.value.includes(name)) return Swal.showValidationMessage('Nama lembar sudah digunakan!');
            return name;
        }
    }).then((res) => {
        if (res.isConfirmed && res.value) {
            saveCurrentGridToWorkbook();
            const name = res.value;
            const XLSX = window.XLSX;
            const ws = XLSX.utils.aoa_to_sheet([[]]);
            XLSX.utils.book_append_sheet(currentWorkbook.value, ws, name);
            excelSheetNames.value.push(name);
            selectSheet(name);
            isExcelDirty.value = true;
        }
    });
};

const renameSheetPrompt = (sheetName) => {
    Swal.fire({
        title: 'Ubah Nama Lembar',
        input: 'text',
        inputValue: sheetName,
        showCancelButton: true,
        confirmButtonText: 'Simpan Nama',
        confirmButtonColor: '#059669',
        preConfirm: (newName) => {
            if (!newName) return Swal.showValidationMessage('Nama lembar kerja tidak boleh kosong!');
            if (newName !== sheetName && excelSheetNames.value.includes(newName)) return Swal.showValidationMessage('Nama lembar sudah digunakan!');
            return newName;
        }
    }).then((res) => {
        if (res.isConfirmed && res.value && res.value !== sheetName) {
            const newName = res.value;
            saveCurrentGridToWorkbook();
            const idx = excelSheetNames.value.indexOf(sheetName);
            if (idx !== -1) {
                excelSheetNames.value[idx] = newName;
                currentWorkbook.value.SheetNames[idx] = newName;
                currentWorkbook.value.Sheets[newName] = currentWorkbook.value.Sheets[sheetName];
                delete currentWorkbook.value.Sheets[sheetName];
                activeSheetName.value = newName;
                isExcelDirty.value = true;
            }
        }
    });
};

const deleteSheetPrompt = (sheetName) => {
    if (excelSheetNames.value.length <= 1) {
        return Swal.fire('Perhatian', 'Dokumen harus memiliki minimal 1 lembar kerja.', 'warning');
    }
    Swal.fire({
        title: 'Hapus Lembar?',
        text: `Seluruh data pada lembar "${sheetName}" akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus Lembar'
    }).then((res) => {
        if (res.isConfirmed) {
            const idx = excelSheetNames.value.indexOf(sheetName);
            if (idx !== -1) {
                delete currentWorkbook.value.Sheets[sheetName];
                currentWorkbook.value.SheetNames.splice(idx, 1);
                excelSheetNames.value.splice(idx, 1);
                const nextSheet = excelSheetNames.value[Math.max(0, idx - 1)];
                activeSheetName.value = nextSheet;
                loadWorksheetData(nextSheet);
                isExcelDirty.value = true;
            }
        }
    });
};

const saveExcelChanges = async () => {
    if (!editingExcelItem.value || !currentWorkbook.value) return;
    isExcelSaving.value = true;
    try {
        saveCurrentGridToWorkbook();
        const XLSX = window.XLSX;
        const base64 = XLSX.write(currentWorkbook.value, { bookType: 'xlsx', type: 'base64' });

        const res = await axios.post(route('backup.save-excel', editingExcelItem.value.id), {
            base64_content: base64
        });

        if (res.data.status === 'success') {
            isExcelDirty.value = false;
            editingExcelItem.value.file_size = res.data.file_size;
            editingExcelItem.value.size_human = res.data.size_human;
            editingExcelItem.value.date_human = res.data.date_human;

            const inList = props.contents.find(c => c.id === editingExcelItem.value.id);
            if (inList) {
                inList.file_size = res.data.file_size;
                inList.size_human = res.data.size_human;
                inList.date_human = res.data.date_human;
            }

            if (props.pc) {
                props.pc.usage_human = res.data.pc_usage_human;
                props.pc.usage_percentage = res.data.pc_usage_percentage;
            }

            Swal.fire({
                title: 'Tersimpan!',
                text: 'Perubahan pada berkas Excel berhasil disimpan ke penyimpanan cadangan.',
                icon: 'success',
                confirmButtonColor: '#059669'
            });
        }
    } catch (err) {
        console.error('Gagal menyimpan Excel:', err);
        Swal.fire('Gagal Menyimpan', err.response?.data?.message || err.message || 'Terjadi kesalahan saat menyimpan.', 'error');
    } finally {
        isExcelSaving.value = false;
    }
};

const downloadCurrentExcel = () => {
    if (!currentWorkbook.value || !editingExcelItem.value) return;
    saveCurrentGridToWorkbook();
    const XLSX = window.XLSX;
    XLSX.writeFile(currentWorkbook.value, editingExcelItem.value.file_name);
};

const closeExcelEditor = () => {
    if (isExcelDirty.value) {
        Swal.fire({
            title: 'Perubahan Belum Disimpan',
            text: 'Ada perubahan yang belum Anda simpan ke server. Apakah Anda yakin ingin keluar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#059669',
            confirmButtonText: 'Keluar Tanpa Menyimpan',
            cancelButtonText: 'Lanjutkan Mengedit'
        }).then((res) => {
            if (res.isConfirmed) {
                isExcelEditorOpen.value = false;
                editingExcelItem.value = null;
                currentWorkbook.value = null;
            }
        });
    } else {
        isExcelEditorOpen.value = false;
        editingExcelItem.value = null;
        currentWorkbook.value = null;
    }
};

const createNewExcelPrompt = () => {
    Swal.fire({
        title: 'Buat Berkas Excel Baru',
        input: 'text',
        inputLabel: 'Nama Berkas Spreadsheet',
        inputValue: 'DOKUMEN_BARU',
        placeholder: 'contoh: DATA_INVENTARIS_2026',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        confirmButtonText: 'Buat Berkas',
        preConfirm: (name) => {
            if (!name) return Swal.showValidationMessage('Nama berkas wajib diisi!');
            return name;
        }
    }).then(async (result) => {
        if (result.isConfirmed && result.value) {
            try {
                const XLSX = await loadSheetJs();
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet([
                    ['NO', 'KOLOM A', 'KOLOM B', 'KOLOM C', 'KETERANGAN'],
                    [1, '', '', '', '']
                ]);
                XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
                const base64 = XLSX.write(wb, { bookType: 'xlsx', type: 'base64' });

                router.post(route('backup.create-excel'), {
                    pc_id: props.pc.id,
                    parent_id: props.currentFolderId,
                    file_name: result.value,
                    base64_content: base64
                }, {
                    onSuccess: () => {
                        Swal.fire('Berhasil!', 'Berkas Excel baru berhasil ditambahkan ke folder.', 'success');
                    }
                });
            } catch (e) {
                Swal.fire('Gagal', 'Terjadi kesalahan: ' + e.message, 'error');
            }
        }
    });
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
        <div class="space-y-6 font-sans relative" 
             @contextmenu.prevent=""
             @dragenter.prevent="handleDragEnter"
             @dragover.prevent="handleDragOver"
             @dragleave.prevent="handleDragLeave"
             @drop.prevent="handleDrop">
            
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
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="createFolder" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-xl text-xs font-black uppercase flex items-center gap-2 transition shadow-xs cursor-pointer">
                                <span>📁 Folder Baru</span>
                            </button>
                            <button @click="createNewExcelPrompt" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-black uppercase flex items-center gap-2 transition shadow-xs cursor-pointer">
                                <span>📊 Excel Baru</span>
                            </button>
                            <!-- Tombol Bagikan Folder Ini / Pangkalan -->
                            <button @click="openShareModal(null)" 
                                    :class="currentShare?.is_active ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-500 shadow-sm' : 'bg-blue-600 hover:bg-blue-700 text-white border-blue-500 shadow-sm'" 
                                    class="px-4 py-2 rounded-xl text-xs font-black uppercase flex items-center gap-2 transition cursor-pointer border"
                                    :title="currentFolderId ? 'Bagikan Folder Ini via Tautan' : 'Bagikan Pangkalan Ini via Tautan'">
                                <span>🔗</span>
                                <span>{{ currentFolderId ? 'Bagikan Folder Ini' : 'Bagikan Pangkalan' }}</span>
                                <span v-if="currentShare?.is_active" class="px-1.5 py-0.2 text-[9px] bg-emerald-950 text-emerald-300 rounded-md font-bold uppercase">Aktif</span>
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Hidden Multi-file input (Dukungan Bulk Tanpa Batas) -->
                            <input 
                                id="file-input" 
                                type="file" 
                                multiple 
                                @change="handleFileInputChange" 
                                class="hidden" 
                            />
                            
                            <!-- Tombol Unggah Berkas Bulk -->
                            <button 
                                type="button"
                                @click="triggerFileInput" 
                                class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2 rounded-xl text-xs font-black uppercase transition flex items-center gap-2 shadow-xs cursor-pointer active:scale-95"
                                title="Pilih satu atau banyak berkas sekaligus tanpa batas (bisa drag & drop langsung)">
                                <span>📤</span>
                                <span>Unggah Berkas (Bulk)</span>
                            </button>

                            <!-- Indikator Drawer Antrean Aktif / Diminimalkan -->
                            <button 
                                v-if="isQueueOpen && isQueueMinimized" 
                                type="button" 
                                @click="isQueueMinimized = false" 
                                class="px-3.5 py-2 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-xl text-xs font-black uppercase flex items-center gap-1.5 transition cursor-pointer shadow-xs"
                                title="Buka kembali panel pemantau antrean unggah">
                                <span class="w-2 h-2 rounded-full bg-indigo-600 animate-ping"></span>
                                <span>{{ completedQueueFiles }}/{{ totalQueueFiles }} Berkas</span>
                            </button>
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
                                @dblclick="item.is_folder ? $inertia.get(route('backup.explore', { id: pc.id, folder: item.id })) : (isExcel(item) ? openExcelEditor(item) : openPreview(item))"
                                @contextmenu.stop="openContextMenu($event, item)"
                                class="border-b hover:bg-blue-50 cursor-pointer transition select-none group"
                            >
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <span v-if="item.is_folder" class="text-2xl">📁</span>
                                        <span v-else-if="isExcel(item)" class="text-2xl">📊</span>
                                        <span v-else class="text-2xl">📄</span>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <p class="font-black text-gray-800 uppercase tracking-tighter">{{ item.file_name }}</p>
                                                <span v-if="item.is_folder && item.share_info?.is_active" class="px-2 py-0.5 text-[9px] bg-emerald-100 text-emerald-700 rounded-full font-bold uppercase border border-emerald-300 flex items-center gap-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Link Aktif
                                                </span>
                                            </div>
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
                                    <div class="flex justify-end items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                                        <!-- Tombol Bagikan Folder (Google Drive Style) -->
                                        <button v-if="item.is_folder && isAdmin" 
                                                @click.stop="openShareModal(item)" 
                                                class="bg-indigo-100 text-indigo-700 hover:bg-indigo-600 hover:text-white p-2 rounded-lg transition shadow-xs flex items-center justify-center cursor-pointer" 
                                                :title="item.share_info?.is_active ? 'Kelola Tautan Berbagi (Aktif)' : 'Bagikan Folder (Buat Tautan)'">
                                            🔗
                                        </button>
                                        <!-- Tombol Edit Excel Khusus Berkas Spreadsheet -->
                                        <button v-if="isExcel(item)" 
                                                @click.stop="openExcelEditor(item)" 
                                                class="bg-emerald-100 text-emerald-700 hover:bg-emerald-600 hover:text-white p-2 rounded-lg transition shadow-xs flex items-center justify-center cursor-pointer" 
                                                title="Edit Berkas Excel (Spreadsheet)">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/>
                                            </svg>
                                        </button>
                                        <button v-if="!item.is_folder && (item.file_type?.toLowerCase() === 'zip' || item.file_name?.toLowerCase().endsWith('.zip'))" 
                                                @click.stop="handleExtract(item)" 
                                                class="bg-indigo-100 text-indigo-700 hover:bg-indigo-200 p-2 rounded-lg transition" 
                                                title="Ekstrak Paket ZIP">
                                            📦
                                        </button>
                                        <button v-if="!item.is_folder" 
                                                @click.stop="openPreview(item)" 
                                                class="bg-blue-100 text-blue-700 hover:bg-blue-200 p-2 rounded-lg transition" 
                                                title="Preview">
                                            👁️
                                        </button>
                                        <a v-if="!item.is_folder" 
                                           :href="route('backup.download', item.id)" 
                                           class="bg-green-100 text-green-700 hover:bg-green-200 p-2 rounded-lg transition" 
                                           title="Download">
                                            ⬇️
                                        </a>
                                        <button @click.stop="deleteItem(item)" 
                                                class="bg-red-100 text-red-700 hover:bg-red-200 p-2 rounded-lg transition" 
                                                title="Hapus">
                                            🗑️
                                        </button>
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
             :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }" class="fixed z-[100] bg-white border border-slate-200 shadow-2xl rounded-xl w-56 py-2 text-[11px] font-black text-gray-700 uppercase tracking-tighter">
            
            <div @click="contextMenu.item.is_folder ? $inertia.get(route('backup.explore', { id: pc.id, folder: contextMenu.item.id })) : (isExcel(contextMenu.item) ? openExcelEditor(contextMenu.item) : openPreview(contextMenu.item))" class="px-4 py-2 hover:bg-blue-600 hover:text-white cursor-pointer flex items-center gap-3 transition">
                <span>👁️</span> BUKA ITEM
            </div>

            <!-- OPSI BAGIKAN LINK UNTUK FOLDER -->
            <div v-if="contextMenu.item?.is_folder && isAdmin"
                 @click="openShareModal(contextMenu.item)"
                 class="px-4 py-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white cursor-pointer flex items-center gap-3 transition font-black">
                <span>🔗</span>
                <span>{{ contextMenu.item.share_info?.is_active ? 'KELOLA TAUTAN (AKTIF)' : 'BAGIKAN TAUTAN (PIN)' }}</span>
            </div>

            <!-- OPSI EDIT EXCEL PADA KLIK KANAN -->
            <div v-if="isExcel(contextMenu.item)"
                 @click="openExcelEditor(contextMenu.item)"
                 class="px-4 py-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white cursor-pointer flex items-center gap-3 transition font-black">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/>
                </svg>
                <span>EDIT FILE EXCEL</span>
            </div>
            
            <div v-if="!contextMenu.item.is_folder && (contextMenu.item.file_type?.toLowerCase() === 'zip' || contextMenu.item.file_name?.toLowerCase().endsWith('.zip'))"
                 @click="handleExtract(contextMenu.item)" class="px-4 py-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white cursor-pointer flex items-center gap-3 transition font-black">
                <span>📦</span> EKSTRAK BERKAS (ZIP)
            </div>
            
            <div class="border-t my-1 border-slate-100"></div>
            <div @click="handleRename(contextMenu.item)" class="px-4 py-2 hover:bg-blue-600 hover:text-white cursor-pointer flex items-center gap-3 transition">
                <span>✏️</span> UBAH NAMA
            </div>
            <div @click="showProperties(contextMenu.item)" class="px-4 py-2 hover:bg-blue-600 hover:text-white cursor-pointer flex items-center gap-3 transition">
                <span>ℹ️</span> PROPERTIES
            </div>
            <div class="border-t my-1 border-slate-100"></div>
            <div @click="deleteItem(contextMenu.item)" class="px-4 py-2 hover:bg-red-600 hover:text-white cursor-pointer flex items-center gap-3 transition text-red-600">
                <span>🗑️</span> HAPUS
            </div>
        </div>

        <!-- MODAL PREVIEW DOKUMEN -->
        <div v-if="previewUrl" class="fixed inset-0 z-[250] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm">
            <div class="bg-white w-full max-w-6xl h-[90vh] rounded-[2rem] flex flex-col relative overflow-hidden shadow-2xl border-t-8 border-blue-600">
                <div class="p-5 border-b flex justify-between items-center bg-slate-50">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📄</span>
                        <h3 class="font-black text-sm uppercase tracking-tighter">Preview Dokumen Strategis</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button v-if="activePreviewItem && isExcel(activePreviewItem)" 
                                @click="const itm = activePreviewItem; closePreview(); openExcelEditor(itm);" 
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl font-black text-xs transition shadow-sm flex items-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/>
                            </svg>
                            <span>Edit Excel Ini</span>
                        </button>
                        <button @click="closePreview" class="bg-red-500 text-white px-6 py-2 rounded-xl font-black text-xs hover:bg-red-600 transition shadow-lg cursor-pointer">TUTUP</button>
                    </div>
                </div>
                
                <div class="flex-1 overflow-auto p-0 bg-slate-200 flex justify-center items-center">
                    <img v-if="previewType === 'image'" :src="previewUrl" class="max-h-full shadow-2xl rounded-lg" />
                    
                    <iframe v-if="previewType === 'pdf' || previewType === 'office'" :src="previewUrl" class="w-full h-full border-none"></iframe>
                </div>
            </div>
        </div>

        <!-- MODAL EDITOR EXCEL SPREADSHEET (FULL INTERACTIVE) -->
        <div v-if="isExcelEditorOpen" class="fixed inset-0 z-[260] flex items-center justify-center bg-slate-950/85 p-2 sm:p-4 backdrop-blur-md animate-in fade-in duration-200">
            <div class="bg-white w-full max-w-[96vw] h-[94vh] rounded-2xl sm:rounded-3xl flex flex-col relative overflow-hidden shadow-2xl border border-slate-200">
                
                <!-- 1. Header Toolbar -->
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black shadow-md shadow-emerald-600/30 shrink-0">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-2h2v2zm0-4H7v-2h2v2zm0-4H7V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-extrabold text-sm sm:text-base text-slate-900 tracking-tight">
                                    {{ editingExcelItem?.file_name }}
                                </h3>
                                <span v-if="isExcelDirty" class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 text-[10px] font-black uppercase tracking-wider animate-pulse">
                                    * Belum Disimpan
                                </span>
                                <span v-else class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider">
                                    Tersimpan di Backup
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 font-semibold flex items-center gap-2">
                                <span>Lembar: <b class="text-emerald-700 font-mono">{{ activeSheetName }}</b></span>
                                <span>•</span>
                                <span>Ukuran: {{ editingExcelItem?.size_human }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 sm:gap-2 w-full sm:w-auto justify-end flex-wrap">
                        <button 
                            type="button" 
                            @click="addRowBelow" 
                            class="px-2.5 py-1.5 bg-slate-200/80 hover:bg-slate-300 text-slate-700 rounded-lg text-[11px] font-extrabold uppercase tracking-wider transition cursor-pointer flex items-center gap-1"
                            title="Sisipkan baris baru di bawah sel terpilih"
                        >
                            <span>+ Baris</span>
                        </button>
                        <button 
                            type="button" 
                            @click="addColumnRight" 
                            class="px-2.5 py-1.5 bg-slate-200/80 hover:bg-slate-300 text-slate-700 rounded-lg text-[11px] font-extrabold uppercase tracking-wider transition cursor-pointer flex items-center gap-1"
                            title="Sisipkan kolom baru di kanan sel terpilih"
                        >
                            <span>+ Kolom</span>
                        </button>
                        <button 
                            type="button" 
                            @click="deleteCurrentRow" 
                            class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-extrabold uppercase tracking-wider transition cursor-pointer flex items-center gap-1"
                            title="Hapus baris saat ini"
                        >
                            <span>- Baris</span>
                        </button>
                        <button 
                            type="button" 
                            @click="deleteCurrentColumn" 
                            class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-extrabold uppercase tracking-wider transition cursor-pointer flex items-center gap-1"
                            title="Hapus kolom saat ini"
                        >
                            <span>- Kolom</span>
                        </button>

                        <div class="h-6 w-px bg-slate-300 mx-1 hidden sm:block"></div>

                        <button 
                            type="button" 
                            @click="downloadCurrentExcel" 
                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer flex items-center gap-1 border border-slate-300"
                            title="Unduh salinan berkas Excel ke komputer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Unduh</span>
                        </button>

                        <button 
                            type="button" 
                            @click="saveExcelChanges" 
                            :disabled="isExcelSaving || isExcelLoading"
                            class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-md shadow-emerald-600/30 flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="isExcelSaving" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ isExcelSaving ? 'Menyimpan...' : 'Simpan ke Backup' }}</span>
                        </button>

                        <button 
                            type="button" 
                            @click="closeExcelEditor" 
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer"
                        >
                            ✕ Tutup
                        </button>
                    </div>
                </div>

                <!-- 2. Formula & Coordinate Bar -->
                <div class="px-5 py-2 bg-slate-100 border-b border-slate-200 flex items-center gap-3 text-xs">
                    <!-- Coordinate box -->
                    <div class="px-2.5 py-1 bg-white rounded-lg border border-slate-300 font-mono font-black text-emerald-800 shadow-2xs min-w-[55px] text-center">
                        {{ selectedCellCoordinate }}
                    </div>

                    <!-- Fx symbol -->
                    <div class="font-mono font-bold text-slate-400 italic text-sm">
                        fx
                    </div>

                    <!-- Active Cell Input -->
                    <div class="flex-1">
                        <input 
                            type="text" 
                            v-model="activeFormulaValue" 
                            @input="onFormulaChange"
                            placeholder="Ketik data sel atau formula (contoh: =SUM(A1:A10) atau teks)..."
                            class="w-full bg-white border border-slate-300 rounded-lg px-3 py-1 text-xs font-mono font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                    </div>

                    <!-- Search within spreadsheet -->
                    <div class="relative w-48 hidden md:block">
                        <input 
                            type="text" 
                            v-model="excelSearchQuery"
                            placeholder="Cari teks di tabel..."
                            class="w-full bg-white border border-slate-300 rounded-lg pl-7 pr-2 py-1 text-[11px] font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        />
                        <span class="absolute left-2 top-1.5 text-slate-400 text-xs">🔍</span>
                    </div>
                </div>

                <!-- 3. Spreadsheet Grid Scroll Area -->
                <div class="flex-1 overflow-auto bg-slate-200 relative select-none">
                    <!-- Loading state -->
                    <div v-if="isExcelLoading" class="absolute inset-0 z-30 bg-white/90 flex flex-col items-center justify-center gap-3">
                        <div class="w-10 h-10 border-4 border-emerald-600 border-t-transparent rounded-full animate-spin"></div>
                        <p class="font-extrabold text-xs uppercase tracking-wider text-slate-700">Mendekripsi & Membuka Berkas Spreadsheet...</p>
                    </div>

                    <!-- Grid Table -->
                    <table v-else class="border-collapse table-fixed bg-white">
                        <thead>
                            <tr class="sticky top-0 z-20 bg-slate-100 shadow-2xs">
                                <!-- Top-left corner -->
                                <th class="sticky left-0 z-30 w-12 min-w-[48px] bg-slate-200 border border-slate-300 p-1 text-[10px] font-black text-slate-500 text-center select-none">
                                    #
                                </th>
                                <!-- Column Headers (A, B, C, ...) -->
                                <th 
                                    v-for="(col, cIdx) in (excelGrid[0] || [])" 
                                    :key="'col-' + cIdx"
                                    :class="selectedCell.col === cIdx ? 'bg-emerald-100 text-emerald-800 border-b-2 border-b-emerald-600' : 'bg-slate-100 text-slate-600'"
                                    class="w-32 min-w-[120px] max-w-[200px] border border-slate-300 px-2 py-1 text-xs font-black text-center select-none transition-colors"
                                >
                                    {{ getColumnLabel(cIdx) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, rIdx) in excelGrid" :key="'row-' + rIdx" class="hover:bg-slate-50/50">
                                <!-- Row Header (1, 2, 3, ...) -->
                                <th 
                                    :class="selectedCell.row === rIdx ? 'bg-emerald-100 text-emerald-800 border-r-2 border-r-emerald-600' : 'bg-slate-100 text-slate-500'"
                                    class="sticky left-0 z-10 w-12 min-w-[48px] border border-slate-300 p-1 text-[10px] font-black text-center font-mono select-none transition-colors"
                                >
                                    {{ rIdx + 1 }}
                                </th>
                                <!-- Cell Data -->
                                <td 
                                    v-for="(cellVal, cIdx) in row" 
                                    :key="'cell-' + rIdx + '-' + cIdx"
                                    @click="onCellSelect(rIdx, cIdx)"
                                    :class="[
                                        selectedCell.row === rIdx && selectedCell.col === cIdx ? 'ring-2 ring-emerald-500 bg-emerald-50/40 z-10' : '',
                                        excelSearchQuery && String(cellVal).toLowerCase().includes(excelSearchQuery.toLowerCase()) ? 'bg-yellow-100' : ''
                                    ]"
                                    class="border border-slate-200 p-0 relative transition-all"
                                >
                                    <input 
                                        :id="`cell-${rIdx}-${cIdx}`"
                                        type="text" 
                                        v-model="excelGrid[rIdx][cIdx]"
                                        @focus="onCellSelect(rIdx, cIdx)"
                                        @input="onCellChange(rIdx, cIdx, $event.target.value)"
                                        @keydown="navigateCell(rIdx, cIdx, $event)"
                                        class="w-full h-8 px-2 py-1 text-xs font-medium text-slate-800 bg-transparent border-none focus:outline-none focus:ring-0 truncate"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. Bottom Sheet Tabs Footer -->
                <div class="px-4 py-2 bg-slate-100 border-t border-slate-200 flex items-center justify-between gap-3 text-xs overflow-x-auto">
                    <div class="flex items-center gap-1.5 overflow-x-auto py-0.5">
                        <button 
                            v-for="sName in excelSheetNames" 
                            :key="sName"
                            @click="selectSheet(sName)"
                            :class="activeSheetName === sName ? 'bg-white text-emerald-700 font-extrabold shadow-xs border-b-2 border-b-emerald-600' : 'bg-slate-200/70 text-slate-600 hover:bg-slate-200 font-bold'"
                            class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-xs transition cursor-pointer flex items-center gap-2 group whitespace-nowrap"
                        >
                            <span>📊 {{ sName }}</span>
                            <span 
                                v-if="excelSheetNames.length > 1" 
                                @click.stop="deleteSheetPrompt(sName)"
                                class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-600 font-black ml-1 text-xs transition"
                                title="Hapus Lembar Ini"
                            >
                                &times;
                            </span>
                            <span 
                                @click.stop="renameSheetPrompt(sName)"
                                class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-emerald-700 font-black text-[10px] transition"
                                title="Ubah Nama Lembar"
                            >
                                ✏️
                            </span>
                        </button>

                        <button 
                            type="button" 
                            @click="addNewSheetPrompt" 
                            class="px-2.5 py-1.5 bg-slate-200 hover:bg-emerald-100 hover:text-emerald-700 text-slate-600 rounded-lg text-xs font-black transition cursor-pointer flex items-center gap-1"
                            title="Tambah Lembar Kerja Baru"
                        >
                            <span>+ Sheet Baru</span>
                        </button>
                    </div>

                    <div class="text-[10px] text-slate-400 font-semibold hidden md:block whitespace-nowrap">
                        Gunakan <kbd class="px-1.5 py-0.5 bg-white rounded border text-slate-600 font-mono">Enter</kbd> untuk pindah ke bawah, <kbd class="px-1.5 py-0.5 bg-white rounded border text-slate-600 font-mono">Tab</kbd> ke kanan.
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL BAGIKAN FOLDER DENGAN PROTEKSI PIN (GOOGLE DRIVE STYLE) -->
        <Teleport to="body">
            <div v-if="isShareModalOpen" class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4 animate-fade-in">
                <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl overflow-hidden border border-slate-200">
                    <!-- Modal Header -->
                    <div class="p-6 bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-900 text-white flex justify-between items-start">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">🔗</span>
                                <h3 class="text-lg font-black tracking-tight">Bagikan Folder & Akses Personel</h3>
                            </div>
                            <p class="text-xs text-blue-100 font-medium">
                                Bagikan akses folder dengan tautan terproteksi PIN keamanan (Akses Terbatas: Hanya Lihat & Unduh).
                            </p>
                        </div>
                        <button @click="isShareModalOpen = false" class="text-white/70 hover:text-white text-2xl font-bold p-1 leading-none transition">
                            &times;
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-6">
                        <!-- Info Folder -->
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-200">
                            <span class="text-3xl">📁</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-black uppercase text-slate-400 tracking-wider">Target Folder</p>
                                <p class="text-sm font-black text-slate-800 truncate uppercase">{{ shareForm.share_name }}</p>
                                <p class="text-[10px] text-slate-500 font-bold">Pangkalan: {{ pc.pc_name }}</p>
                            </div>
                            <div>
                                <span v-if="shareForm.is_active" class="px-2.5 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full text-[10px] font-black uppercase flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-ping"></span>
                                    Aktif
                                </span>
                                <span v-else class="px-2.5 py-1 bg-rose-100 text-rose-800 border border-rose-300 rounded-full text-[10px] font-black uppercase">
                                    Nonaktif
                                </span>
                            </div>
                        </div>

                        <!-- Saklar Status Akses -->
                        <div class="flex items-center justify-between p-4 bg-indigo-50/60 rounded-2xl border border-indigo-100">
                            <div>
                                <h4 class="text-xs font-black uppercase tracking-wide text-indigo-950">Status Akses Tautan</h4>
                                <p class="text-[11px] text-indigo-700">Jika dinonaktifkan, tautan akan langsung terkunci dan tidak dapat diakses.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="shareForm.is_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <!-- Input PIN Keamanan -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <label class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1">
                                    <span>🔒</span> PIN Keamanan Akses
                                </label>
                                <button type="button" @click="generateRandomPin" class="text-xs font-black text-indigo-600 hover:text-indigo-800 uppercase flex items-center gap-1 cursor-pointer">
                                    <span>🎲</span> Acak PIN Baru
                                </button>
                            </div>
                            <div class="relative">
                                <input 
                                    v-model="shareForm.pin" 
                                    type="text" 
                                    maxlength="12" 
                                    placeholder="Contoh: 123456" 
                                    class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-300 focus:border-indigo-600 focus:bg-white rounded-xl text-base font-black tracking-widest text-slate-800 transition"
                                />
                            </div>
                            <p class="text-[11px] text-slate-500 font-medium">
                                * Personel yang membuka tautan wajib memasukkan PIN ini untuk verifikasi sebelum melihat berkas.
                            </p>
                        </div>

                        <!-- Box Salin Link Tautan -->
                        <div class="space-y-2">
                            <label class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1">
                                <span>🌐</span> Tautan Akses Personel
                            </label>
                            <div class="flex items-center gap-2">
                                <input 
                                    type="text" 
                                    readonly 
                                    :value="shareForm.share_url || 'Simpan PIN terlebih dahulu untuk mengaktifkan tautan...'" 
                                    class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-300 rounded-xl text-xs font-mono text-slate-600 select-all cursor-pointer"
                                    @click="copyShareLink"
                                />
                                <button 
                                    type="button" 
                                    @click="copyShareLink" 
                                    :disabled="!shareForm.share_url" 
                                    :class="isCopiedLink ? 'bg-emerald-600 text-white' : 'bg-slate-800 hover:bg-slate-900 text-white'"
                                    class="px-4 py-2.5 rounded-xl text-xs font-black uppercase whitespace-nowrap transition cursor-pointer flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <span>{{ isCopiedLink ? '✓ Tersalin' : '📋 Salin' }}</span>
                                </button>
                                <a 
                                    v-if="shareForm.share_url" 
                                    :href="shareForm.share_url" 
                                    target="_blank" 
                                    class="px-3 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-black transition"
                                    title="Buka Pratinjau Tautan">
                                    ↗
                                </a>
                            </div>
                        </div>

                        <!-- Statistik Akses -->
                        <div v-if="shareForm.access_count > 0 || shareForm.last_accessed_at" class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px]">
                            <div>
                                <span class="text-slate-400 font-bold uppercase block text-[10px]">Frekuensi Diakses</span>
                                <span class="font-black text-slate-700">{{ shareForm.access_count }} Kali</span>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold uppercase block text-[10px]">Akses Terakhir</span>
                                <span class="font-black text-slate-700">{{ shareForm.last_accessed_at || 'Belum pernah' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-wrap justify-between items-center gap-3">
                        <button 
                            v-if="shareForm.share_url && shareForm.is_active" 
                            type="button" 
                            @click="toggleDeactivateShare" 
                            :disabled="isSavingShare"
                            class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-black uppercase transition cursor-pointer">
                            Tutup / Nonaktifkan Akses
                        </button>
                        <div v-else></div>

                        <div class="flex items-center gap-2">
                            <button 
                                type="button" 
                                @click="isShareModalOpen = false" 
                                class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-black uppercase transition cursor-pointer">
                                Tutup
                            </button>
                            <button 
                                type="button" 
                                @click="submitShareSettings" 
                                :disabled="isSavingShare"
                                class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black uppercase transition cursor-pointer flex items-center gap-2 shadow-md hover:shadow-lg disabled:opacity-50">
                                <span v-if="isSavingShare" class="animate-spin text-sm">⏳</span>
                                <span>{{ isSavingShare ? 'Menyimpan...' : 'Simpan & Terapkan' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- DRAG AND DROP OVERLAY (VISUAL FEEDBACK KETIKA MENGGESER BERKAS) -->
        <Teleport to="body">
            <div v-if="isDraggingOver" 
                 class="fixed inset-0 z-[350] bg-blue-900/60 backdrop-blur-xs border-8 border-dashed border-white/80 flex flex-col items-center justify-center p-6 pointer-events-none animate-fade-in">
                <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-2xl flex flex-col items-center gap-4 text-center max-w-md border-t-8 border-blue-600 animate-bounce">
                    <span class="text-6xl">📥</span>
                    <div>
                        <h3 class="text-xl font-black uppercase tracking-tight text-slate-900">Lepaskan Berkas Di Sini</h3>
                        <p class="text-xs font-bold text-slate-500 mt-1">
                            Bisa melepaskan banyak berkas sekaligus tanpa batas. Sistem akan mengunggah secara berurutan dan sangat enteng bagi server.
                        </p>
                    </div>
                    <span class="px-3.5 py-1.5 bg-blue-50 text-blue-700 rounded-full text-[11px] font-black uppercase tracking-wider">
                        Tujuan: {{ currentFolderId ? (breadcrumbs?.[breadcrumbs.length - 1]?.name || 'Folder Aktif') : pc.pc_name }}
                    </span>
                </div>
            </div>
        </Teleport>

        <!-- FLOATING BULK UPLOAD DOCK (GOOGLE DRIVE STYLE - RINGAN KE SERVER) -->
        <Teleport to="body">
            <div v-if="isQueueOpen" class="fixed bottom-5 right-5 z-[280] w-full max-w-sm sm:max-w-md transition-all duration-300">
                
                <!-- MINIMIZED VIEW (SLIM FLOATING PILL) -->
                <div v-if="isQueueMinimized" 
                     @click="isQueueMinimized = false"
                     class="bg-slate-900/95 backdrop-blur-md text-white p-3.5 rounded-2xl shadow-2xl border border-slate-700 flex items-center justify-between cursor-pointer hover:bg-slate-800 transition group">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center text-sm font-bold shadow-xs shrink-0" :class="{ 'animate-pulse': isQueueActive }">
                            📤
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase truncate">
                                {{ isQueueActive ? `Mengunggah (${completedQueueFiles}/${totalQueueFiles})` : `Selesai (${completedQueueFiles}/${totalQueueFiles})` }}
                            </p>
                            <p class="text-[10px] text-slate-300 font-mono">
                                {{ isQueueActive ? `${overallProgressPercent}% • ${currentUploadSpeed || 'Memproses'}` : 'Klik untuk melihat rincian' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[11px] font-black text-blue-400 group-hover:text-blue-300 uppercase">Buka ↗</span>
                        <button @click.stop="closeUploadDrawer" class="text-slate-400 hover:text-white text-lg font-bold leading-none p-1 transition" title="Tutup">
                            &times;
                        </button>
                    </div>
                </div>

                <!-- EXPANDED VIEW (FULL UPLOAD MANAGEMENT CARD) -->
                <div v-else class="bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[75vh] animate-fade-in">
                    
                    <!-- Header -->
                    <div class="p-4 bg-gradient-to-r from-slate-900 via-blue-900 to-indigo-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="text-lg">📤</span>
                            <div class="min-w-0">
                                <h4 class="text-xs font-black uppercase tracking-tight truncate">
                                    {{ isQueueActive ? 'Proses Unggah Berkas' : (failedQueueFiles > 0 ? 'Unggah Berkas Selesai (Ada Kendala)' : 'Seluruh Berkas Selesai Diunggah') }}
                                </h4>
                                <p class="text-[10px] text-blue-200 font-bold">
                                    {{ completedQueueFiles }} dari {{ totalQueueFiles }} selesai
                                    <span v-if="currentUploadSpeed && isQueueActive" class="font-mono text-emerald-300 ml-1.5 font-normal">({{ currentUploadSpeed }})</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <button @click="isQueueMinimized = true" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center font-bold text-xs transition cursor-pointer" title="Minimalkan ke pojok">
                                _
                            </button>
                            <button @click="closeUploadDrawer" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-rose-600 text-white flex items-center justify-center font-bold text-sm transition cursor-pointer" title="Tutup">
                                &times;
                            </button>
                        </div>
                    </div>

                    <!-- Overall Progress Bar -->
                    <div class="w-full bg-slate-100 h-2 overflow-hidden">
                        <div 
                            class="h-2 transition-all duration-300 ease-out"
                            :class="failedQueueFiles > 0 && !isQueueActive ? 'bg-amber-500' : 'bg-gradient-to-r from-blue-600 to-emerald-500'"
                            :style="{ width: overallProgressPercent + '%' }"
                        ></div>
                    </div>

                    <!-- File List (Scrollable) -->
                    <div class="p-3 overflow-y-auto divide-y divide-slate-100 flex-1 min-h-[140px] max-h-[300px]">
                        <div v-for="item in uploadQueue" :key="item.id" class="py-2.5 px-2 flex items-center justify-between gap-3 text-xs hover:bg-slate-50 rounded-xl transition">
                            
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <span class="text-base shrink-0">
                                    {{ item.status === 'completed' ? '✅' : (item.status === 'error' ? '❌' : (item.status === 'uploading' ? '🚀' : '⏳')) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-black text-slate-800 truncate uppercase tracking-tight">{{ item.name }}</p>
                                    <div class="flex items-center gap-2 text-[10px] text-slate-400 font-semibold">
                                        <span>{{ item.size_formatted }}</span>
                                        <span v-if="item.status === 'uploading'" class="text-blue-600 font-black">{{ item.progress }}%</span>
                                        <span v-else-if="item.status === 'completed'" class="text-emerald-600 font-bold">Tersimpan</span>
                                        <span v-else-if="item.status === 'error'" class="text-rose-600 font-bold truncate" :title="item.error">{{ item.error }}</span>
                                        <span v-else-if="item.status === 'cancelled'" class="text-slate-400 italic">Dibatalkan</span>
                                        <span v-else class="text-slate-400">Menunggu giliran...</span>
                                    </div>

                                    <!-- Individual progress bar if currently uploading -->
                                    <div v-if="item.status === 'uploading'" class="w-full bg-slate-200 rounded-full h-1 mt-1 overflow-hidden">
                                        <div class="bg-blue-600 h-1 rounded-full transition-all duration-150" :style="{ width: item.progress + '%' }"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Single Cancel Button -->
                            <div class="flex items-center gap-1 pl-1 shrink-0">
                                <button 
                                    v-if="item.status === 'pending' || item.status === 'uploading'"
                                    @click="cancelSingleUpload(item)"
                                    class="w-6 h-6 rounded-md hover:bg-rose-100 text-slate-400 hover:text-rose-600 flex items-center justify-center font-bold text-xs transition cursor-pointer"
                                    title="Batalkan berkas ini">
                                    &times;
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Controls -->
                    <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs gap-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            {{ isQueueActive ? 'Transmisi 1 per 1 (Ramah Server)' : 'Semua Antrean Selesai' }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button 
                                v-if="isQueueActive"
                                type="button"
                                @click="cancelAllUploads"
                                class="px-3 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-[10px] font-black uppercase transition cursor-pointer">
                                Batalkan Semua
                            </button>
                            <button 
                                v-if="!isQueueActive && completedQueueFiles > 0"
                                type="button"
                                @click="clearFinishedQueue"
                                class="px-3 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-[10px] font-black uppercase transition cursor-pointer">
                                Bersihkan
                            </button>
                            <button 
                                v-if="!isQueueActive"
                                type="button"
                                @click="closeUploadDrawer"
                                class="px-3.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[10px] font-black uppercase transition cursor-pointer">
                                Selesai
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </Teleport>

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