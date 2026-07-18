<template>
  <div class="min-h-screen bg-[#F8F9FD] text-slate-800 p-4 sm:p-6 lg:p-8" style="font-family: 'Arial', sans-serif;">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-200 pb-6 mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight uppercase">
          DASHBOARD ADMIN PESS
        </h1>
        <p class="text-xs font-semibold text-slate-500 tracking-wide mt-1">
          Sistem Verifikasi Berkas & Security Clearance Portal
        </p>
      </div>
      <div class="flex items-center space-x-2 bg-white border border-gray-200 px-4 py-2 rounded-xl shadow-sm">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span class="text-[10px] font-black tracking-widest text-slate-600 uppercase">SISTEM AKTIF</span>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div class="bg-white border border-gray-200/80 p-6 rounded-2xl shadow-sm">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Pendaftar</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ applicants.length }} <span class="text-xs text-slate-500 font-normal">Orang</span></p>
      </div>
      <div class="bg-white border border-gray-200/80 p-6 rounded-2xl shadow-sm border-l-4 border-l-amber-500">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Menunggu Verifikasi</p>
        <p class="text-2xl font-bold text-amber-600 mt-1">
          {{ submissions.filter(s => s.status === 'verifying').length }} <span class="text-xs text-slate-500 font-normal">Berkas</span>
        </p>
      </div>
      <div class="bg-white border border-gray-200/80 p-6 rounded-2xl shadow-sm border-l-4 border-l-indigo-500">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Proses Wawancara & SKHPP</p>
        <p class="text-2xl font-bold text-indigo-600 mt-1">
          {{ submissions.filter(s => ['wawancara_ready', 'wawancara_process', 'skhpp_pending_digital'].includes(s.status)).length }} <span class="text-xs text-slate-500 font-normal">Data</span>
        </p>
      </div>
      <div class="bg-white border border-gray-200/80 p-6 rounded-2xl shadow-sm border-l-4 border-l-emerald-500">
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Selesai (SC Terbit)</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1">
          {{ submissions.filter(s => s.status === 'sc_published').length }} <span class="text-xs text-slate-500 font-normal">Selesai</span>
        </p>
      </div>
    </div>

    <div class="flex space-x-2 border-b border-gray-200 mb-6">
      <button @click="currentTab = 'submissions'" :class="currentTab === 'submissions' ? 'border-b-2 border-indigo-600 text-indigo-600 font-black' : 'text-slate-500 font-bold'" class="px-4 py-2 text-xs uppercase tracking-wider focus:outline-none transition">
        📡 Antrean Berkas PESS
      </button>
      <button @click="currentTab = 'applicants'" :class="currentTab === 'applicants' ? 'border-b-2 border-indigo-600 text-indigo-600 font-black' : 'text-slate-500 font-bold'" class="px-4 py-2 text-xs uppercase tracking-wider focus:outline-none transition">
        👥 Manajemen Akun Personel
      </button>
    </div>

    <div v-if="currentTab === 'submissions'" class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
      <div class="bg-slate-50/80 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-xs font-black text-slate-700 tracking-widest uppercase flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-indigo-500 animate-ping"></span>
          Daftar Antrean Masuk (Portal PESS)
        </h2>
        <span class="text-[10px] font-mono font-bold text-slate-400">Koneksi: Terhubung ke Si Sinden</span>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-[10px] font-black uppercase text-slate-500 tracking-wider border-b border-gray-200">
              <th class="p-4 text-center w-16">No</th>
              <th class="p-4 border-r border-gray-100">Nama Lengkap / Identitas</th>
              <th class="p-4 border-r border-gray-100">Kategori Pengajuan</th>
              <th class="p-4 border-r border-gray-100">Status Prosedur</th>
              <th class="p-4 text-center w-48">Tindakan</th>
            </tr>
          </thead>
          <tbody class="text-xs divide-y divide-gray-100 bg-white">
            <tr v-for="(sub, index) in submissions" :key="'sub-'+sub.id" class="hover:bg-slate-50/50 transition duration-150">
              <td class="p-4 font-bold text-slate-400 text-center border-r border-gray-100">{{ index + 1 }}</td>
              <td class="p-4 border-r border-gray-100">
                <div class="font-bold text-slate-900 tracking-wide uppercase">{{ sub.applicant?.nama_lengkap || 'NAMA TIDAK TERSEDIA' }}</div>
                <div class="text-[10px] text-indigo-600 font-mono tracking-wider mt-1">ID/NRP: {{ sub.applicant?.nomor_identitas || '-' }}</div>
              </td>
              <td class="p-4 border-r border-gray-100">
                <span class="px-2.5 py-1 text-[9px] font-black rounded border tracking-wider shadow-sm uppercase"
                      :class="sub.category?.name?.toLowerCase().includes('nikah') ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-blue-50 text-blue-700 border-blue-200'">
                  {{ sub.category?.name || 'UMUM / KHUSUS' }}
                </span>
              </td>
              <td class="p-4 border-r border-gray-100">
                <span class="px-2 py-1 font-mono font-bold uppercase tracking-wide text-[10px] rounded" :class="getStatusColor(sub.status)">
                  {{ formatStatus(sub.status) }}
                </span>
              </td>
              <td class="p-4 text-center">
                <button v-if="sub.status === 'verifying' || sub.status === 'rejected_files'" 
                        @click="openVerifyModal(sub)" 
                        :disabled="isLoading"
                        class="w-full sm:w-auto px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm transition-all active:scale-95 disabled:opacity-50">
                  {{ isLoading ? 'Memproses...' : 'Cek Berkas' }}
                </button>

                <button v-if="sub.status === 'wawancara_ready'" 
                        @click="openInterviewModal(sub)" 
                        :disabled="isLoading"
                        class="w-full sm:w-auto px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm transition-all active:scale-95 disabled:opacity-50">
                  {{ isLoading ? 'Memproses...' : 'Plot Wawancara' }}
                </button>

                <button v-if="sub.status === 'wawancara_process'" 
                        @click="openSkhppModal(sub)" 
                        :disabled="isLoading"
                        class="w-full sm:w-auto px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm transition-all active:scale-95 disabled:opacity-50">
                  {{ isLoading ? 'Memproses...' : 'Terbit SKHPP' }}
                </button>

                <button v-if="sub.status === 'skhpp_pending_digital'" 
                        @click="executeSignKomandan(sub.id)" 
                        :disabled="isLoading"
                        class="w-full sm:w-auto px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm transition-all active:scale-95 disabled:opacity-50 border border-purple-400/30">
                  {{ isLoading ? 'Mohon Tunggu...' : 'Sign Digital' }}
                </button>

                <button v-if="sub.status === 'skhpp_complete'" 
                        @click="forwardToSinkodv(sub.id)" 
                        :disabled="isLoading"
                        class="w-full sm:w-auto px-4 py-2 bg-slate-900 hover:bg-black text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-sm transition-all active:scale-95 disabled:opacity-50">
                  {{ isLoading ? 'Mengirim...' : 'Kirim SINKODV ➔' }}
                </button>

                <span v-if="sub.status === 'sinkodv_verification'" class="text-[10px] text-slate-400 font-bold italic uppercase tracking-wider animate-pulse">📡 Proses Verifikasi SINKODV</span>
                <span v-if="sub.status === 'sc_published'" class="text-[10px] text-emerald-600 font-black uppercase tracking-widest flex items-center justify-center gap-1">✅ SC Selesai Terbit</span>
              </td>
            </tr>
            <tr v-if="submissions.length === 0">
              <td colspan="5" class="p-8 text-center text-slate-400 font-bold uppercase italic tracking-widest bg-slate-50">Tidak ada data antrean masuk dari portal pemohon.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="currentTab === 'applicants'" class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
      <div class="bg-slate-50/80 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-xs font-black text-slate-700 tracking-widest uppercase flex items-center gap-2">
          👥 Otoritas Akses Akun Portal PESS
        </h2>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-[10px] font-black uppercase text-slate-500 tracking-wider border-b border-gray-200">
              <th class="p-4 text-center w-16">No</th>
              <th class="p-4">Nama Lengkap Personel</th>
              <th class="p-4">NRP / NIP / NIK</th>
              <th class="p-4">No. WhatsApp Notification</th>
              <th class="p-4 text-center">Status Akses</th>
              <th class="p-4 text-center w-64">Aksi Kontrol</th>
            </tr>
          </thead>
          <tbody class="text-xs divide-y divide-gray-100 bg-white">
            <tr v-for="(app, index) in applicants" :key="'app-'+app.id" class="hover:bg-slate-50/50 transition duration-150">
              <td class="p-4 font-bold text-slate-400 text-center">{{ index + 1 }}</td>
              <td class="p-4 font-bold text-slate-900 uppercase tracking-wide">{{ app.nama_lengkap || 'TIDAK TERIDENTIFIKASI' }}</td>
              <td class="p-4 font-mono font-bold text-indigo-600">{{ app.nomor_identitas || '-' }}</td>
              <td class="p-4 font-mono text-slate-600">{{ app.no_wa || '-' }}</td>
              <td class="p-4 text-center">
                <span class="px-2 py-0.5 text-[9px] font-black rounded uppercase"
                      :class="{
                        'bg-emerald-50 text-emerald-700 border border-emerald-200': app.status === 'approved',
                        'bg-amber-50 text-amber-700 border border-amber-200': app.status === 'pending' || !app.status,
                        'bg-rose-50 text-rose-700 border border-rose-200': app.status === 'rejected'
                      }">
                  {{ app.status || 'PENDING' }}
                </span>
              </td>
              <td class="p-4 text-center flex items-center justify-center gap-1.5">
                <button v-if="app.status !== 'approved'" @click="changeAccountStatus(app.id, 'approved')" class="px-2.5 py-1.5 bg-emerald-600 text-white font-black text-[9px] uppercase tracking-wider rounded-lg shadow-sm hover:bg-emerald-700 transition">ACC</button>
                <button v-if="app.status !== 'rejected'" @click="changeAccountStatus(app.id, 'rejected')" class="px-2.5 py-1.5 bg-rose-600 text-white font-black text-[9px] uppercase tracking-wider rounded-lg shadow-sm hover:bg-rose-700 transition">TOLAK</button>
                <button @click="openEditModal(app)" class="px-2.5 py-1.5 bg-blue-500 text-white font-black text-[9px] uppercase tracking-wider rounded-lg shadow-sm hover:bg-blue-600 transition">EDIT</button>
                <button @click="deleteAccount(app.id)" class="px-2.5 py-1.5 bg-slate-800 text-white font-black text-[9px] uppercase tracking-wider rounded-lg shadow-sm hover:bg-black transition">HAPUS</button>
              </td>
            </tr>
            <tr v-if="applicants.length === 0">
              <td colspan="6" class="p-8 text-center text-slate-400 font-bold uppercase italic tracking-widest bg-slate-50">Tidak ada pangkalan data akun personel terdaftar.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="activeModal === 'verify'" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
      <div class="bg-white max-w-xl w-full p-6 rounded-2xl shadow-xl space-y-4 border border-gray-200 text-left transition-all">
        <div class="flex justify-between items-center border-b border-gray-200 pb-3">
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Verifikasi Berkas Lampiran</h3>
          <button @click="activeModal = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
        </div>
        
        <div class="bg-slate-50 p-4 rounded-xl border border-gray-200 text-xs text-slate-700 space-y-3">
          <p><strong class="text-slate-500">Nama Pendaftar:</strong> <span class="text-slate-900 uppercase font-bold">{{ selectedSub?.applicant?.nama_lengkap || '-' }}</span></p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
            <a v-if="selectedSub?.generated_pdf_path" :href="'/storage/' + selectedSub.generated_pdf_path" target="_blank" class="flex items-center justify-center gap-2 p-2.5 bg-indigo-50 border border-indigo-200 rounded-xl font-black text-indigo-600 hover:bg-indigo-100 text-center uppercase tracking-wider">
              📄 Lihat Master DRH (PDF)
            </a>
            <template v-for="(path, key) in selectedSub?.attachment_paths" :key="key">
              <a v-if="path" :href="'/storage/' + path" target="_blank" class="flex items-center justify-center gap-2 p-2.5 bg-white border border-gray-200 rounded-xl font-bold text-slate-700 hover:bg-slate-50 text-center uppercase truncate">
                📎 File: {{ key.replace('_', ' ') }}
              </a>
            </template>
          </div>
        </div>

        <div class="space-y-3 pt-2">
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Catatan Alasan Penolakan Berkas (Opsional):</label>
            <textarea v-model="form.catatan_admin" placeholder="Tulis alasan jika berkas salah atau tidak sesuai kualifikasi..." class="w-full text-xs bg-white border border-gray-200 p-2 rounded-xl text-slate-800 focus:outline-none focus:border-indigo-500 h-16 mt-1"></textarea>
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Unggah Soal Lembar SC (Format PDF/DOCX - Wajib):</label>
            <input type="file" @change="e => form.file_soal_sc = e.target.files[0]" class="w-full text-xs text-slate-500 border border-gray-200 p-2 rounded-xl bg-white mt-1">
          </div>
          
          <div v-if="selectedSub?.category?.name?.toLowerCase().includes('nikah')" class="space-y-1">
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Unggah Berkas Urusan Kesehatan (Calon Pasangan):</label>
            <input type="file" @change="e => form.file_kesehatan = e.target.files[0]" class="w-full text-xs text-slate-500 border border-gray-200 p-2 rounded-xl bg-white mt-1">
          </div>
        </div>

        <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
          <button @click="submitVerify('REJECTED')" :disabled="isLoading" class="px-4 py-2 bg-red-600 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm hover:bg-red-700 disabled:opacity-50">Tolak Berkas</button>
          <button @click="submitVerify('APPROVED')" :disabled="isLoading" class="px-4 py-2 bg-indigo-600 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm hover:bg-indigo-700 disabled:opacity-50">Setujui Berkas</button>
        </div>
      </div>
    </div>

    <div v-if="activeModal === 'interview'" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
      <div class="bg-white max-w-md w-full p-6 rounded-2xl shadow-xl space-y-4 border border-gray-200 text-left">
        <div class="flex justify-between items-center border-b border-gray-200 pb-3">
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Penjadwalan Wawancara Pemohon</h3>
          <button @click="activeModal = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
        </div>
        <div class="space-y-3">
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Metode Pelaksanaan:</label>
            <select v-model="interviewForm.metode" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 font-bold text-slate-700 focus:border-indigo-500">
              <option value="langsung">Tatap Muka Langsung / Luring</option>
              <option value="daring">Online / Virtual (Daring)</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Waktu Pelaksanaan (YYYY-MM-DD HH:MM:SS):</label>
            <input type="text" v-model="interviewForm.waktu" placeholder="Contoh: 2026-05-20 09:00:00" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 font-mono text-slate-800">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Lokasi Ruangan / Link Meeting Zoom:</label>
            <input type="text" v-model="interviewForm.lokasi_link" placeholder="Ruang Rapat Sintel / Link URL Google Meet" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 text-slate-800">
          </div>
        </div>
        <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
          <button @click="activeModal = null" class="px-3 py-1.5 bg-slate-100 text-slate-500 text-[10px] font-black uppercase rounded-xl border border-gray-200">Batal</button>
          <button @click="submitInterview" :disabled="isLoading" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm disabled:opacity-50">Kirim Notifikasi Jadwal</button>
        </div>
      </div>
    </div>

    <div v-if="activeModal === 'skhpp'" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
      <div class="bg-white max-w-md w-full p-6 rounded-2xl shadow-xl space-y-4 border border-gray-200 text-left">
        <div class="flex justify-between items-center border-b border-gray-200 pb-3">
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Unggah Hasil Format SKHPP</h3>
          <button @click="activeModal = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
        </div>
        <div class="space-y-3">
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Metode Tanda Tangan Dokumen:</label>
            <select v-model="skhppForm.jenis_ttd" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 font-bold text-slate-700">
              <option value="manual">Manual (Unggah File Hasil Scan TTD Fisik)</option>
              <option value="digital">Digital (Sistem Deteksi Koordinat Otomatis)</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">File Dokumen SKHPP (Ekstensi .PDF):</label>
            <input type="file" @change="e => skhppForm.file_skhpp = e.target.files[0]" class="w-full text-xs text-slate-500 border border-gray-200 p-2 rounded-xl bg-white mt-1">
          </div>
        </div>
        <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
          <button @click="activeModal = null" class="px-3 py-1.5 bg-slate-100 text-slate-500 text-[10px] font-black uppercase rounded-xl border border-gray-200">Batal</button>
          <button @click="submitSkhpp" :disabled="isLoading" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm disabled:opacity-50">Proses Unggahan</button>
        </div>
      </div>
    </div>

    <div v-if="activeModal === 'editApplicant'" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
      <div class="bg-white max-w-md w-full p-6 rounded-2xl shadow-xl space-y-4 border border-gray-200 text-left">
        <div class="flex justify-between items-center border-b border-gray-200 pb-3">
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">Koreksi Data Primer Personel</h3>
          <button @click="activeModal = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
        </div>
        <div class="space-y-3">
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Nama Lengkap:</label>
            <input type="text" v-model="editForm.nama_lengkap" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 uppercase font-bold text-slate-900">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">Nomor Identitas (NRP / NIP):</label>
            <input type="text" v-model="editForm.nomor_identitas" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 font-mono font-bold text-indigo-600">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase text-slate-500 tracking-wider">No. WhatsApp:</label>
            <input type="text" v-model="editForm.no_wa" class="w-full bg-white border border-gray-200 p-2.5 text-xs rounded-xl mt-1 font-mono text-slate-800">
          </div>
        </div>
        <div class="flex justify-end space-x-2 pt-4 border-t border-gray-200">
          <button @click="activeModal = null" class="px-3 py-1.5 bg-slate-100 text-slate-500 text-[10px] font-black uppercase rounded-xl border border-gray-200">Batal</button>
          <button @click="submitEditApplicant" :disabled="isLoading" class="px-4 py-2 bg-indigo-600 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-sm hover:bg-indigo-700 disabled:opacity-50">Simpan Perubahan</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

// SULTAN FIXED PROPS: Mendeklarasikan data array applicants murni dari backend
defineProps({
  submissions: Array,
  applicants: Array
});

const currentTab = ref('submissions'); // Default Tab Control Antrean Berkas
const activeModal = ref(null);
const selectedSub = ref(null);
const isLoading = ref(false);

const form = useForm({
  status_verifikasi: '',
  catatan_admin: '',
  file_soal_sc: null,
  file_kesehatan: null,
});

const interviewForm = useForm({
  metode: 'langsung',
  waktu: '',
  lokasi_link: ''
});

const skhppForm = useForm({
  jenis_ttd: 'manual',
  file_skhpp: null
});

// SULTAN CONFIG: Form State Khusus Pengendali Edit Data Profil Personel
const editForm = useForm({
  id: '',
  nama_lengkap: '',
  nomor_identitas: '',
  no_wa: ''
});

const formatStatus = (status) => {
  const map = {
    'verifying': 'Menunggu Verifikasi Berkas',
    'rejected_files': 'Berkas Salah (Perlu Perbaikan)',
    'wawancara_ready': 'Siap Dijadwalkan Wawancara',
    'wawancara_process': 'Proses Tahap Wawancara',
    'skhpp_pending_digital': 'Menunggu TTD Digital',
    'skhpp_complete': 'SKHPP Selesai / Siap Transmit',
    'sinkodv_verification': 'Sedang Diverifikasi SINKODV',
    'sc_published': 'Security Clearance Selesai Terbit'
  };
  return map[status] || status;
};

const getStatusColor = (status) => {
  const colorMap = {
    'verifying': 'text-amber-700 bg-amber-50 border border-amber-200',
    'rejected_files': 'text-rose-700 bg-rose-50 border border-rose-200',
    'wawancara_ready': 'text-blue-700 bg-blue-50 border border-blue-200',
    'wawancara_process': 'text-yellow-700 bg-yellow-50 border border-yellow-200',
    'skhpp_pending_digital': 'text-purple-700 bg-purple-50 border border-purple-200',
    'skhpp_complete': 'text-teal-700 bg-teal-50 border border-teal-200',
    'sinkodv_verification': 'text-slate-600 bg-slate-50 border border-slate-200',
    'sc_published': 'text-emerald-700 bg-emerald-50 border border-emerald-200'
  };
  return colorMap[status] || 'text-slate-600 bg-white border border-gray-200';
};

const openVerifyModal = (sub) => {
  selectedSub.value = sub;
  activeModal.value = 'verify';
};

const submitVerify = (status) => {
  isLoading.value = true;
  form.status_verifikasi = status;
  form.post(`/admin/submissions/verify/${selectedSub.value.id}`, {
    onSuccess: () => { activeModal.value = null; form.reset(); },
    onFinish: () => { isLoading.value = false; }
  });
};

const openInterviewModal = (sub) => {
  selectedSub.value = sub;
  activeModal.value = 'interview';
};

const submitInterview = () => {
  isLoading.value = true;
  interviewForm.post(`/admin/submissions/interview/${selectedSub.value.id}`, {
    onSuccess: () => { activeModal.value = null; interviewForm.reset(); },
    onFinish: () => { isLoading.value = false; }
  });
};

const openSkhppModal = (sub) => {
  selectedSub.value = sub;
  activeModal.value = 'skhpp';
};

const submitSkhpp = () => {
  isLoading.value = true;
  skhppForm.post(`/admin/submissions/skhpp/${selectedSub.value.id}`, {
    onSuccess: () => { activeModal.value = null; skhppForm.reset(); },
    onFinish: () => { isLoading.value = false; }
  });
};

const executeSignKomandan = (id) => {
  if (confirm('Konfirmasi: Eksekusi stempel dan tanda tangan digital pada berkas SKHPP ini?')) {
    isLoading.value = true;
    router.post(`/admin/submissions/sign-komandan/${id}`, {}, {
      onFinish: () => { isLoading.value = false; }
    });
  }
};

const forwardToSinkodv = (id) => {
  if (confirm('Konfirmasi: Teruskan seluruh data berkas dan dokumen SKHPP ini ke sistem SINKODV?')) {
    isLoading.value = true;
    router.post(`/admin/submissions/forward-sinkodv/${id}`, {}, {
      onFinish: () => { isLoading.value = false; }
    });
  }
};

// =========================================================================
// SULTAN CONFIG: AJAX CONTROLLER METHODS UNTUK MANAJEMEN AKUN UTAMA
// =========================================================================

// 1. Otorisasi Akses ACC / TOLAK Akun Portal PESS
const changeAccountStatus = (id, status) => {
  const teks = status === 'approved' ? 'MENYETUJUI' : 'MENOLAK';
  if (confirm(`Apakah Anda yakin ingin ${teks} permohonan akses akun portal personel ini?`)) {
    isLoading.value = true;
    router.post(`/admin/applicants/${id}/update-status`, { status: status }, {
      onFinish: () => { isLoading.value = false; }
    });
  }
};

// 2. Buka Modal Pengedit Data Identitas Primer
const openEditModal = (applicant) => {
  editForm.id = applicant.id;
  editForm.nama_lengkap = applicant.nama_lengkap;
  editForm.nomor_identitas = applicant.nomor_identitas;
  editForm.no_wa = applicant.no_wa;
  activeModal.value = 'editApplicant';
};

// 3. Kirim Paket Update Koreksi Data Profile Personel
const submitEditApplicant = () => {
  isLoading.value = true;
  editForm.put(`/admin/applicants/${editForm.id}/edit`, {
    onSuccess: () => { activeModal.value = null; editForm.reset(); },
    onFinish: () => { isLoading.value = false; }
  });
};

// 4. Eksekusi Penghapusan Akun Permanen (Auto-Cascade)
const deleteAccount = (id) => {
  if (confirm('🚨 PERINGATAN MILITER: Menghapus akun pendaftar ini akan memusnahkan seluruh file riwayat pengajuan DRH mereka secara permanen dari pangkalan radar Si Sinden! Lanjutkan?')) {
    isLoading.value = true;
    router.delete(`/admin/applicants/${id}/delete`, {
      onFinish: () => { isLoading.value = false; }
    });
  }
};
</script>