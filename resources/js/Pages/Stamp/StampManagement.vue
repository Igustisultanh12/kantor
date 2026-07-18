<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import Swal from 'sweetalert2';
import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';

// Konfigurasi Worker PDF.js agar stabil
const PDF_JS_VERSION = '3.11.174';
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/pdf.worker.min.js`;

const props = defineProps({ 
    approvedRequests: Array, // Antrian (Belum Stempel)
    stampHistory: Array,     // Riwayat (Sudah Stempel)
    settings: Object 
});

const page = usePage();
const commanderStamp = computed(() => props.settings?.commander_stamp || 'settings/stempel_default.png');

// --- STATE KONTROL ---
const isPreviewOpen = ref(false);
const isLoadingPdf = ref(false);
const isPdfReady = ref(false); 
const selectedDoc = ref(null);
const currentPage = ref(1);
const totalPages = ref(0);

// --- STATE STEMPEL AKURAT ---
const stampPos = ref({ x: 50, y: 50 }); 
const stampSize = ref({ width: 130, height: 130 });

const stampForm = useForm({
    request_id: null,
    pdf_file: null,
    x: 0, 
    y: 0, 
    width: 0,
    target_page: 1,
    subject: '', 
    _method: 'POST' 
});

// --- LOGIKA RENDER PDF (SINKRON PRESISI MILITER) ---
const renderPdf = async (pdfData) => {
    isLoadingPdf.value = true;
    isPdfReady.value = false;
    const wrapper = document.getElementById('pdf-render-wrapper');
    if (wrapper) wrapper.innerHTML = ''; 

    try {
        const loadingTask = pdfjsLib.getDocument({ data: pdfData });
        const pdf = await loadingTask.promise;
        totalPages.value = pdf.numPages;

        for (let num = 1; num <= pdf.numPages; num++) {
            const pdfPage = await pdf.getPage(num);
            const viewport = pdfPage.getViewport({ scale: 1.5 });

            // PEMBUATAN CONTAINER HALAMAN (KUNCI AKURASI)
            const pageDiv = document.createElement('div');
            pageDiv.id = `page-container-${num}`;
            // Container relative adalah kunci agar stempel mengunci di dalam halaman PDF
            pageDiv.className = "pdf-page-container relative shadow-2xl bg-white border border-gray-200 mb-10 mx-auto inline-block";
            pageDiv.style.width = viewport.width + 'px';
            pageDiv.style.height = viewport.height + 'px';
            
            const canvas = document.createElement('canvas');
            canvas.id = `canvas-page-${num}`;
            const context = canvas.getContext('2d');
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            pageDiv.appendChild(canvas);
            wrapper.appendChild(pageDiv);

            await pdfPage.render({ canvasContext: context, viewport }).promise;
        }
        
        isPdfReady.value = true;
        await nextTick();
        initInteract();
    } catch (e) {
        Swal.fire('Radar Gagal', 'Gagal memproses dokumen PDF', 'error');
    } finally {
        isLoadingPdf.value = false;
    }
};

const openStamping = async (doc) => {
    selectedDoc.value = doc;
    stampForm.request_id = doc.id;
    stampForm.subject = doc.subject;
    isPreviewOpen.value = true;
    currentPage.value = 1;
    stampPos.value = { x: 50, y: 50 }; 
    await nextTick();
    
    try {
        const response = await fetch(`/storage/${doc.file_path}`);
        const buffer = await response.arrayBuffer();
        await renderPdf(buffer);
    } catch (e) {
        Swal.fire('Error', 'Berkas Radar Tidak Ditemukan', 'error');
    }
};

const initInteract = () => {
    interact('.drag-stamp').draggable({
        inertia: false,
        autoScroll: true,
        listeners: { 
            move(event) { 
                stampPos.value.x += event.dx; 
                stampPos.value.y += event.dy; 
            } 
        }
    }).resizable({
        edges: { right: true, bottom: true, left: true, top: true },
        listeners: { 
            move(event) {
                stampSize.value.width = event.rect.width;
                stampSize.value.height = event.rect.height;
                stampPos.value.x += event.deltaRect.left;
                stampPos.value.y += event.deltaRect.top;
            }
        }
    });
};

const finalizeStamp = () => {
    const targetPageContainer = document.getElementById(`page-container-${currentPage.value}`);
    if (!targetPageContainer) return Swal.fire('Radar Sibuk', 'Halaman belum siap', 'warning');

    const canvas = targetPageContainer.querySelector('canvas');

    // KALIBRASI AKHIR: Menghitung Rasio Koordinat murni terhadap Canvas
    stampForm.x = stampPos.value.x / canvas.width;
    stampForm.y = stampPos.value.y / canvas.height;
    stampForm.width = stampSize.value.width / canvas.width;
    stampForm.target_page = currentPage.value;
    
    stampForm.post(route('signature.apply-stamp'), {
        onSuccess: () => {
            isPreviewOpen.value = false;
            Swal.fire('Berhasil', 'Stempel Sah Telah Dikunci ke Dokumen!', 'success');
        }
    });
};

const handleManualUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    Swal.fire({
        title: 'Konfirmasi Upload',
        text: `Kirim berkas ${file.name} ke antrian stempel?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'SIAP, KIRIM!',
        cancelButtonText: 'BATAL'
    }).then((result) => {
        if (result.isConfirmed) {
            const uploadForm = useForm({
                pdf_file: file,
                subject: file.name.replace(/\.[^/.]+$/, ""),
            });

            uploadForm.post(route('stamp.upload-manual'), {
                forceFormData: true,
                onSuccess: () => {
                    Swal.fire('Berhasil', 'Berkas masuk ke antrian!', 'success');
                    e.target.value = ''; 
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Otoritas Stempel" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-black text-xl text-indigo-950 uppercase italic tracking-tighter">Otoritas Stempel Digital</h2>
                <label class="bg-indigo-600 text-white px-6 py-2 rounded-xl font-black text-[10px] uppercase cursor-pointer hover:bg-indigo-700 transition-all shadow-lg">
                    + Upload PDF Manual
                    <input type="file" @change="handleManualUpload" accept=".pdf" class="hidden" />
                </label>
            </div>
        </template>

        <div class="py-12 px-4 space-y-12">
            <div class="max-w-6xl mx-auto bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="font-black text-indigo-900 uppercase text-[10px] tracking-widest italic">📡 Berkas Menunggu Validasi Stempel</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 font-black text-[9px] text-gray-400 uppercase tracking-widest">
                            <tr>
                                <th class="p-6">Nomor/Perihal</th>
                                <th class="p-6">Status TTD</th>
                                <th class="p-6 text-right">Aksi Strategis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="doc in approvedRequests" :key="doc.id" class="hover:bg-indigo-50/30 transition-all group">
                                <td class="p-6">
                                    <div class="font-black text-indigo-950 text-xs uppercase italic group-hover:text-indigo-600 transition-colors">{{ doc.subject }}</div>
                                    <div class="text-[9px] text-gray-400 font-bold mt-1 uppercase">{{ doc.letter_number || 'NO-REF' }}</div>
                                </td>
                                <td class="p-6 text-[10px]">
                                    <span class="text-emerald-500 font-black uppercase tracking-tighter italic">✓ DISAHKAN KOMANDAN</span>
                                </td>
                                <td class="p-6 text-right">
                                    <button @click="openStamping(doc)" class="bg-indigo-600 text-white px-8 py-3 rounded-2xl font-black text-[9px] uppercase shadow-lg active:scale-95 transition-all">LAKUKAN STEMPEL</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="max-w-6xl mx-auto bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8 border-b border-emerald-50 bg-emerald-50/30 flex justify-between items-center">
                    <h3 class="font-black text-emerald-900 uppercase text-[10px] tracking-widest italic">✅ Riwayat Berkas Selesai Stempel</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="history in stampHistory" :key="history.id" class="hover:bg-emerald-50/20 transition-all">
                                <td class="p-6">
                                    <div class="font-bold text-slate-700 text-xs uppercase">{{ history.subject }}</div>
                                    <span v-if="history.is_manual" class="text-[7px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded font-black">UPLOAD MANUAL</span>
                                </td>
                                <td class="p-6">
                                    <div class="text-[10px] font-black text-slate-900 uppercase">{{ history.operator }}</div>
                                    <div class="text-[8px] text-slate-400 font-bold uppercase mt-1">{{ history.date }}</div>
                                </td>
                                <td class="p-6 text-right">
                                    <a :href="history.file_url" target="_blank" class="bg-white border border-emerald-200 text-emerald-600 px-5 py-2 rounded-xl font-black text-[9px] uppercase hover:bg-emerald-600 hover:text-white transition-all shadow-sm">📄 LIHAT PDF</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isPreviewOpen" class="fixed inset-0 z-[1000] bg-slate-950 flex flex-col animate-in fade-in duration-300">
            <div class="bg-white p-4 flex justify-between items-center shadow-2xl border-b-4 border-indigo-600 z-[1100]">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white font-black italic shadow-lg">S</div>
                    <div>
                        <h3 class="text-[11px] font-black text-slate-800 uppercase italic leading-none">{{ selectedDoc?.subject || stampForm.subject }}</h3>
                        <p class="text-[8px] text-slate-400 font-bold uppercase mt-1 tracking-widest tracking-tighter">Otoritas Pengesahan Stempel Basah Digital</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex items-center bg-slate-50 px-4 rounded-xl border border-slate-200">
                        <span class="text-[9px] font-black text-slate-400 uppercase mr-3 italic">Halaman Target:</span>
                        <input type="number" v-model="currentPage" class="w-14 border-none bg-transparent text-xs font-black text-indigo-600 focus:ring-0" :max="totalPages" min="1" />
                    </div>
                    <button @click="finalizeStamp" class="bg-emerald-600 text-white px-10 py-3 rounded-xl font-black text-[10px] uppercase shadow-xl hover:bg-emerald-700 active:scale-95 transition-all">SAHKAN SEKARANG</button>
                    <button @click="isPreviewOpen = false" class="bg-rose-50 text-rose-600 px-6 py-3 rounded-xl font-black text-[10px] uppercase border border-rose-100 hover:bg-rose-600 transition-all">BATAL</button>
                </div>
            </div>

            <div class="flex-1 overflow-auto bg-slate-900/90 relative p-10 custom-scrollbar" id="stamp-pdf-container">
                <div id="pdf-render-wrapper" class="relative flex flex-col items-center">
                    
                    <div v-if="isPdfReady" 
                         class="drag-stamp absolute z-[3000] cursor-move border-2 border-emerald-500 bg-emerald-50/10 backdrop-blur-[1px] shadow-2xl flex items-center justify-center touch-none"
                         :style="{ 
                            left: stampPos.x + 'px', 
                            top: (stampPos.y + (document.getElementById(`page-container-${currentPage}`)?.offsetTop || 0)) + 'px', 
                            width: stampSize.width + 'px', 
                            height: stampSize.height + 'px' 
                         }">
                        
                        <img :src="'/storage/' + commanderStamp" class="w-full h-full object-contain pointer-events-none opacity-80" alt="Stempel" />
                        
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-emerald-600 rounded-full border-4 border-white shadow-lg cursor-se-resize flex items-center justify-center">
                            <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>

                <div v-if="isLoadingPdf" class="absolute inset-0 flex items-center justify-center bg-slate-950/50 backdrop-blur-sm z-[4000]">
                    <div class="text-center">
                        <div class="w-16 h-16 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
                        <p class="text-white font-black uppercase text-[10px] tracking-[0.4em] animate-pulse">Memindai Berkas Markas Besar...</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#stamp-pdf-container { background-image: radial-gradient(rgba(79, 70, 229, 0.1) 1px, transparent 1px); background-size: 40px 40px; }
.drag-stamp { touch-action: none; user-select: none; }
.custom-scrollbar::-webkit-scrollbar { width: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #4f46e5; border-radius: 20px; }
</style>