<script setup> import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import Swal from 'sweetalert2';
import interact from 'interactjs';

const props = defineProps({
    settings: Object
});

const page = usePage();
const currentStamp = computed(() => props.settings?.commander_stamp || 'settings/default_stamp.png');

// State untuk pengujian Drag-and-Drop Stempel
const stampPos = ref({ x: 50, y: 50 });
const stampSize = ref({ width: 120, height: 120 });
const isTesting = ref(false);

const form = useForm({
    stamp_file: null,
    _method: 'POST'
});

const handleFileUpload = (e) => {
    form.stamp_file = e.target.files[0];
};

const uploadStamp = () => {
    form.post(route('settings.update-stamp'), {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Stempel Komandan telah diperbarui!', 'success');
            form.reset();
        }
    });
};

const startTest = () => {
    isTesting.value = true;
    nextTick(() => {
        interact('.drag-stamp-test').draggable({
            inertia: false,
            modifiers: [interact.modifiers.restrictRect({ restriction: 'parent', endOnly: true })],
            listeners: {
                move(event) {
                    stampPos.value.x += event.dx;
                    stampPos.value.y += event.dy;
                }
            }
        }).resizable({
            edges: { right: true, bottom: true },
            listeners: {
                move(event) {
                    stampSize.value.width = event.rect.width;
                    stampSize.value.height = event.rect.height;
                    stampPos.value.x += event.deltaRect.left;
                    stampPos.value.y += event.deltaRect.top;
                }
            }
        });
    });
};
</script>

<template>
    <Head title="Manajemen Stempel Digital" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-xl text-indigo-950 uppercase italic tracking-tighter">Otoritas Stempel Digital</h2>
        </template>

        <div class="py-12 px-4">
            <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                    <h3 class="font-black text-indigo-900 uppercase text-xs tracking-widest mb-6 italic">Unggah Stempel Baru (PNG)</h3>
                    
                    <div class="mb-8 flex justify-center">
                        <div class="w-48 h-48 bg-slate-50 rounded-full border-4 border-dashed border-indigo-100 flex items-center justify-center overflow-hidden">
                            <img :src="'/storage/' + currentStamp" class="max-w-full max-h-full object-contain p-4 opacity-80" alt="Stempel Saat Ini">
                        </div>
                    </div>

                    <form @submit.prevent="uploadStamp" class="space-y-4">
                        <div class="relative group w-full h-16 border-2 border-indigo-50 rounded-2xl bg-indigo-50/20 flex items-center px-6 cursor-pointer overflow-hidden transition-all hover:border-indigo-500">
                            <input type="file" @change="handleFileUpload" accept="image/png" class="absolute inset-0 opacity-0 z-10 cursor-pointer" />
                            <div class="flex flex-col truncate w-full items-center">
                                <span class="text-[10px] font-black text-indigo-950 uppercase italic">{{ form.stamp_file ? 'FILE TERPILIH' : 'PILIH FILE STEMPEL PNG' }}</span>
                                <span class="text-[8px] text-slate-400 font-bold truncate mt-1 italic">{{ form.stamp_file?.name }}</span>
                            </div>
                        </div>
                        <button type="submit" :disabled="form.processing || !form.stamp_file" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-lg active:scale-95 disabled:opacity-50"> GANTI STEMPEL SEKARANG
                        </button>
                    </form>
                </div>

                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                    <h3 class="font-black text-indigo-900 uppercase text-xs tracking-widest mb-6 italic">Simulasi Penempatan Stempel</h3>
                    
                    <div class="w-full h-80 bg-slate-100 rounded-3xl relative overflow-hidden border-4 border-white shadow-inner" id="test-canvas">
                        <div v-if="!isTesting" class="absolute inset-0 flex items-center justify-center p-10 text-center">
                            <p class="text-[10px] font-bold text-slate-400 uppercase leading-relaxed tracking-widest">Klik tombol di bawah untuk mencoba pergerakan stempel pada dokumen</p>
                        </div>

                        <div v-show="isTesting"class="drag-stamp-test absolute z-[100] cursor-move border-2 border-emerald-500 bg-emerald-50/30 backdrop-blur-[1px] shadow-2xl flex items-center justify-center touch-none"
                             :style="{ left: stampPos.x + 'px', top: stampPos.y + 'px', width: stampSize.width + 'px', height: stampSize.height + 'px' }">
                            <img :src="'/storage/' + currentStamp" class="w-full h-full object-contain pointer-events-none opacity-80" alt="Test Stempel" />
                            <div class="absolute -bottom-2 -right-2 w-6 h-6 bg-emerald-600 rounded-full border-2 border-white shadow-lg flex items-center justify-center">
                                <div class="w-1 h-1 bg-white rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    <button @click="startTest" v-if="!isTesting" class="mt-6 px-8 py-3 bg-emerald-50 text-emerald-600 rounded-xl font-black text-[10px] uppercase shadow-sm hover:bg-emerald-600 hover:text-white transition-all"> UJI COBA DRAG STEMPEL
                    </button>
                    <p v-else class="mt-4 text-[9px] font-black text-emerald-600 uppercase italic">Radar Aktif: Gerakkan stempel untuk simulasi penempatan.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.drag-stamp-test { touch-action: none; user-select: none; }
#test-canvas { background-image: radial-gradient(#d1d5db 1px, transparent 1px); background-size: 20px 20px; }
</style>