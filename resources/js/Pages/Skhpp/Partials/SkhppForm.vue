<script setup>
// Form SKHPP bersama untuk halaman Buat (tanpa prop skhpp) dan Revisi (dengan prop skhpp).
import { Link, useForm } from '@inertiajs/vue3';
import { ref, reactive, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import { JENIS_IDENTITAS, berpangkat, gabungPangkat, uraiIdentitas, teksPengikut, labelPengikut, judulLampiran, satuanDariPengantar, kegiatanDariPeruntukan, peruntukanDariKegiatan } from '@/Utils/skhppIdentitas';

const props = defineProps({
    skhpp: { type: Object, default: null },
    // { dinas: { kode, nama, jenis: [{id, name}] }, perusahaan: {...} } — kategori & jenis surat di Agenda Surat
    agendaSurat: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => !!props.skhpp);
const s = props.skhpp || {};

const form = useForm({
    kategori_personel: s.kategori_personel || 'militer',
    is_pernikahan: Boolean(s.is_pernikahan),
    is_mitra_pks: Boolean(s.is_mitra_pks),
    letter_sub_category_id: s.letter_sub_category_id || '',
    nomor_manual: s.nomor_manual || '',
    nomor_tulis_tangan: Boolean(s.nomor_tulis_tangan),
    ttd_mode: s.ttd_mode === 'manual' ? 'manual' : 'tte',
    surat_pengantar: s.surat_pengantar || '',
    nama: s.nama || '',
    pangkat_korps_nrp: s.pangkat_korps_nrp || '',
    nik: s.nik || '',
    jenis_identitas: s.jenis_identitas || null,
    jabatan_pekerjaan: s.jabatan_pekerjaan || '',
    tempat_lahir: s.tempat_lahir || '',
    tanggal_lahir: s.tanggal_lahir ? s.tanggal_lahir.split('T')[0] : '',
    jenis_kelamin: s.jenis_kelamin || 'Laki-laki',
    agama: s.agama || 'Islam',
    alamat: s.alamat || '',
    has_pengikut: Boolean(s.has_pengikut),
    satuan: s.satuan || '',
    kegiatan: s.kegiatan || kegiatanDariPeruntukan(s.peruntukan),
    members: (s.members || []).map((m) => ({ nama: m.nama, pangkat_nrp_nik: m.pangkat_nrp_nik, jabatan: m.jabatan, usaha: m.usaha || '', lokasi_usaha: m.lokasi_usaha || '' })),
    peruntukan: s.peruntukan || '',
    foto_1: null,
    foto_2: null,
});

// Jenis SKHPP hanya dua format: Dinas (SKHPP-D) dan Mitra Kerja (SKHPP-P).
// Militer / PNS tidak dipilih terpisah lagi — mengikuti jenis nomor identitas (NRP = militer).
const format = ref(s.kategori_personel === 'perusahaan' ? 'perusahaan' : 'dinas');

// Identitas: dropdown jenis + nomor; pangkat hanya untuk NRP/NIP.
const identitas = reactive(props.skhpp ? uraiIdentitas(props.skhpp) : { jenis: 'NRP', pangkat: '', nomor: '' });
const punyaPangkat = computed(() => berpangkat(identitas.jenis));

const agenda = computed(() => props.agendaSurat[format.value] || { kode: '', nama: '', jenis: [] });

const pilihFormat = (nilai) => {
    if (format.value !== nilai) form.letter_sub_category_id = ''; // jenis surat milik kategori agenda yang lain
    format.value = nilai;
    // Bantu pilih jenis identitas yang paling umum selama nomornya belum diisi.
    if (!identitas.nomor) identitas.jenis = nilai === 'perusahaan' ? 'NIK' : 'NRP';
};

const TEKS_IDENTITAS = {
    NRP: { pangkat: 'Pangkat / Korps', contohPangkat: 'Contoh: Kapten Laut (K/W)', contohNomor: 'Contoh: 22608/P' },
    NIP: { pangkat: 'Pangkat / Golongan', contohPangkat: 'Contoh: Penata III/c', contohNomor: 'Contoh: 197305121998031002' },
    NIM: { contohNomor: 'Nomor Induk Mahasiswa' },
    NIK: { contohNomor: 'Contoh: 3515081205640009' },
};
const teks = computed(() => TEKS_IDENTITAS[identitas.jenis]);

const ROMAWI = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
const bulanRomawiIni = ROMAWI[new Date().getMonth()];
const tahunIni = new Date().getFullYear();

// Dasar surat pengantar (Poin 1c): tiga isian → satu kalimat yang disimpan di surat_pengantar.
const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const uraiPengantar = (teksLama) => {
    const m = (teksLama || '').match(/^Surat\s+(.+?)\s+No\.?\s*(.+?)\s+tanggal\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i);
    const bulan = m ? BULAN.findIndex((b) => b.toLowerCase() === m[4].toLowerCase()) : -1;
    if (bulan < 0) return { dari: '', nomor: '', tanggal: '' };
    return { dari: m[1], nomor: m[2], tanggal: `${m[5]}-${String(bulan + 1).padStart(2, '0')}-${m[3].padStart(2, '0')}` };
};
const pengantar = reactive(uraiPengantar(s.surat_pengantar));
// Data lama yang kalimatnya tidak berpola "Surat … No. … tanggal …" dibiarkan apa adanya sampai ketiga isian diisi.
const pengantarLama = pengantar.dari ? '' : (s.surat_pengantar || '');
const teksPengantar = computed(() => {
    const dari = pengantar.dari.trim();
    const nomor = pengantar.nomor.trim();
    if (!dari || !nomor || !pengantar.tanggal) return pengantarLama;
    const [y, m, d] = pengantar.tanggal.split('-').map(Number);
    return `Surat ${dari} No. ${nomor} tanggal ${d} ${BULAN[m - 1]} ${y}, tentang permohonan Security Clearance.`;
});

// Pengikut
const tambahPengikut = () => form.members.push({ nama: '', pangkat_nrp_nik: '', jabatan: '', usaha: '', lokasi_usaha: '' });
// Mitra / PKS (khusus SKHPP-P): tiap pengikut punya jenis usaha, tampil sebagai kolom USAHA di lampiran.
const mitraPks = computed(() => format.value === 'perusahaan' && form.is_mitra_pks);
const hapusPengikut = (i) => form.members.splice(i, 1);
const togglePengikut = () => {
    if (form.has_pengikut && form.members.length === 0) tambahPengikut();
};

// Satuan judul lampiran ikut "Surat Dari" selama belum diketik sendiri oleh operator.
let satuanOtomatis = !form.satuan || form.satuan === satuanDariPengantar(pengantar.dari);
watch(() => pengantar.dari, (dari) => {
    if (satuanOtomatis) form.satuan = satuanDariPengantar(dari);
}, { immediate: !form.satuan });
const ketikSatuan = () => { satuanOtomatis = form.satuan === ''; };

// Peruntukan: operator cukup menulis kegiatannya; awalan baku ditambahkan otomatis.
// Data lama yang tidak berpola awalan baku dibiarkan apa adanya sampai isian kegiatan diisi.
const peruntukanLama = form.kegiatan ? '' : (s.peruntukan || '');
const teksPeruntukan = computed(() => peruntukanDariKegiatan(form.kegiatan) || peruntukanLama);

// Foto
const preview1 = ref(s.foto_1 ? '/storage/' + s.foto_1 : null);
const preview2 = ref(s.foto_2 ? '/storage/' + s.foto_2 : null);
const pilihFoto = (e, kolom, preview) => {
    const file = e.target.files[0];
    if (!file) return;
    form[kolom] = file;
    preview.value = URL.createObjectURL(file);
};

const submit = () => {
    form.transform((data) => {
        const adaPengikut = data.has_pengikut && data.members.length > 0;
        return {
            ...data,
            surat_pengantar: teksPengantar.value,
            kategori_personel: format.value === 'perusahaan' ? 'perusahaan' : (identitas.jenis === 'NRP' ? 'militer' : 'sipil_dinas'),
            jenis_identitas: identitas.jenis,
            pangkat_korps_nrp: punyaPangkat.value ? gabungPangkat(identitas.pangkat, identitas.jenis, identitas.nomor) : '',
            nik: punyaPangkat.value ? '' : identitas.nomor.trim(),
            has_pengikut: adaPengikut,
            peruntukan: teksPeruntukan.value,
            kegiatan: data.kegiatan.trim().replace(/\.+$/, ''),
            nomor_manual: data.nomor_tulis_tangan ? '' : data.nomor_manual,
            is_mitra_pks: mitraPks.value,
            members: adaPengikut ? data.members.map((m) => ({
                ...m,
                usaha: mitraPks.value ? m.usaha.trim() : '',
                lokasi_usaha: mitraPks.value ? m.lokasi_usaha.trim() : '',
            })) : [],
        };
    }).post(isEdit.value ? route('skhpp.update', props.skhpp.id) : route('skhpp.store'), {
        onSuccess: () => Swal.fire(
            'BERHASIL',
            form.ttd_mode === 'manual'
                ? 'SKHPP tersimpan untuk TTD manual. Cetak naskahnya, mintakan tanda tangan Komandan, lalu unggah hasilnya lewat tombol "Unggah TTD".'
                : (isEdit.value
                    ? 'Revisi data SKHPP telah diperbarui & dikirim ulang ke TTD Komandan.'
                    : 'Permohonan SKHPP telah diterbitkan dan dikirim ke TTD Komandan.'),
            'success',
        ),
    });
};

const kartu = 'bg-white p-6 rounded-3xl shadow-xs border border-slate-100 space-y-4';
const judul = 'text-xs font-black uppercase tracking-wider text-emerald-600 border-b pb-2';
const label = 'block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1';
const input = 'w-full text-xs font-semibold px-4 py-2.5 rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500';
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">

        <!-- 1. Jenis SKHPP -->
        <div :class="kartu">
            <h2 :class="judul">1. Jenis SKHPP</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Jenis SKHPP">
                <button
                    v-for="opsi in [
                        { nilai: 'dinas', nama: 'Dinas — Militer / PNS', ket: 'Format SKHPP-D' },
                        { nilai: 'perusahaan', nama: 'Mitra Kerja / Perusahaan', ket: 'Format SKHPP-P' },
                    ]"
                    :key="opsi.nilai"
                    type="button"
                    role="radio"
                    :aria-checked="format === opsi.nilai"
                    @click="pilihFormat(opsi.nilai)"
                    :class="format === opsi.nilai ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'"
                    class="flex items-center gap-3 p-4 rounded-2xl border text-left transition-all cursor-pointer"
                >
                    <span :class="format === opsi.nilai ? 'border-emerald-600' : 'border-slate-300'" class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0">
                        <span v-if="format === opsi.nilai" class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    </span>
                    <span>
                        <span class="block text-sm font-bold text-slate-900">{{ opsi.nama }}</span>
                        <span class="block text-[11px] text-slate-500 font-medium">{{ opsi.ket }}</span>
                    </span>
                </button>
            </div>

            <label v-if="format === 'perusahaan'" class="flex items-center gap-2.5 cursor-pointer select-none w-fit">
                <input type="checkbox" v-model="form.is_mitra_pks" class="w-4 h-4 text-emerald-600 rounded-md focus:ring-emerald-500" />
                <span class="text-xs font-semibold text-slate-700">Mitra / PKS <span class="font-medium text-slate-500">(tiap pengikut diisi jenis &amp; lokasi usahanya, mis. bengkel, laundry; tampil di lampiran)</span></span>
            </label>

            <label class="flex items-center gap-2.5 cursor-pointer select-none w-fit">
                <input type="checkbox" v-model="form.is_pernikahan" class="w-4 h-4 text-emerald-600 rounded-md focus:ring-emerald-500" />
                <span class="text-xs font-semibold text-slate-700">Untuk pengajuan nikah <span class="font-medium text-slate-500">(memakai 2 pas foto: calon suami &amp; calon istri)</span></span>
            </label>
            <p v-if="form.errors.kategori_personel" class="text-xs text-rose-600 font-medium">{{ form.errors.kategori_personel }}</p>

            <!-- Cara tanda tangan Komandan -->
            <div class="pt-2 border-t border-slate-100">
                <span :class="label" id="skhpp-ttd-label">Tanda Tangan Komandan</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1" role="radiogroup" aria-labelledby="skhpp-ttd-label">
                    <button
                        v-for="opsi in [
                            { nilai: 'tte', nama: 'TTE (elektronik)', ket: 'Dikirim ke Komandan untuk disahkan di aplikasi; terbit dengan QR.' },
                            { nilai: 'manual', nama: 'TTD manual', ket: 'Dicetak, ditandatangani basah, lalu hasilnya diunggah.' },
                        ]"
                        :key="opsi.nilai"
                        type="button"
                        role="radio"
                        :aria-checked="form.ttd_mode === opsi.nilai"
                        @click="form.ttd_mode = opsi.nilai"
                        :class="form.ttd_mode === opsi.nilai ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white hover:bg-slate-50'"
                        class="flex items-center gap-3 p-4 rounded-2xl border text-left transition-all cursor-pointer"
                    >
                        <span :class="form.ttd_mode === opsi.nilai ? 'border-emerald-600' : 'border-slate-300'" class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0">
                            <span v-if="form.ttd_mode === opsi.nilai" class="w-2 h-2 rounded-full bg-emerald-600"></span>
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-slate-900">{{ opsi.nama }}</span>
                            <span class="block text-[11px] text-slate-500 font-medium">{{ opsi.ket }}</span>
                        </span>
                    </button>
                </div>
                <p v-if="form.ttd_mode === 'manual'" class="mt-2 text-[11px] text-amber-800 bg-amber-50 p-2.5 rounded-xl border border-amber-200" role="status">
                    {{ form.nomor_tulis_tangan ? 'Nomor dikosongkan untuk ditulis tangan.' : 'Nomor SKHPP langsung diberikan saat disimpan supaya tercetak di naskah.' }} SKHPP baru berstatus terbit setelah berkas bertanda tangan diunggah.
                </p>
            </div>

            <!-- Sinkronisasi Agenda Surat & penomoran -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <label for="skhpp-jenis-surat" :class="label">Jenis Surat (Agenda Surat)</label>
                    <select id="skhpp-jenis-surat" v-model="form.letter_sub_category_id" :disabled="!agenda.jenis.length" :class="input" class="disabled:bg-slate-50 disabled:text-slate-400">
                        <option value="">{{ agenda.jenis.length ? '— Tanpa jenis surat —' : 'Belum ada jenis surat di kategori ini' }}</option>
                        <option v-for="j in agenda.jenis" :key="j.id" :value="j.id">{{ j.name }}</option>
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Saat disahkan, SKHPP dicatat di Agenda Surat pada kategori <b class="text-slate-700">{{ agenda.nama }} ({{ agenda.kode }})</b>.
                    </p>
                    <p v-if="form.errors.letter_sub_category_id" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.letter_sub_category_id }}</p>
                </div>

                <div>
                    <label class="flex items-center gap-2.5 cursor-pointer select-none w-fit mb-2">
                        <input type="checkbox" v-model="form.nomor_tulis_tangan" class="w-4 h-4 text-emerald-600 rounded-md focus:ring-emerald-500" />
                        <span class="text-xs font-semibold text-slate-700">Nomor ditulis tangan</span>
                    </label>
                    <p v-if="form.nomor_tulis_tangan" class="text-[11px] text-amber-800 bg-amber-50 p-2.5 rounded-xl border border-amber-200" role="status">
                        Nomor tercetak kosong: <b>R/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /SKHPP/{{ bulanRomawiIni }}/{{ tahunIni }}</b>, untuk diisi dengan pulpen.
                        Nomor tidak diambil dari Agenda Surat.
                    </p>

                    <template v-else>
                    <label for="skhpp-nomor-manual" :class="label">Nomor Urut SKHPP <span class="font-medium normal-case text-slate-500">(opsional)</span></label>
                    <input id="skhpp-nomor-manual" type="text" v-model.trim="form.nomor_manual" maxlength="8" pattern="[0-9]{1,6}[A-Za-z]{0,2}"
                        title="Angka saja, boleh diikuti huruf. Contoh: 120 atau 120a" placeholder="Contoh: 120 atau 120a" :class="input" />
                    <p class="mt-1 text-[11px] text-slate-500">
                        <template v-if="form.nomor_manual">Nomor menjadi <b class="text-slate-700">R / {{ form.nomor_manual }} / SKHPP / (bulan) / (tahun)</b>.</template>
                        <template v-else>Cukup angkanya saja, boleh berhuruf (120a). Kosongkan untuk nomor otomatis.</template>
                    </p>
                    <p v-if="form.errors.nomor_manual" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.nomor_manual }}</p>
                    </template>
                </div>
            </div>
        </div>

        <!-- 2. Surat pengantar (Poin 1c) -->
        <div :class="kartu">
            <h2 :class="judul">2. Dasar Surat Pengantar (Poin 1c)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="skhpp-pengantar-dari" :class="label">Surat Dari *</label>
                    <input id="skhpp-pengantar-dari" type="text" v-model="pengantar.dari" :required="!pengantarLama" placeholder="Contoh: Dandenma Kodaeral V" :class="input" />
                </div>
                <div>
                    <label for="skhpp-pengantar-nomor" :class="label">Nomor Surat *</label>
                    <input id="skhpp-pengantar-nomor" type="text" v-model="pengantar.nomor" :required="!pengantarLama" placeholder="Contoh: R/19/III/2026" :class="input" />
                </div>
                <div>
                    <label for="skhpp-pengantar-tgl" :class="label">Tanggal Surat *</label>
                    <input id="skhpp-pengantar-tgl" type="date" v-model="pengantar.tanggal" :required="!pengantarLama" :class="input" />
                </div>
                <div class="sm:col-span-3">
                    <label for="skhpp-satuan" :class="label">Satuan {{ form.has_pengikut ? '*' : '' }}</label>
                    <input id="skhpp-satuan" type="text" v-model="form.satuan" @input="ketikSatuan" :required="form.has_pengikut"
                        placeholder="Contoh: Pomal Kodaeral V" :class="input" />
                    <p class="mt-1 text-[11px] text-slate-500">Terisi otomatis dari "Surat Dari" (Danpomal Kodaeral V → Pomal Kodaeral V); boleh diubah. Dipakai di judul lampiran pengikut.</p>
                    <p v-if="form.errors.satuan" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.satuan }}</p>
                </div>
            </div>
            <p v-if="teksPengantar" class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200" role="status">
                Tertulis di poin 1c: <b class="text-slate-800">{{ teksPengantar }}</b>
            </p>
            <p v-if="form.errors.surat_pengantar" class="text-xs text-rose-600 font-medium">{{ form.errors.surat_pengantar }}</p>
        </div>

        <!-- 3. Data personel (Poin 2) -->
        <div :class="kartu">
            <h2 :class="judul">3. Data Personel yang Diteliti (Poin 2)</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="skhpp-nama" :class="label">Nama Lengkap &amp; Gelar *</label>
                    <input id="skhpp-nama" type="text" v-model="form.nama" required placeholder="Contoh: dr. Lilis Haryani atau Sardijono, S.H." :class="input" />
                    <p v-if="form.errors.nama" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.nama }}</p>
                </div>

                <!-- Nomor identitas: jenis (dropdown) + nomor -->
                <div :class="punyaPangkat ? '' : 'sm:col-span-2'">
                    <label for="skhpp-nomor" :class="label">Nomor Identitas *</label>
                    <div class="flex">
                        <select v-model="identitas.jenis" aria-label="Jenis nomor identitas"
                            class="w-24 shrink-0 text-xs font-bold pl-4 pr-8 py-2.5 rounded-l-xl border-slate-200 bg-slate-50 focus:border-emerald-500 focus:ring-emerald-500 focus:z-10">
                            <option v-for="j in JENIS_IDENTITAS" :key="j" :value="j">{{ j }}</option>
                        </select>
                        <input id="skhpp-nomor" type="text" v-model="identitas.nomor" required
                            :inputmode="identitas.jenis === 'NIK' ? 'numeric' : 'text'"
                            :placeholder="teks.contohNomor"
                            :class="input" class="!rounded-l-none -ml-px focus:z-10" />
                    </div>
                    <p v-if="form.errors.pangkat_korps_nrp || form.errors.nik" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.pangkat_korps_nrp || form.errors.nik }}</p>
                </div>

                <div v-if="punyaPangkat">
                    <label for="skhpp-pangkat" :class="label">{{ teks.pangkat }} *</label>
                    <input id="skhpp-pangkat" type="text" v-model="identitas.pangkat" required :placeholder="teks.contohPangkat" :class="input" />
                </div>

                <div class="sm:col-span-2">
                    <label for="skhpp-jabatan" :class="label">{{ punyaPangkat ? 'Jabatan' : 'Pekerjaan' }} *</label>
                    <input id="skhpp-jabatan" type="text" v-model="form.jabatan_pekerjaan" required
                        :placeholder="punyaPangkat ? 'Contoh: Ka/PS BP Tg. Sadari Diskes Kodaeral V' : 'Contoh: Pimpinan CV Afnalia Jaya / Mahasiswa'"
                        :class="input" />
                    <p v-if="form.errors.jabatan_pekerjaan" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.jabatan_pekerjaan }}</p>
                </div>

                <div>
                    <label for="skhpp-tempat" :class="label">Tempat Lahir *</label>
                    <input id="skhpp-tempat" type="text" v-model="form.tempat_lahir" required placeholder="Contoh: Surabaya" :class="input" />
                </div>

                <div>
                    <label for="skhpp-tgl" :class="label">Tanggal Lahir *</label>
                    <input id="skhpp-tgl" type="date" v-model="form.tanggal_lahir" required :class="input" />
                    <p v-if="form.errors.tanggal_lahir" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.tanggal_lahir }}</p>
                </div>

                <div>
                    <label for="skhpp-jk" :class="label">Jenis Kelamin *</label>
                    <select id="skhpp-jk" v-model="form.jenis_kelamin" required :class="input">
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>

                <div>
                    <label for="skhpp-agama" :class="label">Agama *</label>
                    <input id="skhpp-agama" type="text" v-model="form.agama" required list="skhpp-daftar-agama" :class="input" />
                    <datalist id="skhpp-daftar-agama">
                        <option v-for="a in ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']" :key="a" :value="a"></option>
                    </datalist>
                </div>

                <div class="sm:col-span-2">
                    <label for="skhpp-alamat" :class="label">Alamat Rumah Lengkap *</label>
                    <textarea id="skhpp-alamat" v-model="form.alamat" rows="2" required placeholder="Contoh: Jl. Petukangan 62 Ampel Surabaya." :class="input" class="!font-medium"></textarea>
                </div>
            </div>
        </div>

        <!-- 4. Pengikut (Poin 2h + Lampiran) -->
        <div :class="kartu">
            <h2 :class="judul">4. Pengikut / Rombongan (Poin 2h)</h2>

            <label class="flex items-center gap-2.5 cursor-pointer select-none w-fit">
                <input type="checkbox" v-model="form.has_pengikut" @change="togglePengikut" class="w-4 h-4 text-emerald-600 rounded-md focus:ring-emerald-500" />
                <span class="text-xs font-semibold text-slate-700">Lebih dari 1 orang (ada pengikut)</span>
            </label>

            <div v-if="form.has_pengikut" class="space-y-3">
                <p class="text-xs text-amber-800 font-medium bg-amber-50 p-3 rounded-xl border border-amber-200" role="status">
                    Poin 2h akan tertulis: <b>Pengikut : {{ teksPengikut(form.members.length) }}</b>.
                    Nama-nama di bawah ini dimuat pada lembar 2 (Lampiran).
                </p>

                <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200" role="status">
                    Judul lampiran: <b class="text-slate-800">{{ judulLampiran(form.satuan, form.kegiatan, peruntukanLama) }}</b>
                    <span class="block mt-0.5 text-[11px] text-slate-500">Dari isian Satuan (bagian 2) dan Maksud &amp; Peruntukan (bagian 5).</span>
                </p>

                <div v-for="(member, idx) in form.members" :key="idx" class="grid grid-cols-1 sm:grid-cols-[1.5rem_1fr_1fr_1fr_auto] gap-2 sm:items-center p-3 sm:p-0 bg-slate-50 sm:bg-transparent rounded-2xl">
                    <span class="text-xs font-bold text-slate-500 tabular-nums">{{ idx + 1 }}.</span>
                    <input type="text" v-model="member.nama" required placeholder="Nama lengkap" :aria-label="`Nama pengikut ${idx + 1}`" :class="input" />
                    <input type="text" v-model="member.pangkat_nrp_nik" required :placeholder="labelPengikut(identitas.jenis)" :aria-label="`${labelPengikut(identitas.jenis)} pengikut ${idx + 1}`" :class="input" />
                    <input type="text" v-model="member.jabatan" required placeholder="Jabatan / pekerjaan" :aria-label="`Jabatan pengikut ${idx + 1}`" :class="input" />
                    <button type="button" @click="hapusPengikut(idx)" class="text-xs font-bold text-rose-600 hover:underline px-2 py-2 text-left cursor-pointer">Hapus</button>
                    <div v-if="mitraPks" class="sm:col-start-2 sm:col-span-3 grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-2 sm:mb-2">
                        <input type="text" v-model="member.usaha" required list="skhpp-daftar-usaha" placeholder="Usaha (bengkel, laundry, …)" :aria-label="`Usaha pengikut ${idx + 1}`" :class="input" />
                        <input type="text" v-model="member.lokasi_usaha" required placeholder="Lokasi usaha (contoh: Kantin Mako Kodaeral V, Jl. Perak Barat)" :aria-label="`Lokasi usaha pengikut ${idx + 1}`" :class="input" />
                    </div>
                </div>

                <datalist id="skhpp-daftar-usaha">
                    <option v-for="u in ['Bengkel', 'Laundry', 'Warung Makan', 'Kantin', 'Toko Kelontong', 'Fotokopi', 'Cleaning Service', 'Catering']" :key="u" :value="u"></option>
                </datalist>

                <button type="button" @click="tambahPengikut" class="px-4 py-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold uppercase rounded-xl transition-all cursor-pointer">
                    + Tambah Pengikut
                </button>
            </div>
        </div>

        <!-- 5. Peruntukan (Poin 4) -->
        <div :class="kartu">
            <h2 :class="judul">5. Peruntukan SKHPP (Poin 4)</h2>
            <div>
                <label for="skhpp-peruntukan" :class="label">Maksud &amp; Peruntukan *</label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 whitespace-nowrap">dalam rangka melengkapi administrasi</span>
                    <input id="skhpp-peruntukan" type="text" v-model="form.kegiatan" :required="!peruntukanLama"
                        placeholder="Contoh: Seleksi Diktukpa LVI/56 TA. 2026" :class="input" />
                </div>
                <p v-if="teksPeruntukan" class="mt-2 text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200" role="status">
                    Tertulis di dokumen: <b class="text-slate-800">SKHPP ini diberikan {{ teksPeruntukan.replace(/\.+$/, '') }}.</b>
                    <span v-if="peruntukanLama && !form.kegiatan" class="block mt-0.5 text-[11px] text-slate-500">(Teks lama dipertahankan sampai isian di atas diisi.)</span>
                </p>
                <p v-if="form.errors.peruntukan" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.peruntukan }}</p>
            </div>
        </div>

        <!-- 6. Pas foto -->
        <div :class="kartu">
            <h2 :class="judul">6. Pas Foto{{ isEdit ? ' (kosongkan jika tidak diubah)' : '' }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="skhpp-foto1" :class="label" class="!mb-2">{{ form.is_pernikahan ? 'Foto 1: Calon Suami' : 'Pas Foto Personel (3x4)' }}{{ isEdit ? '' : ' *' }}</label>
                    <input id="skhpp-foto1" type="file" @change="pilihFoto($event, 'foto_1', preview1)" accept="image/jpeg,image/png" :required="!isEdit"
                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" />
                    <p v-if="form.errors.foto_1" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.foto_1 }}</p>
                    <img v-if="preview1" :src="preview1" alt="Pratinjau foto 1" class="mt-3 w-32 h-40 object-cover rounded-xl border border-slate-200 shadow-md" />
                </div>

                <div v-if="form.is_pernikahan">
                    <label for="skhpp-foto2" :class="label" class="!mb-2">Foto 2: Calon Istri{{ isEdit ? '' : ' *' }}</label>
                    <input id="skhpp-foto2" type="file" @change="pilihFoto($event, 'foto_2', preview2)" accept="image/jpeg,image/png" :required="!isEdit"
                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" />
                    <p v-if="form.errors.foto_2" class="mt-1 text-xs text-rose-600 font-medium">{{ form.errors.foto_2 }}</p>
                    <img v-if="preview2" :src="preview2" alt="Pratinjau foto 2" class="mt-3 w-32 h-40 object-cover rounded-xl border border-slate-200 shadow-md" />
                </div>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-4 pt-4">
            <Link :href="route('skhpp.index')" class="px-6 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold uppercase rounded-2xl transition-all">Batal</Link>
            <button type="submit" :disabled="form.processing"
                class="px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-600/20 transition-all disabled:opacity-50 cursor-pointer">
                {{ form.processing ? 'Mengirim...' : (form.ttd_mode === 'manual' ? 'Simpan untuk TTD Manual' : (isEdit ? 'Simpan & Ajukan Ulang ke Komandan' : 'Kirim Permohonan SKHPP ke Komandan')) }}
            </button>
        </div>
    </form>
</template>
