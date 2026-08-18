<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    users: Array,
    personnels: Array,
    birthday_message: String
});

const activeTab = ref('users');
const isTestModalOpen = ref(false); // Status Modal Tes
const testTarget = ref(null);       // Target terpilih untuk tes

// Form untuk Pengaturan Ucapan (Narasi Config)
const settingForm = useForm({
    birthday_message: props.birthday_message || ''
});

// Form untuk Tambah Personel Non-Akun
const personnelForm = useForm({
    pangkat: '',
    name: '',
    phone: '',
    birth_date: ''
});

/**
 * OPERASI 1: SIMPAN PENGATURAN PESAN
 */
const saveSettings = () => {
    settingForm.post(route('admin.settings.birthday'), {
        preserveScroll: true,
        onSuccess: () => Swal.fire('Berhasil', 'Template ucapan telah diperbarui.', 'success')
    });
};

/**
 * OPERASI KHUSUS: KIRIM TES UCAPAN
 */
const sendTest = () => {
    if (!testTarget.value) {
        Swal.fire('Peringatan', 'Pilih target personel terlebih dahulu!', 'warning');
        return;
    }

    router.post(route('admin.birthday.test'), {
        phone: testTarget.value.phone,
        name: testTarget.value.name,
        pangkat: testTarget.value.pangkat,
        message_template: settingForm.birthday_message
    }, {
        onSuccess: () => {
            isTestModalOpen.value = false;
            Swal.fire('Radiogram Terkirim', `Cek WhatsApp ${testTarget.value.name} untuk melihat hasil.`, 'success');
        }
    });
};

/**
 * OPERASI 2: UPDATE DATA USER BERAKUN
 */
const updateUser = (user) => {
    router.put(route('user.birthday.update', user.id), {
        pangkat: user.pangkat,
        birth_date: user.birth_date,
        phone: user.phone
    }, {
        preserveScroll: true,
        onSuccess: () => Swal.fire('Berhasil', `Data ${user.pangkat} ${user.name} diperbarui.`, 'success')
    });
};

/**
 * OPERASI 3: TAMBAH PERSONEL NON-AKUN
 */
const submitPersonnel = () => {
    personnelForm.post(route('personnel.store'), {
        onSuccess: () => {
            personnelForm.reset();
            Swal.fire('Berhasil', 'Personel baru masuk radar.', 'success');
        }
    });
};

/**
 * OPERASI 4: HAPUS PERSONEL NON-AKUN
 */
const deletePersonnel = (id) => {
    Swal.fire({
        title: 'Hapus Personel?',
        text: "Data akan dihapus dari daftar radar ulang tahun.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('personnel.destroy', id));
        }
    });
};
</script>

<template>
    <Head title="Radar HUT Personel" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-black text-indigo-950 uppercase tracking-[0.2em] text-sm"> Radar & Manajemen HUT Personel</h2>
        </template>

        <div class="py-6 space-y-6 text-left">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-indigo-50">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-8 w-8 bg-indigo-600 rounded-full flex items-center justify-center text-white text-xs"></div>
                    <h3 class="font-black text-xs uppercase tracking-widest text-slate-700">Konfigurasi Ucapan Otomatis</h3>
                </div>
                <div class="space-y-4">
                    <textarea 
                        v-model="settingForm.birthday_message"rows="4"class="w-full rounded-2xl border-gray-100 text-sm font-bold italic p-4 focus:ring-indigo-500"placeholder="Contoh: Selamat Ulang Tahun {name}, Jalesveva Jayamahe!"
                    ></textarea>
                    <div class="flex justify-between items-center">
                        <p class="text-[10px] text-gray-400 font-bold uppercase italic">* Gunakan <span class="text-indigo-600 font-black">{name}</span> untuk memanggil pangkat & nama secara otomatis.</p>
                        <div class="flex gap-2">
                            <button @click="isTestModalOpen = true" class="bg-amber-500 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase shadow-lg hover:bg-amber-600 transition"> Tes Ucapan
                            </button>
                            <button @click="saveSettings" class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase shadow-lg hover:bg-indigo-700 transition"> Simpan Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 p-1 bg-gray-100 rounded-2xl w-fit">
                <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Personel Akun</button>
                <button @click="activeTab = 'non-akun'" :class="activeTab === 'non-akun' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase transition-all">Personel Non-Akun</button>
            </div>

            <div v-if="activeTab === 'users'" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-800 text-white text-[10px] uppercase font-black tracking-widest">
                        <tr>
                            <th class="p-4">Pangkat</th>
                            <th class="p-4">Nama Personel</th>
                            <th class="p-4">Tanggal Lahir</th>
                            <th class="p-4">WhatsApp</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs font-bold uppercase italic">
                        <tr v-for="user in users" :key="user.id" class="border-b hover:bg-slate-50 transition">
                            <td class="p-4">
                                <input type="text" v-model="user.pangkat" class="border-gray-100 rounded-lg text-[10px] py-1 w-20" placeholder="Pangkat" />
                            </td>
                            <td class="p-4 text-indigo-900">{{ user.name }}</td>
                            <td class="p-4 text-gray-500">
                                <input type="date" v-model="user.birth_date" class="border-gray-100 rounded-lg text-[10px] py-1" />
                            </td>
                            <td class="p-4">
                                <input type="text" v-model="user.phone" class="border-gray-100 rounded-lg text-[10px] py-1 w-full" placeholder="628xxx" />
                            </td>
                            <td class="p-4 text-center">
                                <button @click="updateUser(user)" class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg hover:bg-emerald-600 hover:text-white transition">Update</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="space-y-6">
                <div class="bg-white p-6 rounded-3xl border-2 border-dashed border-gray-200">
                    <h3 class="text-[10px] font-black text-gray-400 uppercase mb-4 tracking-widest"> Daftarkan Personel Baru ke Radar</h3>
                    <form @submit.prevent="submitPersonnel" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <input v-model="personnelForm.pangkat" type="text" placeholder="Pangkat" class="rounded-xl border-gray-200 text-xs font-black uppercase italic" required />
                        <input v-model="personnelForm.name" type="text" placeholder="Nama Lengkap" class="rounded-xl border-gray-200 text-xs font-bold uppercase italic" required />
                        <input v-model="personnelForm.phone" type="text" placeholder="628xxxx" class="rounded-xl border-gray-200 text-xs font-bold uppercase italic" required />
                        <input v-model="personnelForm.birth_date" type="date" class="rounded-xl border-gray-200 text-xs font-bold uppercase italic" required />
                        <button class="bg-slate-800 text-white rounded-xl text-[10px] font-black uppercase hover:bg-black transition shadow-lg">Input Data</button>
                    </form>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-600 text-white text-[10px] uppercase font-black tracking-widest">
                            <tr>
                                <th class="p-4">Pangkat</th>
                                <th class="p-4">Nama</th>
                                <th class="p-4">WhatsApp</th>
                                <th class="p-4">Tgl Lahir</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-bold uppercase italic">
                            <tr v-for="p in personnels" :key="p.id" class="border-b hover:bg-red-50 transition group">
                                <td class="p-4 text-indigo-700">{{ p.pangkat }}</td>
                                <td class="p-4">{{ p.name }}</td>
                                <td class="p-4 text-gray-400">{{ p.phone }}</td>
                                <td class="p-4">{{ p.birth_date }}</td>
                                <td class="p-4 text-center">
                                    <button @click="deletePersonnel(p.id)" class="text-red-400 hover:text-red-700 transition"></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="isTestModalOpen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-[2.5rem] p-8 shadow-2xl border border-indigo-50">
                <div class="text-center mb-6">
                    <div class="h-12 w-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-xl"></div>
                    <h3 class="font-black text-indigo-950 uppercase tracking-widest text-sm">Uji Coba Radiogram</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 italic">Pilih target untuk simulasi pengiriman</p>
                </div>
                
                <div class="space-y-4">
                    <label class="text-[10px] font-black uppercase text-indigo-900 ml-2">Target Personel</label>
                    <select v-model="testTarget" class="w-full rounded-2xl border-gray-100 text-xs font-bold p-3 focus:ring-indigo-500">
                        <option :value="null" disabled>-- Pilih Personel Terdaftar --</option>
                        <optgroup label="Personel Akun">
                            <option v-for="u in users" :key="u.id" :value="u">{{ u.pangkat }} {{ u.name }}</option>
                        </optgroup>
                        <optgroup label="Personel Non-Akun">
                            <option v-for="p in personnels" :key="p.id" :value="p">{{ p.pangkat }} {{ p.name }}</option>
                        </optgroup>
                    </select>
                </div>

                <div class="flex gap-3 mt-8">
                    <button @click="isTestModalOpen = false" class="flex-1 py-3 text-[10px] font-black uppercase text-gray-400 hover:text-indigo-600 transition">Batal</button>
                    <button @click="sendTest" class="flex-1 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition">Kirim Tes</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>