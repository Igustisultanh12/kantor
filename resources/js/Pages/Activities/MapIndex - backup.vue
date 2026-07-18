<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { LMap, LTileLayer, LMarker, LPopup } from "@vue-leaflet/vue-leaflet";
import "leaflet/dist/leaflet.css";
// AMUNISI PENCARIAN ALAMAT MANDIRI
import { OpenStreetMapProvider } from 'leaflet-geosearch';
import 'leaflet-geosearch/dist/geosearch.css';
import L from 'leaflet';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    activities: Array,
    stats_by_province: Array,
    stats_by_category: Array
});

// PENGATURAN PETA UTAMA
const zoom = ref(7);
const center = ref([-7.5360, 112.2384]);
const pickerMapRef = ref(null);

// State Kontrol Modal
const showModal = ref(false);
const showDetailModal = ref(false);
const isEditing = ref(false); 
const selectedActivity = ref(null);

// STATE TAMBAH BIDANG MANUAL
const isAddingNewCategory = ref(false);

// State untuk Pencarian Mandiri (Di luar Peta)
const searchInput = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

// --- FITUR BARU: STATE PENAMPUNG DAERAH HASIL DETEKSI KOORDINAT ---
const activityRegions = ref({});

// Form Input Plotting Baru / Edit
const form = useForm({
    id: null,
    title: '',
    category: 'Keamanan',
    province: 'Jawa Timur',
    location_name: '',
    latitude: '',
    longitude: '',
    activity_date: new Date().toISOString().substr(0, 10),
    description: ''
});

// --- LOGIKA DAFTAR BIDANG DINAMIS (AGAR BIDANG BARU TERDAFTAR OTOMATIS) ---
const availableCategories = computed(() => {
    // Daftar dasar wajib
    const defaults = ['Politik', 'Ekonomi', 'Sosial Budaya', 'Keamanan'];
    // Ambil semua bidang yang sudah pernah diinput ke database dari props
    const existing = props.stats_by_category.map(s => s.category);
    // Gabungkan dan bersihkan duplikat menggunakan Set
    const combined = [...new Set([...defaults, ...existing])];
    return combined;
});

const handleCategoryChange = (e) => {
    if (e.target.value === 'TAMBAH_BIDANG_BARU') {
        isAddingNewCategory.value = true;
        form.category = ''; // Kosongkan agar Bapak bisa mengetik manual
    } else {
        isAddingNewCategory.value = false;
    }
};

const cancelNewCategory = () => {
    isAddingNewCategory.value = false;
    form.category = 'Keamanan'; // Kembalikan ke default jika batal
};
// --- END LOGIKA DAFTAR BIDANG ---

// --- MODIFIKASI TAKTIS: PIN PETA BERWARNA & BERNOMOR DI TENGAH ---
const getNumberedIcon = (category, displayNumber) => {
    let color = '#3b82f6'; // Biru default
    if (category === 'Politik') color = '#ef4444'; // Merah
    else if (category === 'Ekonomi') color = '#22c55e'; // Hijau
    else if (category === 'Sosial Budaya') color = '#3b82f6'; // Biru
    else if (category === 'Keamanan') color = '#f97316'; // Oranye

    // Membuat Pin dinamis menggunakan L.divIcon agar nomor muncul di marker 
    return L.divIcon({
        className: 'custom-numbered-marker',
        html: `
            <div style="position: relative; display: flex; flex-direction: column; align-items: center;">
                <div style="
                    background-color: ${color};
                    width: 26px;
                    height: 26px;
                    border-radius: 50% 50% 50% 0;
                    transform: rotate(-45deg);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                ">
                    <span style="
                        transform: rotate(45deg);
                        color: white;
                        font-weight: 900;
                        font-size: 10px;
                        font-family: sans-serif;
                    ">${displayNumber}</span>
                </div>
                <div style="width: 2px; height: 6px; background-color: ${color};"></div>
            </div>
        `,
        iconSize: [26, 32],
        iconAnchor: [13, 32],
        popupAnchor: [0, -32]
    });
};

// --- OPERASI INTELIJEN: DETEKSI KAB/KOTA DARI KOORDINAT ---
const detectRegionsFromCoordinates = async () => {
    for (const act of props.activities) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${act.latitude}&lon=${act.longitude}&zoom=10`
            );
            const data = await response.json();
            // Ambil label Kota atau Kabupaten
            const city = data.address.city || data.address.town || data.address.municipality || data.address.county || "Lainnya";
            activityRegions.value[act.id] = city.replace("Kabupaten ", "Kab. ").replace("Kota ", "");
        } catch (error) {
            activityRegions.value[act.id] = "Radar Gangguan";
        }
    }
};

onMounted(() => {
    detectRegionsFromCoordinates();
});

// LOGIKA PENCARIAN ALAMAT MANDIRI
const provider = new OpenStreetMapProvider({
    params: { 'accept-language': 'id', countrycodes: 'id' }
});

let searchTimeout = null;
const handleSearch = async () => {
    clearTimeout(searchTimeout);

    if (searchInput.value.length < 3) {
        searchResults.value = [];
        return;
    }
    searchTimeout = setTimeout(async () => {
        isSearching.value = true;
        try {
            const results = await provider.search({ query: searchInput.value });
            searchResults.value = results.slice(0, 10); 
        } catch (error) {
            console.error("Gagal mencari alamat:", error);
        } finally {
            isSearching.value = false;
        }
    }, 600);
};

const selectLocation = (result) => {
    form.latitude = result.y.toFixed(6);
    form.longitude = result.x.toFixed(6);
    form.location_name = result.label;
    searchInput.value = result.label;
    searchResults.value = [];
    
    if (pickerMapRef.value && pickerMapRef.value.leafletObject) {
        pickerMapRef.value.leafletObject.flyTo([result.y, result.x], 15);
    }
};

watch(pickerMapRef, async (newVal) => {
    if (newVal && newVal.leafletObject) {
        await nextTick();
        newVal.leafletObject.invalidateSize();
    }
});

const handleMapPickerClick = (e) => {
    form.latitude = e.latlng.lat.toFixed(6);
    form.longitude = e.latlng.lng.toFixed(6);
};

const viewDetail = (act) => {
    selectedActivity.value = act;
    showDetailModal.value = true;
};

const openEditMode = () => {
    isEditing = true;
    showDetailModal.value = false;
    
    form.id = selectedActivity.value.id;
    form.title = selectedActivity.value.title;
    form.category = selectedActivity.value.category;
    form.province = selectedActivity.value.province;
    form.location_name = selectedActivity.value.location_name;
    form.latitude = selectedActivity.value.latitude;
    form.longitude = selectedActivity.value.longitude;
    form.activity_date = selectedActivity.value.activity_date;
    form.description = selectedActivity.value.description;
    
    searchInput.value = selectedActivity.value.location_name;
    showModal.value = true;
};

const deleteActivity = (id) => {
    if (confirm('Lapor! Apakah Bapak Letnan yakin ingin memusnahkan data plotting rencana kegiatan ini?')) {
        router.delete(route('activities.destroy', id), {
            onSuccess: () => {
                showDetailModal.value = false;
                alert('Lapor! Data plotting telah berhasil dimusnahkan.');
            }
        });
    }
};

// --- LOGIKA TRANSFORMASI DATA: SINKRON KAB/KOTA DARI KOORDINAT ---
const chartData = computed(() => {
    const counts = {};
    props.activities.forEach(act => {
        // Ambil data daerah berdasarkan koordinat hasil deteksi
        const region = activityRegions.value[act.id] || "Memindai...";
        counts[region] = (counts[region] || 0) + 1;
    });

    return {
        labels: Object.keys(counts),
        datasets: [{
            label: 'Jumlah Giat per Daerah',
            backgroundColor: '#4f46e5',
            borderRadius: 8,
            data: Object.values(counts)
        }]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (context) => ` Total: ${context.raw} Kegiatan`
            }
        }
    },
    scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10, weight: 'bold' } } },
        x: { ticks: { font: { size: 9, weight: 'black' }, autoSkip: false, maxRotation: 0, minRotation: 0 } }
    }
};

const submit = () => {
    if (!form.latitude || !form.longitude) {
        alert('Lapor! Lokasi belum ditentukan. Silakan gunakan kolom pencarian atau klik pada peta.');
        return;
    }

    const options = {
        onSuccess: () => {
            showModal.value = false;
            isEditing.value = false;
            isAddingNewCategory.value = false; // Reset status input manual
            form.reset();
            searchInput.value = '';
            alert('Lapor! Data plotting berhasil diamankan.');
            detectRegionsFromCoordinates(); // Jalankan ulang deteksi untuk data baru
        },
        onError: (errors) => {
            console.error("Gagal menyimpan:", errors);
            alert('Lapor! Gagal menyimpan data. Cek log server.');
        },
        onFinish: () => {
            form.processing = false;
        }
    };

    if (isEditing.value) {
        form.put(route('activities.update', form.id), options);
    } else {
        form.post(route('activities.store'), options);
    }
};

const closeMainModal = () => {
    showModal.value = false;
    isEditing.value = false;
    isAddingNewCategory.value = false;
    form.reset();
    searchInput.value = '';
};

// Fungsi getIcon lama dipertahankan untuk kompatibilitas internal
const getIcon = (category) => {
    let color = 'blue'; 
    if (category === 'Politik') color = 'red';
    else if (category === 'Ekonomi') color = 'green';
    else if (category === 'Sosial Budaya') color = 'blue';
    else if (category === 'Keamanan') color = 'orange';

    return new L.Icon({
        iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
};
</script>

<template>
    <Head title="Peta Rencana Kegiatan" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center text-left">
                <div class="flex items-center gap-4 text-left">
                    <h2 class="font-bold text-2xl text-gray-800 leading-tight text-left">Radar Rencana Kegiatan Masyarakat</h2>
                    <button @click="showModal = true" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-black uppercase text-[10px] tracking-widest shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all active:scale-95 text-left text-left">
                        + Plot Kegiatan Baru
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 px-4 md:px-6 space-y-8 text-left text-left text-left">
            <div class="bg-white p-4 rounded-[2.5rem] shadow-sm border border-gray-100 h-[600px] relative z-10 overflow-hidden text-left">
                <div class="absolute top-8 left-8 z-[1000] bg-white/90 backdrop-blur p-5 rounded-2xl shadow-xl border border-gray-100 pointer-events-none text-left">
                    <p class="text-[9px] font-black text-gray-400 uppercase mb-3 tracking-widest text-left">Legend Bidang:</p>
                    <div class="flex flex-col gap-2.5 text-left">
                        <div class="flex items-center gap-3 text-left text-left"><span class="w-3 h-3 rounded-full bg-red-500 shadow-sm text-left text-left"></span> <span class="text-[10px] font-black uppercase text-gray-700 text-left text-left">Politik</span></div>
                        <div class="flex items-center gap-3 text-left text-left"><span class="w-3 h-3 rounded-full bg-green-500 shadow-sm text-left text-left"></span> <span class="text-[10px] font-black uppercase text-gray-700 text-left text-left">Ekonomi</span></div>
                        <div class="flex items-center gap-3 text-left text-left"><span class="w-3 h-3 rounded-full bg-blue-500 shadow-sm text-left text-left"></span> <span class="text-[10px] font-black uppercase text-gray-700 text-left text-left">Sosial Budaya</span></div>
                        <div class="flex items-center gap-3 text-left text-left"><span class="w-3 h-3 rounded-full bg-orange-500 shadow-sm text-left text-left"></span> <span class="text-[10px] font-black uppercase text-gray-700 text-left text-left">Keamanan</span></div>
                    </div>
                </div>

                <l-map v-model:zoom="zoom" :center="center" :use-global-leaflet="false">
                    <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" layer-type="base" name="OpenStreetMap"></l-tile-layer>
                    
                    <l-marker v-for="(act, index) in activities" 
                        :key="act.id" 
                        :lat-lng="[act.latitude, act.longitude]" 
                        :icon="getNumberedIcon(act.category, activities.length - index)"
                    >
                        <l-popup>
                            <div class="p-3 min-w-[220px] text-left text-left">
                                <div class="flex justify-between items-center mb-2 text-left text-left text-left">
                                    <span class="text-[8px] font-black bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full uppercase text-left text-left">
                                        #{{ activities.length - index }} | {{ act.category }}
                                    </span>
                                    <span class="text-[8px] font-bold text-gray-400 uppercase text-left text-left text-left">{{ act.activity_date }}</span>
                                </div>
                                <h4 @click="viewDetail(act)" class="font-black text-xs text-indigo-900 mb-1 uppercase leading-tight cursor-pointer hover:text-indigo-600 underline decoration-indigo-200 decoration-2 underline-offset-4 transition-colors text-left text-left text-left">
                                    {{ activities.length - index }}. {{ act.title }}
                                </h4>
                                <p class="text-[10px] text-gray-600 mb-3 italic leading-relaxed text-left text-left text-left">"{{ act.description }}"</p>
                                <div class="flex items-center gap-1.5 pt-2 border-t border-gray-50 text-left text-left text-left">
                                    <span class="text-[10px] text-left text-left text-left">📍</span>
                                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-tighter text-left text-left text-left">{{ act.location_name }}</span>
                                </div>
                                <p class="text-[7px] text-indigo-400 font-black mt-2 uppercase tracking-widest italic text-left text-left text-left">* Klik judul untuk detail & aksi</p>
                            </div>
                        </l-popup>
                    </l-marker>
                </l-map>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 text-left text-left text-left">
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 text-left text-left text-left text-left">
                    <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest mb-6 text-left text-left text-left text-left">Distribusi Giat per Daerah (Analisa Koordinat)</h3>
                    <div class="relative h-[300px] w-full text-left text-left text-left text-left">
                        <Bar :data="chartData" :options="chartOptions" />
                    </div>
                </div>
                
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 text-left text-left text-left text-left">
                    <h3 class="font-black text-gray-800 uppercase text-xs tracking-widest mb-6 text-left text-left text-left text-left">Persentase Bidang IPOLEKSOSBUDHANKAM</h3>
                    <div class="space-y-6 text-left text-left text-left text-left">
                        <div v-for="stat in stats_by_category" :key="stat.category" class="text-left text-left text-left text-left text-left">
                            <div class="flex justify-between mb-2 items-center text-left text-left text-left text-left">
                                <span class="text-[10px] font-black uppercase text-gray-500 tracking-wider text-left text-left text-left">{{ stat.category }}</span>
                                <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-3 py-1 rounded-lg text-left text-left text-left text-left">{{ stat.total }} Giat</span>
                            </div>
                            <div class="w-full bg-gray-50 rounded-full h-3 border border-gray-100 p-0.5 text-left text-left text-left text-left">
                                <div class="bg-indigo-600 h-full rounded-full shadow-sm transition-all duration-1000" 
                                     :style="{ width: (stat.total / (activities.length || 1) * 100) + '%' }"></div>
                            </div>
                        </div>
                        <div v-if="stats_by_category.length === 0" class="py-10 text-center text-left text-left text-left text-left">
                            <p class="text-[11px] font-bold text-gray-300 italic uppercase text-left text-left text-left text-left">Belum ada data bidang terinput.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-[10000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm text-left font-sans text-left text-left text-left text-left">
            <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-3xl w-full max-h-[95vh] overflow-hidden flex flex-col animate-in fade-in zoom-in duration-200 text-left text-left text-left text-left text-left">
                <div class="p-8 border-b bg-gray-50 flex justify-between items-center text-left text-left text-left text-left text-left text-left text-left">
                    <h3 class="font-black text-gray-800 uppercase text-sm tracking-widest text-left text-left text-left text-left text-left text-left">
                        {{ isEditing ? 'Update Data Plotting' : 'Plot Rencana Giat Teritorial' }}
                    </h3>
                    <button @click="closeMainModal" class="text-gray-400 hover:text-red-500 text-2xl font-black text-left text-left text-left text-left">&times;</button>
                </div>
                
                <form @submit.prevent="submit" class="p-8 space-y-6 overflow-y-auto custom-scrollbar text-left text-left text-left text-left text-left text-left text-left text-left">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left text-left text-left text-left text-left text-left">
                        <div class="text-left text-left text-left text-left text-left text-left">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2 block mb-1 text-left text-left text-left">Judul Kegiatan</label>
                            <input v-model="form.title" type="text" placeholder="Contoh: UNRAS NELAYAN" class="w-full rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold uppercase focus:ring-indigo-500 text-left text-left text-left text-left text-left text-left">
                        </div>

                        <div class="text-left text-left text-left text-left text-left text-left text-left text-left">
                            <label class="text-[9px] font-black text-gray-400 uppercase ml-2 block mb-1 text-left text-left text-left text-left">Bidang Sektoral</label>
                            <div v-if="!isAddingNewCategory" class="text-left text-left text-left text-left text-left text-left text-left">
                                <select v-model="form.category" @change="handleCategoryChange" class="w-full rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold focus:ring-indigo-500 text-left text-left text-left text-left text-left text-left">
                                    <option v-for="cat in availableCategories" :key="cat" :value="cat">{{ cat }}</option>
                                    <option value="TAMBAH_BIDANG_BARU" class="text-indigo-600 font-black italic text-left text-left text-left text-left text-left">+ TAMBAH BIDANG BARU</option>
                                </select>
                            </div>
                            <div v-else class="flex gap-2 animate-in slide-in-from-right duration-300 text-left text-left text-left text-left text-left text-left text-left">
                                <input v-model="form.category" type="text" placeholder="Ketik Bidang Baru..." class="flex-1 rounded-2xl border-2 border-indigo-200 bg-white h-12 text-xs font-black uppercase focus:ring-indigo-500 text-left text-left text-left text-left text-left" autofocus>
                                <button @click="cancelNewCategory" type="button" class="px-4 bg-red-50 text-red-600 rounded-2xl font-black text-[10px] hover:bg-red-600 hover:text-white transition-all text-left text-left text-left text-left text-left text-left">BATAL</button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                        <label class="text-[10px] font-black text-indigo-600 uppercase ml-2 flex items-center gap-2 text-left text-left text-left text-left text-left text-left text-left">
                            <span class="animate-pulse text-left text-left text-left text-left text-left text-left">🔍</span> Cari Alamat/Desa/Kecamatan:
                        </label>
                        <div class="relative text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                            <input v-model="searchInput" @input="handleSearch" type="text" placeholder="Ketik nama lokasi (skala desa/kec/provinsi)..." class="w-full rounded-2xl border-2 border-indigo-50 bg-white h-12 text-xs font-bold px-4 focus:border-indigo-500 shadow-sm text-left text-left text-left text-left text-left text-left text-left">
                            <div v-if="searchResults.length > 0" class="absolute left-0 right-0 z-[11000] bg-white rounded-2xl shadow-2xl border border-gray-100 mt-1 overflow-hidden text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                                <div v-for="res in searchResults" :key="res.x" @click="selectLocation(res)" class="px-4 py-3 hover:bg-indigo-50 cursor-pointer text-[10px] font-bold border-b border-gray-50 last:border-0 text-left text-left text-left text-left text-left text-left text-left text-left text-left">{{ res.label }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="h-64 w-full rounded-3xl border-2 border-indigo-100 overflow-hidden relative z-[1000] text-left text-left text-left text-left text-left text-left text-left text-left">
                        <l-map ref="pickerMapRef" :zoom="zoom" :center="isEditing ? [form.latitude, form.longitude] : center" @click="handleMapPickerClick" :use-global-leaflet="false">
                            <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"></l-tile-layer>
                            <l-marker v-if="form.latitude" :lat-lng="[form.latitude, form.longitude]"></l-marker>
                        </l-map>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                        <select v-model="form.province" class="w-full rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold focus:ring-indigo-500 text-left text-left text-left text-left text-left text-left text-left text-left text-left"><option>Jawa Timur</option><option>Bali</option><option>Jawa Tengah</option></select>
                        <input v-model="form.activity_date" type="date" class="w-full rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold focus:ring-indigo-500 text-left text-left text-left text-left text-left text-left text-left">
                    </div>

                    <input v-model="form.location_name" type="text" placeholder="Detail Lokasi / Alamat Lengkap" class="w-full rounded-2xl border-gray-100 bg-gray-50 h-12 text-xs font-bold uppercase focus:ring-indigo-500 text-left text-left text-left text-left text-left text-left text-left">
                    <textarea v-model="form.description" placeholder="Uraian Rencana Giat..." class="w-full rounded-2xl border-gray-100 bg-gray-50 h-24 text-xs font-medium focus:ring-indigo-500 text-left text-left text-left text-left text-left text-left text-left"></textarea>

                    <button type="submit" :disabled="form.processing" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg active:scale-95 disabled:opacity-50 text-left text-left text-left text-left text-left text-left text-left">
                        {{ form.processing ? 'Tunggu Sejenak...' : 'AMANKAN RENCANA PLOTTING' }}
                    </button>
                </form>
            </div>
        </div>

        <div v-if="showDetailModal" class="fixed inset-0 z-[11000] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md text-left text-left text-left text-left text-left text-left text-left text-left">
            <div class="bg-white rounded-[3rem] shadow-2xl max-w-lg w-full p-10 text-left relative overflow-hidden animate-in zoom-in duration-300 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                <div class="absolute top-0 left-0 w-full h-2 bg-indigo-600 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left"></div>
                <button @click="showDetailModal = false" class="absolute top-6 right-8 text-gray-300 hover:text-red-500 text-3xl font-black text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">&times;</button>
                
                <span class="bg-indigo-100 text-indigo-700 px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">{{ selectedActivity?.category }}</span>
                <h3 class="text-2xl font-black text-indigo-950 uppercase mt-4 leading-tight border-b-2 border-indigo-50 pb-4 text-left text-left text-left text-left text-left text-left text-left text-left">{{ selectedActivity?.title }}</h3>
                <div class="mt-8 space-y-6 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                    <div class="flex gap-4 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                        <div class="h-10 w-10 bg-indigo-50 rounded-xl flex items-center justify-center text-xl shrink-0 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">📅</div>
                        <div class="text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">Waktu Pelaksanaan</p>
                            <p class="text-sm font-bold text-gray-700 text-left text-left text-left text-left text-left text-left text-left text-left">{{ selectedActivity?.activity_date }}</p>
                        </div>
                    </div>
                    <div class="flex gap-4 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                        <div class="h-10 w-10 bg-indigo-50 rounded-xl flex items-center justify-center text-xl shrink-0 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">📍</div>
                        <div class="text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-left text-left text-left text-left text-left text-left text-left text-left text-left">Lokasi Teritorial</p>
                            <p class="text-sm font-bold text-gray-700 uppercase leading-relaxed text-left text-left text-left text-left text-left text-left text-left text-left">{{ selectedActivity?.location_name }}</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-6 rounded-[2rem] border border-gray-100 text-left text-left text-left text-left text-left text-left text-left text-left">
                        <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-3 flex items-center gap-2 text-left text-left text-left text-left text-left text-left text-left text-left text-left"><span>📝</span> Uraian Detail Rencana:</p>
                        <p class="text-sm text-gray-600 italic leading-relaxed whitespace-pre-line text-left text-left text-left text-left text-left text-left text-left text-left text-left">"{{ selectedActivity?.description || 'Tidak ada uraian detail terlampir.' }}"</p>
                    </div>
                </div>
                <div class="flex gap-3 mt-10 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">
                    <button @click="openEditMode" class="flex-1 py-4 bg-indigo-50 text-indigo-600 rounded-2xl font-black uppercase text-[11px] tracking-widest hover:bg-indigo-600 hover:text-white transition-all active:scale-95 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">⚙️ Edit Plot</button>
                    <button @click="deleteActivity(selectedActivity.id)" class="px-8 py-4 bg-red-50 text-red-600 rounded-2xl font-black uppercase text-[11px] tracking-widest hover:bg-red-600 hover:text-white transition-all border border-red-100 active:scale-95 shadow-lg shadow-red-100 text-left text-left text-left text-left text-left text-left text-left text-left text-left text-left">🗑️ Hapus</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.leaflet-container { height: 100%; width: 100%; z-index: 1; border-radius: 1.5rem; }
.custom-scrollbar::-webkit-scrollbar { width: 0px; }
.cursor-pointer { cursor: pointer !important; }
.leaflet-control-geosearch form { display: none !important; }
/* Hapus border default divIcon */
.custom-numbered-marker { background: transparent !important; border: none !important; }
</style>