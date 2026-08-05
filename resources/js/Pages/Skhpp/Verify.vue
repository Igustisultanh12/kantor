<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  skhpp: Object,
  settings: Object
});

const page = usePage();
const pageSettings = computed(() => props.settings || page.props.settings || {});
const appName = computed(() => pageSettings.value.agency_name || pageSettings.value.app_name || 'SINDEN');

const configuredLogo = computed(() => {
  const logo = pageSettings.value.agency_logo || pageSettings.value.logo || pageSettings.value.logo_tni;
  if (!logo) return '/images/logo.png';
  return (logo.startsWith('http') || logo.startsWith('/storage') || logo.startsWith('/images')) ? logo : '/storage/' + logo;
});

const loginBg = computed(() => {
  const bg = pageSettings.value.login_background;
  if (!bg) return null;
  return (bg.startsWith('http') || bg.startsWith('/storage')) ? bg : '/storage/' + bg;
});

const formatDateIndo = (dateStr) => {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>

<template>
  <Head :title="`Verifikasi Legalitas SKHPP - ${skhpp?.nama || 'SINDEN'}`" />

  <div 
    class="min-h-screen flex text-slate-800 font-sans bg-cover bg-center relative transition-all duration-300 bg-slate-950"
    :style="loginBg ? { backgroundImage: `url(${loginBg})` } : { backgroundColor: '#020617' }"
  >
    <!-- Dark overlay when background image is present -->
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-[3px] z-0"></div>

    <!-- Main Wrapper -->
    <div class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
      
      <!-- Bagian Kiri: Logo & Informasi Aplikasi (Desktop) -->
      <div 
        class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 lg:p-16 text-white z-10"
      >
        <div></div>
        
        <!-- Live Preview Logo -->
        <div class="my-auto max-w-lg space-y-6 flex flex-col items-center text-center mx-auto">
          <!-- Logo Utama SINDEN / Denintel -->
          <div class="flex justify-center animate-float-slow">
            <img v-if="configuredLogo" :src="configuredLogo" class="h-32 lg:h-44 object-contain drop-shadow-[0_10px_25px_rgba(0,0,0,0.6)]" alt="Logo SINDEN" />
            <div v-else class="w-32 h-32 rounded-3xl bg-blue-600/30 backdrop-blur-md border border-blue-500/40 flex items-center justify-center shadow-2xl">
              <span class="font-black text-white text-5xl">S</span>
            </div>
          </div>
          
          <div class="space-y-3">
            <h2 class="text-3xl font-extrabold tracking-tight uppercase text-white drop-shadow-md">
              {{ appName }} DETASEMEN INTELIJEN
            </h2>
            <p class="text-xs leading-relaxed max-w-md mx-auto text-slate-300 font-medium">
              Portal Otentikasi & Verifikasi Keabsahan Dokumen Resmi Surat Keterangan Hasil Penelitian Personel (SKHPP) Komando Daerah TNI Angkatan Laut V.
            </p>
          </div>
        </div>

        <p class="text-xs text-slate-400 text-center font-medium">
          © {{ new Date().getFullYear() }} {{ appName }}. Detasemen Intelijen Komando Daerah TNI Angkatan Laut V. All Rights Reserved.
        </p>
      </div>

      <!-- Bagian Kanan: Card Form Verifikasi Dokumen Publik -->
      <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-8 z-10 my-auto">
        <div 
          class="w-full max-w-lg p-6 sm:p-10 rounded-2xl shadow-2xl transition-all duration-300 space-y-6 bg-slate-900/90 backdrop-blur-md border border-white/10 text-white"
        >
          <!-- Header logo di mobile -->
          <div class="lg:hidden flex flex-col items-center justify-center gap-2 mb-4 text-center">
            <img v-if="configuredLogo" :src="configuredLogo" class="h-16 object-contain drop-shadow-md" />
            <h1 class="text-sm font-black uppercase text-white tracking-wider">{{ appName }} LEGALITAS DIGITAL</h1>
          </div>

          <!-- KONDISI 1: DOKUMEN VALID & TERDAFTAR -->
          <div v-if="skhpp" class="space-y-6">
            
            <!-- BANNER STATUS SAH -->
            <div 
              class="p-5 rounded-2xl text-center space-y-2 border shadow-xs bg-emerald-500/20 border-emerald-400/40 text-emerald-200"
            >
              <div class="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center mx-auto text-xl font-bold shadow-md shadow-emerald-500/30">
                ✓
              </div>
              <h3 class="text-lg font-extrabold tracking-tight uppercase">DOKUMEN RESMI TERVERIFIKASI & SAH</h3>
              <p class="text-xs leading-relaxed opacity-90">
                Surat Keterangan Hasil Penelitian Personel Ini Sah Ditandatangani Komandan Detasemen Intelijen Kodaeral V secara Digital.
              </p>
            </div>

            <!-- DETAIL HASIL VERIFIKASI -->
            <div class="space-y-4">
              <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300">
                  Manifes Otentikasi (Kode Unik)
                </span>
                <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded border bg-white/10 border-white/20 text-orange-300">
                  {{ skhpp.verification_code }}
                </span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                
                <!-- Judul Dokumen -->
                <div class="sm:col-span-2 p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Judul Dokumen Resmi</span>
                  <p class="text-xs font-bold text-white uppercase">SURAT KETERANGAN HASIL PENELITIAN PERSONEL (SKHPP)</p>
                  <p v-if="skhpp.kategori_personel === 'perusahaan'" class="text-[10px] font-bold text-blue-400 uppercase">MITRA KERJA TNI ANGKATAN LAUT</p>
                </div>

                <!-- Nomor Surat Resmi -->
                <div class="sm:col-span-2 p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nomor Surat Resmi</span>
                  <p class="text-xs font-mono font-bold text-orange-400">{{ skhpp.nomor_skhpp || 'Draft (Proses TTD)' }}</p>
                </div>

                <!-- Nama Personel (Subjek) -->
                <div class="p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Nama Personel (Subjek)</span>
                  <p class="text-xs font-bold text-emerald-300">{{ skhpp.nama }}</p>
                </div>

                <!-- Identitas: NRP / NIP / NIK -->
                <div class="p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span v-if="skhpp.pangkat_korps_nrp" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pangkat / Korps / NRP</span>
                  <span v-else-if="skhpp.nik" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">NIK Identitas</span>
                  <span v-else class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kategori Personel</span>

                  <p v-if="skhpp.pangkat_korps_nrp" class="text-xs font-mono font-bold text-blue-300">{{ skhpp.pangkat_korps_nrp }}</p>
                  <p v-else-if="skhpp.nik" class="text-xs font-mono font-bold text-white">{{ skhpp.nik }}</p>
                  <p v-else class="text-xs font-bold text-white uppercase">{{ skhpp.kategori_personel ? skhpp.kategori_personel.toUpperCase() : 'SIPIL' }}</p>
                </div>

                <!-- Jabatan / Pekerjaan -->
                <div class="sm:col-span-2 p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Jabatan / Pekerjaan</span>
                  <p class="text-xs font-semibold text-slate-200">{{ skhpp.jabatan_pekerjaan }}</p>
                </div>

                <!-- Maksud & Peruntukan -->
                <div class="sm:col-span-2 p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maksud & Peruntukan</span>
                  <p class="text-xs font-medium text-slate-300 leading-relaxed">{{ skhpp.peruntukan }}</p>
                </div>

                <!-- Tanggal & Waktu Penerbitan -->
                <div class="p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal Pengesahan</span>
                  <p class="text-xs font-semibold text-slate-200">{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at) }}</p>
                </div>

                <!-- Yang Bertanda Tangan -->
                <div class="p-3.5 rounded-xl border border-white/10 bg-white/5 space-y-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Yang Bertanda Tangan (Pejabat TTE)</span>
                  <p class="text-xs font-bold text-white">Hari Bagio Wijayanto, M.Tr.Opsla.</p>
                  <p class="text-[10px] text-slate-400">Komandan Detasemen Intelijen Kodaeral V — Kolonel Laut (E) NRP 16085/P</p>
                </div>

              </div>

              <!-- CATATAN JAMINAN KEASLIAN -->
              <div 
                class="p-4 rounded-xl text-xs leading-relaxed border border-blue-400/30 bg-blue-500/10 text-blue-200 space-y-1"
              >
                <strong class="block font-bold">🔒 Catatan Jaminan Keaslian:</strong>
                <p class="text-[11px] opacity-90 leading-relaxed">
                  Jika data pada dokumen fisik / lembar cetak berbeda dengan data di atas, maka dokumen tersebut dinyatakan <strong>TIDAK SAH / PALSU</strong>.
                </p>
              </div>
            </div>

          </div>

          <!-- KONDISI 2: KODE DOKUMEN TIDAK DITEMUKAN / TIDAK VALID -->
          <div v-else class="space-y-4">
            <div 
              class="p-6 rounded-2xl text-center space-y-3 border bg-red-500/20 border-red-400/40 text-red-200"
            >
              <div class="w-12 h-12 bg-red-500 text-white rounded-full flex items-center justify-center mx-auto text-xl font-bold shadow-md shadow-red-500/30">
                ✕
              </div>
              <h3 class="text-lg font-extrabold tracking-tight uppercase">DOKUMEN TIDAK TERDAFTAR</h3>
              <p class="text-xs leading-relaxed">
                Kode verifikasi tidak ditemukan pada basis data resmi SINDEN Detasemen Intelijen Kodaeral V.
              </p>
            </div>
          </div>

          <div class="pt-2 border-t border-white/10">
            <Link :href="route('login')" class="w-full text-center text-xs font-bold text-blue-400 hover:text-blue-300 hover:underline block cursor-pointer">
              ← Kembali ke Portal SINDEN
            </Link>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style>
html, body {
  background-color: #020617 !important; /* bg-slate-950 */
  margin: 0;
  padding: 0;
  min-height: 100vh;
}
</style>
