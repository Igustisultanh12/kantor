<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ref, shallowRef, markRaw, computed, nextTick, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';

// Konfigurasi Worker PDF.js Core
const PDF_JS_VERSION = '3.11.174';
pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/pdf.worker.min.js`;

const props = defineProps({ 
  requests: Object,
  skhppRequests: Object
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const commanderSignature = computed(() => page.props.settings?.commander_signature || 'signatures/komandan_ttd.png');

// State Active Tab
const activeTab = ref('skhpp'); // 'skhpp' | 'pdf'

// State Modals
const isModalOpen = ref(false);
const isRevisionModalOpen = ref(false);
const isPreviewOpen = ref(false);
const isSkhppPreviewOpen = ref(false);

const selectedReqId = ref(null);
const selectedSkhpp = ref(null);

const isAdjusting = ref(false);
const isLoadingPdf = ref(false);

// Multi-Page PDF State (Gunakan shallowRef agar tidak di-Proxy oleh Vue 3)
const currentPage = ref(1);
const totalPages = ref(1);
const pdfDoc = shallowRef(null);

// Signature Drag Position
// Signature Drag Position (Default 1:1 Presisi e-Materai Standard)
const signaturePos = ref({ x: 100, y: 100 });
const signatureSize = ref({ width: 90, height: 90 });
const pageSignatures = ref({});

// Forms
const isOperatorConfiguring = ref(false);
const form = useForm({ 
  subject: '', 
  document_title: '', 
  letter_number: '', 
  person_name: '', 
  pangkat_nrp: '', 
  jabatan: '', 
  peruntukan: '', 
  file: null,
  x: 0.58,
  y: 0.72,
  width: 0.15,
  target_page: 1,
  pages_data: null
});
const decisionForm = useForm({ 
  status: '', 
  x: 0, 
  y: 0, 
  width: 0, 
  canvas_width: 0, 
  target_page: 1, 
  apply_to_all: false,
  pages_data: null,
  note: '', 
  file: null, 
  _method: 'PATCH' 
});

// --- LOGIKA AUTO REFRESH TABEL ---
let refreshTimer = null;
const autoRefreshData = () => {
  if (!isPreviewOpen.value && !isSkhppPreviewOpen.value && !isModalOpen.value && !isRevisionModalOpen.value && !form.processing && !decisionForm.processing) {
    router.reload({ only: ['requests', 'skhppRequests'], preserveScroll: true, preserveState: true });
  }
};
onMounted(() => { refreshTimer = setInterval(autoRefreshData, 1000); });
onUnmounted(() => { if (refreshTimer) clearInterval(refreshTimer); });

// --- LOGIKA SKHPP APPROVE / REJECT ---
const approveSkhpp = (skhpp) => {
  const defaultSeq = skhpp.nomor_urut || '';
  const katCode = (skhpp.kategori_personel === 'perusahaan') ? 'SKHPP-P (Mitra Kerja/Perusahaan)' : 'SKHPP-D (Dinas Militer & PNS)';

  Swal.fire({
    title: 'OTORISASI & TERBITKAN SKHPP',
    html: `
      <div class="text-left text-xs space-y-3 font-sans">
        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200">
          <p class="font-black text-blue-600 text-[10px] uppercase mb-0.5">${katCode}</p>
          <p class="font-bold text-slate-800">${skhpp.nama}</p>
          <p class="text-slate-500">${skhpp.pangkat_korps_nrp || skhpp.nik || '-'}</p>
        </div>

        <div>
          <label class="block font-bold uppercase mb-1 text-slate-700 text-[10px]">Nomor Urut ${skhpp.kategori_personel === 'perusahaan' ? 'SKHPP-P' : 'SKHPP-D'} (Isi jika mau loncati nomor)</label>
          <input id="swal-custom-seq" type="number" value="${defaultSeq}" class="w-full text-xs font-bold p-2.5 border rounded-xl" placeholder="Kosongkan untuk nomor urut otomatis ${skhpp.kategori_personel === 'perusahaan' ? 'SKHPP-P' : 'SKHPP-D'}" />
          <span class="text-[9px] text-slate-400 mt-1 block">*Penomoran SKHPP-P dan SKHPP-D terpisah & independen.</span>
        </div>

        <p class="text-emerald-700 font-bold text-[10px] bg-emerald-50 p-2 rounded-lg border border-emerald-200"> Diterbitkan nomor resmi ${katCode}, QR Code TTD Komandan, dan tersinkron ke Buku Agenda.
        </p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'YA, SETUJUI & TERBITKAN',
    cancelButtonText: 'BATAL',
    preConfirm: () => {
      const seqVal = document.getElementById('swal-custom-seq').value;
      return { custom_nomor_urut: seqVal };
    }
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('skhpp.approve', skhpp.id), {
        custom_nomor_urut: res.value.custom_nomor_urut
      }, {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('BERHASIL DISAHKAN', 'SKHPP Resmi telah ditandatangani Komandan & tersinkron ke Buku Agenda.', 'success');
        }
      });
    }
  });
};

const rejectSkhpp = (skhpp) => {
  Swal.fire({
    title: 'TOLAK / MINTA REVISI SKHPP',
    text: "Tuliskan catatan revisi untuk staf/operator pengaju:",
    input: 'textarea',
    inputPlaceholder: 'Tuliskan alasan penolakan atau instruksi revisi...',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    confirmButtonText: 'KIRIM REVISI',
    cancelButtonText: 'BATAL',
    inputValidator: (val) => { if (!val) return 'Catatan revisi wajib diisi!'; }
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('skhpp.reject', skhpp.id), { catatan_revisi: res.value }, {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('REVISI DIKIRIM', 'Catatan revisi berhasil dikirim ke pengaju.', 'warning');
        }
      });
    }
  });
};

const openSkhppPreviewModal = (skhpp) => {
  selectedSkhpp.value = skhpp;
  isSkhppPreviewOpen.value = true;
};

// --- LOGIKA MULTI-PAGE PDF & TTD DRAGGABLE ---
const renderPdfPage = async (pageNumber) => {
  if (!pdfDoc.value) return;
  isLoadingPdf.value = true;
  try {
    const pdfPage = await pdfDoc.value.getPage(pageNumber);
    const canvas = document.getElementById('pdf-render-canvas');
    const context = canvas.getContext('2d', { alpha: false });
    const container = document.getElementById('pdf-render-container');

    let availableWidth = container.clientWidth - 32;
    let targetWidth = availableWidth > 750 ? 750 : availableWidth;

    const viewport = pdfPage.getViewport({ scale: targetWidth / pdfPage.getViewport({ scale: 1 }).width });

    canvas.width = viewport.width;
    canvas.height = viewport.height;

    const renderContext = {
      canvasContext: context,
      viewport: viewport,
      intent: 'print'
    };

    await pdfPage.render(renderContext).promise;
  } catch (err) {
    console.error("Render Page Error:", err);
  } finally {
    isLoadingPdf.value = false;
  }
};

const isCurrentPageActive = computed(() => {
  return pageSignatures.value && !!pageSignatures.value[currentPage.value];
});

const activePagesList = computed(() => {
  if (!pageSignatures.value) return [];
  return Object.keys(pageSignatures.value).map(Number).sort((a, b) => a - b);
});

const saveCurrentPagePos = () => {
  const canvas = document.getElementById('pdf-render-canvas');
  if (canvas && isAdjusting.value && pageSignatures.value && pageSignatures.value[currentPage.value]) {
    pageSignatures.value[currentPage.value] = {
      x: parseFloat(signaturePos.value.x / canvas.width),
      y: parseFloat(signaturePos.value.y / canvas.height),
      width: parseFloat(signatureSize.value.width / canvas.width)
    };
  }
};

const removeCurrentPageSignature = () => {
  if (pageSignatures.value && pageSignatures.value[currentPage.value]) {
    const updated = { ...pageSignatures.value };
    delete updated[currentPage.value];
    pageSignatures.value = updated;
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: `TTD Halaman ${currentPage.value} Dihapus`,
      showConfirmButton: false,
      timer: 1500
    });
  }
};

const addCurrentPageSignature = () => {
  const canvas = document.getElementById('pdf-render-canvas');
  const canvasW = canvas ? canvas.width : 500;
  const canvasH = canvas ? canvas.height : 700;

  const updated = { ...pageSignatures.value };
  updated[currentPage.value] = {
    x: 0.58,
    y: 0.72,
    width: 0.15
  };
  pageSignatures.value = updated;
  loadPagePos();
  enableDrag();
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: `TTD Ditambahkan di Halaman ${currentPage.value}`,
    showConfirmButton: false,
    timer: 1500
  });
};

const loadPagePos = () => {
  const canvas = document.getElementById('pdf-render-canvas');
  if (!canvas) return;

  if (pageSignatures.value && pageSignatures.value[currentPage.value]) {
    const pData = pageSignatures.value[currentPage.value];
    signaturePos.value = { x: pData.x * canvas.width, y: pData.y * canvas.height };
    const wPx = pData.width * canvas.width;
    signatureSize.value = { width: wPx, height: wPx };
  } else {
    signaturePos.value = {
      x: Math.max(10, canvas.width * 0.58),
      y: Math.max(10, canvas.height * 0.72)
    };
    signatureSize.value = { width: 90, height: 90 };
  }
};

const changePdfPage = async (delta) => {
  saveCurrentPagePos();
  const newPage = currentPage.value + delta;
  if (newPage >= 1 && newPage <= totalPages.value) {
    currentPage.value = newPage;
    await renderPdfPage(currentPage.value);
    loadPagePos();
  }
};

const onOperatorFileSelect = async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.file = file;

  try {
    const buffer = await file.arrayBuffer();
    const loadingTask = pdfjsLib.getDocument({
      data: buffer,
      cMapUrl: `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/cmaps/`,
      cMapPacked: true
    });
    const rawPdf = await loadingTask.promise;
    pdfDoc.value = markRaw(rawPdf);
    totalPages.value = pdfDoc.value.numPages;
    currentPage.value = 1; // Default ke halaman pertama (halaman 1)
    pageSignatures.value = {};
  } catch (err) {
    console.error("Gagal membaca PDF untuk pratinjau:", err);
  }
};

const openOperatorPosPicker = async () => {
  if (!form.file) {
    Swal.fire('Perhatian', 'Harap pilih berkas PDF terlebih dahulu.', 'warning');
    return;
  }
  isModalOpen.value = false;
  isOperatorConfiguring.value = true;
  isPreviewOpen.value = true;
  isAdjusting.value = true;
  currentPage.value = 1; // Buka dari halaman 1
  await nextTick();
  await renderPdfPage(currentPage.value);

  if (!pageSignatures.value || Object.keys(pageSignatures.value).length === 0) {
    pageSignatures.value = {
      [currentPage.value]: { x: 0.58, y: 0.72, width: 0.15 }
    };
  }

  loadPagePos();
  enableDrag();
};

const saveOperatorPos = () => {
  saveCurrentPagePos();
  const canvas = document.getElementById('pdf-render-canvas');
  if (canvas) {
    form.x = parseFloat(signaturePos.value.x / canvas.width);
    form.y = parseFloat(signaturePos.value.y / canvas.height);
    form.width = parseFloat(signatureSize.value.width / canvas.width);
    form.target_page = currentPage.value;
    form.pages_data = Object.keys(pageSignatures.value).length > 0 ? JSON.stringify(pageSignatures.value) : null;
  }
  isPreviewOpen.value = false;
  isOperatorConfiguring.value = false;
  isModalOpen.value = true;
  Swal.fire({ icon: 'success', title: 'Posisi TTD Di-Set', text: `Letak TTD Komandan diset pada ${activePagesList.value.length} Halaman (${activePagesList.value.join(', ')})`, timer: 2000, showConfirmButton: false });
};

const closeOperatorPosPicker = () => {
  isPreviewOpen.value = false;
  if (isOperatorConfiguring.value) {
    isOperatorConfiguring.value = false;
    isModalOpen.value = true;
  }
};

const openPdfPreview = async (req) => {
  selectedReqId.value = req.id;
  isOperatorConfiguring.value = false;
  isPreviewOpen.value = true;
  isAdjusting.value = true;
  currentPage.value = 1; // Buka dari halaman 1 untuk Komandan
  pageSignatures.value = {};
  if (req.pages_data) {
    try {
      pageSignatures.value = typeof req.pages_data === 'string' ? JSON.parse(req.pages_data) : req.pages_data;
    } catch (e) {}
  }
  if (!pageSignatures.value || Object.keys(pageSignatures.value).length === 0) {
    pageSignatures.value = {
      [req.target_page || 1]: { x: req.x || 0.58, y: req.y || 0.72, width: req.width || 0.15 }
    };
  }
  signatureSize.value = { width: 90, height: 90 };

  await nextTick();
  try {
    isLoadingPdf.value = true;
    const response = await fetch(`${window.location.origin}/storage/${req.file_path}?t=${Date.now()}`);
    const buffer = await response.arrayBuffer();

    const loadingTask = pdfjsLib.getDocument({
      data: buffer,
      cMapUrl: `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/${PDF_JS_VERSION}/cmaps/`,
      cMapPacked: true,
      disableFontFace: false
    });

    const rawPdf = await loadingTask.promise;
    pdfDoc.value = markRaw(rawPdf);
    totalPages.value = pdfDoc.value.numPages;
    currentPage.value = 1; // Selalu tampilkan dari halaman 1

    await renderPdfPage(currentPage.value);
    loadPagePos();
    enableDrag();
  } catch (e) {
    Swal.fire('Error', 'File PDF tidak dapat dibaca atau diproteksi password.', 'error');
  } finally {
    isLoadingPdf.value = false;
  }
};

const enableDrag = () => {
  isAdjusting.value = true;
  saveCurrentPagePos();
  nextTick(() => {
    const canvas = document.getElementById('pdf-render-canvas');
    if (canvas) {
      if (signaturePos.value.x <= 50 || signaturePos.value.y <= 100) {
        signaturePos.value = {
          x: Math.max(10, canvas.width * 0.58),
          y: Math.max(10, canvas.height * 0.72)
        };
      }
    }

    interact('.drag-signature').draggable({
      inertia: false,
      modifiers: [interact.modifiers.restrictRect({ restriction: '#pdf-render-canvas', endOnly: true })],
      listeners: { 
        move(event) { 
          signaturePos.value.x += event.dx; 
          signaturePos.value.y += event.dy;
          saveCurrentPagePos();
        } 
      }
    }).resizable({
      edges: { right: true, bottom: true },
      listeners: { move(event) {
        const squareSize = Math.max(event.rect.width, event.rect.height);
        signatureSize.value.width = squareSize;
        signatureSize.value.height = squareSize;
        signaturePos.value.x += event.deltaRect.left;
        signaturePos.value.y += event.deltaRect.top;
        saveCurrentPagePos();
      }},
      modifiers: [interact.modifiers.restrictSize({ min: { width: 50, height: 50 }, max: { width: 250, height: 250 } })]
    });
  });
};

const handlePdfDecision = (status, applyAll = false) => {
  if (status === 'rejected') {
    Swal.fire({
      title: 'Tolak Berkas PDF',
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

  saveCurrentPagePos();
  const canvas = document.getElementById('pdf-render-canvas');
  const configuredCount = Object.keys(pageSignatures.value).length;
  
  decisionForm.status = 'approved';
  decisionForm.x = parseFloat(signaturePos.value.x / canvas.width); 
  decisionForm.y = parseFloat(signaturePos.value.y / canvas.height);
  decisionForm.width = parseFloat(signatureSize.value.width / canvas.width);
  decisionForm.canvas_width = canvas.width;
  decisionForm.target_page = currentPage.value;
  decisionForm.apply_to_all = applyAll;
  decisionForm.pages_data = configuredCount > 0 ? JSON.stringify(pageSignatures.value) : null;
  
  let textMsg = `TTD Terpasang Presisi pada Halaman ${currentPage.value}`;
  if (configuredCount > 1 && !applyAll) {
    textMsg = `TTD Terpasang Presisi pada ${configuredCount} Halaman Berbeda dengan Letak Custom!`;
  } else if (applyAll) {
    textMsg = `TTD Terpasang Presisi pada SELURUH HALAMAN (1 s.d. ${totalPages.value})`;
  }

  decisionForm.patch(route('signature.update', selectedReqId.value), {
    onSuccess: () => { 
      isPreviewOpen.value = false; 
      Swal.fire({ icon: 'success', title: 'Berhasil', text: textMsg, timer: 2500, showConfirmButton: false }); 
    }
  });
};

// Utilities
const downloadFile = (filePath, subject) => {
  const link = document.createElement('a');
  link.href = `/storage/${filePath}`;
  link.download = `SINDEN_${subject.replace(/\s+/g, '_')}.pdf`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const deleteRequest = (id) => {
  Swal.fire({
    title: 'Hapus Berkas?',
    text: "Data akan dihapus permanen dan validasi QR dicabut seketika!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    confirmButtonText: 'Ya, Hapus!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('signature.destroy', id), {
        onSuccess: () => Swal.fire('Terhapus', 'Berkas dan validasi berhasil dihapus.', 'success')
      });
    }
  });
};

const deleteSkhpp = (id) => {
  Swal.fire({
    title: 'HAPUS & CABUT SKHPP?',
    text: "Dokumen SKHPP dan status validasi legalitas QR akan langsung dicabut/dihapus seketika!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e11d48',
    confirmButtonText: 'YA, HAPUS & CABUT!',
    cancelButtonText: 'BATAL'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('skhpp.destroy', id), {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('BERHASIL DICABUT', 'SKHPP dan status validasi QR berhasil dihapus seketika.', 'success');
        }
      });
    }
  });
};

const submitRequest = () => {
  form.post(route('signature.store'), {
    onSuccess: () => { isModalOpen.value = false; form.reset(); Swal.fire('Berhasil', 'Berkas dikirim.', 'success'); },
  });
};

const getStatusClass = (status) => {
  if (status === 'approved') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  if (status === 'rejected') return 'bg-rose-50 text-rose-700 border-rose-200';
  return 'bg-amber-50 text-amber-700 border-amber-200';
};
</script>

<template>
  <Head title="Otoritas TTD Digital & SKHPP" />

  <AuthenticatedLayout>
    <div class="space-y-6 font-sans">
      
      <!-- Page Header Card -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Otoritas Validasi TTD Digital & SKHPP</h2>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
          </div>
          <p class="text-xs text-slate-500 font-semibold">Pusat Otorisasi Tanda Tangan Elektronik Komandan & Pengesahan Surat</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
          <Link href="/skhpp/create" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-emerald-500/20 transition tracking-wider text-center">
            + Pengajuan SKHPP Baru
          </Link>
          <button v-if="user.role !== 'komandan'" @click="isModalOpen = true"class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-blue-500/20 transition tracking-wider text-center">
            + Upload PDF Berkas Lain
          </button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-slate-200 gap-4">
        <button 
          @click="activeTab = 'skhpp'"
          :class="activeTab === 'skhpp' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span> PENGAJUAN SKHPP</span>
          <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black">{{ skhppRequests?.data?.length || 0 }}</span>
        </button>

        <button 
          @click="activeTab = 'pdf'"
          :class="activeTab === 'pdf' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span> BERKAS DINAS / PDF LAIN</span>
          <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">{{ requests?.data?.length || 0 }}</span>
        </button>
      </div>

      <!-- TAB 1: PENGAJUAN SKHPP -->
      <div v-if="activeTab === 'skhpp'" class="bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 uppercase font-extrabold text-slate-500 text-[10px] tracking-wider border-b border-slate-100">
              <tr>
                <th class="p-4">Tanggal Pengajuan</th>
                <th class="p-4">Subjek / Nama</th>
                <th class="p-4">Pangkat / NRP / NIK</th>
                <th class="p-4">Peruntukan</th>
                <th class="p-4 text-center">Status</th>
                <th class="p-4 text-right">Aksi & Otoritas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 uppercase font-bold text-slate-800 text-[11px]">
              <tr v-for="skhpp in skhppRequests.data" :key="skhpp.id" class="hover:bg-slate-50/50 transition">
                <td class="p-4 text-slate-400 font-mono">{{ new Date(skhpp.created_at).toLocaleDateString('id-ID') }}</td>
                <td class="p-4 font-black text-slate-900">
                  {{ skhpp.nama }}
                  <span v-if="skhpp.kategori_personel" class="block text-[9px] text-blue-600 font-semibold">{{ skhpp.kategori_personel.toUpperCase() }}</span>
                </td>
                <td class="p-4 text-slate-600 font-mono">{{ skhpp.pangkat_korps_nrp || skhpp.nik || '-' }}</td>
                <td class="p-4 text-slate-500 normal-case max-w-[200px] truncate" :title="skhpp.peruntukan">{{ skhpp.peruntukan }}</td>
                <td class="p-4 text-center">
                  <span :class="getStatusClass(skhpp.status)" class="px-3 py-1 rounded-full text-[9px] font-black border uppercase">
                    {{ skhpp.status === 'approved' ? 'DISETUJUI' : (skhpp.status === 'rejected' ? 'REVISI' : 'PENDING TTD') }}
                  </span>
                </td>
                <td class="p-4 text-right">
                  <div class="flex justify-end gap-2 items-center">
                    <button @click="openSkhppPreviewModal(skhpp)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase"> Lihat SKHPP
                    </button>
                    
                    <template v-if="user.role === 'admin' || user.role === 'komandan'">
                      <button v-if="skhpp.status === 'pending'" @click="approveSkhpp(skhpp)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-[9px] font-black uppercase shadow-sm"> Setujui & TTD
                      </button>
                      <button v-if="skhpp.status === 'pending'" @click="rejectSkhpp(skhpp)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase"> Tolak
                      </button>
                    </template>

                    <button v-if="user.role === 'admin' || user.id === skhpp.user_id" @click="deleteSkhpp(skhpp.id)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase" title="Hapus & Cabut Validasi QR">
                      
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!skhppRequests?.data?.length">
                <td colspan="6" class="p-8 text-center text-slate-400 italic text-xs">Belum ada permohonan SKHPP terdaftar.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: PENGAJUAN BERKAS DINAS / PDF LAIN -->
      <div v-if="activeTab === 'pdf'" class="bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 uppercase font-extrabold text-slate-500 text-[10px] tracking-wider border-b border-slate-100">
              <tr>
                <th class="p-4">Timestamp</th>
                <th class="p-4">Perihal Berkas</th>
                <th class="p-4">Pengaju</th>
                <th class="p-4 text-center">Status</th>
                <th class="p-4 text-right">Otoritas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 uppercase font-bold text-slate-800 text-[11px]">
              <tr v-for="req in requests.data" :key="req.id" class="hover:bg-slate-50/50 transition">
                <td class="p-4 text-slate-400 font-mono">{{ new Date(req.created_at).toLocaleString('id-ID') }}</td>
                <td class="p-4">
                  <div class="font-black text-slate-900">{{ req.subject }}</div>
                  <div v-if="req.note" class="text-[9px] text-rose-500 font-normal italic mt-0.5">Alasan: {{ req.note }}</div>
                </td>
                <td class="p-4 text-slate-600 text-xs">{{ req.user?.name || 'Operator' }}</td>
                <td class="p-4 text-center">
                  <span :class="getStatusClass(req.status)" class="px-3 py-1 rounded-full text-[9px] font-black border uppercase">
                    {{ req.status }}
                  </span>
                </td>
                <td class="p-4 text-right">
                  <div class="flex justify-end gap-2 items-center">
                    <button @click="openPdfPreview(req)" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3.5 py-1.5 rounded-xl text-[9px] font-black uppercase"> Periksa & Atur TTD
                    </button>
                    <button v-if="req.status === 'approved'" @click="downloadFile(req.file_path, req.subject)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl text-[9px] font-black uppercase shadow-sm"> Unduh
                    </button>
                    <button v-if="user.role === 'admin' || user.id === req.user_id" @click="deleteRequest(req.id)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase">
                      
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!requests?.data?.length">
                <td colspan="5" class="p-8 text-center text-slate-400 italic text-xs">Belum ada berkas PDF diajukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- MODAL PRATINJAU DOKUMEN SKHPP (KOREKSI & OTORITAS KOMANDAN) -->
    <div v-if="isSkhppPreviewOpen && selectedSkhpp" class="fixed inset-0 z-[150] flex items-center justify-center p-2 sm:p-4 bg-slate-950/85 backdrop-blur-md overflow-y-auto">
      <div class="bg-slate-100 rounded-3xl max-w-4xl w-full p-3 sm:p-6 space-y-4 shadow-2xl border border-slate-300 text-left my-auto max-h-[95vh] flex flex-col">
        
        <!-- Header Bar Modal (Mobile Responsive) -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-slate-300 pb-3 gap-2.5 shrink-0">
          <div>
            <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-blue-600 block">KOREKSI & OTORITAS VALIDASI SKHPP</span>
            <h3 class="text-xs sm:text-base font-black text-slate-900 uppercase leading-tight">SKHPP: {{ selectedSkhpp.nama }}</h3>
          </div>

          <div class="flex items-center gap-1.5 flex-wrap w-full sm:w-auto">
            <Link :href="`/skhpp/${selectedSkhpp.id}/edit`" class="flex-1 sm:flex-none bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-sm text-center flex items-center justify-center gap-1"> Koreksi
            </Link>

            <template v-if="(user.role === 'admin' || user.role === 'komandan') && selectedSkhpp.status === 'pending'">
              <button @click="approveSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-md text-center flex items-center justify-center gap-1"> Setujui
              </button>
              <button @click="rejectSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-rose-600 hover:bg-rose-700 text-white px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase shadow-sm text-center flex items-center justify-center gap-1"> Tolak
              </button>
            </template>

            <button @click="isSkhppPreviewOpen = false" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded-xl text-[10px] sm:text-xs font-black uppercase">
              
            </button>
          </div>
        </div>

        <!-- Scrollable Authentic SKHPP Preview Container (Mobile Responsive Scaling) -->
        <div class="flex-1 overflow-auto bg-slate-200/70 p-1.5 sm:p-6 border border-slate-300 rounded-2xl shadow-inner custom-scrollbar">
          <div class="bg-white p-4 sm:p-10 border border-slate-300 rounded-xl shadow-md font-sans text-[10pt] sm:text-[12pt] text-black leading-relaxed space-y-3 sm:space-y-4 max-w-[800px] min-w-[300px] mx-auto w-full overflow-x-auto" style="font-family: Arial, Helvetica, sans-serif;">
            
            <!-- Kop Header (Rata Tengah & Presisi Single Line) -->
            <div class="text-center max-w-[380px] w-full mx-auto text-[10pt] sm:text-[12pt] font-normal leading-snug">
                <div class="whitespace-nowrap font-normal">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
                <div class="font-normal text-center">DETASEMEN INTELIJEN</div>
                <div class="w-44 sm:w-48 h-[1.5px] bg-black mx-auto mt-0.5"></div>
            </div>

            <!-- Judul & Nomor SKHPP -->
            <div class="text-center my-3 sm:my-4">
                <div class="font-bold text-[10pt] sm:text-[12pt] underline uppercase tracking-tight"> SURAT KETERANGAN HASIL PENELITIAN PERSONEL
                </div>
                <div v-if="selectedSkhpp.kategori_personel === 'perusahaan'" class="font-bold text-[9pt] sm:text-[11pt] uppercase"> MITRA KERJA TNI ANGKATAN LAUT
                </div>
                <div class="text-[10pt] sm:text-[12pt] font-normal mt-0.5"> Nomor : {{ selectedSkhpp.nomor_skhpp || ('R / ' + (selectedSkhpp.nomor_urut || '....') + ' / SKHPP / ' + (selectedSkhpp.bulan_romawi || 'VIII') + ' / ' + (selectedSkhpp.tahun || '2026')) }}
                </div>
            </div>

            <!-- Poin Isi 1 s.d. 4 -->
            <div class="space-y-2 sm:space-y-3 text-[10pt] sm:text-[12pt]">
                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">1.</span>
                    <div> Dasar : {{ selectedSkhpp.surat_pengantar }}
                    </div>
                </div>

                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">2.</span>
                    <div> Dengan ini menerangkan bahwa hasil penelitian terhadap :
                    </div>
                </div>

                <!-- Sub Poin a-g (Indentasi Rata Huruf D) -->
                <div class="pl-2 sm:pl-[45px] space-y-1 text-[9pt] sm:text-[12pt]">
                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>a. Nama</span>
                        <span>:</span>
                        <span class="font-normal">{{ selectedSkhpp.nama }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>b. NIK / Pangkat</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.pangkat_korps_nrp || selectedSkhpp.nik || '-' }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>c. Jabatan / Pekerjaan</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.jabatan_pekerjaan }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>d. Tempat, tgl. lahir</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.tempat_lahir }}, {{ selectedSkhpp.tanggal_lahir }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>e. Jenis kelamin</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.jenis_kelamin }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>f. Agama</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.agama }}</span>
                    </div>

                    <div class="grid grid-cols-[120px_10px_1fr] sm:grid-cols-[160px_15px_1fr]">
                        <span>g. Alamat rumah</span>
                        <span>:</span>
                        <span>{{ selectedSkhpp.alamat }}</span>
                    </div>
                </div>

                <div class="flex items-start font-normal mt-2">
                    <span class="w-5 sm:w-6 shrink-0">2.</span>
                    <div> Hasil Penelitian Personel <span class="font-bold">Memenuhi Syarat</span>
                    </div>
                </div>

                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">3.</span>
                    <div> SKHPP ini diberikan {{ selectedSkhpp.peruntukan }}
                    </div>
                </div>

                <div class="flex items-start">
                    <span class="w-5 sm:w-6 shrink-0">4.</span>
                    <div> Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut dan diadakan pembetulan seperlunya.
                    </div>
                </div>
            </div>

            <!-- TTD Block Komandan & Pas Foto 4x6 (Foto Suami-Istri Nempel & Mepet TTD, Tanpa Border) -->
            <div class="mt-4 sm:mt-6 pt-2 flex flex-col sm:flex-row justify-end items-center sm:items-end gap-1.5 sm:gap-2">
                <!-- Pas Foto Section 4x6 (Nempel Berhimpitan Tanpa Border) -->
                <div class="flex items-center gap-0 shrink-0">
                    <div v-if="selectedSkhpp.foto_1" class="w-[3.2cm] h-[4.8cm] sm:w-[4cm] sm:h-[6cm] bg-slate-100 flex items-center justify-center text-[9pt] sm:text-[10pt] text-slate-400 overflow-hidden">
                        <img :src="'/storage/' + selectedSkhpp.foto_1" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-[3.2cm] h-[4.8cm] sm:w-[4cm] sm:h-[6cm] border border-slate-300 bg-slate-100 flex items-center justify-center text-[9pt] sm:text-[10pt] text-slate-400 overflow-hidden">
                        <span>FOTO 4x6</span>
                    </div>
                    <div v-if="selectedSkhpp.is_pernikahan && selectedSkhpp.foto_2" class="w-[3.2cm] h-[4.8cm] sm:w-[4cm] sm:h-[6cm] bg-slate-100 flex items-center justify-center text-[9pt] sm:text-[10pt] text-slate-400 overflow-hidden">
                        <img :src="'/storage/' + selectedSkhpp.foto_2" class="w-full h-full object-cover" />
                    </div>
                </div>

                <!-- Block TTD Komandan -->
                <div class="w-full sm:w-[330px] text-center sm:text-left">
                    <div class="text-left">Dikeluarkan di Surabaya</div>
                    <div class="border-b border-black pb-0.5 mb-1 flex justify-between items-center text-[10pt] sm:text-[12pt]">
                        <span>pada tanggal</span>
                        <span>{{ selectedSkhpp.tanggal_skhpp ? new Date(selectedSkhpp.tanggal_skhpp).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '5 Agustus 2026' }}</span>
                    </div>
                    <div class="font-normal text-center whitespace-nowrap mt-1 leading-snug"> Komandan Detasemen Intelijen Kodaeral V,
                    </div>

                    <!-- QR Code TTD Status -->
                    <div class="my-2 py-1 text-center min-h-[85px] sm:min-h-[95px] flex items-center justify-center">
                        <div v-if="selectedSkhpp.status === 'approved'" class="text-center space-y-1">
                            <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(window.location.origin + '/verify-skhpp/' + selectedSkhpp.verification_code)}`" class="w-[75px] h-[75px] sm:w-[85px] sm:h-[85px] border border-slate-300 p-0.5 rounded-sm mx-auto block" />
                            <span class="text-[7px] sm:text-[8px] font-bold text-emerald-700 block"> TERVERIFIKASI TTD DIGITAL</span>
                        </div>
                        <div v-else class="h-[75px] sm:h-[85px] w-full flex items-center justify-center border border-dashed border-slate-300 text-[9pt] sm:text-[10px] text-slate-400 font-sans italic bg-slate-50">
                            [ PENDING TTD KOMANDAN ]
                        </div>
                    </div>

                    <div class="text-center font-normal text-[10pt] sm:text-[12pt]">
                        <div>Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                        <div>Kolonel Laut (E) NRP 16085/P</div>
                    </div>
                </div>
            </div>

            <!-- Kepada Footer -->
            <div class="pt-3 text-[10pt] sm:text-[12pt] font-normal">
                <div>Kepada :</div>
                <div class="whitespace-nowrap">Yth. Asintel Dankodaeral V</div>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions (Mobile Friendly) -->
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center border-t border-slate-300 pt-3 gap-2.5 shrink-0">
          <Link :href="`/skhpp/${selectedSkhpp.id}`" class="text-[11px] sm:text-xs font-bold text-blue-600 hover:underline flex items-center justify-center sm:justify-start gap-1"> Buka Halaman Cetak Lengkap (Dengan Lampiran Jika Ada) →
          </Link>
          <div class="flex gap-2 w-full sm:w-auto">
            <template v-if="(user.role === 'admin' || user.role === 'komandan') && selectedSkhpp.status === 'pending'">
              <button @click="approveSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase shadow-md text-center"> Setujui & TTD Komandan
              </button>
              <button @click="rejectSkhpp(selectedSkhpp)" class="flex-1 sm:flex-none bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-2.5 rounded-xl text-[11px] sm:text-xs font-black uppercase shadow-md text-center"> Tolak / Minta Revisi
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL MULTI-PAGE PDF PREVIEW & ATUR TTD -->
    <div v-if="isPreviewOpen" class="fixed inset-0 z-[150] flex flex-col bg-slate-950 animate-in fade-in duration-300">
      
      <!-- Top Navigation & Actions Bar -->
      <div class="bg-white border-b px-4 py-3 flex justify-between items-center shadow-xl shrink-0">
        <div class="flex items-center gap-3">
          <span class="text-blue-600 text-[10px] font-black uppercase tracking-widest">VERIFIKASI PDF MULTI-HALAMAN</span>
          
          <!-- Page Controls (Halaman Lebih Dari Satu) -->
          <div v-if="totalPages > 1" class="flex items-center gap-2 bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 text-xs">
            <button @click="changePdfPage(-1)" :disabled="currentPage <= 1" class="px-2 py-0.5 rounded bg-white font-bold text-slate-700 disabled:opacity-30 hover:bg-slate-200">◀</button>
            <span class="font-bold text-slate-800">Halaman {{ currentPage }} / {{ totalPages }}</span>
            <button @click="changePdfPage(1)" :disabled="currentPage >= totalPages" class="px-2 py-0.5 rounded bg-white font-bold text-slate-700 disabled:opacity-30 hover:bg-slate-200">▶</button>
            <span v-if="activePagesList.length > 0" class="ml-1 px-2.5 py-0.5 bg-blue-600 text-white rounded-md text-[10px] font-extrabold uppercase shadow-xs"> TTD Terpasang di {{ activePagesList.length }} Hal: (Hal. {{ activePagesList.join(', ') }})
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button v-if="isAdjusting && isCurrentPageActive" @click="removeCurrentPageSignature" class="bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-2 rounded-xl text-xs font-black uppercase shadow-md"> Hapus TTD di Hal. {{ currentPage }}
          </button>
          <button v-else-if="isAdjusting && !isCurrentPageActive" @click="addCurrentPageSignature" class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 rounded-xl text-xs font-black uppercase shadow-md">
            + Pasang TTD di Hal. {{ currentPage }}
          </button>
          <button @click="closeOperatorPosPicker" class="bg-rose-50 text-rose-600 px-3 py-2 rounded-xl text-xs font-black uppercase border border-rose-100"></button>
        </div>
      </div>

      <!-- Canvas Render Area -->
      <div class="flex-1 relative overflow-auto bg-slate-900/60 p-4 custom-scrollbar flex justify-center items-start" id="pdf-render-container">
        <div v-if="isLoadingPdf" class="absolute inset-0 z-[200] flex items-center justify-center bg-slate-950/80">
          <div class="animate-spin border-4 border-blue-500 border-t-transparent rounded-full w-10 h-10"></div>
        </div>

        <div class="relative bg-white shadow-2xl overflow-hidden rounded-sm" style="line-height: 0;">
          <canvas id="pdf-render-canvas"></canvas>

          <!-- Draggable Signature Box (Presisi 1:1 e-Materai TTD Digital) -->
          <div v-if="isAdjusting && isCurrentPageActive" class="drag-signature absolute z-[200] cursor-move border-2 border-blue-600 bg-white shadow-2xl flex items-center justify-center touch-none text-center p-0.5"
               :style="{ left: signaturePos.x + 'px', top: signaturePos.y + 'px', width: signatureSize.width + 'px', height: signatureSize.height + 'px' }">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=SINDEN_PREVIEW" class="w-full h-full object-contain pointer-events-none" alt="QR Code TTD Digital" />
            <div class="absolute -bottom-2 -right-2 w-6 h-6 bg-blue-600 rounded-full border-2 border-white shadow-lg cursor-se-resize flex items-center justify-center">
              <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
            </div>
          </div>

          <!-- Overlay Halaman Tanpa TTD -->
          <div v-else-if="isAdjusting && !isCurrentPageActive" class="absolute inset-0 bg-slate-950/30 backdrop-blur-[1px] flex flex-col items-center justify-center gap-3 z-[100] text-center p-4">
            <div class="bg-white/95 p-5 rounded-2xl shadow-2xl border border-slate-200 max-w-xs space-y-2">
              <span class="text-xs font-black uppercase text-slate-500 block">Halaman {{ currentPage }} Tanpa TTD</span>
              <p class="text-[10px] text-slate-600 font-medium">Halaman ini dibuat tanpa tanda tangan Komandan.</p>
              <button type="button" @click="addCurrentPageSignature" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-xl text-xs font-black uppercase shadow-md">
                + Pasang TTD di Halaman {{ currentPage }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Banner Petunjuk Multi-Halaman -->
      <div v-if="totalPages > 1" class="bg-blue-50 border-t border-b border-blue-100 px-4 py-2.5 text-center text-xs font-bold text-blue-900 shrink-0 flex items-center justify-center gap-2 flex-wrap">
        <span>Dokumen ini memiliki <strong>{{ totalPages }} Halaman</strong>. Buka halaman tempat TTD berada menggunakan tombol ◀ ▶ di atas, lalu pilih halaman mana saja yang ingin dipasang atau dihapus TTD-nya.</span>
      </div>

      <!-- Bottom Bar untuk Operator (Save Position Only) -->
      <div v-if="isOperatorConfiguring" class="bg-white border-t p-4 flex justify-center gap-3 shrink-0 shadow-2xl">
        <button type="button" @click="saveOperatorPos" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3.5 rounded-2xl font-black text-xs uppercase shadow-lg"> SIMPAN POSISI LOKASI TTD INI ( {{ activePagesList.length }} HALAMAN TERPILIH )
        </button>
        <button type="button" @click="closeOperatorPosPicker" class="bg-slate-100 text-slate-600 px-6 py-3.5 rounded-2xl font-black text-xs uppercase border border-slate-200"> BATAL
        </button>
      </div>

      <!-- Bottom Confirm / Reject Bar untuk Komandan & Admin -->
      <div v-else-if="user.role === 'komandan' || user.role === 'admin'" class="bg-white border-t p-4 flex flex-wrap justify-center gap-3 shrink-0 shadow-2xl">
        <button v-if="isAdjusting" @click="handlePdfDecision('approved', false)" :disabled="decisionForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3.5 rounded-2xl font-black text-xs uppercase shadow-lg"> SETUJUI & STAMP TTD DIGITAL ( PADA {{ activePagesList.length }} HALAMAN TERPILIH )
        </button>
        <button v-if="isAdjusting && totalPages > 1" @click="handlePdfDecision('approved', true)" :disabled="decisionForm.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3.5 rounded-2xl font-black text-xs uppercase shadow-lg"> SETUJUI DI SEMUA HALAMAN (1 s.d. {{ totalPages }})
        </button>
        <button @click="handlePdfDecision('rejected')" :disabled="decisionForm.processing" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-3.5 rounded-2xl font-black text-xs uppercase shadow-lg"> TOLAK / MINTA REVISI BERKAS
        </button>
        <button v-if="isAdjusting" @click="isPreviewOpen = false" class="bg-slate-100 text-slate-600 px-5 py-3.5 rounded-2xl font-black text-xs uppercase border border-slate-200"> BATAL
        </button>
      </div>

    </div>

    <!-- MODAL REGISTRASI BERKAS PDF BARU (LENGKAP SESUAI LAYOUT VERIFIKASI) -->
    <transition name="modal-pop">
      <div v-show="isModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-4 bg-slate-950/75 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white w-full max-w-xl rounded-3xl p-6 sm:p-7 shadow-2xl border border-slate-100 space-y-5 text-left my-auto max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between border-b pb-3 shrink-0">
            <div>
              <span class="text-[9px] font-black text-blue-600 uppercase tracking-widest block">REGISTRASI BERKAS & METADATA PUBLIC VERIFY</span>
              <h3 class="font-black text-sm sm:text-base text-slate-900 uppercase leading-tight">Unggah Berkas Dinas (PDF)</h3>
            </div>
            <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold p-1"></button>
          </div>

          <form @submit.prevent="submitRequest" class="space-y-4 overflow-y-auto custom-scrollbar pr-1 flex-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Judul / Jenis Naskah Dinas</label>
                <input v-model="form.document_title" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="SURAT PERINTAH / NOTA DINAS..." required>
              </div>

              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Nomor Surat Resmi / Agenda (Opsional)</label>
                <input v-model="form.letter_number" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Sprin / 15 / VIII / 2026...">
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Perihal / Subjek Surat</label>
              <input v-model="form.subject" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Perihal Penugasan / Pengamanan Sektor..." required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Nama Subjek / Personel (Opsional)</label>
                <input v-model="form.person_name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" :placeholder="user?.name || 'Nama Personel...'">
              </div>

              <div>
                <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Pangkat / Korps / NRP / NIK (Opsional)</label>
                <input v-model="form.pangkat_nrp" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" :placeholder="(user?.pangkat || 'TNI AL') + ' / NRP ' + (user?.nrp || '12345')">
              </div>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Jabatan / Pekerjaan (Opsional)</label>
              <input v-model="form.jabatan" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Personel Denintel Kodaeral V...">
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Maksud & Peruntukan / Keperluan (Opsional)</label>
              <textarea v-model="form.peruntukan" rows="2" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-2.5 uppercase focus:ring-2 focus:ring-blue-500" placeholder="Maksud & Keperluan Penerbitan Dokumen Resmi..."></textarea>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">Unggah Berkas PDF (Dapat Lebih Dari 1 Halaman)</label>
              <input type="file" @change="onOperatorFileSelect" accept=".pdf" class="w-full text-xs text-slate-500 border border-slate-200 rounded-xl p-2 bg-slate-50" :required="!form.file" />
              <div v-if="form.file" class="mt-2 text-xs font-bold text-emerald-800 bg-emerald-50 p-2.5 rounded-xl border border-emerald-200 flex items-center justify-between">
                <span>Berkas Terpilih: <strong>{{ form.file.name }}</strong> ({{ (form.file.size / 1024 / 1024).toFixed(2) }} MB)</span>
                <span class="text-[9px] bg-emerald-600 text-white px-2.5 py-0.5 rounded-full font-black uppercase">TERLAMPIR SIAP DIKIRIM</span>
              </div>
            </div>

            <!-- Tombol Penentuan Posisi TTD Komandan oleh Operator -->
            <div v-if="form.file" class="bg-blue-50/80 p-3.5 rounded-2xl border border-blue-200 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-900">ATUR POSISI LOKASI TTD KOMANDAN</span>
                <span v-if="form.target_page" class="text-[9px] font-extrabold bg-blue-600 text-white px-2 py-0.5 rounded-full">Hal. {{ form.target_page }} Di-Set</span>
              </div>
              <p class="text-[10px] font-semibold text-slate-600">Geser & tandai posisi QR Code TTD Komandan pada lembar PDF ini agar Komandan dapat langsung melihat & menyetujui.</p>
              <button type="button" @click="openOperatorPosPicker" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-xl text-xs font-black uppercase shadow-md flex items-center justify-center gap-2">
                <span>Tandai / Atur Posisi TTD Komandan (Preview PDF)</span>
              </button>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t shrink-0">
              <button type="button" @click="isModalOpen = false" class="bg-slate-100 text-slate-600 px-4 py-2.5 rounded-xl text-xs font-bold uppercase">Batal</button>
              <button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-xs font-black uppercase shadow-md">Kirim Berkas</button>
            </div>
          </form>
        </div>
      </div>
    </transition>

  </AuthenticatedLayout>
</template>

<style scoped>
#pdf-render-canvas { display: block; margin: 0 auto; background-color: #fff; }
.drag-signature { touch-action: none; user-select: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
</style>