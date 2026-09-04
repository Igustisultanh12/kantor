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
    today_attendance: Object,
    recent_attendances: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
const isCommanderOrAdmin = computed(() => {
    const role = (currentUser.value?.role || '').toLowerCase();
    return role === 'admin' || role === 'komandan' || role === 'pasops' || currentUser.value?.name === 'I Gusti Sultan H.A, A.Md.Kom';
});

// Jam Digital Real-Time Kedinasan
const currentTimeStr = ref('');
let clockTimer = null;

const updateClock = () => {
    const now = new Date();
    currentTimeStr.value = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    }) + ' WIB';
};

// Deteksi Otomatis Merk HP & Seri Perangkat
const detectDeviceModel = async () => {
    // 1. Prioritas Client Hints Modern
    if (navigator.userAgentData && navigator.userAgentData.getHighEntropyValues) {
        try {
            const data = await navigator.userAgentData.getHighEntropyValues(['model', 'platform', 'platformVersion']);
            if (data.model) {
                const brandObj = navigator.userAgentData.brands?.find(b => !b.brand.includes('Brand') && !b.brand.includes('Chromium'));
                const brand = brandObj ? brandObj.brand + ' ' : '';
                return `${brand}${data.model} (${data.platform || 'Mobile'})`.trim();
            }
            if (data.platform) {
                return `${data.platform} (${navigator.userAgentData.mobile ? 'Mobile' : 'Desktop'})`;
            }
        } catch (e) {}
    }

    // 2. Parser Cerdas User Agent String
    const ua = navigator.userAgent || '';
    if (/iPhone/i.test(ua)) return 'Apple iPhone';
    if (/iPad/i.test(ua)) return 'Apple iPad';

    const androidMatch = ua.match(/Android[^;]+;\s*([^;]+?)\s*(?:Build|\))/i);
    if (androidMatch && androidMatch[1]) {
        let model = androidMatch[1].trim();
        if (model.startsWith('SM-') || /samsung/i.test(ua)) {
            return `Samsung ${model}`;
        }
        if (/Xiaomi|Redmi|POCO/i.test(ua) || /M2\d{3}|2\d{6}/i.test(model)) {
            return `Xiaomi ${model}`;
        }
        if (/OPPO|CPH\d{4}/i.test(ua) || model.startsWith('CPH')) {
            return `OPPO ${model}`;
        }
        if (/vivo|V2\d{3}/i.test(ua) || model.startsWith('V2')) {
            return `Vivo ${model}`;
        }
        if (/Infinix|X\d{3}/i.test(ua) || model.startsWith('X')) {
            return `Infinix ${model}`;
        }
        if (/Realme|RMX\d{4}/i.test(ua) || model.startsWith('RMX')) {
            return `Realme ${model}`;
        }
        return `Android (${model})`;
    }

    if (/Windows NT/i.test(ua)) return 'Windows PC';
    if (/Macintosh/i.test(ua)) return 'Apple Mac';
    if (/Linux/i.test(ua)) return 'Linux PC';

    return 'Perangkat Seluler / Komputer';
};

// Modal & Form Presensi Kehadiran
const isAttendanceModalOpen = ref(false);
const isDetectingLocation = ref(false);
const isSubmittingAttendance = ref(false);
const locationStatusText = ref('Mendeteksi GPS...');

const attendanceForm = ref({
    status: 'hadir',
    notes: '',
    latitude: null,
    longitude: null,
    location_name: '',
    device_model: '',
});

const openAttendanceModal = async () => {
    isAttendanceModalOpen.value = true;
    isDetectingLocation.value = true;
    locationStatusText.value = 'Mendeteksi titik koordinat GPS dan perangkat Anda...';

    // Deteksi perangkat
    attendanceForm.value.device_model = await detectDeviceModel();

    // Deteksi GPS Geolocation
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                attendanceForm.value.latitude = lat;
                attendanceForm.value.longitude = lng;
                locationStatusText.value = `Koordinat: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;

                // Reverse Geocode nama wilayah
                try {
                    const res = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=id`);
                    if (res.ok) {
                        const d = await res.json();
                        const loc = d.locality || '';
                        const city = d.city || d.principalSubdivision || '';
                        if (loc && city) {
                            attendanceForm.value.location_name = `${loc}, ${city}`;
                            locationStatusText.value = `${loc}, ${city}`;
                        } else if (city || loc) {
                            attendanceForm.value.location_name = city || loc;
                            locationStatusText.value = city || loc;
                        }
                    }
                } catch (e) {}
                isDetectingLocation.value = false;
            },
            (err) => {
                locationStatusText.value = 'Akses koordinat GPS belum diizinkan. Lokasi diperkirakan via IP pangkalan.';
                isDetectingLocation.value = false;
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    } else {
        locationStatusText.value = 'Peramban tidak mendukung GPS. Lokasi ditentukan via IP jaringan.';
        isDetectingLocation.value = false;
    }
};

const submitAttendance = () => {
    isSubmittingAttendance.value = true;
    router.post(route('attendances.store'), attendanceForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isAttendanceModalOpen.value = false;
            isSubmittingAttendance.value = false;
        },
        onError: (err) => {
            alert('Gagal mencatat presensi: ' + Object.values(err)[0]);
            isSubmittingAttendance.value = false;
        }
    });
};

const openMap = (lat, lng) => {
    if (lat && lng) {
        window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank');
    }
};

const sendPreciseLocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                axios.post('/update-location', {
                    latitude: lat,
                    longitude: lng
                }).catch(() => {});
            },
            () => {},
            { enableHighAccuracy: true } 
        );
    }
};

onMounted(() => {
    updateClock();
    clockTimer = setInterval(updateClock, 1000);
    sendPreciseLocation();
});

onUnmounted(() => {
    if (clockTimer) clearInterval(clockTimer);
});
</script>

<template>
    <Head title="Dashboard Utama - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 sm:space-y-8 font-sans">
            
            <!-- Hero Welcome Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-[#E2E8F0] shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 font-extrabold text-[10px] uppercase rounded-full tracking-wider">Dashboard Analitik</span>
                        <span class="text-slate-400 text-xs font-semibold">Live System SINDEN</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                        Selamat datang kembali, {{ $page.props.auth.user.pangkat || '' }} {{ $page.props.auth.user.name }}
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed max-w-2xl">
                        Sistem Informasi Detasemen Intelijen. Siap mendukung efisiensi kedinasan, pemantauan presensi, pengawasan lokasi, dan administrasi naskah intelijen hari ini.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <Link :href="route('letters.index')" class="px-5 py-3 bg-blue-600 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider hover:bg-blue-700 transition shadow-md shadow-blue-500/20">
                        + Buat Surat Baru
                    </Link>
                </div>
            </div>

            <!-- KARTU PRESENSI KEHADIRAN PERSONEL (OPSIONAL) -->
            <div v-if="today_attendance" class="bg-emerald-50/70 border border-emerald-200/90 rounded-3xl p-6 shadow-xs flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-xl shrink-0 shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 bg-emerald-600 text-white font-extrabold text-[10px] uppercase rounded-full tracking-wider">
                                Presensi Hari Ini Tercatat: {{ today_attendance.status.replace('_', ' ').toUpperCase() }}
                            </span>
                            <span class="text-xs font-mono font-bold text-emerald-800">
                                Pukul {{ today_attendance.time_in ? today_attendance.time_in.substring(0, 5) : '-' }} WIB
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-1 text-xs text-slate-600 pt-1">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Perangkat:</span>
                                <span class="font-bold text-slate-800">{{ today_attendance.device || 'Perangkat Terdaftar' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Alamat IP:</span>
                                <span class="font-mono font-bold text-slate-800">{{ today_attendance.ip_address || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Titik Lokasi:</span>
                                <span class="font-bold text-slate-800 truncate block max-w-xs" :title="today_attendance.location_name">
                                    {{ today_attendance.location_name || 'GPS Kedinasan' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-end lg:self-center">
                    <button 
                        v-if="today_attendance.latitude && today_attendance.longitude"
                        @click="openMap(today_attendance.latitude, today_attendance.longitude)"
                        class="px-4 py-2.5 bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-extrabold uppercase tracking-wider transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                        title="Buka titik koordinat di Google Maps"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Peta
                    </button>
                    <button 
                        @click="openAttendanceModal"
                        class="px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-extrabold uppercase tracking-wider transition shadow-xs cursor-pointer"
                    >
                        Perbarui Presensi
                    </button>
                </div>
            </div>

            <!-- JIKA BELUM PRESENSI HARI INI -->
            <div v-else class="bg-slate-900 text-white rounded-3xl p-6 sm:p-7 shadow-lg border border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-1.5 max-w-2xl">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 bg-amber-500/20 text-amber-400 font-extrabold text-[10px] uppercase rounded-full border border-amber-500/30 tracking-wider">
                            Presensi Personel (Opsional)
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-400">{{ currentTimeStr }}</span>
                    </div>
                    <h3 class="text-lg font-extrabold text-white tracking-tight">
                        Catat Kehadiran Kedinasan Hari Ini
                    </h3>
                    <p class="text-xs text-slate-400 leading-relaxed font-medium">
                        Fitur presensi bersifat mandiri dan opsional. Sistem secara otomatis mencatat waktu pukul kedatangan, merk dan tipe perangkat HP yang digunakan, alamat IP pangkalan, serta koordinat lokasi penugasan.
                    </p>
                </div>

                <div class="shrink-0 self-end md:self-center">
                    <button 
                        @click="openAttendanceModal"
                        class="px-5 py-3.5 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl font-extrabold text-xs uppercase tracking-wider transition shadow-lg shadow-blue-600/30 flex items-center gap-2 cursor-pointer active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Catat Kehadiran Sekarang
                    </button>
                </div>
            </div>

            <!-- Stats Widgets Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- Presensi Hari Ini Stat -->
                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Presensi Hari Ini</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.today_attendances || 0 }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Personel Tercatat Hadir</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Buku Nomor Surat</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.total_logs }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Total Registrasi Surat Keluar</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Arsip Berkas</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.total_archives }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Dokumen Terarsip</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#E2E8F0] shadow-xs flex items-center justify-between transition hover:shadow-md">
                    <div class="space-y-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Master Personel</span>
                        <h3 class="text-3xl font-bold text-slate-800 py-1">{{ stats.active_personnel }}</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Pengguna Sistem Aktif</p>
                    </div>
                    <div class="w-14 h-14 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-lg shadow-xs">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                </div>

            </div>

            <!-- TABEL REKAPITULASI PRESENSI PERSONEL HARI INI (KHUSUS PIMPINAN & ADMIN) -->
            <div v-if="isCommanderOrAdmin && recent_attendances" class="bg-white rounded-3xl border border-[#E2E8F0] p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Pengawasan Kedinasan</span>
                        <h4 class="text-sm font-extrabold text-slate-900 uppercase">
                            Rekapitulasi Presensi Kehadiran Personel Hari Ini
                        </h4>
                    </div>
                    <span class="text-xs font-extrabold bg-blue-50 text-blue-600 px-3 py-1.5 rounded-xl uppercase tracking-wider self-start sm:self-auto">
                        Total: {{ recent_attendances.length }} Personel
                    </span>
                </div>

                <div v-if="recent_attendances.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                                <th class="p-3">Personel</th>
                                <th class="p-3">Pukul</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Perangkat (Merk & Seri HP)</th>
                                <th class="p-3">Alamat IP</th>
                                <th class="p-3">Lokasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            <tr v-for="att in recent_attendances" :key="att.id" class="hover:bg-slate-50/80 transition">
                                <td class="p-3">
                                    <div class="font-extrabold text-slate-900">{{ att.user?.name || '-' }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ att.user?.pangkat }} NRP. {{ att.user?.nrp || '-' }}</div>
                                </td>
                                <td class="p-3 font-mono font-extrabold text-indigo-700">
                                    {{ att.time_in ? att.time_in.substring(0, 5) : '-' }} WIB
                                </td>
                                <td class="p-3">
                                    <span :class="{
                                        'bg-emerald-100 text-emerald-800': att.status === 'hadir',
                                        'bg-blue-100 text-blue-800': att.status === 'piket',
                                        'bg-amber-100 text-amber-800': att.status === 'dinas_luar',
                                        'bg-purple-100 text-purple-800': att.status === 'izin',
                                    }" class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider">
                                        {{ att.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="font-bold text-slate-800">{{ att.device || '-' }}</span>
                                </td>
                                <td class="p-3 font-mono text-[11px] text-slate-500">
                                    {{ att.ip_address || '-' }}
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs truncate max-w-[180px]" :title="att.location_name">{{ att.location_name || '-' }}</span>
                                        <button 
                                            v-if="att.latitude && att.longitude"
                                            @click="openMap(att.latitude, att.longitude)"
                                            class="p-1 hover:bg-slate-200 text-blue-600 rounded-md transition"
                                            title="Lihat Peta"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-8 text-xs text-slate-400 font-semibold italic">
                    Belum ada personel yang melakukan presensi hari ini.
                </div>
            </div>

            <!-- Content Grid: Activity Logs & Pending Registrations -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Log Aktivitas Terkini -->
                <div v-if="combined_activities" class="bg-white rounded-3xl border border-[#E2E8F0] p-6 lg:col-span-2 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 bg-blue-600 rounded-full animate-pulse"></span> Log Aktivitas & Akses Terkini
                        </h4>
                    </div>
                    
                    <div v-if="combined_activities.length > 0" class="space-y-3">
                        <div v-for="activity in combined_activities" :key="activity.id" 
                             :class="activity.type === 'ADMIN_ACTION' ? 'bg-amber-50/50 border-amber-200/60' : 'bg-slate-50/70 border-slate-100'" class="flex items-center justify-between p-4 rounded-2xl border transition hover:shadow-xs">
                            
                            <div class="min-w-0 flex-1 pr-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-extrabold text-slate-900 uppercase truncate max-w-[200px]">{{ activity.user_name }}</span>
                                    <span v-if="activity.type === 'ADMIN_ACTION'" class="text-[8px] font-extrabold bg-amber-500 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Action</span>
                                    <span v-else class="text-[8px] font-extrabold bg-blue-600 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Access</span>
                                </div>
                                <p :class="activity.type === 'ADMIN_ACTION' ? 'text-amber-800' : 'text-slate-600'" class="text-[11px] font-medium tracking-tight truncate">
                                    {{ activity.type === 'ADMIN_ACTION' ? activity.description : 'Akses Portal: ' + activity.location }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                 <span class="text-[10px] font-mono font-bold text-slate-400 uppercase">{{ activity.login_at }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold italic">Menunggu sinkronisasi aktivitas...</div>
                </div>

                <!-- Antrean Registrasi Pending -->
                <div v-if="pending_users" class="bg-white rounded-3xl border border-[#E2E8F0] p-6 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                        <h4 class="text-xs font-extrabold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <span class="h-2 w-2 bg-rose-500 rounded-full"></span> Antrean Registrasi Pending
                        </h4>
                    </div>

                    <div v-if="pending_users.length > 0" class="space-y-3">
                        <div v-for="pending in pending_users" :key="pending.id" class="flex items-center justify-between p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-extrabold text-slate-900 block uppercase truncate">{{ pending.name }}</span>
                                <span class="text-[10px] text-slate-400 font-bold block mt-0.5">NRP. {{ pending.nrp }}</span>
                            </div>
                            <Link :href="route('users.index')" class="ms-3 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-900 hover:text-white text-slate-700 rounded-xl shadow-xs transition-all text-[10px] font-extrabold uppercase tracking-wider"> Tinjau
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 text-xs text-slate-400 font-semibold uppercase tracking-wider bg-slate-50/40 rounded-2xl border border-dashed border-slate-200">0 Antrean Pending</div>
                </div>

            </div>

        </div>

        <!-- MODAL FORMULIR PRESENSI KEHADIRAN -->
        <Teleport to="body">
            <div v-if="isAttendanceModalOpen" class="fixed inset-0 z-[160] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto animate-in fade-in duration-150">
                <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden my-auto animate-in zoom-in-95 duration-150">
                    
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-black">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase text-blue-600 tracking-wider block">Sistem Presensi Kedinasan</span>
                                <h3 class="text-base font-extrabold text-slate-900">Catat Kehadiran Personel</h3>
                            </div>
                        </div>
                        <button @click="isAttendanceModalOpen = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition cursor-pointer">
                            &times;
                        </button>
                    </div>

                    <form @submit.prevent="submitAttendance" class="p-6 space-y-4 text-xs font-semibold">
                        <!-- Identitas Personel Card -->
                        <div class="p-3.5 bg-slate-50 border border-slate-200/70 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Personel:</span>
                                <span class="font-extrabold text-slate-900 text-sm">{{ currentUser.pangkat }} {{ currentUser.name }}</span>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-500">NRP. {{ currentUser.nrp || '-' }}</span>
                        </div>

                        <!-- Status Kehadiran -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Status Kehadiran</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <label :class="attendanceForm.status === 'hadir' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="hadir" class="hidden" />
                                    Hadir Dinas
                                </label>
                                <label :class="attendanceForm.status === 'piket' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="piket" class="hidden" />
                                    Piket / Jaga
                                </label>
                                <label :class="attendanceForm.status === 'dinas_luar' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="dinas_luar" class="hidden" />
                                    Dinas Luar
                                </label>
                                <label :class="attendanceForm.status === 'izin' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'" class="p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition">
                                    <input type="radio" v-model="attendanceForm.status" value="izin" class="hidden" />
                                    Izin Dinas
                                </label>
                            </div>
                        </div>

                        <!-- Perangkat yang Terdeteksi -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Perangkat yang Digunakan (Merk & Seri HP)</label>
                            <input 
                                type="text" 
                                v-model="attendanceForm.device_model" 
                                required
                                placeholder="Contoh: Samsung Galaxy A54 5G, Xiaomi Redmi Note 12, dll." 
                                class="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            />
                            <span class="text-[10px] text-slate-400 block px-1">*Terdeteksi otomatis dari browser perangkat Anda (dapat disesuaikan bila perlu).</span>
                        </div>

                        <!-- Lokasi GPS & Wilayah -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Titik Lokasi / Koordinat GPS</label>
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 truncate pr-2">
                                    <span v-if="isDetectingLocation" class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                    <span v-else-if="attendanceForm.latitude" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span v-else class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    <span class="font-bold text-slate-800 truncate">{{ locationStatusText }}</span>
                                </div>
                                <span v-if="attendanceForm.latitude" class="text-[10px] font-mono font-bold text-blue-600 shrink-0">GPS Aktif</span>
                            </div>
                        </div>

                        <!-- Catatan Opsional -->
                        <div class="space-y-1">
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500 px-1">Catatan Tambahan (Opsional)</label>
                            <textarea 
                                v-model="attendanceForm.notes" 
                                rows="2" 
                                placeholder="Tuliskan keterangan penugasan atau catatan operasional jika ada..." 
                                class="w-full text-xs font-medium p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isAttendanceModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-extrabold text-xs uppercase tracking-wider transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="isSubmittingAttendance"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-extrabold text-xs uppercase tracking-wider shadow-md shadow-blue-600/20 transition cursor-pointer disabled:opacity-50"
                            >
                                {{ isSubmittingAttendance ? 'Merekam Presensi...' : 'Simpan Presensi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
