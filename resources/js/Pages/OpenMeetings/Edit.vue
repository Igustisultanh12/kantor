<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    meeting: Object,
    commander: Object,
});

const form = useForm({
    title: props.meeting.title || '',
    date: props.meeting.date || '',
    time_start: props.meeting.time_start || '',
    time_end: props.meeting.time_end || '',
    location: props.meeting.location || '',
    description: props.meeting.description || '',
    status: props.meeting.status || 'open',
    require_signature: Boolean(props.meeting.require_signature),
    custom_fields: props.meeting.custom_fields || [],
});

const addCustomField = () => {
    form.custom_fields.push({
        id: 'field_' + Date.now(),
        name: 'kolom_' + (form.custom_fields.length + 1),
        label: 'Kolom Baru ' + (form.custom_fields.length + 1),
        type: 'text',
        required: false,
        placeholder: '',
    });
};

const removeCustomField = (index) => {
    form.custom_fields.splice(index, 1);
};

const submit = () => {
    if (!form.title.trim()) {
        Swal.fire('Peringatan', 'Judul rapat wajib diisi.', 'warning');
        return;
    }

    form.put(route('rapat-terbuka.update', props.meeting.id), {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Pengaturan sesi rapat berhasil diperbarui.', 'success');
        },
        onError: (err) => {
            const firstErr = Object.values(err)[0];
            Swal.fire('Gagal Menyimpan', firstErr || 'Periksa kembali formulir.', 'error');
        }
    });
};
</script>

<template>
    <Head :title="'Edit - ' + meeting.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('rapat-terbuka.show', meeting.id)"
                    class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-black text-slate-800 tracking-tight">Edit Sesi Rapat Terbuka</h2>
                    <p class="text-xs text-slate-500 font-medium">Ubah agenda, jadwal waktu, status buka/tutup, atau kolom kustom.</p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <form @submit.prevent="submit" class="space-y-6">

                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-5">
                    <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2 pb-3 border-b border-slate-100">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Informasi Rapat</span>
                    </h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Nama / Judul Agenda Rapat <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="form.title"
                            class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-3 outline-none"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Tanggal Pelaksanaan <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="date"
                                v-model="form.date"
                                class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Waktu Mulai
                            </label>
                            <input
                                type="time"
                                v-model="form.time_start"
                                class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Waktu Selesai
                            </label>
                            <input
                                type="time"
                                v-model="form.time_end"
                                class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Lokasi / Tempat Rapat
                            </label>
                            <input
                                type="text"
                                v-model="form.location"
                                class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                Status Absensi
                            </label>
                            <select
                                v-model="form.status"
                                class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none font-bold"
                            >
                                <option value="open">DIBUKA (Aktif)</option>
                                <option value="closed">DITUTUP (Selesai)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Keterangan / Deskripsi Agenda
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                        ></textarea>
                    </div>
                </div>

                <!-- KOLOM KUSTOM DINAMIS -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">
                                Kolom Formulir Kustom
                            </h3>
                            <p class="text-xs text-slate-400">Kolom 1 s.d. 3 adalah Nama, Pangkat / NRP, dan Jabatan.</p>
                        </div>

                        <button
                            type="button"
                            @click="addCustomField"
                            class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-bold transition"
                        >
                            + Tambah Kolom
                        </button>
                    </div>

                    <div v-if="form.custom_fields.length > 0" class="space-y-3">
                        <div
                            v-for="(field, idx) in form.custom_fields"
                            :key="field.id"
                            class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex flex-col sm:flex-row items-center gap-3"
                        >
                            <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 text-slate-500 text-xs font-bold flex items-center justify-center shrink-0">
                                {{ idx + 4 }}
                            </span>

                            <div class="flex-1 w-full sm:w-auto">
                                <input
                                    type="text"
                                    v-model="field.label"
                                    placeholder="Nama Kolom (Label)"
                                    class="w-full text-xs rounded-lg border border-slate-200 bg-white p-2 font-bold text-slate-800 outline-none"
                                />
                            </div>

                            <div class="w-full sm:w-44">
                                <select
                                    v-model="field.type"
                                    class="w-full text-xs rounded-lg border border-slate-200 bg-white p-2 text-slate-700 outline-none font-medium"
                                >
                                    <option value="text">Teks Singkat</option>
                                    <option value="number">Nomor / Angka</option>
                                    <option value="tel">Nomor HP / WhatsApp</option>
                                    <option value="email">Alamat Email</option>
                                    <option value="textarea">Teks Panjang (Catatan)</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-3 self-end sm:self-center shrink-0">
                                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                                    <input type="checkbox" v-model="field.required" class="rounded border-slate-300 text-blue-600" />
                                    <span>Wajib</span>
                                </label>

                                <button
                                    type="button"
                                    @click="removeCustomField(idx)"
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-slate-400 italic py-2">
                        Tidak ada kolom kustom tambahan.
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-800">Wajibkan Tanda Tangan / Paraf Digital di Layar HP</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.require_signature" class="sr-only peer" />
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- SUBMIT BUTTONS -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link
                        :href="route('rapat-terbuka.show', meeting.id)"
                        class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition"
                    >
                        Batal
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm shadow-blue-500/25 transition disabled:opacity-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                    </button>
                </div>

            </form>
        </div>
    </AuthenticatedLayout>
</template>
