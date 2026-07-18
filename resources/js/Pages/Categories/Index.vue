<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    categories: Array
});

// Perbaikan Form: Menambahkan field start_number
const form = useForm({
    name: '',
    code: '',
    start_number: 1, // Default mulai dari nomor 1
    category_type: 'umum' 
});

const subCategoryInputs = ref({});

const submitCategory = () => {
    const data = {
        ...form.data(),
        is_telegram: form.category_type === 'telegram'
    };

    router.post(route('categories.store'), data, {
        onSuccess: () => form.reset(),
    });
};

const submitSub = (categoryId) => {
    const name = subCategoryInputs.value[categoryId];
    if (!name) return;

    router.post(route('categories.storeSub', categoryId), { name }, {
        onSuccess: () => {
            subCategoryInputs.value[categoryId] = '';
        },
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Manajemen Kategori" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-xl text-gray-800 leading-tight">Manajemen Kategori & Penomoran</h2>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 sticky top-24">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="h-10 w-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="font-black text-indigo-900 uppercase text-xs tracking-widest">Kategori Baru</h3>
                    </div>
                    
                    <form @submit.prevent="submitCategory" class="space-y-4">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-1">Tipe Kategori</label>
                            <select v-model="form.category_type" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 focus:ring-indigo-500 text-sm font-semibold h-12">
                                <option value="umum">KATEGORI UMUM (SURAT)</option>
                                <option value="telegram">KATEGORI TELEGRAM</option>
                                <!--<option value="SURAT R">SURAT R (RAHASIA)</option>-->
                                <!--<option value="SPRIN">SPRIN (SURAT PERINTAH)</option>-->
                            </select>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-1">Nama Kategori</label>
                            <input v-model="form.name" type="text" placeholder="MISAL: SURAT KEPUTUSAN" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 focus:ring-indigo-500 uppercase text-sm font-bold h-12">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-1">Kode</label>
                                <input v-model="form.code" type="text" placeholder="SKHPP" class="w-full mt-1 rounded-2xl border-gray-100 bg-gray-50 focus:ring-indigo-500 uppercase text-sm font-black h-12">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest px-1">Mulai Nomor</label>
                                <input v-model="form.start_number" type="number" min="1" class="w-full mt-1 rounded-2xl border-gray-100 bg-indigo-50 text-indigo-700 focus:ring-indigo-500 text-sm font-black h-12">
                            </div>
                        </div>

                        <button :disabled="form.processing" 
                                :class="form.category_type === 'telegram' ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-100' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-100'"
                                class="w-full text-white py-4 rounded-2xl font-black uppercase text-[10px] tracking-[0.2em] transition shadow-xl disabled:opacity-50 active:scale-95">
                            Simpan {{ form.category_type === 'telegram' ? 'Telegram' : 'Kategori' }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-6">
                <div v-for="cat in categories" :key="cat.id" class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-all duration-300">
                    <div class="p-6 flex justify-between items-center bg-gray-50/30">
                        <div class="flex items-center gap-4">
                            <div :class="cat.is_telegram ? 'bg-amber-500' : 'bg-indigo-600'" class="h-12 w-12 rounded-2xl flex flex-col items-center justify-center text-white shadow-lg shadow-indigo-50">
                                <span class="text-[8px] font-black leading-none mb-0.5 opacity-70">START</span>
                                <span class="text-sm font-black leading-none">{{ cat.start_number }}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span :class="cat.is_telegram ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'" class="px-2 py-0.5 text-[8px] font-black rounded uppercase">
                                        {{ cat.is_telegram ? 'TELEGRAM' : 'UMUM' }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-bold font-mono tracking-tighter">{{ cat.code }}</span>
                                </div>
                                <h4 class="font-black text-gray-800 uppercase text-sm tracking-tight">{{ cat.name }}</h4>
                            </div>
                        </div>
                        <button @click="router.delete(route('categories.destroy', cat.id))" class="h-10 w-10 flex items-center justify-center rounded-xl text-gray-300 hover:bg-red-50 hover:text-red-500 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>

                    <div class="p-6 border-t border-gray-50 bg-white">
                        <template v-if="!cat.is_telegram">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                                <div v-for="sub in cat.sub_categories" :key="sub.id" class="flex justify-between items-center bg-gray-50/50 border border-gray-100 px-4 py-3 rounded-2xl text-xs group/sub">
                                    <span class="text-gray-600 font-bold uppercase tracking-tight">{{ sub.name }}</span>
                                    <button @click="router.delete(route('sub-categories.destroy', sub.id))" class="opacity-0 group-hover/sub:opacity-100 text-red-400 hover:text-red-600 transition font-black text-[8px] tracking-widest">HAPUS</button>
                                </div>
                            </div>
                            <form @submit.prevent="submitSub(cat.id)" class="flex gap-2 p-1 bg-gray-50 rounded-2xl border border-gray-100 focus-within:border-indigo-200 transition">
                                <input v-model="subCategoryInputs[cat.id]" type="text" placeholder="TAMBAH JENIS SPESIFIK..." class="flex-1 text-[10px] font-bold border-none bg-transparent focus:ring-0 placeholder:text-gray-300">
                                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-[9px] font-black tracking-widest hover:bg-indigo-700 transition shadow-md">TAMBAH</button>
                            </form>
                        </template>
                        <div v-else class="text-center py-4 bg-amber-50/30 rounded-2xl border border-dashed border-amber-100 text-amber-600 text-[10px] font-bold italic tracking-widest uppercase">
                            Mode Telegram: Tanpa Sub-Jenis
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>