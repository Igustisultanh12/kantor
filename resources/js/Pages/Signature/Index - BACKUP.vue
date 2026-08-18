<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';

// Konfigurasi Worker PDF.js Core - Menggunakan jalur eksternal yang stabil
const PDF_JS_VERSION = '3.11.174';
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/pdf.worker.min.js`;

const props = defineProps({ requests: Object });
const page = usePage();
const user = computed(() => page.props.auth.user);
const commanderSignature = computed(() => page.props.settings?.commander_signature || 'signatures/komandan_ttd.png');

const isModalOpen = ref(false);
const isRevisionModalOpen = ref(false);
const isPreviewOpen = ref(false);
const selectedReqId = ref(null);
const isAdjusting = ref(false);
const isLoadingPdf = ref(false);

const signaturePos = ref({ x: 50, y: 150 });
const signatureSize = ref({ width: 100, height: 70 });

const form = useForm({ subject: '', letter_number: '', file: null });
const decisionForm = useForm({ status: '', x: 0, y: 0, width: 0, canvas_width: 0, note: '', file: null, _method: 'PATCH' });

// --- LOGIKA AUTO UPDATE TABEL (0.5 DETIK) ---
let refreshTimer = null;
const autoRefreshData = () => {
    if (!isPreviewOpen.value && !isModalOpen.value && !isRevisionModalOpen.value && !form.processing && !decisionForm.processing) {
        router.reload({ only: ['requests'], preserveScroll: true, preserveState: true });
    }
};
onMounted(() => { refreshTimer = setInterval(autoRefreshData, 500); });
onUnmounted(() => { if (refreshTimer) clearInterval(refreshTimer); });

// --- FUNGSI DOWNLOAD BERKAS ---
const downloadFile = (filePath, subject) => {
    const link = document.createElement('a');
    link.href = `/storage/${filePath}`;
    link.download = `SINDEN_${subject.replace(/\s+/g, '_')}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// --- FUNGSI HAPUS BERKAS ---
const deleteRequest = (id) => {
    Swal.fire({
        title: 'Hapus Berkas?',
        text: "Data akan dihapus permanen dari server!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('signature.destroy', id), {
                onSuccess: () => Swal.fire('Terhapus', 'Berkas berhasil dihapus.', 'success')
            });
        }
    });
};

// --- FUNGSI KHUSUS ADMIN: BERSIHKAN SELURUH DATA ---
const clearAllData = () => {
    Swal.fire({
        title: 'BERSIHKAN SELURUH DATA?',
        text: "Seluruh riwayat pengajuan dan file fisik akan dihapus permanen dari sistem!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'YA, BERSIHKAN TOTAL!',
        cancelButtonText: 'BATAL'
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('signature.clear-all'), {}, {
                onSuccess: () => Swal.fire('Sistem Bersih', 'Seluruh riwayat telah dimusnahkan.', 'success'),
                onError: () => Swal.fire('Gagal', 'Terjadi kesalahan sistem.', 'error')
            });
        }
    });
};

// --- LOGIKA RENDER PDF.JS CORE (PERBAIKAN UNTUK PDF MS WORD) ---
const renderPdfToCanvas = async (pdfData) => {
    isLoadingPdf.value = true;
    try {
        // Penambahan konfigurasi cMap agar font khusus MS Word terbaca
        const loadingTask = pdfjsLib.getDocument({ 
            data: pdfData,
            cMapUrl: `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/cmaps/`,
            cMapPacked: true,
            disableFontFace: false // Mengaktifkan font bawaan PDF
        });
        
        const pdf = await loadingTask.promise;
        const pdfPage = await pdf.getPage(pdf.numPages);
        
        const canvas = document.getElementById('pdf-render-canvas');
        const context = canvas.getContext('2d', { alpha: false }); // Optimasi performa & kejelasan
        const container = document.getElementById('pdf-render-container');

        let availableWidth = container.clientWidth - 32; 
        let targetWidth = availableWidth > 750 ? 750 : availableWidth;
        
        // Optimasi resolusi render
        const viewport = pdfPage.getViewport({ scale: targetWidth / pdfPage.getViewport({scale: 1}).width });

        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const renderContext = {
            canvasContext: context,
            viewport: viewport,
            intent: 'print' // Memaksa render seluruh layer dokumen
        };

        await pdfPage.render(renderContext).promise;
    } catch (error) {
        console.error("Render Error:", error);
        Swal.fire('Error', 'Radar gagal membaca dokumen. Pastikan PDF tidak diproteksi password.', 'error');
    } finally {
        isLoadingPdf.value = false;
    }
};

const openPreview = async (req) => {
    selectedReqId.value = req.id;
    isPreviewOpen.value = true;
    isAdjusting.value = false;
    signaturePos.value = { x: 20, y: 100 };
    await nextTick();
    try {
        isLoadingPdf.value = true;
        // Tambahkan timestamp untuk memintas cache browser yang mungkin menyimpan file kosong
        const response = await fetch(`${window.location.origin}/storage/${req.file_path}?t=${Date.now()}`);
        const buffer = await response.arrayBuffer();
        await renderPdfToCanvas(buffer);
    } catch (e) {
        Swal.fire('Error', 'File tidak dapat diakses di server', 'error');
    } finally {
        isLoadingPdf.value = false;
    }
};

const enableDrag = () => {
    isAdjusting.value = true;
    nextTick(() => {
        interact('.drag-signature').draggable({
            inertia: false,
            modifiers: [interact.modifiers.restrictRect({ restriction: '#pdf-render-canvas', endOnly: true })],
            listeners: { move(event) { signaturePos.value.x += event.dx; signaturePos.value.y += event.dy; } }
        }).resizable({
            edges: { right: true, bottom: true },
            listeners: { move(event) {
                signatureSize.value.width = event.rect.width;
                signatureSize.value.height = event.rect.height;
                signaturePos.value.x += event.deltaRect.left;
                signaturePos.value.y += event.deltaRect.top;
            }},
            modifiers: [interact.modifiers.restrictSize({ min: { width: 50, height: 35 } })]
        });
    });
};

const handleDecision = (status) => {
    if (status === 'rejected') {
        Swal.fire({
            title: 'Tolak Berkas',
            text: "Berikan alasan penolakan untuk staf:",
            input: 'textarea',
            showCancelButton: true,
            confirmButtonText: 'Kirim Penolakan',
            confirmButtonColor: '#e11d48',
            inputValidator: (value) => { if (!value) return 'Alasan wajib diisi!' }
        }).then((result) => {
            if (result.isConfirmed) {
                decisionForm.status = 'rejected';
                decisionForm.note = result.value;
                decisionForm.patch(route('signature.update', selectedReqId.value), {
                    onSuccess: () => { isPreviewOpen.value = false; Swal.fire('Berhasil', 'Berkas ditolak.', 'success'); }
                });
            }
        });
        return;
    }

    const canvas = document.getElementById('pdf-render-canvas');
    
    decisionForm.status = 'approved';
    decisionForm.x = parseFloat(signaturePos.value.x / canvas.width); 
    decisionForm.y = parseFloat(signaturePos.value.y / canvas.height);
    decisionForm.width = parseFloat(signatureSize.value.width / canvas.width);
    decisionForm.canvas_width = canvas.width;
    
    decisionForm.patch(route('signature.update', selectedReqId.value), {
        onSuccess: () => { 
            isPreviewOpen.value = false; 
            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'TTD Terpasang Presisi', timer: 2000, showConfirmButton: false }); 
        }
    });
};

const submitRequest = () => {
    form.post(route('signature.store'), {
        onSuccess: () => { isModalOpen.value = false; form.reset(); Swal.fire('Berhasil', 'Berkas dikirim.', 'success'); },
    });
};

const submitRevision = () => {
    decisionForm.post(route('signature.update', selectedReqId.value), {
        forceFormData: true,
        onSuccess: () => { isRevisionModalOpen.value = false; decisionForm.reset(); Swal.fire('Berhasil', 'Revisi dikirim.', 'success'); },
    });
};

const closeModals = () => {
    isModalOpen.value = false;
    isRevisionModalOpen.value = false;
    form.reset();
    decisionForm.reset();
};

const getStatusClass = (status) => {
    if (status === 'approved') return 'bg-emerald-50 text-emerald-700 border-emerald-100 shadow-sm';
    if (status === 'rejected') return 'bg-rose-50 text-rose-700 border-rose-100 shadow-sm';
    return 'bg-amber-50 text-amber-700 border-amber-100 shadow-sm';
};

const printPdf = () => {
    const canvas = document.getElementById('pdf-render-canvas');
    if (!canvas) {
        Swal.fire('Error', 'Dokumen belum siap.', 'error');
        return;
    }

    const qualityMultiplier = 3; 
    const tempCanvas = document.createElement('canvas');
    const tempCtx = tempCanvas.getContext('2d');

    tempCanvas.width = canvas.width * qualityMultiplier;
    tempCanvas.height = canvas.height * qualityMultiplier;
    tempCtx.scale(qualityMultiplier, qualityMultiplier);
    tempCtx.drawImage(canvas, 0, 0);

    const dataUrl = tempCanvas.toDataURL('image/jpeg', 1.0);

    let iframe = document.getElementById('print-iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'print-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);
    }

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <html>
            <head>
                <title>Cetak Dokumen HD</title>
                <style>
                    @page { size: A4; margin: 0; }
                    body { margin: 0; padding: 0; background: #fff; text-align: center; }
                    img { 
                        width: 210mm; 
                        height: auto;
                        image-rendering: -webkit-optimize-contrast;
                    }
                </style>
            </head>
            <body>
                <img src="${dataUrl}">
                <script> window.onload = function() {
                        setTimeout(() => {
                            window.focus();
                            window.print();
                        }, 200);
                    }
                <\/script>
            </body>
        </html>
    `);
    doc.close();
};
</script>

<template>
    <Head title="SI SINDEN - Mobile Verified" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 px-2 text-left">
                <div class="flex flex-col gap-1">
                    <h2 class="font-black text-xl text-indigo-950 uppercase italic tracking-tighter sm:tracking-widest">SISINDEN <span class="text-indigo-600">M</span></h2>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></div>
                        <span class="text-[9px] font-black text-emerald-600 uppercase italic leading-none">Live 0.5s active</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <button v-if="user.role !== 'komandan'" @click="isModalOpen = true"class="hidden sm:block bg-indigo-600 text-white px-8 py-4 rounded-2xl font-black text-[10px] uppercase shadow-xl transition-all active:scale-95 text-center">
                        + Registrasi Berkas Baru
                    </button>
                    <button v-if="user.role === 'admin'" @click="clearAllData"class="w-full sm:w-auto bg-white text-rose-600 border border-rose-100 px-8 py-3 rounded-2xl font-black text-[9px] uppercase hover:bg-rose-50 transition-all italic tracking-widest shadow-sm text-center"> Bersihkan Riwayat Sistem
                    </button>
                </div>
            </div>
        </template>

        <div class="py-4 sm:py-6 px-2 sm:px-4 pb-32 sm:pb-6 text-left">
            <div class="hidden sm:block bg-white/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-indigo-50/50 uppercase font-black text-indigo-400 text-[10px] tracking-widest">
                            <tr>
                                <th class="p-6">Timestamp</th>
                                <th class="p-6">Subjek Berkas</th>
                                <th class="p-6 text-center">Status</th>
                                <th class="p-6 text-right">Otoritas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-50 uppercase font-bold text-slate-800 text-[11px]">
                            <tr v-for="req in requests.data" :key="req.id" class="hover:bg-white transition-all group">
                                <td class="p-6 text-slate-400 font-mono text-left">{{ new Date(req.created_at).toLocaleString('id-ID') }}</td>
                                <td class="p-6 italic truncate max-w-[200px] text-left">
                                    {{ req.subject }}
                                    <div v-if="req.note" class="text-[8px] text-rose-500 lowercase font-normal italic mt-1 text-left">Alasan: {{ req.note }}</div>
                                </td>
                                <td class="p-6 text-center">
                                    <span :class="getStatusClass(req.status)" class="px-5 py-2 rounded-full text-[9px] font-black border uppercase italic shadow-sm">
                                        {{ req.status }}
                                    </span>
                                </td>
                                <td class="p-6 text-right flex justify-end gap-2">
                                    <button @click="openPreview(req)" class="bg-white text-indigo-600 border border-indigo-100 px-5 py-2.5 rounded-xl text-[9px] font-black uppercase shadow-sm">Preview</button>
                                    <button v-if="req.status === 'approved'" 
                                        @click="downloadFile(req.file_path, req.subject)"class="bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-[9px] font-black uppercase shadow-lg hover:bg-emerald-700 transition-all text-center"> Download
                                    </button>
                                    <button v-if="user.role === 'admin' || user.id === req.user_id" 
                                        @click="deleteRequest(req.id)"class="bg-rose-50 text-rose-600 border border-rose-100 px-3 py-2.5 rounded-xl text-[9px] font-black uppercase hover:bg-rose-600 hover:text-white transition-all text-center">Hapus</button>
                                    <button v-if="req.status === 'rejected' && user.id === req.user_id" 
                                        @click="isRevisionModalOpen = true; selectedReqId = req.id"class="bg-rose-600 text-white px-4 py-2 rounded-xl text-[9px] font-black uppercase shadow-lg shadow-rose-100 text-center">Revisi</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sm:hidden space-y-4">
                <div v-for="req in requests.data" :key="req.id"class="bg-white rounded-[2rem] p-5 shadow-sm border border-slate-100 active:scale-[0.98] transition-all text-left">
                    <div class="flex justify-between items-start mb-3">
                        <span :class="getStatusClass(req.status)" class="text-[8px] font-black uppercase px-2.5 py-1 rounded-lg border italic shadow-sm">
                            {{ req.status }}
                        </span>
                        <span class="text-[9px] font-mono text-slate-400">{{ new Date(req.created_at).toLocaleDateString() }}</span>
                    </div>
                    <h4 class="text-sm font-black text-indigo-950 uppercase italic leading-tight mb-2 text-left">{{ req.subject }}</h4>
                    <div v-if="req.note" class="bg-rose-50 p-2.5 rounded-xl border border-rose-100 mb-3 text-left">
                        <p class="text-[9px] text-rose-600 font-bold leading-relaxed italic"> {{ req.note }}</p>
                    </div>
                    <div class="flex justify-between items-center mt-4">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Oleh: {{ req.user?.name }}</div>
                        <div class="flex gap-2">
                            <button v-if="req.status === 'approved'" 
                                @click.stop="downloadFile(req.file_path, req.subject)"class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-[8px] font-black uppercase italic shadow-md text-center"> Download
                            </button>
                            <button @click.stop="openPreview(req)" class="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl text-[8px] font-black uppercase italic text-center">Lihat</button>
                            <button v-if="user.role === 'admin' || user.id === req.user_id" @click.stop="deleteRequest(req.id)" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-[8px] font-black uppercase italic text-center">Hapus</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button v-if="user.role !== 'komandan' && !isPreviewOpen" @click="isModalOpen = true"class="sm:hidden fixed bottom-24 right-6 w-16 h-16 bg-indigo-600 text-white rounded-3xl shadow-2xl shadow-indigo-300 flex items-center justify-center active:scale-90 transition-all z-[100] border-4 border-white">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        </button>

        <div v-if="isPreviewOpen" class="fixed inset-0 z-[150] flex flex-col bg-slate-950 animate-in fade-in duration-300">
            <div class="bg-white border-b px-4 py-4 flex justify-between items-center shadow-xl shrink-0 text-left">
                <div class="flex flex-col truncate max-w-[50%]">
                    <span class="text-indigo-600 text-[8px] font-black uppercase tracking-widest leading-none mb-1 text-left">Otorisasi Dokumen</span>
                    <h3 class="text-[10px] sm:text-xs font-black text-slate-800 uppercase italic truncate leading-none text-left">{{ requests.data.find(r => r.id === selectedReqId)?.subject }}</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="printPdf" class="bg-emerald-600 text-white px-3 sm:px-5 py-2.5 rounded-xl text-[9px] font-black uppercase shadow-lg flex items-center gap-2 text-center">
                        <span></span> <span class="hidden sm:inline">Cetak</span>
                    </button>
                    <button v-if="!isAdjusting && (user.role === 'komandan' || user.role === 'admin')" @click="enableDrag" class="bg-indigo-600 text-white px-3 sm:px-5 py-2.5 rounded-xl text-[9px] font-black uppercase shadow-lg text-center">Atur TTD</button>
                    <button @click="isPreviewOpen = false" class="bg-rose-50 text-rose-600 px-4 py-2.5 rounded-xl text-[9px] font-black uppercase border border-rose-100 text-center">X</button>
                </div>
            </div>

            <div class="flex-1 relative overflow-auto bg-slate-900/50 p-2 sm:p-12 custom-scrollbar flex justify-center items-start text-left" id="pdf-render-container">
                <div v-if="isLoadingPdf" class="absolute inset-0 z-[200] flex items-center justify-center bg-slate-950/80">
                    <div class="animate-spin border-4 border-indigo-500 border-t-transparent rounded-full w-10 h-10"></div>
                </div>
                <div class="relative bg-white shadow-2xl overflow-hidden rounded-sm" style="line-height: 0;">
                    <canvas id="pdf-render-canvas"></canvas>
                    <div v-if="isAdjusting" class="drag-signature absolute z-[200] cursor-move border-2 border-indigo-600 bg-indigo-50/20 backdrop-blur-[1px] shadow-2xl flex items-center justify-center touch-none text-left"
                         :style="{ left: signaturePos.x + 'px', top: signaturePos.y + 'px', width: signatureSize.width + 'px', height: signatureSize.height + 'px' }">
                        <img :src="'/storage/' + commanderSignature" class="w-full h-full object-contain pointer-events-none opacity-90" alt="TTD" />
                        <div class="absolute -bottom-2 -right-2 w-7 h-7 bg-indigo-600 rounded-full border-4 border-white shadow-lg cursor-se-resize flex items-center justify-center text-center">
                             <div class="w-1.5 h-1.5 bg-white rounded-full text-center"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="user.role === 'komandan' || user.role === 'admin'" class="bg-white border-t p-4 sm:p-6 flex justify-center gap-3 shrink-0 shadow-2xl pb-10 text-center">
                <button v-if="isAdjusting" @click="handleDecision('approved')" :disabled="decisionForm.processing" class="flex-[2] max-w-[400px] flex items-center justify-center gap-2 bg-emerald-600 text-white py-5 rounded-[1.5rem] font-black text-[10px] sm:text-xs uppercase shadow-xl active:scale-95 transition-all text-center">KONFIRMASI TTD</button>
                <button @click="handleDecision('rejected')" :disabled="decisionForm.processing" class="flex-1 max-w-[200px] bg-rose-500 text-white py-5 rounded-2xl font-black text-[10px] uppercase shadow-lg text-center">TOLAK</button>
                <button v-if="isAdjusting" @click="isAdjusting = false" class="flex-1 max-w-[150px] bg-slate-100 text-slate-500 px-6 py-5 rounded-2xl font-black text-[10px] uppercase border border-slate-200 text-center">BATAL</button>
            </div>
        </div>

        <transition name="modal-pop">
            <div v-if="isModalOpen" class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-indigo-950/70 backdrop-blur-sm text-left">
                <div class="relative bg-white w-full max-w-md rounded-t-[3rem] sm:rounded-[3rem] p-10 shadow-2xl border border-white/20 overflow-hidden text-left">
                    <div v-if="form.progress" class="absolute top-0 left-0 w-full h-1.5 bg-slate-100 text-left">
                        <div class="h-full bg-indigo-600 transition-all duration-300 shadow-[0_0_15px_#4f46e5]" :style="{ width: form.progress.percentage + '%' }"></div>
                    </div>
                    <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8 sm:hidden text-center"></div>
                    <h3 class="font-black text-xl text-indigo-950 uppercase italic text-center mb-8">Registrasi Berkas</h3>
                    <form @submit.prevent="submitRequest" class="space-y-6 text-left">
                        <input v-model="form.subject" type="text" class="w-full rounded-2xl border-slate-100 bg-slate-50 h-14 text-[11px] font-bold px-6 uppercase shadow-sm focus:ring-4 focus:ring-indigo-600/10 text-left" placeholder="PERIHAL BERKAS..." required>
                        <div class="relative group w-full h-24 border-2 border-dashed border-indigo-100 rounded-3xl bg-indigo-50/20 flex items-center px-6 cursor-pointer overflow-hidden transition-all hover:border-indigo-500 text-left">
                            <input type="file" @input="form.file = $event.target.files[0]" accept=".pdf" class="absolute inset-0 opacity-0 z-10 text-left" required />
                            <div class="flex flex-col truncate w-full items-center text-center">
                                <span class="text-[10px] font-black text-indigo-950 uppercase italic text-center">{{ form.file ? 'BERKAS SIAP' : 'PILIH BERKAS (PDF)' }}</span>
                                <span class="text-[8px] text-slate-400 font-bold truncate mt-1 italic text-center">{{ form.file?.name }}</span>
                            </div>
                        </div>
                        <button type="submit" :disabled="form.processing" class="w-full bg-indigo-600 text-white py-5 rounded-[2rem] font-black text-[10px] uppercase shadow-xl active:scale-95 transition-all text-center">KIRIM SEKARANG</button>
                        <button type="button" @click="isModalOpen = false" class="w-full text-[9px] font-black uppercase text-slate-400 mb-4 text-center">Batalkan</button>
                    </form>
                </div>
            </div>
        </transition>

        <transition name="modal-pop">
            <div v-if="isRevisionModalOpen" class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-rose-950/70 backdrop-blur-sm text-left">
                <div class="relative bg-white w-full max-w-md rounded-t-[3rem] sm:rounded-[3rem] p-10 shadow-2xl border border-white/20 overflow-hidden animate-in slide-in-from-bottom duration-300 text-left">
                    <div v-if="decisionForm.progress" class="absolute top-0 left-0 w-full h-1.5 bg-slate-100 text-left">
                        <div class="h-full bg-rose-500 transition-all duration-300 shadow-[0_0_10px_#f43f5e]" :style="{ width: decisionForm.progress.percentage + '%' }"></div>
                    </div>
                    <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8 sm:hidden text-center"></div>
                    <div class="text-center mb-8">
                        <h3 class="font-black text-xl text-indigo-950 uppercase italic tracking-tighter text-center">Perbaikan Berkas</h3>
                        <p class="text-[9px] text-rose-500 font-bold uppercase mt-2 italic tracking-widest text-center">Unggah file revisi terbaru</p>
                    </div>
                    <form @submit.prevent="submitRevision" class="space-y-6 text-left">
                        <div class="relative w-full h-32 border-2 border-dashed border-rose-200 rounded-3xl bg-rose-50/20 flex items-center px-8 cursor-pointer overflow-hidden transition-all hover:border-rose-500 text-left">
                            <input type="file" @input="decisionForm.file = $event.target.files[0]" accept=".pdf" class="absolute inset-0 opacity-0 cursor-pointer z-10 text-left" required />
                            <div class="flex flex-col items-center w-full text-center">
                                <span class="text-[10px] font-black text-rose-600 uppercase italic text-center">{{ decisionForm.file ? 'FILE REVISI SIAP' : 'PILIH FILE REVISI (PDF)' }}</span>
                                <span class="text-[8px] text-slate-400 font-bold truncate mt-2 italic text-center">{{ decisionForm.file?.name }}</span>
                            </div>
                        </div>
                        <button type="submit" :disabled="decisionForm.processing" class="w-full bg-rose-600 text-white py-5 rounded-[2rem] font-black text-[10px] uppercase shadow-xl active:scale-95 transition-all text-center">KIRIM PERBAIKAN</button>
                        <button type="button" @click="closeModals" class="w-full text-[9px] font-black uppercase text-slate-400 mb-4 text-center">Nanti Saja</button>
                    </form>
                </div>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>

<style scoped>
#pdf-render-canvas { display: block; margin: 0 auto; background-color: #fff; }
.drag-signature { touch-action: none; user-select: none; }
.custom-scrollbar::-webkit-scrollbar { width: 0px; }
.modal-pop-enter-active { animation: sultan-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
@keyframes sultan-pop { 
    from { opacity: 0; transform: translateY(100px); } 
    to { opacity: 1; transform: translateY(0); } 
}
@media (max-width: 640px) { .drag-signature { border-width: 1px; box-shadow: 0 0 20px rgba(79,70,229,0.3); } }
</style>