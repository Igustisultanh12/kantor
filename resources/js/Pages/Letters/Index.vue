<script setup>
import { ask, notify } from '@/Utils/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AuditTrailModal from '@/Components/AuditTrailModal.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    letters: {
        type: Object,
        default: () => ({ data: [], total: 0, links: [] })
    },
    categories: Array
});

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

// Kelompok keperluan: Nikah, Dik (pendidikan/seleksi), atau Lainnya.
// Diambil dari Jenis Surat bila ada, selain itu dari perihal.
const KELOMPOK = [
    { key: 'nikah', nama: 'Nikah', pola: /nikah|perkawinan/i },
    { key: 'dik', nama: 'Dik', pola: /\bdik(?:tuk|ma|reg|lat|bang|spes|jur|pa|ba)\w*|\bdik\b|pendidikan|seleksi|sesko|kursus|beasiswa/i },
    { key: 'lainnya', nama: 'Lainnya', pola: null },
];
const kelompokSurat = (letter) => {
    const teks = `${letter.sub_category?.name || ''} ${letter.subject || ''}`;
    return (KELOMPOK.find((k) => k.pola && k.pola.test(teks)) || KELOMPOK[2]).key;
};

// Perihal SKHPP otomatis berpola "SKHPP - NAMA (KATEGORI) - peruntukan": pisahkan nama & perihalnya.
const uraiPerihal = (subject) => {
    const s = String(subject || '').trim();
    const m = s.match(/^SKHPP\s*-\s*(.+?)\s*\(([^)]+)\)\s*-\s*(.*)$/i);
    if (m) return { nama: m[1], keterangan: m[3] };
    const nikah = s.match(/^NIKAH\s*:\s*(.*)$/i);
    if (nikah) return { nama: null, keterangan: nikah[1] };
    return { nama: null, keterangan: s };
};
const kapitalAwal = (t) => (t ? t.charAt(0).toUpperCase() + t.slice(1) : '');
const namaRapi = (t) => String(t || '').toLowerCase().replace(/(^|[\s.'-])\S/g, (c) => c.toUpperCase());

const filterKelompok = ref('semua');

const cocokCari = (letter) => {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    return [letter.subject, letter.letter_number, letter.issuer].some((v) => v?.toLowerCase().includes(q));
};

const hasilCari = computed(() => props.letters.data.filter(cocokCari));

const jumlahKelompok = computed(() => {
    const n = { semua: hasilCari.value.length, nikah: 0, dik: 0, lainnya: 0 };
    hasilCari.value.forEach((l) => { n[kelompokSurat(l)]++; });
    return n;
});

// Kategori -> kelompok -> daftar surat (urut tanggal terbaru).
const groupedLetters = computed(() => {
    const out = {};
    hasilCari.value
        .filter((l) => filterKelompok.value === 'semua' || kelompokSurat(l) === filterKelompok.value)
        .forEach((l) => {
            const cat = l.category?.name || 'Umum';
            const k = kelompokSurat(l);
            (out[cat] ??= { total: 0, kelompok: { nikah: [], dik: [], lainnya: [] } });
            out[cat].kelompok[k].push(l);
            out[cat].total++;
        });
    Object.values(out).forEach((c) => Object.values(c.kelompok).forEach((list) =>
        list.sort((a, b) => String(b.date).localeCompare(String(a.date)))));
    return out;
});

const formatTanggal = (d) => d
    ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    : '-';

const openPdf = (letter) => {
    // Arsip disajikan lewat rute berotorisasi, bukan URL /storage publik (audit S04).
    selectedPdfUrl.value = route('letters.file', letter.id) + `?t=${new Date().getTime()}`;
    isShowingPdf.value = true;
};

const openEdit = (letter) => {
    editForm.id = letter.id;
    editForm.letter_number = letter.letter_number;
    editForm.subject = letter.subject;
    editForm.category_id = letter.category_id;
    editForm.security_level = letter.security_level;
    editForm.issuer = letter.issuer;
    editForm.date = letter.date;
    isEditing.value = true;
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

const warnaKelompok = {
    nikah: 'bg-pink-500',
    dik: 'bg-sky-500',
    lainnya: 'bg-slate-400',
};
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
                
                <Link :href="route('letters.create')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl text-xs font-extrabold uppercase shadow-md shadow-blue-500/20 transition tracking-wider flex items-center gap-2">
                    + Arsip Baru
                </Link>
            </div>

            <!-- Cari & saring kelompok -->
            <div class="bg-white p-4 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="relative w-full lg:w-80">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <label for="cari-arsip" class="sr-only">Cari arsip</label>
                    <input id="cari-arsip" v-model="search" type="search" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-sm py-2.5 pl-10 focus:ring-emerald-500 focus:border-emerald-500 placeholder:text-slate-400" placeholder="Cari nomor, nama, atau perihal" />
                </div>

                <div role="tablist" aria-label="Kelompok keperluan" class="flex flex-wrap gap-1.5 lg:ml-auto">
                    <button v-for="t in [{ key: 'semua', nama: 'Semua' }, ...KELOMPOK]" :key="t.key" type="button" role="tab"
                        :aria-selected="filterKelompok === t.key" @click="filterKelompok = t.key"
                        :class="filterKelompok === t.key ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                        <span v-if="t.key !== 'semua'" :class="warnaKelompok[t.key]" class="w-2 h-2 rounded-full" aria-hidden="true"></span>
                        {{ t.nama }}
                        <span class="tabular-nums text-xs" :class="filterKelompok === t.key ? 'text-slate-300' : 'text-slate-400'">{{ jumlahKelompok[t.key] }}</span>
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

                <template v-for="k in KELOMPOK" :key="k.key">
                    <div v-if="cat.kelompok[k.key].length" class="border-t border-slate-100">
                        <div class="flex items-center gap-2 px-6 py-2.5 bg-slate-50/70">
                            <span :class="warnaKelompok[k.key]" class="w-2 h-2 rounded-full" aria-hidden="true"></span>
                            <h4 class="text-sm font-semibold text-slate-700">{{ k.nama }}</h4>
                            <span class="text-xs text-slate-400 tabular-nums">{{ cat.kelompok[k.key].length }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <caption class="sr-only">{{ catName }}, kelompok {{ k.nama }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium text-slate-500">
                                        <th scope="col" class="pl-6 pr-3 py-2 w-12 font-medium">No</th>
                                        <th scope="col" class="px-3 py-2 w-56 font-medium">Nomor surat</th>
                                        <th scope="col" class="px-3 py-2 font-medium">Perihal</th>
                                        <th scope="col" class="px-3 py-2 w-32 font-medium">Tanggal</th>
                                        <th scope="col" class="pl-3 pr-6 py-2 w-44 font-medium text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(letter, index) in cat.kelompok[k.key]" :key="letter.id" class="align-top hover:bg-slate-50/60">
                                        <td class="pl-6 pr-3 py-3.5 text-slate-400 tabular-nums">{{ index + 1 }}</td>
                                        <td class="px-3 py-3.5">
                                            <span class="block font-semibold text-slate-900 tabular-nums whitespace-nowrap">{{ letter.letter_number }}</span>
                                            <span :class="sifat(letter.security_level).kelas" class="mt-1.5 inline-block rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset">{{ sifat(letter.security_level).nama }}</span>
                                        </td>
                                        <td class="px-3 py-3.5 max-w-xl">
                                            <span v-if="uraiPerihal(letter.subject).nama" class="block font-semibold text-slate-900">{{ namaRapi(uraiPerihal(letter.subject).nama) }}</span>
                                            <span class="block text-slate-600 leading-relaxed">{{ kapitalAwal(uraiPerihal(letter.subject).keterangan) }}</span>
                                            <span v-if="letter.issuer && !/^internal/i.test(letter.issuer)" class="block mt-1 text-xs text-slate-500">Dari {{ letter.issuer }}</span>
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
            <div v-if="isShowingPdf" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="isShowingPdf = false"></div>
                <div class="relative w-full max-w-6xl bg-white border-4 border-gray-900 flex flex-col h-[90vh]">
                    <div class="px-6 py-2 border-b-2 border-gray-900 flex justify-between items-center bg-gray-50">
                        <span class="text-[10px] font-black uppercase text-indigo-700">Preview Dokumen</span>
                        <button @click="isShowingPdf = false" class="font-black text-gray-900 uppercase text-[10px]">[ Tutup X ]</button>
                    </div>
                    <iframe :src="selectedPdfUrl" class="flex-1 w-full border-none bg-gray-200"></iframe>
                </div>
            </div>

            <div v-if="isEditing" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="isEditing = false"></div>
                <div class="relative w-full max-w-xl bg-white border-4 border-gray-900 p-8 shadow-2xl animate-in zoom-in duration-300">
                    <h3 class="font-black text-xs uppercase tracking-widest text-gray-900 mb-8 border-b-2 border-gray-900 pb-2"> Pembaruan Data</h3>
                    <form @submit.prevent="updateLetter" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-gray-500 uppercase px-1">Nomor Surat</label>
                                <input v-model="editForm.letter_number" type="text" required class="w-full border-2 border-gray-800 bg-gray-50 h-11 text-xs font-bold" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-gray-500 uppercase px-1">Tanggal</label>
                                <input v-model="editForm.date" type="date" required class="w-full border-2 border-gray-800 bg-gray-50 h-11 text-xs font-bold" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-black text-gray-500 uppercase px-1">Perihal</label>
                            <input v-model="editForm.subject" type="text" required class="w-full border-2 border-gray-800 bg-gray-50 h-11 text-xs font-bold" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[9px] font-black text-gray-500 uppercase px-1">Instansi</label>
                            <input v-model="editForm.issuer" type="text" class="w-full border-2 border-gray-800 bg-gray-50 h-11 text-[11px] font-black uppercase" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-gray-500 uppercase px-1">Kategori</label>
                                <select v-model="editForm.category_id" required class="w-full border-2 border-gray-800 bg-gray-50 h-11 text-[11px] font-bold">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-gray-500 uppercase px-1">Sifat</label>
                                <select v-model="editForm.security_level" required class="w-full border-2 border-gray-800 bg-gray-50 h-11 text-[11px] font-bold uppercase">
                                    <option value="biasa">Biasa</option>
                                    <option value="rahasia">Rahasia</option>
                                    <option value="kilat">Kilat</option>
                                </select>
                            </div>
                        </div>
                        <div class="pt-6 flex justify-end gap-3">
                            <button type="button" @click="isEditing = false" class="px-6 py-2 text-[10px] font-black uppercase text-gray-400 hover:text-gray-900 transition-colors">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="bg-gray-900 text-white px-8 py-3 font-black text-[10px] uppercase shadow-[4px_4px_0px_0px_rgba(0,0,0,0.2)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all disabled:opacity-50">
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <AuditTrailModal :show="showAuditModal" :target="auditTarget" title="Jejak Audit Arsip Surat" @close="showAuditModal = false" />
    </AuthenticatedLayout>
</template>
