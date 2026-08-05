<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
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

// Multi-Page PDF State
const currentPage = ref(1);
const totalPages = ref(1);
const pdfDoc = ref(null);

// Signature Drag Position
const signaturePos = ref({ x: 50, y: 150 });
const signatureSize = ref({ width: 100, height: 70 });

// Forms
const form = useForm({ subject: '', letter_number: '', file: null });
const decisionForm = useForm({ 
  status: '', 
  x: 0, 
  y: 0, 
  width: 0, 
  canvas_width: 0, 
  target_page: 1, 
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
  Swal.fire({
    title: 'SETUJUI & TTD SKHPP?',
    html: `
      <div class="text-left text-xs space-y-2">
        <p><strong>Subjek:</strong> ${skhpp.nama}</p>
        <p><strong>Pangkat/NRP/NIK:</strong> ${skhpp.pangkat_korps_nrp || skhpp.nik || '-'}</p>
        <p><strong>Peruntukan:</strong> ${skhpp.peruntukan}</p>
        <hr class="my-2"/>
        <p class="text-emerald-600 font-bold">Dokumen akan otomatis diterbitkan nomor resmi, QR Code TTD Komandan, dan disinkronkan ke Buku Agenda SINDEN.</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'YA, SETUJUI & TERBITKAN',
    cancelButtonText: 'BATAL'
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('skhpp.approve', skhpp.id), {}, {
        onSuccess: () => {
          isSkhppPreviewOpen.value = false;
          Swal.fire('BERHASIL DISAHKAN', 'SKHPP Resmi telah ditandatangani Komandan.', 'success');
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

const changePdfPage = async (delta) => {
  const newPage = currentPage.value + delta;
  if (newPage >= 1 && newPage <= totalPages.value) {
    currentPage.value = newPage;
    await renderPdfPage(currentPage.value);
  }
};

const openPdfPreview = async (req) => {
  selectedReqId.value = req.id;
  isPreviewOpen.value = true;
  isAdjusting.value = false;
  currentPage.value = 1;
  signaturePos.value = { x: 20, y: 100 };

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

    pdfDoc.value = await loadingTask.promise;
    totalPages.value = pdfDoc.value.numPages;
    currentPage.value = req.target_page || 1;

    await renderPdfPage(currentPage.value);
  } catch (e) {
    Swal.fire('Error', 'File PDF tidak dapat dibaca atau diproteksi password.', 'error');
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

const handlePdfDecision = (status) => {
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

  const canvas = document.getElementById('pdf-render-canvas');
  
  decisionForm.status = 'approved';
  decisionForm.x = parseFloat(signaturePos.value.x / canvas.width); 
  decisionForm.y = parseFloat(signaturePos.value.y / canvas.height);
  decisionForm.width = parseFloat(signatureSize.value.width / canvas.width);
  decisionForm.canvas_width = canvas.width;
  decisionForm.target_page = currentPage.value; // Halaman target yang dipilih
  
  decisionForm.patch(route('signature.update', selectedReqId.value), {
    onSuccess: () => { 
      isPreviewOpen.value = false; 
      Swal.fire({ icon: 'success', title: 'Berhasil', text: `TTD Terpasang Presisi pada Halaman ${currentPage.value}`, timer: 2000, showConfirmButton: false }); 
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
          <button v-if="user.role !== 'komandan'" @click="isModalOpen = true" 
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-blue-500/20 transition tracking-wider text-center">
            + Upload PDF Berkas Lain
          </button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-slate-200 gap-4">
        <button 
          @click="activeTab = 'skhpp'"
          :class="activeTab === 'skhpp' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span>📜 PENGAJUAN SKHPP</span>
          <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black">{{ skhppRequests?.data?.length || 0 }}</span>
        </button>

        <button 
          @click="activeTab = 'pdf'"
          :class="activeTab === 'pdf' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span>📑 BERKAS DINAS / PDF LAIN</span>
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
                    <button @click="openSkhppPreviewModal(skhpp)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase">
                      🔍 Lihat SKHPP
                    </button>
                    
                    <template v-if="user.role === 'admin' || user.role === 'komandan'">
                      <button v-if="skhpp.status === 'pending'" @click="approveSkhpp(skhpp)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-[9px] font-black uppercase shadow-sm">
                        ✅ Setujui & TTD
                      </button>
                      <button v-if="skhpp.status === 'pending'" @click="rejectSkhpp(skhpp)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase">
                        ❌ Tolak
                      </button>
                    </template>

                    <button v-if="user.role === 'admin' || user.id === skhpp.user_id" @click="deleteSkhpp(skhpp.id)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase" title="Hapus & Cabut Validasi QR">
                      🗑️
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
                    <button @click="openPdfPreview(req)" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3.5 py-1.5 rounded-xl text-[9px] font-black uppercase">
                      🔍 Periksa & Atur TTD
                    </button>
                    <button v-if="req.status === 'approved'" @click="downloadFile(req.file_path, req.subject)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl text-[9px] font-black uppercase shadow-sm">
                      📥 Unduh
                    </button>
                    <button v-if="user.role === 'admin' || user.id === req.user_id" @click="deleteRequest(req.id)" class="bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-xl text-[9px] font-black uppercase">
                      🗑️
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

    <!-- MODAL PRATINJAU SKHPP -->
    <div v-if="isSkhppPreviewOpen && selectedSkhpp" class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 space-y-6 shadow-2xl border border-slate-200 text-left">
        <div class="flex items-center justify-between border-b pb-4">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block">Detail Pengajuan SKHPP</span>
            <h3 class="text-base font-black text-slate-900 uppercase">{{ selectedSkhpp.nama }}</h3>
          </div>
          <button @click="isSkhppPreviewOpen = false" class="text-slate-400 hover:text-slate-600 text-sm font-black">✕</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 block uppercase">Identitas (Pangkat/NRP/NIK)</span>
            <span class="font-bold text-slate-800">{{ selectedSkhpp.pangkat_korps_nrp || selectedSkhpp.nik || '-' }}</span>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 block uppercase">Jabatan / Pekerjaan</span>
            <span class="font-semibold text-slate-800">{{ selectedSkhpp.jabatan_pekerjaan }}</span>
          </div>
          <div class="sm:col-span-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 block uppercase">Maksud & Peruntukan</span>
            <span class="font-medium text-slate-700 leading-relaxed">{{ selectedSkhpp.peruntukan }}</span>
          </div>
          <div class="sm:col-span-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 block uppercase">Nomor SKHPP</span>
            <span class="font-mono font-bold text-orange-600">{{ selectedSkhpp.nomor_skhpp || 'Draft (Otomatis saat disetujui Komandan)' }}</span>
          </div>
        </div>

        <div class="flex justify-between items-center pt-4 border-t gap-3">
          <Link :href="`/skhpp/${selectedSkhpp.id}`" class="text-xs font-bold text-blue-600 hover:underline">
            📄 Buka Halaman Cetak SKHPP Lengkap →
          </Link>
          <div class="flex gap-2">
            <template v-if="(user.role === 'admin' || user.role === 'komandan') && selectedSkhpp.status === 'pending'">
              <button @click="approveSkhpp(selectedSkhpp)" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs font-bold uppercase shadow-md">
                ✅ Setujui & TTD
              </button>
              <button @click="rejectSkhpp(selectedSkhpp)" class="bg-rose-50 text-rose-600 border border-rose-200 px-4 py-2 rounded-xl text-xs font-bold uppercase">
                ❌ Tolak
              </button>
            </template>
            <button @click="isSkhppPreviewOpen = false" class="bg-slate-100 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold uppercase">
              Tutup
            </button>
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
          <div v-if="totalPages > 1" class="flex items-center gap-1.5 bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 text-xs">
            <button @click="changePdfPage(-1)" :disabled="currentPage <= 1" class="px-2 py-0.5 rounded bg-white font-bold text-slate-700 disabled:opacity-30 hover:bg-slate-200">◀</button>
            <span class="font-bold text-slate-800">Halaman {{ currentPage }} / {{ totalPages }}</span>
            <button @click="changePdfPage(1)" :disabled="currentPage >= totalPages" class="px-2 py-0.5 rounded bg-white font-bold text-slate-700 disabled:opacity-30 hover:bg-slate-200">▶</button>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button v-if="!isAdjusting && (user.role === 'komandan' || user.role === 'admin')" @click="enableDrag" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs font-black uppercase shadow-md">
            📍 Atur Posisi TTD (Hal. {{ currentPage }})
          </button>
          <button @click="isPreviewOpen = false" class="bg-rose-50 text-rose-600 px-3 py-2 rounded-xl text-xs font-black uppercase border border-rose-100">✕</button>
        </div>
      </div>

      <!-- Canvas Render Area -->
      <div class="flex-1 relative overflow-auto bg-slate-900/60 p-4 custom-scrollbar flex justify-center items-start" id="pdf-render-container">
        <div v-if="isLoadingPdf" class="absolute inset-0 z-[200] flex items-center justify-center bg-slate-950/80">
          <div class="animate-spin border-4 border-blue-500 border-t-transparent rounded-full w-10 h-10"></div>
        </div>

        <div class="relative bg-white shadow-2xl overflow-hidden rounded-sm" style="line-height: 0;">
          <canvas id="pdf-render-canvas"></canvas>

          <!-- Draggable Signature Box -->
          <div v-if="isAdjusting" class="drag-signature absolute z-[200] cursor-move border-2 border-blue-600 bg-blue-500/20 backdrop-blur-[1px] shadow-2xl flex items-center justify-center touch-none"
               :style="{ left: signaturePos.x + 'px', top: signaturePos.y + 'px', width: signatureSize.width + 'px', height: signatureSize.height + 'px' }">
            <img :src="'/storage/' + commanderSignature" class="w-full h-full object-contain pointer-events-none opacity-90" alt="TTD Komandan" />
            <div class="absolute -bottom-2 -right-2 w-6 h-6 bg-blue-600 rounded-full border-2 border-white shadow-lg cursor-se-resize flex items-center justify-center">
              <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Confirm / Reject Bar -->
      <div v-if="user.role === 'komandan' || user.role === 'admin'" class="bg-white border-t p-4 flex justify-center gap-3 shrink-0 shadow-2xl">
        <button v-if="isAdjusting" @click="handlePdfDecision('approved')" :disabled="decisionForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3.5 rounded-2xl font-black text-xs uppercase shadow-lg">
          ✅ KONFIRMASI TTD HALAMAN {{ currentPage }}
        </button>
        <button @click="handlePdfDecision('rejected')" :disabled="decisionForm.processing" class="bg-rose-600 hover:bg-rose-700 text-white px-6 py-3.5 rounded-2xl font-black text-xs uppercase shadow-lg">
          ❌ TOLAK BERKAS
        </button>
        <button v-if="isAdjusting" @click="isAdjusting = false" class="bg-slate-100 text-slate-600 px-6 py-3.5 rounded-2xl font-black text-xs uppercase border border-slate-200">
          BATAL
        </button>
      </div>

    </div>

    <!-- MODAL REGISTRASI BERKAS PDF BARU -->
    <transition name="modal-pop">
      <div v-if="isModalOpen" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white w-full max-w-md rounded-3xl p-8 shadow-2xl border border-slate-100 space-y-6 text-left">
          <div class="flex items-center justify-between border-b pb-4">
            <h3 class="font-black text-base text-slate-900 uppercase">Registrasi Berkas Dinas (PDF)</h3>
            <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
          </div>

          <form @submit.prevent="submitRequest" class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Perihal / Subjek Surat</label>
              <input v-model="form.subject" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-3 uppercase focus:ring-2 focus:ring-blue-500" placeholder="SURAT PERINTAH / NOTA DINAS..." required>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Unggah Berkas PDF (Dapat Lebih Dari 1 Halaman)</label>
              <input type="file" @input="form.file = $event.target.files[0]" accept=".pdf" class="w-full text-xs text-slate-500 border border-slate-200 rounded-xl p-2 bg-slate-50" required />
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
              <button type="button" @click="isModalOpen = false" class="bg-slate-100 text-slate-600 px-4 py-2.5 rounded-xl text-xs font-bold uppercase">Batal</button>
              <button type="submit" :disabled="form.processing" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl text-xs font-bold uppercase shadow-md">Kirim Berkas</button>
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