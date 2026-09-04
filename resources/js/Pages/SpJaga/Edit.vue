<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    spJaga: Object,
    personels: Array,
});

const monthNames = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const form = useForm({
    bulan: props.spJaga.bulan,
    tahun: props.spJaga.tahun,
    nomor_urut: props.spJaga.nomor_urut || 29,
    tmt_mulai: props.spJaga.tmt_mulai ? props.spJaga.tmt_mulai.substring(0, 10) : '',
    tmt_selesai: props.spJaga.tmt_selesai ? props.spJaga.tmt_selesai.substring(0, 10) : '',
    tanggal_surat: props.spJaga.tanggal_surat ? props.spJaga.tanggal_surat.substring(0, 10) : '',
    ttd_type: props.spJaga.ttd_type || 'tte',
    
    perwiras: props.spJaga.perwiras?.length > 0 ? props.spJaga.perwiras.map(p => ({
        user_id: p.user_id || '',
        nama: p.nama,
        pangkat_korps: p.pangkat_korps,
        nrp: p.nrp,
        tgl_1: p.tgl_1 || '-',
        tgl_2: p.tgl_2 || '-',
        tgl_3: p.tgl_3 || '-',
        tgl_4: p.tgl_4 || '-',
        tgl_5: p.tgl_5 || '-',
        tgl_6: p.tgl_6 || '-',
        tgl_7: p.tgl_7 || '-',
    })) : [],

    anggotas: props.spJaga.anggotas?.length > 0 ? props.spJaga.anggotas.map(a => ({
        divisi_no: a.divisi_no,
        tanggal_list_text: a.tanggal_list_text,
        anggota_items: isArray(a.anggota_items) ? a.anggota_items : JSON.parse(a.anggota_items || '[]')
    })) : []
});

function isArray(val) {
    return Array.isArray(val);
}

const onPerwiraSelect = (idx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.perwiras[idx].nama = p.name;
        form.perwiras[idx].pangkat_korps = p.pangkat || '';
        form.perwiras[idx].nrp = p.nrp || '';
    }
};

const onAnggotaSelect = (divIdx, itemIdx, userId) => {
    const p = props.personels.find(x => x.id === userId);
    if (p) {
        form.anggotas[divIdx].anggota_items[itemIdx].nama = p.name;
        form.anggotas[divIdx].anggota_items[itemIdx].pangkat_korps = p.pangkat || '';
        form.anggotas[divIdx].anggota_items[itemIdx].nrp_nip = p.nrp || '';
    }
};

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


// Fungsi Otomatis Hitung Ulang Tanggal Berdasarkan Pilihan Bulan & Tahun
const recalculateDatesForMonth = () => {
    const b = parseInt(form.bulan);
    const y = parseInt(form.tahun);
    if (!b || !y) return;

    const mName = (monthNames[b] || '').toUpperCase();
    const daysInMonth = new Date(y, b, 0).getDate();

    // 1. TMT Mulai & Selesai
    form.tmt_mulai = `${y}-${String(b).padStart(2, '0')}-01`;
    form.tmt_selesai = `${y}-${String(b).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`;

    // 2. Tanggal Surat (Akhir Bulan Sebelumnya)
    const prevMonth = b === 1 ? 12 : b - 1;
    const prevYear = b === 1 ? y - 1 : y;
    const daysInPrevMonth = new Date(prevYear, prevMonth, 0).getDate();
    form.tanggal_surat = `${prevYear}-${String(prevMonth).padStart(2, '0')}-${String(daysInPrevMonth).padStart(2, '0')}`;

    // 3. Distribusi Tanggal 5 Divisi Anggota
    // Divisi 1 (Start tgl 4): 04, 09, 14, 19, 24, 29
    // Divisi 2 (Start tgl 5): 05, 10, 15, 20, 25, 30
    // Divisi 3 (Start tgl 1): 01, 06, 11, 16, 21, 26 (, 31)
    // Divisi 4 (Start tgl 2): 02, 07, 12, 17, 22, 27
    // Divisi 5 (Start tgl 3): 03, 08, 13, 18, 23, 28
    const startOffsets = [4, 5, 1, 2, 3];
    if (form.anggotas && form.anggotas.length >= 5) {
        form.anggotas.forEach((div, idx) => {
            if (idx < startOffsets.length) {
                const startDay = startOffsets[idx];
                const dates = [];
                for (let d = startDay; d <= daysInMonth; d += 5) {
                    dates.push(String(d).padStart(2, '0'));
                }
                div.tanggal_list_text = `${dates.join(', ')} ${mName} ${y}`;
            }
        });
    }
};

watch(() => [form.bulan, form.tahun], () => {
    recalculateDatesForMonth();
});

const submit = () => {
    form.put(route('sp-jaga.update', props.spJaga.id), {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Perubahan jadwal SP Jaga berhasil disimpan.', 'success');
        }
    });
};
</script>

<template>
    <Head title="Koreksi SP Jaga - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 max-w-6xl mx-auto font-sans pb-12">
            
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <Link :href="route('sp-jaga.index')" class="hover:text-blue-600">SP Jaga</Link>
                        <span>/</span>
                        <span class="text-slate-800 font-bold">Koreksi & Perbarui Jadwal</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Koreksi Surat Perintah Jaga</h1>
                </div>

                <Link 
                    :href="route('sp-jaga.index')"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                >
                    ← Kembali
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                
                <!-- BAGIAN 1: INFORMASI SURAT PERINTAH -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">1. Header & Periode Surat Perintah</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Bulan Dinas</label>
                            <select v-model="form.bulan" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500">
                                <option v-for="(m, i) in monthNames.slice(1)" :key="i" :value="i + 1">{{ m }}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tahun</label>
                            <input type="number" v-model="form.tahun" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Nomor Urut Sprin</label>
                            <input type="number" v-model="form.nomor_urut" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Mulai</label>
                            <input type="date" v-model="form.tmt_mulai" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">TMT Selesai</label>
                            <input type="date" v-model="form.tmt_selesai" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-700 block">Tanggal Dikeluarkan</label>
                            <input type="date" v-model="form.tanggal_surat" class="w-full p-2.5 text-xs border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                </div>

                <!-- BAGIAN 2: DAFTAR PERWIRA JAGA (LAMPIRAN 1) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900">2. Lampiran 1: Daftar Nama Perwira Jaga</h2>
                            <p class="text-xs text-slate-500">Perbarui perwira jaga dan jadwal tanggal dinas.</p>
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
                                            <input type="text" v-model="p.nama" placeholder="Ketik nama..." class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.pangkat_korps" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
                                    <td class="p-2">
                                        <input type="text" v-model="p.nrp" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg" />
                                    </td>
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

                <!-- BAGIAN 3: DAFTAR ANGGOTA JAGA DIVISI (LAMPIRAN 2) -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-extrabold text-slate-900">3. Lampiran 2: Daftar Nama Anggota Jaga (5 Kelompok Divisi)</h2>
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
                                                    <input type="text" v-model="item.nama" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                                </div>
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.pangkat_korps" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
                                            </td>
                                            <td class="py-2 pr-2">
                                                <input type="text" v-model="item.nrp_nip" class="w-full p-1.5 text-xs border border-slate-200 rounded-lg bg-white" />
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
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan Jadwal' }}
                    </button>
                </div>

            </form>

        </div>
    </AuthenticatedLayout>
</template>