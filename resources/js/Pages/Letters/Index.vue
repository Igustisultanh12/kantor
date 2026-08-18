<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

const editForm = useForm({
    id: null,
    letter_number: '',
    subject: '',
    category_id: '',
    security_level: '',
    issuer: '',
    date: '',
});

// LOGIKA PENGELOMPOKAN BERLAPIS
const groupedLetters = computed(() => {
    const filtered = props.letters.data.filter(letter => {
        return (
            (letter.subject?.toLowerCase().includes(search.value.toLowerCase())) ||
            (letter.letter_number?.toLowerCase().includes(search.value.toLowerCase())) ||
            (letter.issuer?.toLowerCase().includes(search.value.toLowerCase()))
        );
    });

    return filtered.reduce((acc, letter) => {
        const level = letter.security_level?.toLowerCase() || 'biasa';
        if (!acc[level]) acc[level] = {};
        const catName = letter.category?.name || 'UMUM';
        if (!acc[level][catName]) acc[level][catName] = [];
        acc[level][catName].push(letter);
        return acc;
    }, {});
});

const openPdf = (filePath) => {
    selectedPdfUrl.value = `/storage/${filePath}?t=${new Date().getTime()}`;
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
            alert('Arsip berhasil diperbarui');
        },
        onError: (err) => alert('Gagal menyimpan: ' + Object.values(err)[0])
    });
};

const deleteLetter = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus arsip ini?')) {
        router.delete(route('letters.destroy', id), { preserveScroll: true });
    }
};

const levelConfig = (level) => {
    const s = level.toLowerCase();
    if (s === 'rahasia') return { text: 'text-red-600', bg: 'bg-red-50', border: 'border-red-800' };
    if (s === 'kilat') return { text: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-800' };
    return { text: 'text-indigo-700', bg: 'bg-indigo-50', border: 'border-gray-800' };
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

            <!-- Search Card -->
            <div class="bg-white p-4 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="relative w-full sm:w-80">
                    <input v-model="search" type="text" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 pl-10 focus:ring-blue-500 focus:border-blue-600 placeholder:text-slate-400" placeholder="Nomor atau perihal..." />
                    <span class="absolute left-3.5 top-3 text-sm text-slate-400"></span>
                </div>
                <div class="text-xs font-extrabold text-slate-500 uppercase tracking-wider bg-slate-50 px-4 py-2 rounded-xl"> Total: <span class="text-blue-600 font-black">{{ letters.total || 0 }}</span> Berkas
                </div>
            </div>

        <div class="space-y-8 pb-24">
            <div v-for="(categoriesInLevel, level) in groupedLetters" :key="level">
                
                <div class="bg-slate-900 text-white px-4 py-2 rounded-2xl inline-block mb-3 shadow-xs">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider">Sifat: {{ level }}</span>
                </div>

                <div v-for="(items, catName) in categoriesInLevel" :key="catName" class="mb-6 last:mb-0 bg-white rounded-3xl border border-[#E2E8F0] overflow-hidden shadow-xs">
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-100 flex justify-between items-center">
                        <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Kategori: {{ catName }}</span>
                        <span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-3 py-1 rounded-full uppercase">{{ items.length }} Berkas</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead class="bg-slate-50/50 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-center w-12">NO</th>
                                    <th class="px-4 py-3 text-left">Identitas & Perihal Surat</th>
                                    <th class="px-4 py-3 text-left w-48">Asal / Instansi</th>
                                    <th class="px-4 py-3 text-center w-32">Tanggal</th>
                                    <th class="px-4 py-3 text-center w-32">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-semibold">
                                <tr v-for="(letter, index) in items" :key="letter.id" class="hover:bg-slate-50/80 transition group">
                                    <td class="px-4 py-3.5 text-center font-mono text-xs font-bold text-slate-400">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="border border-gray-800 px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-mono text-sm font-black text-indigo-700 leading-none mb-1">{{ letter.letter_number }}</span>
                                            <span class="text-[11px] font-bold text-gray-800 leading-tight uppercase italic group-hover:not-italic">"{{ letter.subject }}"</span>
                                        </div>
                                    </td>
                                    <td class="border border-gray-800 px-4 py-3 text-[10px] font-black uppercase text-gray-600">
                                        {{ letter.issuer || 'INTERNAL' }}
                                    </td>
                                    <td class="border border-gray-800 px-4 py-3 text-center font-mono text-xs font-bold text-gray-700">
                                        {{ new Date(letter.date).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                                    </td>
                                    <td class="border border-gray-800 px-4 py-3 text-center">
                                        <div class="flex justify-center gap-3">
                                            <button @click="openPdf(letter.file_path)" class="text-gray-400 hover:text-indigo-600" title="PDF">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                            <button @click="openEdit(letter)" class="text-gray-400 hover:text-amber-600" title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button @click="deleteLetter(letter.id)" class="text-gray-400 hover:text-red-600" title="Hapus">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
    </AuthenticatedLayout>
</template>