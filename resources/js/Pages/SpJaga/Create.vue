<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    personels: Array,
    defaultMonth: Number,
    defaultYear: Number,
});

const monthNames = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Data Form SP Jaga
const form = useForm({
    bulan: props.defaultMonth || 7,
    tahun: props.defaultYear || 2026,
    nomor_urut: 29,
    tmt_mulai: `${props.defaultYear || 2026}-${String(props.defaultMonth || 7).padStart(2, '0')}-01`,
    tmt_selesai: `${props.defaultYear || 2026}-${String(props.defaultMonth || 7).padStart(2, '0')}-31`,
    tanggal_surat: `${props.defaultYear || 2026}-${String(props.defaultMonth === 1 ? 12 : props.defaultMonth - 1).padStart(2, '0')}-30`,
    ttd_type: 'tte', // 'tte' atau 'manual'
    
    // Perwira Jaga (Default 7 Baris Sesuai Format Resmi PDF)
    perwiras: [
        { user_id: '', nama: 'Bambang', pangkat_korps: 'Peltu Saa', nrp: '82068', tgl_1: '-', tgl_2: '06', tgl_3: '12', tgl_4: '18', tgl_5: '24', tgl_6: '30', tgl_7: '-' },
        { user_id: '', nama: 'Rizal Nurdin W.', pangkat_korps: 'Kapten Laut (P)', nrp: '20873/P', tgl_1: '-', tgl_2: '07', tgl_3: '13', tgl_4: '19', tgl_5: '25', tgl_6: '31', tgl_7: '-' },
        { user_id: '', nama: 'Alex Hamid R.T.', pangkat_korps: 'Kapten Laut (P)', nrp: '22029/P', tgl_1: '01', tgl_2: '-', tgl_3: '14', tgl_4: '20', tgl_5: '26', tgl_6: '-', tgl_7: '-' },
        { user_id: '', nama: 'Indra Gunawan', pangkat_korps: 'Kapten Laut (P)', nrp: '19739/P', tgl_1: '02', tgl_2: '08', tgl_3: '-', tgl_4: '21', tgl_5: '27', tgl_6: '-', tgl_7: '-' },
        { user_id: '', nama: 'Erwan Junaidi', pangkat_korps: 'Peltu Ttg', nrp: '84025', tgl_1: '03', tgl_2: '09', tgl_3: '15', tgl_4: '-', tgl_5: '29', tgl_6: '-', tgl_7: '-' },
        { user_id: '', nama: "Agus Sub'chan", pangkat_korps: 'Lettu Laut (T)', nrp: '25724/P', tgl_1: '04', tgl_2: '10', tgl_3: '16', tgl_4: '22', tgl_5: '-', tgl_6: '-', tgl_7: '-' },
        { user_id: '', nama: 'Agus Musonif', pangkat_korps: 'Lettu Laut (P)', nrp: '26327/P', tgl_1: '05', tgl_2: '11', tgl_3: '17', tgl_4: '23', tgl_5: '29', tgl_6: '-', tgl_7: '-' },
    ],

    // Anggota Jaga Divisi (Default 5 Divisi Sesuai Format Resmi PDF)
    anggotas: [
        {
            divisi_no: 1,
            tanggal_list_text: '04, 09, 14, 19, 24, 29 JULI 2026',
            anggota_items: [
                { user_id: '', nama: 'HASAN BASRI', pangkat_korps: 'PELDA MAR', nrp_nip: '106737', role_jaga: 'BAGA' },
                { user_id: '', nama: 'ADITYA H.', pangkat_korps: 'SERMA KOM', nrp_nip: '115980', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'ACHMAD H.', pangkat_korps: 'KOPKA LIS', nrp_nip: '89853', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'SUNARKO, S.H', pangkat_korps: 'PENATA III/C', nrp_nip: '197308261994011001', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 2,
            tanggal_list_text: '05, 10, 15, 20, 25, 30 JULI 2026',
            anggota_items: [
                { user_id: '', nama: 'ANDIS Y.', pangkat_korps: 'SERKA EKO', nrp_nip: '114153', role_jaga: 'BAGA' },
                { user_id: '', nama: 'PUJIANTO', pangkat_korps: 'SERKA TKU', nrp_nip: '117387', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'RINO Y', pangkat_korps: 'KOPTU TLG', nrp_nip: '113623', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'HADIS S.', pangkat_korps: 'PENDA TK I III/B', nrp_nip: '197101311994031002', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 3,
            tanggal_list_text: '01, 06, 11, 16, 21, 26, 31 JULI 2026',
            anggota_items: [
                { user_id: '', nama: 'HARTANTO', pangkat_korps: 'PELTU NAV', nrp_nip: '98486', role_jaga: 'BAGA' },
                { user_id: '', nama: 'DWI PURNOMO', pangkat_korps: 'SERTU TTU', nrp_nip: '105224', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'RUMADI', pangkat_korps: 'SERTU TTU', nrp_nip: '88386', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'EKO YANUAR', pangkat_korps: 'PENG TK I II/D', nrp_nip: '197201141998031003', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 4,
            tanggal_list_text: '02, 07, 12, 17, 22, 27 JULI 2026',
            anggota_items: [
                { user_id: '', nama: 'TRI WINDARTO', pangkat_korps: 'SERMA PDK', nrp_nip: '114222', role_jaga: 'BAGA' },
                { user_id: '', nama: 'KARIYADI', pangkat_korps: 'SERKA JAS', nrp_nip: '85822', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'IFAN SUSANTO', pangkat_korps: 'KOPKA MES', nrp_nip: '99018', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'SAGUS N', pangkat_korps: 'PENATA TK I III/D', nrp_nip: '19701221994021001', role_jaga: 'ANGGOTA' },
            ]
        },
        {
            divisi_no: 5,
            tanggal_list_text: '03, 08, 13, 18, 23, 28 JULI 2026',
            anggota_items: [
                { user_id: '', nama: 'RIBUT JOHAN P', pangkat_korps: 'SERMA KEU', nrp_nip: '112631', role_jaga: 'BAGA' },
                { user_id: '', nama: 'HENDRA S', pangkat_korps: 'SERMA KOM', nrp_nip: '114931', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'HERI WARSITO', pangkat_korps: 'PENDA TK I III/B', nrp_nip: '197502022002122006', role_jaga: 'ANGGOTA' },
                { user_id: '', nama: 'GUNAWAN', pangkat_korps: 'PENDA III/A', nrp_nip: '197810062005011005', role_jaga: 'ANGGOTA' },
            ]
        },
    ]
});

// Auto-fill Perwira saat memilih dari database User
const onPerwiraSelect = (idx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.perwiras[idx].nama = p.name;
        form.perwiras[idx].pangkat_korps = p.pangkat || '';
        form.perwiras[idx].nrp = p.nrp || '';
    }
};

// Auto-fill Anggota saat memilih dari database User
const onAnggotaSelect = (divIdx, itemIdx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.anggotas[divIdx].anggota_items[itemIdx].nama = p.name;
        form.anggotas[divIdx].anggota_items[itemIdx].pangkat_korps = p.pangkat || '';
        form.anggotas[divIdx].anggota_items[itemIdx].nrp_nip = p.nrp || '';
    }
};

// Tambah / Hapus Perwira
const addPerwira = () => {
    form.perwiras.push({
        user_id: '',
        nama: '',
        pangkat_korps: '',
        nrp: '',
        tgl_1: '-',
        tgl_2: '-',
        tgl_3: '-',
        tgl_4: '-',
        tgl_5: '-',
        tgl_6: '-',
        tgl_7: '-',
    });
};

const removePerwira = (idx) => {
    if (form.perwiras.length > 1) {
        form.perwiras.splice(idx, 1);
    }
};

// Tambah / Hapus Anggota di Divisi
const addAnggotaToDivisi = (divIdx) => {
    form.anggotas[divIdx].anggota_items.push({
        user_id: '',
        nama: '',
        pangkat_korps: '',
        nrp_nip: '',
        role_jaga: 'ANGGOTA'
    });
};

const removeAnggotaFromDivisi = (divIdx, itemIdx) => {
    if (form.anggotas[divIdx].anggota_items.length > 1) {
        form.anggotas[divIdx].anggota_items.splice(itemIdx, 1);
    }
};

const submit = () => {
    form.post(route('sp-jaga.store'), {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Surat Perintah Jaga Siaga Sintel berhasil disimpan.', 'success');
        }
    });
};
</script>

<template>
    <Head title="Buat SP Jaga Baru - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 max-w-6xl mx-auto font-sans pb-12">
            
            <!-- Breadcrumb & Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <Link :href="route('sp-jaga.index')" class="hover:text-blue-600">SP Jaga</Link>
                        <span>/</span>
                        <span class="text-slate-800 font-bold">Buat SP Jaga Baru</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Formulir Penerbitan SP Jaga Siaga</h1>
                </div>

                <Link 
                    :href="route('sp-jaga.index')"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                >
                    â Kembali
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                
                <!-- BAGIAN 1: INFORMASI SURAT PERINTAH (HALAMAN 1) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">1. Header & Periode Surat Perintah</h2>
                        <p class="text-xs text-slate-500">Informasi nomor surat perintah, masa berlaku TMT dinas, dan pilihan tanda tangan.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Periode Bulan -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Bulan Dinas</label>
                            <select v-model="form.bulan" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500">
                                <option v-for="(m, i) in monthNames.slice(1)" :key="i" :value="i + 1">{{ m }}</option>
                            </select>
                        </div>

                        <!-- Tahun -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tahun</label>
                            <input type="number" v-model="form.tahun" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- Nomor Urut Sprin -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Nomor Urut Sprin</label>
                            <input type="number" v-model="form.nomor_urut" placeholder="29" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- TMT Mulai -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Mulai</label>
                            <input type="date" v-model="form.tmt_mulai" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- TMT Selesai -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Selesai</label>
                            <input type="date" v-model="form.tmt_selesai" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <!-- Tanggal Surat Terbit -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tanggal Dikeluarkan di Surabaya</label>
                            <input type="date" v-model="form.tanggal_surat" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <!-- Pilihan Jenis Tanda Tangan (TTE vs TTD Manual) -->
                    <div class="p-5 rounded-2xl bg-blue-50/60 border border-blue-200 space-y-3">
                        <label class="text-xs font-black uppercase tracking-wider text-blue-900 block">
                            Metode Pengesahan & Tanda Tangan:
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label 
                                class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition"
                                :class="form.ttd_type === 'tte' ? 'bg-white border-blue-600 ring-2 ring-blue-500/20 shadow-xs' : 'bg-white/50 border-slate-200'"
                            >
                                <input type="radio" v-model="form.ttd_type" value="tte" class="mt-1" />
                                <div>
                                    <span class="font-bold text-xs text-slate-900 block">TTE Digital (QR Code Resmi)</span>
                                    <span class="text-[11px] text-slate-500 leading-snug block">
                                        Draf akan dikirim ke antrean Pasops untuk diperiksa & ditandatangani secara elektronik. QR Code verifikasi akan disematkan pada PDF.
                                    </span>
                                </div>
                            </label>

                            <label 
                                class="p-4 rounded-2xl border flex items-start gap-3 cursor-pointer transition"
                                :class="form.ttd_type === 'manual' ? 'bg-white border-amber-600 ring-2 ring-amber-500/20 shadow-xs' : 'bg-white/50 border-slate-200'"
                            >
                                <input type="radio" v-model="form.ttd_type" value="manual" class="mt-1" />
                                <div>
                                    <span class="font-bold text-xs text-slate-900 block">TTD Manual (Tanda Tangan Basah)</span>
                                    <span class="text-[11px] text-slate-500 leading-snug block">
                                        Cetak SP Jaga dengan kolom tanda tangan fisik kosong. Setelah ditandatangani basah, hasil scan PDF diunggah ke sistem.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- BAGIAN 2: DAFTAR PERWIRA JAGA SINTEL (LAMPIRAN 1) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900">2. Lampiran 1: Daftar Nama Perwira Jaga</h2>
                            <p class="text-xs text-slate-500">Daftar perwira siaga dan jadwal rotasi tanggal dinas selama bulan berjalan.</p>
                        </div>
                        <button 
                            @click="addPerwira" 
                            type="button"
                            class="px-3.5 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 font-bold text-xs rounded-xl transition cursor-pointer"
                        >
                            + Tambah Baris Perwira
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-500">
                                    <th class="p-2.5 text-center w-10">No</th>
                                    <th class="p-2.5 w-64">Nama Perwira</th>
                                    <th class="p-2.5 w-44">Pangkat, Korps</th>
                                    <th class="p-2.5 w-32">NRP</th>
                                    <th class="p-2.5 text-center" colspan="7">Tanggal Dinas Jaga</th>
                                    <th class="p-2.5 text-center w-12">Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(p, idx) in form.perwiras" :key="idx" class="hover:bg-slate-50/50">
                                    <td class="p-2 text-center font-bold text-slate-500">{{ idx + 1 }}.</td>
                                    <td class="p-2">
                                        <div class="space-y-1">
                                            <select 
                                                v-model="p.user_id" 
                                                @change="onPerwiraSelect(idx, p.user_id)"
                                                class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-slate-50"
                                            >
                                                <option value="">-- Pilih dari Personel --</option>
                                                <option v-for="user in personels" :key="user.id" :value="user.id">{{ user.pangkat }} {{ user.name }}</option>
                                            </select>
                                            <input type="text" v-model="p.nama" placeholder="Ketik manual nama..." class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.pangkat_korps" placeholder="Peltu Saa" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.nrp" placeholder="82068" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
                                    <!-- 7 Kolom Tanggal Dinas -->
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_1" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_2" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_3" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_4" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_5" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_6" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-1 w-12"><input type="text" v-model="p.tgl_7" class="w-full p-1.5 text-xs text-center border border-slate-200 rounded-lg" /></td>
                                    <td class="p-2 text-center">
                                        <button 
                                            @click="removePerwira(idx)" 
                                            type="button" 
                                            class="text-red-500 hover:text-red-700 font-bold p-1 cursor-pointer"
                                            title="Hapus Baris"
                                        >
                                            x
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- BAGIAN 3: DAFTAR ANGGOTA JAGA DIVISI (LAMPIRAN 2) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">3. Lampiran 2: Daftar Nama Anggota Jaga (5 Kelompok Divisi)</h2>
                        <p class="text-xs text-slate-500">Kelompok regu divisi jaga (1 BAGA + 3 Anggota per regu) dan jadwal tanggal bertugas.</p>
                    </div>

                    <div class="space-y-6">
                        <div 
                            v-for="(divisi, divIdx) in form.anggotas" 
                            :key="divIdx"
                            class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-4"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 bg-slate-800 text-white font-black text-xs rounded-lg">
                                        Divisi {{ divisi.divisi_no }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-700">Jadwal Tanggal Dinas:</span>
                                </div>
                                <div class="flex-1 max-w-md">
                                    <input 
                                        type="text" 
                                        v-model="divisi.tanggal_list_text" 
                                        placeholder="04, 09, 14, 19, 24, 29 JULI 2026" 
                                        class="w-full p-2 text-xs border border-slate-300 rounded-xl bg-white font-bold text-blue-700"
                                    />
                                </div>
                                <button 
                                    @click="addAnggotaToDivisi(divIdx)" 
                                    type="button"
                                    class="px-3 py-1 bg-blue-600 text-white font-bold text-xs rounded-lg hover:bg-blue-700 transition"
                                >
                                    + Tambah Personel
                                </button>
                            </div>

                            <!-- Tabel Anggota Divisi -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead>
                                        <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-200">
                                            <th class="py-2 w-64">Nama Lengkap</th>
                                            <th class="py-2 w-44">Pangkat / Korps</th>
                                            <th class="py-2 w-36">NRP / NIP</th>
                                            <th class="py-2 w-28 text-center">Peran Jaga</th>
                                            <th class="py-2 w-10 text-center">Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(item, itemIdx) in divisi.anggota_items" :key="itemIdx">
                                            <td class="py-2 pr-2">
                                                <div class="space-y-1">
                                                    <select 
                                                        v-model="item.user_id" 
                                                        @change="onAnggotaSelect(divIdx, itemIdx, item.user_id)"
                                                        class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white"
                                                    >
                                                        <option value="">-- Pilih dari Personel --</option>
                                                        <option v-for="user in personels" :key="user.id" :value="user.id">{{ user.pangkat }} {{ user.name }}</option>
                                                    </select>
                                                    <input type="text" v-model="item.nama" placeholder="Ketik nama..." class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                                </div>
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.pangkat_korps" placeholder="PELDA MAR" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.nrp_nip" placeholder="106737" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                            </td>
                                            <td class="py-2 pr-2 text-center">
                                                <select v-model="item.role_jaga" class="p-1.5 text-xs font-bold border border-slate-200 rounded-lg bg-white">
                                                    <option value="BAGA">BAGA</option>
                                                    <option value="ANGGOTA">ANGGOTA</option>
                                                </select>
                                            </td>
                                            <td class="py-2 text-center">
                                                <button 
                                                    @click="removeAnggotaFromDivisi(divIdx, itemIdx)" 
                                                    type="button" 
                                                    class="text-red-500 hover:text-red-700 font-bold p-1"
                                                >
                                                    x
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT BAR -->
                <div class="flex items-center justify-end gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-xs">
                    <Link 
                        :href="route('sp-jaga.index')"
                        class="px-6 py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-2xl transition"
                    >
                        Batal
                    </Link>

                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs uppercase tracking-wider rounded-2xl transition shadow-lg shadow-blue-500/20 active:scale-[0.98] cursor-pointer"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan & Terbitkan SP Jaga' }}
                    </button>
                </div>

            </form>

        </div>
    </AuthenticatedLayout>
</template>