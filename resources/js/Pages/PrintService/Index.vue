<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  activeJob: Object,
  queuedJobs: Array,
  recentJobs: Array,
  printerSettings: Object,
  stats: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

// Status antrean dokumen
const currentActiveJob = ref(props.activeJob);
const currentQueuedJobs = ref(props.queuedJobs || []);
const currentRecentJobs = ref(props.recentJobs || []);
const currentStats = ref(props.stats || { total_queued: 0, total_today: 0, sheets_today: 0 });

// Modal Pratinjau & Konfirmasi Cetak
const isPreviewModalOpen = ref(false);
const previewJob = ref(null);
const previewUrl = ref(null);
const isUploading = ref(false);
const uploadProgress = ref(0);

// Formulir Unggah Dokumen
const uploadForm = useForm({
  document_title: '',
  file: null,
});

// Formulir Pengaturan Printer (Khusus Admin)
const adminPrinterForm = useForm({
  printer_ip: props.printerSettings?.printer_ip || '192.168.1.200',
  printer_port: props.printerSettings?.printer_port || 9100,
  printer_name: props.printerSettings?.printer_name || 'Brother Network Printer',
});

const isTestingConnection = ref(false);
const testConnectionResult = ref(null);

// Tab Antrean & Riwayat
const activeTab = ref('queue'); // 'queue' atau 'history'

// Handle Pilih Berkas
const selectedFileName = ref('');
const selectedFileSize = ref('');
const handleFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    uploadForm.file = file;
    selectedFileName.value = file.name;
    selectedFileSize.value = (file.size / 1024 / 1024).toFixed(2) + ' MB';
    if (!uploadForm.document_title) {
      const nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
      uploadForm.document_title = nameWithoutExt.replace(/[-_]/g, ' ').toUpperCase();
    }
  }
};

// Kirim Berkas untuk Dikonversi & Ditampilkan Pratinjau
const submitUploadAndPreview = async () => {
  if (!uploadForm.file) {
    Swal.fire({
      title: 'BERKAS BELUM DIPILIH',
      text: 'Silakan pilih berkas dokumen PDF atau DOCX terlebih dahulu.',
      icon: 'warning',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2563eb',
    });
    return;
  }

  isUploading.value = true;
  uploadProgress.value = 10;

  const formData = new FormData();
  formData.append('document_title', uploadForm.document_title);
  formData.append('file', uploadForm.file);

  try {
    const response = await fetch(route('printing.upload'), {
      method: 'POST',
      body: formData,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Gagal memproses dokumen.');
    }

    previewJob.value = data.job;
    previewUrl.value = data.preview_url;
    isPreviewModalOpen.value = true;

    // Reset formulir unggah
    uploadForm.reset();
    selectedFileName.value = '';
    selectedFileSize.value = '';

  } catch (error) {
    Swal.fire({
      title: 'GAGAL MEMPROSES DOKUMEN',
      text: error.message || 'Terjadi kesalahan sistem saat mengonversi berkas dokumen.',
      icon: 'error',
      confirmButtonText: 'TUTUP',
      confirmButtonColor: '#e11d48',
    });
  } finally {
    isUploading.value = false;
    uploadProgress.value = 0;
  }
};

// Konfirmasi Cetak Dokumen Setelah Memeriksa Pratinjau
const confirmPrintDocument = () => {
  if (!previewJob.value) return;

  router.post(route('printing.confirm', previewJob.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      isPreviewModalOpen.value = false;
      previewJob.value = null;
      previewUrl.value = null;
      Swal.fire({
        title: 'BERHASIL MASUK ANTREAN',
        text: 'Dokumen berhasil dimasukkan ke dalam antrean cetak Printer Brother.',
        icon: 'success',
        timer: 2500,
        showConfirmButton: false,
      });
      fetchQueueStatus();
    },
    onError: (err) => {
      Swal.fire({
        title: 'GAGAL MEMPROSES',
        text: err?.message || 'Terjadi kendala saat mengirim dokumen ke antrean.',
        icon: 'error',
        confirmButtonText: 'TUTUP',
        confirmButtonColor: '#e11d48',
      });
    }
  });
};

// Batalkan Dokumen yang Sedang Antre
const cancelQueueJob = (job) => {
  Swal.fire({
    title: 'BATALKAN ANTREAN CETAK?',
    text: `Dokumen "${job.document_title}" akan dibatalkan dari antrean printer.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'YA, BATALKAN',
    confirmButtonColor: '#e11d48',
    cancelButtonText: 'KEMBALI',
    cancelButtonColor: '#94a3b8',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('printing.cancel', job.id), {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire({
            title: 'DIBATALKAN',
            text: 'Antrean dokumen berhasil dibatalkan.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
          });
          fetchQueueStatus();
        }
      });
    }
  });
};

// Tes Koneksi Soket IP Printer (Khusus Admin)
const testPrinterConnection = async () => {
  isTestingConnection.value = true;
  testConnectionResult.value = null;

  try {
    const res = await fetch(route('printing.test-connection'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        ip: adminPrinterForm.printer_ip,
        port: adminPrinterForm.printer_port,
      }),
    });

    const data = await res.json();
    testConnectionResult.value = data;
  } catch (err) {
    testConnectionResult.value = {
      success: false,
      message: 'Gagal mengirim permintaan tes koneksi: ' + err.message,
    };
  } finally {
    isTestingConnection.value = false;
  }
};

// Simpan Pengaturan Printer (Khusus Admin)
const savePrinterSettings = () => {
  adminPrinterForm.post(route('printing.update-settings'), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({
        title: 'BERHASIL DISIMPAN',
        text: 'Konfigurasi Printer Brother berhasil diperbarui.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
      });
    }
  });
};

// Pembaruan status antrean berkala otomatis tanpa refresh (2000ms)
let pollingTimer = null;
const fetchQueueStatus = async () => {
  try {
    const res = await fetch(route('printing.queue-status'), {
      headers: { 'Accept': 'application/json' }
    });
    if (res.ok) {
      const data = await res.json();
      currentActiveJob.value = data.active_job;
      currentQueuedJobs.value = data.queued_jobs || [];
      currentRecentJobs.value = data.recent_jobs || [];
      if (data.stats) {
        currentStats.value = data.stats;
      }
    }
  } catch (e) {
    // Silent fail on background polling
  }
};

onMounted(() => {
  pollingTimer = setInterval(fetchQueueStatus, 2000);
});

onUnmounted(() => {
  if (pollingTimer) clearInterval(pollingTimer);
});
</script>

<template>
  <Head title="Layanan Printing Dokumen" />

  <AuthenticatedLayout>
    <div class="space-y-6 font-sans">

      <!-- Page Header Card -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h2 class="font-extrabold text-xl sm:text-2xl text-slate-900 uppercase tracking-tight">
              Layanan Printing Dokumen
            </h2>
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-ping"></span>
          </div>
          <p class="text-xs text-slate-500 font-semibold">
            Fasilitas Cetak Mandiri Berkas Dinas ke Printer Jaringan Brother dengan Sistem Antrean Bebas Tabrakan
          </p>
        </div>

        <!-- Info Perangkat Printer -->
        <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2.5 rounded-2xl shrink-0">
          <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700 shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
          </div>
          <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Printer Jaringan</span>
            <span class="text-xs font-black text-slate-800 uppercase block">
              {{ printerSettings?.printer_name || 'Brother Network Printer' }}
            </span>
            <span class="text-[10px] font-bold text-blue-600 block">
              IP: {{ printerSettings?.printer_ip }}:{{ printerSettings?.printer_port }}
            </span>
          </div>
        </div>
      </div>

      <!-- SPANDUK PERINGATAN WAJIB (HITAM PUTIH SAJA) -->
      <div class="bg-amber-500 text-slate-950 p-4 sm:p-5 rounded-2xl shadow-sm border-2 border-amber-600 flex items-center gap-3.5 sm:gap-4">
        <div class="w-10 h-10 rounded-xl bg-slate-950 text-amber-400 flex items-center justify-center shrink-0 shadow-md">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
          </svg>
        </div>
        <div class="flex-1">
          <span class="text-[10px] font-black tracking-widest uppercase text-slate-900 block">PERHATIAN PENTING SEBELUM MENCETAK</span>
          <p class="text-xs sm:text-sm font-black uppercase text-slate-950 leading-tight mt-0.5">
            Layanan ini hanya tersedia warna Hitam putih saja
          </p>
          <p class="text-[10px] sm:text-[11px] font-semibold text-slate-900 mt-1">
            Seluruh berkas dokumen yang diunggah akan otomatis diproses dan dicetak dalam mode monokrom (hitam-putih). Kertas pemisah kosong (1 lembar) otomatis dikeluarkan di akhir setiap dokumen.
          </p>
        </div>
      </div>

      <!-- Quick Stats Counter Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
          <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Antrean Menunggu</span>
            <span class="text-xl sm:text-2xl font-black text-blue-600 leading-tight block">
              {{ currentStats.total_queued }} Dokumen
            </span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black">
            Q
          </div>
        </div>

        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
          <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Selesai Hari Ini</span>
            <span class="text-xl sm:text-2xl font-black text-emerald-600 leading-tight block">
              {{ currentStats.total_today }} Berkas
            </span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black">
            D
          </div>
        </div>

        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
          <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Total Lembar Keluar</span>
            <span class="text-xl sm:text-2xl font-black text-slate-800 leading-tight block">
              {{ currentStats.sheets_today }} Lembar
            </span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-black">
            P
          </div>
        </div>
      </div>

      <!-- KARTU AKTIF: SEDANG DICETAK (LIVE PROGRESS) -->
      <div v-if="currentActiveJob" class="bg-gradient-to-r from-blue-900 to-indigo-950 text-white p-5 sm:p-6 rounded-3xl shadow-xl border-2 border-blue-500 relative overflow-hidden animate-in fade-in duration-300">
        <div class="absolute -right-10 -bottom-10 opacity-10 text-white pointer-events-none">
          <svg class="w-64 h-64" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8h-1V3H6v5H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM8 5h8v3H8V5zm8 14H8v-4h8v4zm2-4v-2H6v2H4v-4c0-.55.45-1 1-1h14c.55 0 1 .45 1 1v4h-2z"/></svg>
        </div>

        <div class="relative z-10 space-y-3">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <span class="px-3 py-1 rounded-full bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                <span>SEDANG DICETAK PADA PRINTER</span>
              </span>
              <span class="text-[10px] font-bold text-blue-200">
                Job ID #{{ currentActiveJob.id }}
              </span>
            </div>
            <span class="text-xs font-black text-amber-300 uppercase">
              Mode: Hitam Putih (Monochrome)
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
            <div>
              <h3 class="text-base sm:text-xl font-black uppercase tracking-tight text-white leading-tight">
                {{ currentActiveJob.document_title }}
              </h3>
              <p class="text-xs text-blue-200 mt-1">
                Pemohon: <strong class="text-white font-black">{{ currentActiveJob.user?.name || 'Personel' }}</strong> ({{ currentActiveJob.user?.pangkat || 'Personel' }} / NRP {{ currentActiveJob.user?.nrp || '-' }})
              </p>
              <p class="text-[11px] text-blue-300 mt-0.5">
                Berkas Asli: {{ currentActiveJob.original_filename }} ({{ currentActiveJob.total_pages }} Halaman Dokumen + 1 Lembar Pemisah Kosong)
              </p>
            </div>

            <!-- Progress Lembar Tercetak -->
            <div class="bg-white/10 backdrop-blur-md p-3.5 rounded-2xl border border-white/20 space-y-2">
              <div class="flex justify-between items-center text-xs">
                <span class="font-bold text-blue-100">Status Pengiriman Kertas:</span>
                <span class="font-black text-white text-sm">
                  Lembar {{ currentActiveJob.printed_sheets }} dari {{ currentActiveJob.total_sheets }}
                </span>
              </div>
              
              <!-- Progress Bar -->
              <div class="w-full bg-white/20 rounded-full h-3 overflow-hidden p-0.5">
                <div 
                  class="bg-gradient-to-r from-blue-400 to-emerald-400 h-full rounded-full transition-all duration-300"
                  :style="{ width: Math.max(8, (currentActiveJob.printed_sheets / currentActiveJob.total_sheets) * 100) + '%' }"
                ></div>
              </div>

              <span class="text-[10px] text-blue-200 block text-right font-medium">
                Pencegah Tabrakan Aktif: Dokumen lain menunggu hingga proses ini selesai.
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN SECTION: DUA KOLOM (UNGGAH DOKUMEN & ANTREAN BERKAS) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- KOLOM KIRI: FORMULIR UNGGAH DOKUMEN (4 Kolom pada LG) -->
        <div class="lg:col-span-5 bg-white p-5 sm:p-7 rounded-3xl shadow-xs border border-[#E2E8F0] space-y-5">
          <div class="border-b pb-3">
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 block">Formulir Cetak Dokumen</span>
            <h3 class="text-base sm:text-lg font-black text-slate-900 uppercase leading-tight">Unggah Berkas Dinas</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Mendukung format PDF dan Word (.docx / .doc)</p>
          </div>

          <form @submit.prevent="submitUploadAndPreview" class="space-y-4">
            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">
                Judul / Naskah Dokumen
              </label>
              <input 
                v-model="uploadForm.document_title" 
                type="text" 
                class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold p-3 uppercase focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
                placeholder="CONTOH: SURAT PERINTAH TUGAS..."
                required
              />
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-700 uppercase mb-1">
                Pilih Berkas (PDF / DOCX)
              </label>
              
              <div class="relative border-2 border-dashed border-slate-300 hover:border-blue-500 rounded-2xl p-4 sm:p-5 text-center transition bg-slate-50 hover:bg-blue-50/50">
                <input 
                  type="file" 
                  @change="handleFileChange" 
                  accept=".pdf,.docx,.doc" 
                  class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                  required
                />
                
                <div class="space-y-2 pointer-events-none">
                  <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                  </div>
                  <div class="text-xs font-extrabold text-slate-700 uppercase">
                    Klik atau Seret Berkas ke Sini
                  </div>
                  <p class="text-[10px] text-slate-400 font-semibold">Format didukung: PDF, DOCX, DOC (Maks 50 MB)</p>
                </div>
              </div>

              <!-- File Info Box -->
              <div v-if="selectedFileName" class="mt-2.5 bg-blue-50 border border-blue-200 p-3 rounded-xl flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <span class="text-[9px] font-black uppercase tracking-wider text-blue-600 block">Berkas Terpilih:</span>
                  <p class="text-xs font-black text-slate-800 truncate">{{ selectedFileName }}</p>
                  <span class="text-[10px] text-slate-500 font-semibold">{{ selectedFileSize }}</span>
                </div>
                <span class="px-2 py-1 bg-emerald-600 text-white rounded-lg text-[9px] font-black uppercase shrink-0">
                  SIAP DIPROSES
                </span>
              </div>
            </div>

            <!-- Catatan Kertas Pemisah Otomatis -->
            <div class="bg-slate-100 p-3 rounded-xl border border-slate-200 text-[11px] text-slate-600 space-y-1">
              <div class="font-extrabold text-slate-800 text-[10px] uppercase flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>SISTEM PEMISAH LEMBAR OTOMATIS</span>
              </div>
              <p class="text-[10px] leading-relaxed">
                Server secara otomatis menambahkan <strong>1 lembar kertas kosong</strong> di akhir berkas sebagai pemisah antar dokumen personel di baki printer.
              </p>
            </div>

            <!-- Tombol Submit Unggah & Pratinjau -->
            <button 
              type="submit" 
              :disabled="isUploading"
              class="w-full bg-blue-600 hover:bg-blue-700 active:scale-95 disabled:opacity-50 text-white py-3.5 px-4 rounded-xl font-black text-xs uppercase shadow-md shadow-blue-600/20 transition cursor-pointer flex items-center justify-center gap-2"
            >
              <svg v-if="isUploading" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
              </svg>
              <span>{{ isUploading ? 'MEMPROSES DOKUMEN...' : 'UNGGAH & TAMPILKAN PRATINJAU' }}</span>
            </button>
          </form>

          <!-- KARTU PENGATURAN PRINTER IP (KHUSUS ADMIN) -->
          <div v-if="isAdmin" class="mt-6 pt-5 border-t border-slate-200 space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-slate-800 text-white flex items-center justify-center text-xs font-black">A</div>
                <span class="text-xs font-black text-slate-900 uppercase">Pengaturan IP Printer Brother</span>
              </div>
              <span class="text-[9px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-black uppercase">Admin Only</span>
            </div>

            <div class="space-y-3 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
              <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                  <label class="block text-[9px] font-bold text-slate-600 uppercase mb-1">IP Address</label>
                  <input 
                    v-model="adminPrinterForm.printer_ip" 
                    type="text" 
                    class="w-full text-xs font-bold p-2 bg-white rounded-lg border border-slate-300"
                    placeholder="192.168.1.200"
                  />
                </div>
                <div>
                  <label class="block text-[9px] font-bold text-slate-600 uppercase mb-1">Port RAW</label>
                  <input 
                    v-model="adminPrinterForm.printer_port" 
                    type="number" 
                    class="w-full text-xs font-bold p-2 bg-white rounded-lg border border-slate-300"
                    placeholder="9100"
                  />
                </div>
              </div>

              <div>
                <label class="block text-[9px] font-bold text-slate-600 uppercase mb-1">Nama / Tipe Printer</label>
                <input 
                  v-model="adminPrinterForm.printer_name" 
                  type="text" 
                  class="w-full text-xs font-bold p-2 bg-white rounded-lg border border-slate-300 uppercase"
                  placeholder="Brother Laser Series"
                />
              </div>

              <!-- Hasil Diagnostik Tes Koneksi -->
              <div v-if="testConnectionResult" :class="testConnectionResult.success ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'" class="p-2.5 rounded-xl border text-[10px] font-bold">
                {{ testConnectionResult.message }}
              </div>

              <div class="flex items-center gap-2 pt-1">
                <button 
                  type="button" 
                  @click="testPrinterConnection" 
                  :disabled="isTestingConnection"
                  class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-800 py-2 px-3 rounded-lg text-[10px] font-black uppercase transition cursor-pointer text-center"
                >
                  {{ isTestingConnection ? 'MENGUJI...' : 'TES KONEKSI' }}
                </button>
                <button 
                  type="button" 
                  @click="savePrinterSettings" 
                  class="flex-1 bg-slate-900 hover:bg-slate-800 text-white py-2 px-3 rounded-lg text-[10px] font-black uppercase transition cursor-pointer text-center"
                >
                  SIMPAN PENGATURAN
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- KOLOM KANAN: TABEL ANTREAN CETAK & RIWAYAT (7 Kolom pada LG) -->
        <div class="lg:col-span-7 bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
          
          <!-- Navigation Tabs -->
          <div class="flex border-b border-slate-200 gap-2 px-4 sm:px-6 pt-4">
            <button 
              type="button"
              @click="activeTab = 'queue'"
              :class="activeTab === 'queue' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
              class="py-3 px-3 sm:px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
            >
              <span>ANTREAN MENUNGGU</span>
              <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black">
                {{ currentQueuedJobs.length }}
              </span>
            </button>

            <button 
              type="button"
              @click="activeTab = 'history'"
              :class="activeTab === 'history' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
              class="py-3 px-3 sm:px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
            >
              <span>RIWAYAT SELESAI HARI INI</span>
              <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">
                {{ currentRecentJobs.length }}
              </span>
            </button>
          </div>

          <!-- TAB 1: DAFTAR ANTREAN MENUNGGU -->
          <div v-if="activeTab === 'queue'" class="p-4 sm:p-6 space-y-4">
            <div v-if="currentQueuedJobs.length === 0" class="text-center py-12 space-y-2">
              <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center font-black">
                Q
              </div>
              <h4 class="text-sm font-extrabold text-slate-700 uppercase">Tidak Ada Antrean Menunggu</h4>
              <p class="text-xs text-slate-400 font-medium">Printer saat ini siap menerima berkas baru tanpa antre.</p>
            </div>

            <div v-else class="space-y-3">
              <div 
                v-for="(job, idx) in currentQueuedJobs" 
                :key="job.id"
                class="bg-slate-50 hover:bg-slate-100/80 border border-slate-200 p-4 rounded-2xl transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
              >
                <div class="flex items-start gap-3 min-w-0">
                  <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-xs shrink-0 mt-0.5">
                    #{{ idx + 1 }}
                  </div>
                  <div class="min-w-0">
                    <span class="text-[9px] font-black uppercase tracking-wider text-blue-600 block">Antrean Ke-{{ idx + 1 }}</span>
                    <h4 class="text-xs sm:text-sm font-black text-slate-900 uppercase truncate">
                      {{ job.document_title }}
                    </h4>
                    <p class="text-[11px] text-slate-500 font-medium">
                      Pemohon: <strong class="text-slate-700">{{ job.user?.name || 'Personel' }}</strong> ({{ job.user?.pangkat || 'TNI AL' }})
                    </p>
                    <div class="flex items-center gap-2 text-[10px] text-slate-400 font-semibold mt-1">
                      <span>{{ job.total_pages }} Hal Dokumen</span>
                      <span>+ 1 Kertas Pemisah</span>
                      <span class="text-slate-600 font-bold">= {{ job.total_sheets }} Lembar</span>
                    </div>
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                  <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 text-[10px] font-black uppercase">
                    MENUNGGU GILIRAN
                  </span>
                  
                  <!-- Tombol Batalkan (Jika milik sendiri atau admin) -->
                  <button 
                    v-if="job.user_id === user?.id || isAdmin"
                    type="button" 
                    @click="cancelQueueJob(job)"
                    class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border border-rose-200 transition cursor-pointer"
                    title="Batalkan Dokumen dari Antrean"
                  >
                    BATALKAN
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: RIWAYAT SELESAI HARI INI -->
          <div v-if="activeTab === 'history'" class="p-4 sm:p-6 space-y-4">
            <div v-if="currentRecentJobs.length === 0" class="text-center py-12 space-y-2">
              <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center font-black">
                H
              </div>
              <h4 class="text-sm font-extrabold text-slate-700 uppercase">Belum Ada Riwayat Cetak Hari Ini</h4>
              <p class="text-xs text-slate-400 font-medium">Dokumen yang selesai dicetak akan tercatat pada daftar ini.</p>
            </div>

            <div v-else class="overflow-x-auto custom-scrollbar">
              <table class="w-full min-w-[550px] text-left border-collapse text-xs">
                <thead class="bg-slate-50 uppercase font-extrabold text-slate-500 text-[10px] tracking-wider border-b border-slate-200">
                  <tr>
                    <th class="p-3">Waktu</th>
                    <th class="p-3">Naskah Dokumen</th>
                    <th class="p-3">Pemohon</th>
                    <th class="p-3">Jumlah Lembar</th>
                    <th class="p-3">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                  <tr v-for="job in currentRecentJobs" :key="'rec-' + job.id" class="hover:bg-slate-50/80">
                    <td class="p-3 text-[11px] text-slate-500 whitespace-nowrap">
                      {{ new Date(job.completed_at || job.updated_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }} WIB
                    </td>
                    <td class="p-3 text-slate-900">
                      <div class="font-bold uppercase leading-tight">{{ job.document_title }}</div>
                      <span class="text-[10px] text-slate-400 font-medium normal-case block truncate max-w-xs">{{ job.original_filename }}</span>
                    </td>
                    <td class="p-3 text-slate-600">
                      {{ job.user?.name || '-' }}
                    </td>
                    <td class="p-3 whitespace-nowrap">
                      <span class="font-extrabold text-slate-900">{{ job.printed_sheets || job.total_sheets }} Lembar</span>
                      <span class="text-[9px] text-slate-400 block">(Termasuk Pemisah)</span>
                    </td>
                    <td class="p-3 whitespace-nowrap">
                      <span 
                        v-if="job.status === 'completed'" 
                        class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-black text-[9px] uppercase"
                      >
                        SELESAI TERCETAK
                      </span>
                      <span 
                        v-else-if="job.status === 'failed'" 
                        class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 font-black text-[9px] uppercase" 
                        :title="job.error_message"
                      >
                        GAGAL
                      </span>
                      <span 
                        v-else-if="job.status === 'cancelled'" 
                        class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 font-black text-[9px] uppercase"
                      >
                        DIBATALKAN
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Keterangan Otomatisasi Hemat Penyimpanan Server -->
              <div class="mt-3 pt-3 border-t border-slate-200 text-[10px] text-slate-500 font-semibold flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Berkas fisik otomatis dibersihkan dari server setelah cetak selesai untuk efisiensi penyimpanan, seluruh riwayat pemohon dan identitas dokumen tetap tersimpan di buku riwayat.</span>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- MODAL PRATINJAU DOKUMEN & KONFIRMASI CETAK -->
    <div v-if="isPreviewModalOpen && previewJob" class="fixed inset-0 z-[150] flex items-center justify-center p-2 sm:p-4 bg-slate-950/85 backdrop-blur-md overflow-y-auto animate-in fade-in duration-300">
      <div class="bg-white rounded-3xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200 text-left my-auto max-h-[95vh] flex flex-col space-y-4">
        
        <!-- Header Modal -->
        <div class="flex items-center justify-between border-b pb-3 shrink-0">
          <div>
            <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-blue-600 block">
              PRATINJAU DOKUMEN & VALIDASI CETAK
            </span>
            <h3 class="text-xs sm:text-base font-black text-slate-900 uppercase leading-tight">
              {{ previewJob.document_title }}
            </h3>
          </div>

          <button 
            type="button" 
            @click="isPreviewModalOpen = false" 
            class="text-slate-400 hover:text-slate-600 font-bold p-1 text-2xl leading-none transition cursor-pointer"
            title="Tutup Pratinjau"
          >
            &times;
          </button>
        </div>

        <!-- PERINGATAN WAJIB DI DALAM MODAL -->
        <div class="bg-amber-50 border border-amber-300 p-3 rounded-xl text-amber-950 flex items-center gap-2.5 shrink-0">
          <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <div class="text-xs font-black uppercase leading-tight">
            Layanan ini hanya tersedia warna Hitam putih saja
          </div>
        </div>

        <!-- Frame PDF Pratinjau -->
        <div class="flex-1 bg-slate-900/60 rounded-2xl overflow-hidden border border-slate-300 min-h-[350px] sm:min-h-[460px] flex items-center justify-center relative">
          <iframe 
            v-if="previewUrl" 
            :src="previewUrl" 
            class="w-full h-full min-h-[350px] sm:min-h-[460px] border-0 rounded-2xl bg-white"
            title="Pratinjau Dokumen PDF"
          ></iframe>
          <div v-else class="text-white text-xs font-bold">
            Memuat Pratinjau Berkas...
          </div>
        </div>

        <!-- Rincian Kertas & Tombol Konfirmasi -->
        <div class="border-t pt-3 flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
          <!-- Rincian Lembar Kertas -->
          <div class="flex items-center gap-4 text-xs">
            <div>
              <span class="text-[9px] font-bold text-slate-400 uppercase block">Halaman Asli</span>
              <span class="font-black text-slate-800">{{ previewJob.total_pages }} Halaman</span>
            </div>
            <div class="border-l pl-4">
              <span class="text-[9px] font-bold text-slate-400 uppercase block">Kertas Pemisah</span>
              <span class="font-black text-emerald-600">+1 Lembar Kosong</span>
            </div>
            <div class="border-l pl-4">
              <span class="text-[9px] font-bold text-slate-400 uppercase block">Total Keluar Printer</span>
              <span class="font-black text-blue-700 text-sm">{{ previewJob.total_sheets }} Lembar</span>
            </div>
          </div>

          <!-- Tombol Aksi -->
          <div class="flex items-center gap-2 w-full sm:w-auto">
            <button 
              type="button" 
              @click="isPreviewModalOpen = false" 
              class="flex-1 sm:flex-initial bg-slate-100 hover:bg-slate-200 text-slate-600 px-5 py-3 rounded-xl font-black text-xs uppercase border border-slate-200 transition cursor-pointer text-center"
            >
              BATALKAN
            </button>
            <button 
              type="button" 
              @click="confirmPrintDocument" 
              class="flex-1 sm:flex-initial bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white px-7 py-3 rounded-xl font-black text-xs uppercase shadow-lg shadow-emerald-600/30 transition cursor-pointer text-center flex items-center justify-center gap-2"
            >
              <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              <span>CETAK SEKARANG</span>
            </button>
          </div>
        </div>

      </div>
    </div>

  </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
