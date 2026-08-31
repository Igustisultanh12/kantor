<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue'; 
import axios from 'axios'; 

const props = defineProps({
    stats: Object,
    recent_logs: Array,
    combined_activities: Array, 
    pending_users: Array, 
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value.role === 'admin');

// GPS Updater
const sendPreciseLocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                axios.post('/update-location', {
                    latitude: lat,
                    longitude: lng
                }).then(response => {
                    console.log('Sistem SINDEN: Lokasi GPS diperbarui.');
                }).catch(error => {
                    console.error('Gagal memperbarui lokasi GPS.');
                });
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    console.warn("Akses lokasi ditolak oleh personel.");
                }
            },
            { enableHighAccuracy: true } 
        );
    }
};

// Auto-Rotating Announcement Carousel (5-second rotate persis video)
const currentSlide = ref(0);
const slides = ref([
    {
        tag: 'Penyegaran Tampilan',
        title: 'PENYEGARAN TAMPILAN SINDEN',
        description: 'Sistem Informasi & Database Intelijen kini mengadopsi antarmuka modern terpadu CORETAX untuk kemudahan akses di Smartphone dan PC.',
        highlight: 'Navigasi Lebih Cepat & Terintegrasi',
        bg: 'from-amber-500/20 to-blue-500/10'
    },
    {
        tag: 'Keamanan Operasional',
        title: 'RADAR TRACKING & LOKASI REAL-TIME',
        description: 'Seluruh pergerakan personel dan logistik operasional terintegrasi dengan pemetaan GPS cerdas Detasemen Intelijen Kodaeral V.',
        highlight: 'Presisi Tinggi & Deteksi Manipulasi',
        bg: 'from-blue-500/20 to-indigo-500/10'
    },
    {
        tag: 'Otoritas Kedinasan',
        title: 'TTE DIGITAL & SKHPP TERVERIFIKASI',
        description: 'Penerbitan SKHPP dan penandatanganan berkas naskah dinas kini terverifikasi instan melalui pemindaian QR Code resmi.',
        highlight: 'Otentikasi Berkas Resmi',
        bg: 'from-emerald-500/20 to-amber-500/10'
    }
]);

let carouselTimer = null;

onMounted(() => {
    sendPreciseLocation();

    // Rotasi banner setiap 5 detik persis CORETAX DJP
    carouselTimer = setInterval(() => {
        currentSlide.value = (currentSlide.value + 1) % slides.value.length;
    }, 5000);
});

onUnmounted(() => {
    if (carouselTimer) clearInterval(carouselTimer);
});
</script>

<template>
    <Head title="Dashboard Utama - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-8 font-sans animate-fade-in">
            
            <!-- ========================================================================= -->
            <!-- 1. HERO GREETING BANNER                                                   -->
            <!-- ========================================================================= -->
            <div class="p-6 sm:p-8 rounded-3xl border border-white/10 bg-[#121827] shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 bg-amber-500/20 border border-amber-500/40 text-amber-400 font-extrabold text-[10px] uppercase rounded-full tracking-wider">
                            Sistem Intelijen Kedinasan
                        </span>
                        <span class="text-slate-400 text-xs font-semibold">â€¢ Live Online</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                        Selamat datang, <span class="text-amber-400">{{ user.pangkat || '' }} {{ user.name }}</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-400 font-medium leading-relaxed">
                        SINDEN siap melayani kebutuhan operasional, penerbitan SKHPP, pengesahan TTE Digital, dan administrasi intelijen Anda hari ini.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link 
                        :href="route('letters.index')" 
                        class="px-5 py-3 bg-[#FFC107] hover:bg-[#F59E0B] text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition shadow-lg shadow-amber-500/20 active:scale-[0.98]"
                    >
                        + Buat Surat Baru
                    </Link>
                    <Link 
                        :href="route('skhpp.create')" 
                        class="px-5 py-3 bg-white/10 hover:bg-white/20 text-white font-bold text-xs uppercase tracking-wider rounded-2xl transition border border-white/10"
                    >
                        + Ajukan SKHPP
                    </Link>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. QUICK ACTION GRID (8 UBIN IKON EMAS KHAS CORETAX DJP)                  -->
            <!-- ========================================================================= -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-extrabold uppercase tracking-widest text-slate-400">
                        Menu Layanan Cepat
                    </h3>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 sm:gap-4">
                    
                    <!-- 1. Portal Saya -->
                    <Link :href="route('dashboard')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ‘¤
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">Portal Saya</span>
                    </Link>

                    <!-- 2. e-Arsip -->
                    <Link :href="route('letter-logs.index')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ“
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">e-Arsip</span>
                    </Link>

                    <!-- 3. SKHPP -->
                    <Link :href="route('skhpp.index')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ›¡ï¸
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">SKHPP</span>
                    </Link>

                    <!-- 4. TTE Digital -->
                    <Link :href="route('signature.index')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            âœï¸
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">TTE Digital</span>
                    </Link>

                    <!-- 5. Buku Kas -->
                    <Link :href="route('cash.index')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ’°
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">Buku Kas</span>
                    </Link>

                    <!-- 6. Radar GPS -->
                    <Link :href="route('activities.map')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ“¡
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">Radar GPS</span>
                    </Link>

                    <!-- 7. PC Backup -->
                    <Link :href="route('backup.index')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ’¾
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">PC Backup</span>
                    </Link>

                    <!-- 8. Otoritas Admin -->
                    <Link :href="route('users.index')" class="p-4 rounded-2xl border border-white/10 bg-[#121827] hover:border-amber-400 hover:scale-[1.02] transition flex flex-col items-center justify-center text-center space-y-2 shadow-sm group">
                        <div class="w-11 h-11 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg group-hover:bg-amber-500 group-hover:text-slate-950 transition">
                            ðŸ‘¥
                        </div>
                        <span class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition">Personel</span>
                    </Link>

                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. BANNER PENGUMUMAN BERPUTAR (5-SECOND ROTATE PERSIS CORETAX)            -->
            <!-- ========================================================================= -->
            <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-r p-6 sm:p-8 transition-all duration-500 shadow-xl"
                :class="slides[currentSlide].bg"
            >
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-amber-500/20 border border-amber-500/40 text-amber-400 font-extrabold text-[10px] uppercase rounded-full tracking-wider">
                                ðŸ“¢ {{ slides[currentSlide].tag }}
                            </span>
                            <span class="text-[11px] text-slate-400">Banner rotate setiap 5 detik</span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            {{ slides[currentSlide].title }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-medium">
                            {{ slides[currentSlide].description }}
                        </p>
                    </div>

                    <!-- Slide Indicators -->
                    <div class="flex items-center gap-2">
                        <button 
                            v-for="(slide, idx) in slides" 
                            :key="idx"
                            @click="currentSlide = idx"
                            class="h-2 rounded-full transition-all cursor-pointer"
                            :class="currentSlide === idx ? 'w-8 bg-amber-400' : 'w-2 bg-white/20 hover:bg-white/40'"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. STATS WIDGET ROW                                                       -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="bg-[#121827] p-6 rounded-2xl border border-white/10 shadow-sm flex items-center justify-between transition hover:border-amber-500/40">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Buku Nomor Surat</span>
                        <h3 class="text-3xl font-black text-white py-1">{{ stats.total_logs || 0 }}</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Total Registrasi Surat Keluar</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-blue-500/15 border border-blue-500/30 text-blue-400 flex items-center justify-center font-bold text-lg shadow-xs">
                        ðŸ“
                    </div>
                </div>

                <div class="bg-[#121827] p-6 rounded-2xl border border-white/10 shadow-sm flex items-center justify-between transition hover:border-amber-500/40">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Arsip Berkas</span>
                        <h3 class="text-3xl font-black text-white py-1">{{ stats.total_archives || 0 }}</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Dokumen Terarsip</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold text-lg shadow-xs">
                        ðŸ›¡ï¸
                    </div>
                </div>

                <div class="bg-[#121827] p-6 rounded-2xl border border-white/10 shadow-sm flex items-center justify-between transition hover:border-amber-500/40">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Master Personel</span>
                        <h3 class="text-3xl font-black text-white py-1">{{ stats.active_personnel || 0 }}</h3>
                        <p class="text-[11px] text-slate-400 font-medium">Pengguna Sistem Aktif</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400 flex items-center justify-center font-bold text-lg shadow-xs">
                        ðŸ‘¥
                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- 5. SECTION BANTUAN & PANDUAN KEDINASAN (PERSIS VIDEO CORETAX)              -->
            <!-- ========================================================================= -->
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <span class="text-lg">ðŸ“–</span>
                    <h3 class="text-sm font-extrabold uppercase tracking-wider text-white">Bantuan & Panduan Kedinasan</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    <div class="p-5 rounded-2xl border border-white/10 bg-[#121827] space-y-2 hover:border-amber-400/50 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-amber-400 uppercase">Layanan Personel</span>
                            <span class="text-slate-500">â†’</span>
                        </div>
                        <p class="text-xs text-slate-300 font-bold">Prosedur Pengajuan & Penerbitan SKHPP</p>
                        <p class="text-[11px] text-slate-400">Panduan lengkap penerbitan surat keterangan hasil penelitian personel dan mitra kerja.</p>
                    </div>

                    <div class="p-5 rounded-2xl border border-white/10 bg-[#121827] space-y-2 hover:border-amber-400/50 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-amber-400 uppercase">Tata Naskah Dinas</span>
                            <span class="text-slate-500">â†’</span>
                        </div>
                        <p class="text-xs text-slate-300 font-bold">Pengesahan TTE Digital Berkas</p>
                        <p class="text-[11px] text-slate-400">Tata cara penandatanganan elektronik naskah dinas dengan QR Code verifikasi resmi.</p>
                    </div>

                    <div class="p-5 rounded-2xl border border-white/10 bg-[#121827] space-y-2 hover:border-amber-400/50 transition cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-amber-400 uppercase">Radar Keamanan</span>
                            <span class="text-slate-500">â†’</span>
                        </div>
                        <p class="text-xs text-slate-300 font-bold">Pencatatan GPS & Disiplin Personel</p>
                        <p class="text-[11px] text-slate-400">Instruksi pengawasan lapangan, pencatatan radar harian, dan evaluasi kepatuhan.</p>
                    </div>

                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
</style>