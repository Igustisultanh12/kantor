<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    commander: Object,
    creator: Object,
});

const todayDate = new Date().toISOString().split('T')[0];

const form = useForm({
    title: '',
    date: todayDate,
    time_start: '08:00',
    time_end: '12:00',
    location: 'Ruang Rapat Mako Detasemen Intelijen Kodaeral V',
    description: '',
    require_signature: false,
    custom_fields: [],
});

// Tambah Kolom Kustom Dinamis
const addCustomField = (preset = null) => {
    if (preset === 'instansi') {
        form.custom_fields.push({
            id: 'field_' + Date.now(),
            name: 'instansi',
            label: 'Asal Satuan / Instansi',
            type: 'text',
            required: true,
            placeholder: 'Contoh: Lantamal V / Kodam V / Instansi Luar',
        });
    } else if (preset === 'no_hp') {
        form.custom_fields.push({
            id: 'field_' + Date.now(),
            name: 'nomor_hp',
            label: 'Nomor WhatsApp / HP',
            type: 'tel',
            required: false,
            placeholder: 'Contoh: 081234567890',
        });
    } else if (preset === 'keterangan') {
        form.custom_fields.push({
            id: 'field_' + Date.now(),
            name: 'keterangan',
            label: 'Keterangan Tambahan',
            type: 'textarea',
            required: false,
            placeholder: 'Catatan atau keperluan hadir...',
        });
    } else {
        form.custom_fields.push({
            id: 'field_' + Date.now(),
            name: 'kolom_' + (form.custom_fields.length + 1),
            label: 'Kolom Baru ' + (form.custom_fields.length + 1),
            type: 'text',
            required: false,
            placeholder: '',
        });
    }
};

// Hapus Kolom Kustom
const removeCustomField = (index) => {
    form.custom_fields.splice(index, 1);
};

// Submit Form
const submit = () => {
    if (!form.title.trim()) {
        Swal.fire('Peringatan', 'Judul / Agenda Rapat wajib diisi.', 'warning');
        return;
    }

    form.post(route('rapat-terbuka.store'), {
        onError: (err) => {
            const firstErr = Object.values(err)[0];
            Swal.fire('Gagal Menyimpan', firstErr || 'Periksa kembali formulir yang Anda isi.', 'error');
        }
    });
};
</script>

<template>
    <Head title="Buat Sesi Rapat Terbuka - SINDEN" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('rapat-terbuka.index')"
                    class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </Link>
                <div>
                    <h2 class="text-xl font-black text-slate-800 tracking-tight">Buat Sesi Absensi Rapat Terbuka</h2>
                    <p class="text-xs text-slate-500 font-medium">Formulir publik akan otomatis memiliki QR Code siap scan untuk seluruh peserta.</p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            
            <form @submit.prevent="submit" class="space-y-6">

                <!-- INFORMASI UTAMA RAPAT -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-5">
                    <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2 pb-3 border-b border-slate-100">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Informasi Agenda Rapat</span>
                    </h3>

                    <!-- Judul Rapat -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Nama / Judul Agenda Rapat <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="form.title"
                            placeholder="Contoh: Rapat Koordinasi Intelijen Maritim Wilayah Kerja V"
                            class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-3 outline-none"
                            required
                        />
                        <div class="text-[11px] text-slate-400 mt-1">Judul akan tercetak resmi di kop surat dokumen daftar hadir dinas.</div>
                    </div>

                    <!-- Tanggal & Jam -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
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

                    <!-- Lokasi / Tempat -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tempat / Lokasi Rapat
                        </label>
                        <input
                            type="text"
                            v-model="form.location"
                            placeholder="Contoh: Ruang Rapat Mako Detasemen Intelijen Kodaeral V"
                            class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                        />
                    </div>

                    <!-- Deskripsi / Catatan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Keterangan / Deskripsi Agenda
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Catatan rapat atau instruksi pakaian dinas peserta..."
                            class="w-full text-xs rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 p-2.5 outline-none"
                        ></textarea>
                    </div>
                </div>

                <!-- PENGATURAN KOLOM FORMULIR ABSENSI -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                <span>Kolom Formulir Presensi</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">Kolom bawaan dinas & opsi menambah kolom isian kustom.</p>
                        </div>

                        <!-- Tombol Tambah Cepat -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[11px] text-slate-400 font-medium mr-1">Tambah Cepat:</span>
                            <button
                                type="button"
                                @click="addCustomField('instansi')"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold transition"
                            >
                                + Asal Instansi
                            </button>
                            <button
                                type="button"
                                @click="addCustomField('no_hp')"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold transition"
                            >
                                + No WhatsApp
                            </button>
                            <button
                                type="button"
                                @click="addCustomField('keterangan')"
                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold transition"
                            >
                                + Catatan
                            </button>
                            <button
                                type="button"
                                @click="addCustomField()"
                                class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[11px] font-bold transition"
                            >
                                + Kolom Kustom
                            </button>
                        </div>
                    </div>

                    <!-- Kolom Default Dinas (Locked) -->
                    <div class="space-y-2">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kolom Utama (Format Baku TNI):</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="text-xs font-bold text-slate-700">1. Nama Lengkap</span>
                                </div>
                                <span class="text-[10px] font-extrabold uppercase px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded">Wajib</span>
                            </div>

                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="text-xs font-bold text-slate-700">2. Pangkat / NRP</span>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400">Baku Dinas</span>
                            </div>

                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span class="text-xs font-bold text-slate-700">3. Jabatan / Satuan</span>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400">Baku Dinas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kustom Tambahan -->
                    <div v-if="form.custom_fields.length > 0" class="space-y-3 pt-3 border-t border-slate-100">
                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kolom Tambahan Dinamis:</div>
                        
                        <div
                            v-for="(field, idx) in form.custom_fields"
                            :key="field.id"
                            class="p-3.5 bg-slate-50/70 border border-slate-200 rounded-xl flex flex-col sm:flex-row items-center gap-3"
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

                    <!-- Switch Tanda Tangan Digital Peserta -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-800">Wajibkan Tanda Tangan / Paraf Digital di Layar HP</div>
                            <div class="text-[11px] text-slate-400">Peserta dapat menandatangani / memaraf langsung di layar ponsel mereka saat mengisi presensi.</div>
                        </div>

                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" v-model="form.require_signature" class="sr-only peer" />
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                <!-- INFO PENANDATANGAN KEDINASAN -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-xs space-y-4">
                    <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2 pb-3 border-b border-slate-100">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        <span>Atribusi Penandatangan & Petugas Pembuat Dokumen</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Petugas Pembuat -->
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                            <div class="text-[10.5px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pembuat Daftar (Petugas):</div>
                            <div class="text-xs font-bold text-slate-800">{{ creator.name }}</div>
                            <div class="text-[11px] text-slate-500">{{ creator.pangkat_nrp || '-' }}</div>
                            <div class="text-[10px] text-slate-400 mt-1">{{ creator.jabatan || 'Operator SINDEN' }}</div>
                        </div>

                        <!-- Komandan -->
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                            <div class="text-[10.5px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pejabat Penandatangan Resmi:</div>
                            <div class="text-xs font-bold text-slate-800">{{ commander.name }}</div>
                            <div class="text-[11px] text-slate-500">{{ commander.pangkat_nrp }}</div>
                            <div class="text-[10px] text-blue-600 font-bold mt-1">Komandan Detasemen Intelijen Kodaeral V</div>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT BUTTONS -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link
                        :href="route('rapat-terbuka.index')"
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
                        <span>{{ form.processing ? 'Membuat Sesi...' : 'Generate Form & QR Code Absensi' }}</span>
                    </button>
                </div>

            </form>

        </div>
    </AuthenticatedLayout>
</template>
