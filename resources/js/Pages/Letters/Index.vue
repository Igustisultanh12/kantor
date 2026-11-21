<script setup>
import { ask, notify } from '@/Utils/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuditTrailModal from '@/Components/AuditTrailModal.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    letters: {
        type: Object,
        default: () => ({ data: [], total: 0, links: [] })
    },
    categories: Array,
    awaiting_disposition: { type: Number, default: 0 },
    role_labels: { type: Object, default: () => ({}) },
});

// Tipe dokumen: surat masuk (bernomor agenda + disposisi), keluar, telegram.
const TIPE = [
    { key: 'semua', nama: 'Semua' },
    { key: 'masuk', nama: 'Masuk' },
    { key: 'keluar', nama: 'Keluar' },
    { key: 'telegram', nama: 'Telegram' },
];
const filterTipe = ref('semua');
const STATUS_DISPOSISI = {
    didisposisi: { nama: 'Didisposisi', kelas: 'bg-blue-50 text-blue-700 ring-blue-200' },
    selesai: { nama: 'Disposisi selesai', kelas: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
};
const tujuanDisposisi = (letter) => (letter.disposition?.targets || [])
    .map((t) => `${props.role_labels[t.role] || t.role}${t.status === 'selesai' ? ' (selesai)' : ''}`).join(', ');

const search = ref('');
const isShowingPdf = ref(false);
const isEditing = ref(false);
const selectedPdfUrl = ref('');
const showAuditModal = ref(false);
const auditTarget = ref('');

const openAuditTrail = (letterNumber) => {
    auditTarget.value = letterNumber;
    showAuditModal.value = true;
};

const editForm = useForm({
    id: null,
    letter_number: '',
    subject: '',
    category_id: '',
    security_level: '',
    issuer: '',
    date: '',
});

// Kelompok keperluan. Urutan di sini = urutan tabel & tab. Dikenali dari awalan perihal
// ("PKL: ...", "PHL: ...") atau kata kunci di Jenis Surat / perihal.
const KELOMPOK_DIKENAL = [
    { nama: 'Nikah', pola: /nikah|perkawinan/i },
    { nama: 'Dik', pola: /\bdik(?:tuk|ma|reg|lat|bang|spes|jur|pa|ba)\w*|\bdik\b|pendidikan|seleksi|sesko|kursus|beasiswa/i },
    { nama: 'PKL', pola: /\bpkl\b|praktik kerja|praktek kerja|magang/i },
    { nama: 'PHL', pola: /\bphl\b|harian lepas/i },
    { nama: 'Penelitian', pola: /penelitian|riset/i },
    { nama: 'Kependudukan', pola: /kependudukan|penduduk|rusunawa|rumah dinas/i },
    { nama: 'Akses Aplikasi', pola: /akses aplikasi/i },
];
const LAINNYA = 'Lainnya';
const POLA_AWALAN = /^([A-Za-z][A-Za-z .&/-]{1,30}?)\s*:\s*(.+)$/s;

const namaRapi = (t) => String(t || '').toLowerCase().replace(/(^|[\s.'/-])\S/g, (c) => c.toUpperCase());

const kelompokSurat = (letter) => {
    const subject = String(letter.subject || '').trim();
    const awalan = subject.match(POLA_AWALAN)?.[1];
    // 1) Awalan perihal paling tegas ("PHL: ..."); 2) kata kunci di Jenis Surat lalu perihal.
    for (const teks of [awalan, letter.sub_category?.name, subject]) {
        const k = teks && KELOMPOK_DIKENAL.find((g) => g.pola.test(teks));
        if (k) return k.nama;
    }
    // Awalan baru yang belum dikenal tetap jadi kelompok sendiri, bukan "Lainnya".
    return awalan ? namaRapi(awalan) : LAINNYA;
};

const urutanKelompok = (nama) => {
    const i = KELOMPOK_DIKENAL.findIndex((g) => g.nama === nama);
    return i >= 0 ? i : (nama === LAINNYA ? 999 : 500);
};
const urutkanKelompok = (daftar) => [...daftar].sort((a, b) => urutanKelompok(a) - urutanKelompok(b) || a.localeCompare(b));

// Perihal SKHPP otomatis "SKHPP - NAMA (KATEGORI) - peruntukan": pisahkan nama & perihalnya.
// Perihal berawalan ("PKL: ...") tampil tanpa awalan, karena sudah jadi judul kelompoknya.
const uraiPerihal = (subject) => {
    const s = String(subject || '').trim();
    const m = s.match(/^SKHPP\s*-\s*(.+?)\s*\(([^)]+)\)\s*-\s*(.*)$/i);
    if (m) return { nama: m[1], keterangan: m[3] };
    const a = s.match(POLA_AWALAN);
    if (a) return { nama: null, keterangan: a[2] };
    return { nama: null, keterangan: s };
};
const kapitalAwal = (t) => (t ? t.charAt(0).toUpperCase() + t.slice(1) : '');

const filterKelompok = ref('semua');

const cocokCari = (letter) => {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    return [letter.subject, letter.letter_number, letter.agenda_number, letter.issuer].some((v) => v?.toLowerCase().includes(q));
};

const hasilCariSemuaTipe = computed(() => props.letters.data.filter(cocokCari));
const jumlahTipe = computed(() => {
    const n = { semua: hasilCariSemuaTipe.value.length };
    hasilCariSemuaTipe.value.forEach((l) => { n[l.type] = (n[l.type] || 0) + 1; });
    return n;
});
const hasilCari = computed(() => hasilCariSemuaTipe.value.filter((l) => filterTipe.value === 'semua' || l.type === filterTipe.value));

// Tab: "Semua" + setiap kelompok yang ada pada hasil pencarian, beserta jumlahnya.
const tabKelompok = computed(() => {
    const n = {};
    hasilCari.value.forEach((l) => { const k = kelompokSurat(l); n[k] = (n[k] || 0) + 1; });
    return [
        { key: 'semua', nama: 'Semua', jumlah: hasilCari.value.length },
        ...urutkanKelompok(Object.keys(n)).map((k) => ({ key: k, nama: k, jumlah: n[k] })),
    ];
});

// Pilihan tab yang hilang karena pencarian kembali ke "Semua".
watch(tabKelompok, (tabs) => {
    if (!tabs.some((t) => t.key === filterKelompok.value)) filterKelompok.value = 'semua';
});

// Kategori -> kelompok -> daftar surat (urut tanggal terbaru).
const groupedLetters = computed(() => {
    const out = {};
    hasilCari.value
        .filter((l) => filterKelompok.value === 'semua' || kelompokSurat(l) === filterKelompok.value)
        .forEach((l) => {
            const cat = l.category?.name || 'Umum';
            const k = kelompokSurat(l);
            out[cat] ??= { total: 0, kelompok: {} };
            (out[cat].kelompok[k] ??= []).push(l);
            out[cat].total++;
        });
    Object.values(out).forEach((c) => {
        Object.values(c.kelompok).forEach((list) => list.sort((a, b) => String(b.date).localeCompare(String(a.date))));
        c.urutan = urutkanKelompok(Object.keys(c.kelompok));
    });
    return out;
});

const formatTanggal = (d) => d
    ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '-';

const selectedLetter = ref(null);
const tombolTutupPdf = ref(null);

const openPdf = (letter) => {
    // Arsip disajikan lewat rute berotorisasi, bukan URL /storage publik (audit S04).
    selectedLetter.value = letter;
    selectedPdfUrl.value = route('letters.file', letter.id) + `?t=${new Date().getTime()}`;
    isShowingPdf.value = true;
    nextTick(() => tombolTutupPdf.value?.focus()); // Esc langsung bisa menutup
};

const openEdit = (letter) => {
    editForm.id = letter.id;
    editForm.letter_number = letter.letter_number;
    editForm.subject = letter.subject;
    editForm.category_id = letter.category_id;
    editForm.security_level = letter.security_level;
    editForm.issuer = letter.issuer;
    editForm.date = letter.date ? String(letter.date).slice(0, 10) : '';
    isEditing.value = true;
    nextTick(() => document.getElementById('ubah-nomor')?.focus());
};

const updateLetter = () => {
    editForm.put(route('letters.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
            notify('Arsip berhasil diperbarui');
        },
        onError: (err) => notify('Gagal menyimpan: ' + Object.values(err)[0])
    });
};

const deleteLetter = async (id) => {
    if (await ask('Apakah Anda yakin ingin menghapus arsip ini?')) {
        router.delete(route('letters.destroy', id), { preserveScroll: true });
    }
};

// Lencana sifat surat.
const sifat = (level) => {
    const s = String(level || 'biasa').toLowerCase();
    if (s === 'rahasia') return { nama: 'Rahasia', kelas: 'bg-rose-50 text-rose-700 ring-rose-200' };
    if (s === 'kilat') return { nama: 'Kilat', kelas: 'bg-amber-50 text-amber-700 ring-amber-200' };
    return { nama: 'Biasa', kelas: 'bg-slate-100 text-slate-600 ring-slate-200' };
};

// Titik warna per kelompok (kelompok baru memakai warna cadangan bergiliran).
const WARNA = {
    Nikah: 'bg-pink-500', Dik: 'bg-sky-500', PKL: 'bg-amber-500', PHL: 'bg-emerald-500',
    Penelitian: 'bg-violet-500', Kependudukan: 'bg-teal-500', 'Akses Aplikasi': 'bg-indigo-500', [LAINNYA]: 'bg-slate-400',
};
const CADANGAN = ['bg-orange-500', 'bg-lime-500', 'bg-cyan-500', 'bg-fuchsia-500', 'bg-rose-400', 'bg-blue-400'];
const warnaKelompok = (nama) => WARNA[nama]
    || CADANGAN[[...nama].reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 0) % CADANGAN.length];
</script>

<template>
    <Head title="Arsip Surat" />

    <AuthenticatedLayout>
        <div class="space-y-6 font-sans">
            
            <!-- Page Header Card -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Pusat Arsip Digital Surat</h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">Sistem Manajemen Pengarsipan & Penyimpanan Berkas Dokumen</p>
                </div>
                
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('letter-logs.index', { tab: 'masuk' })" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-5 py-3 rounded-2xl text-xs font-extrabold uppercase transition tracking-wider">
                        Agenda surat masuk
                    </Link>
                    <Link :href="route('letters.create')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl text-xs font-extrabold uppercase shadow-md shadow-blue-500/20 transition tracking-wider flex items-center gap-2">
                        + Arsip Baru
                    </Link>
                </div>
            </div>

            <!-- Surat masuk yang belum boleh diarsipkan -->
            <Link v-if="awaiting_disposition" :href="route('letter-logs.index', { tab: 'disposisi' })"
                class="flex items-center gap-3 p-4 rounded-2xl border border-amber-200 bg-amber-50 text-sm text-amber-700 hover:bg-amber-100 transition">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                <span class="flex-1"><strong>{{ awaiting_disposition }} surat masuk</strong> menunggu disposisi Komandan dan belum tampil di arsip.</span>
                <span class="font-semibold whitespace-nowrap">Buka Agenda Surat &rarr;</span>
            </Link>

            <!-- Tipe dokumen -->
            <div role="tablist" aria-label="Tipe dokumen" class="inline-flex p-[3px] rounded-xl bg-slate-100">
                <button v-for="t in TIPE" :key="t.key" type="button" role="tab" :aria-selected="filterTipe === t.key" @click="filterTipe = t.key"
                    :class="['h-[34px] px-3.5 rounded-[10px] text-sm whitespace-nowrap transition inline-flex items-center gap-1.5', filterTipe === t.key ? 'bg-white text-slate-900 font-medium shadow-sm' : 'text-slate-600 hover:text-slate-900']">
                    {{ t.nama }}
                    <span class="text-[11px] font-mono text-slate-400">{{ jumlahTipe[t.key] || 0 }}</span>
                </button>
            </div>

            <!-- Cari & saring kelompok -->
            <div class="bg-white p-4 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="relative w-full lg:w-80">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <label for="cari-arsip" class="sr-only">Cari arsip</label>
                    <input id="cari-arsip" v-model="search" type="search" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-sm py-2.5 pl-10 focus:ring-emerald-500 focus:border-emerald-500 placeholder:text-slate-400" placeholder="Cari nomor, nomor agenda, nama, atau perihal" />
                </div>

                <div role="tablist" aria-label="Kelompok keperluan" class="flex flex-wrap gap-1.5 lg:ml-auto">
                    <button v-for="t in tabKelompok" :key="t.key" type="button" role="tab"
                        :aria-selected="filterKelompok === t.key" @click="filterKelompok = t.key"
                        :class="filterKelompok === t.key ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                        <span v-if="t.key !== 'semua'" :class="warnaKelompok(t.key)" class="w-2 h-2 rounded-full" aria-hidden="true"></span>
                        {{ t.nama }}
                        <span class="tabular-nums text-xs" :class="filterKelompok === t.key ? 'text-slate-300' : 'text-slate-400'">{{ t.jumlah }}</span>
                    </button>
                </div>
            </div>

        <div class="space-y-6 pb-24">
            <!-- Kosong -->
            <div v-if="!Object.keys(groupedLetters).length" class="bg-white rounded-3xl border border-[#E2E8F0] p-10 text-center">
                <p class="text-sm font-semibold text-slate-700">Tidak ada arsip yang cocok.</p>
                <p class="text-sm text-slate-500 mt-1">Ubah kata pencarian atau pilih kelompok lain.</p>
            </div>

            <section v-for="(cat, catName) in groupedLetters" :key="catName" class="bg-white rounded-3xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                <header class="flex items-baseline justify-between gap-4 px-6 pt-5 pb-3">
                    <h3 class="text-base font-bold text-slate-900">{{ catName }}</h3>
                    <span class="text-sm text-slate-500 tabular-nums">{{ cat.total }} berkas</span>
                </header>

                <template v-for="k in cat.urutan" :key="k">
                    <div class="border-t border-slate-100">
                        <div class="flex items-center gap-2 px-6 py-2.5 bg-slate-50/70">
                            <span :class="warnaKelompok(k)" class="w-2 h-2 rounded-full" aria-hidden="true"></span>
                            <h4 class="text-sm font-semibold text-slate-700">{{ k }}</h4>
                            <span class="text-xs text-slate-400 tabular-nums">{{ cat.kelompok[k].length }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <caption class="sr-only">{{ catName }}, kelompok {{ k }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium text-slate-500">
                                        <th scope="col" class="pl-6 pr-3 py-2 w-12 font-medium">No</th>
                                        <th scope="col" class="px-3 py-2 w-56 font-medium">Nomor agenda / surat</th>
                                        <th scope="col" class="px-3 py-2 font-medium">Perihal</th>
                                        <th scope="col" class="px-3 py-2 w-32 font-medium">Tanggal</th>
                                        <th scope="col" class="pl-3 pr-6 py-2 w-44 font-medium text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(letter, index) in cat.kelompok[k]" :key="letter.id" class="align-top hover:bg-slate-50/60">
                                        <td class="pl-6 pr-3 py-3.5 text-slate-400 tabular-nums">{{ index + 1 }}</td>
                                        <td class="px-3 py-3.5">
                                            <template v-if="letter.agenda_number">
                                                <span class="block font-mono text-[13px] font-semibold text-slate-900 whitespace-nowrap">{{ letter.agenda_number }}</span>
                                                <span class="block text-xs text-slate-500 tabular-nums">No. asal {{ letter.letter_number }}</span>
                                            </template>
                                            <span v-else class="block font-semibold text-slate-900 tabular-nums whitespace-nowrap">{{ letter.letter_number }}</span>
                                            <span class="mt-1.5 flex flex-wrap gap-1">
                                                <span :class="letter.type === 'masuk' ? 'bg-indigo-50 text-indigo-700 ring-indigo-200' : 'bg-slate-100 text-slate-600 ring-slate-200'" class="inline-block rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset capitalize">{{ letter.type }}</span>
                                                <span :class="sifat(letter.security_level).kelas" class="inline-block rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset">{{ sifat(letter.security_level).nama }}</span>
                                                <span v-if="STATUS_DISPOSISI[letter.disposition_status]" :class="STATUS_DISPOSISI[letter.disposition_status].kelas" class="inline-block rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset">{{ STATUS_DISPOSISI[letter.disposition_status].nama }}</span>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3.5 max-w-xl">
                                            <span v-if="uraiPerihal(letter.subject).nama" class="block font-semibold text-slate-900">{{ namaRapi(uraiPerihal(letter.subject).nama) }}</span>
                                            <span class="block text-slate-600 leading-relaxed">{{ kapitalAwal(uraiPerihal(letter.subject).keterangan) }}</span>
                                            <span v-if="letter.issuer && !/^internal/i.test(letter.issuer)" class="block mt-1 text-xs text-slate-500">Dari {{ letter.issuer }}</span>
                                            <Link v-if="tujuanDisposisi(letter)" :href="route('letter-logs.index', { tab: 'disposisi' })" class="block mt-1 text-xs text-blue-700 hover:underline">Disposisi ke {{ tujuanDisposisi(letter) }}</Link>
                                        </td>
                                        <td class="px-3 py-3.5 text-slate-600 whitespace-nowrap tabular-nums">{{ formatTanggal(letter.date) }}</td>
                                        <td class="pl-3 pr-6 py-3.5">
                                            <div class="flex justify-end gap-1">
                                                <button @click="openPdf(letter)" type="button" class="p-2 rounded-lg text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-emerald-600" title="Lihat PDF" :aria-label="`Lihat PDF ${letter.letter_number}`">
                                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </button>
                                                <button @click="openAuditTrail(letter.letter_number)" type="button" class="p-2 rounded-lg text-slate-500 hover:text-sky-700 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-emerald-600" title="Riwayat" :aria-label="`Riwayat ${letter.letter_number}`">
                                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </button>
                                                <button @click="openEdit(letter)" type="button" class="p-2 rounded-lg text-slate-500 hover:text-amber-700 hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-emerald-600" title="Ubah" :aria-label="`Ubah ${letter.letter_number}`">
                                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <button @click="deleteLetter(letter.id)" type="button" class="p-2 rounded-lg text-slate-500 hover:text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-emerald-600" title="Hapus" :aria-label="`Hapus ${letter.letter_number}`">
                                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </section>
        </div>
        </div>

        <Teleport to="body">
            <!-- Pratinjau PDF -->
            <div v-if="isShowingPdf" class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="judul-pratinjau" @keydown.esc="isShowingPdf = false">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="isShowingPdf = false"></div>
                <div class="relative w-full max-w-5xl h-[92vh] bg-white rounded-3xl shadow-2xl flex flex-col overflow-hidden">
                    <div class="flex items-start justify-between gap-4 px-5 sm:px-6 py-4 border-b border-slate-100">
                        <div class="min-w-0">
                            <h3 id="judul-pratinjau" class="text-base font-bold text-slate-900 tabular-nums truncate">{{ selectedLetter?.letter_number }}</h3>
                            <p class="text-sm text-slate-500 truncate">{{ kapitalAwal(uraiPerihal(selectedLetter?.subject).keterangan) }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a :href="selectedPdfUrl" target="_blank" rel="noopener" class="hidden sm:inline-flex items-center px-3.5 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600">Buka di tab baru</a>
                            <button ref="tombolTutupPdf" type="button" @click="isShowingPdf = false" class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600" aria-label="Tutup pratinjau">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>
                    <iframe :src="selectedPdfUrl" :title="`PDF ${selectedLetter?.letter_number || ''}`" class="flex-1 w-full border-0 bg-slate-100"></iframe>
                </div>
            </div>

            <!-- Ubah data arsip -->
            <div v-if="isEditing" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="judul-ubah" @keydown.esc="isEditing = false">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="isEditing = false"></div>
                <div class="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl p-6 sm:p-7">
                    <h3 id="judul-ubah" class="text-base font-bold text-slate-900">Ubah data arsip</h3>
                    <p class="text-sm text-slate-500 mt-0.5 mb-5">Berkas PDF tidak berubah; hanya keterangannya.</p>
                    <form @submit.prevent="updateLetter" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="ubah-nomor" class="block text-sm font-medium text-slate-700 mb-1">Nomor surat</label>
                                <input id="ubah-nomor" v-model="editForm.letter_number" type="text" required class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                            <div>
                                <label for="ubah-tanggal" class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
                                <input id="ubah-tanggal" v-model="editForm.date" type="date" required class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                        </div>
                        <div>
                            <label for="ubah-perihal" class="block text-sm font-medium text-slate-700 mb-1">Perihal</label>
                            <textarea id="ubah-perihal" v-model="editForm.subject" rows="3" required maxlength="255" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                        </div>
                        <div>
                            <label for="ubah-instansi" class="block text-sm font-medium text-slate-700 mb-1">Asal / instansi</label>
                            <input id="ubah-instansi" v-model="editForm.issuer" type="text" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="ubah-kategori" class="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
                                <select id="ubah-kategori" v-model="editForm.category_id" required class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="ubah-sifat" class="block text-sm font-medium text-slate-700 mb-1">Sifat</label>
                                <select id="ubah-sifat" v-model="editForm.security_level" required class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="biasa">Biasa</option>
                                    <option value="rahasia">Rahasia</option>
                                    <option value="kilat">Kilat</option>
                                </select>
                            </div>
                        </div>
                        <div class="pt-3 flex justify-end gap-2">
                            <button type="button" @click="isEditing = false" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-600">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                                {{ editForm.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <AuditTrailModal :show="showAuditModal" :target="auditTarget" title="Jejak Audit Arsip Surat" @close="showAuditModal = false" />
    </AuthenticatedLayout>
</template>
