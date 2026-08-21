<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    users: Array,
    title: String,
    unit: String,
    date: String
});

onMounted(() => {
    setTimeout(() => {
        window.print();
    }, 800);
});
</script>

<template>
    <Head :title="title" />
    <div class="p-8 bg-white min-h-screen text-black font-serif">
        
        <!-- KOP SURAT RESMI DETASEMEN INTELIJEN KODAERAL V -->
        <div class="border-b-4 border-double border-black pb-3 mb-6 flex items-center justify-between">
            <div class="text-left font-bold text-xs uppercase leading-tight tracking-wider">
                <p class="font-extrabold text-sm">KOMANDO DAERAH TNI ANGKATAN LAUT V</p>
                <p class="font-black text-base underline decoration-2 underline-offset-2">DETASEMEN INTELIJEN</p>
            </div>
            <div class="text-right text-[10px] font-mono">
                <p class="font-bold">RAHASIA / DOKUMEN KEDINASAAN</p>
                <p>SINDEN DIGITAL SECURITY SYSTEM</p>
            </div>
        </div>

        <!-- JUDUL DOKUMEN -->
        <div class="text-center my-6">
            <h1 class="text-base font-black uppercase tracking-wide underline decoration-1 underline-offset-4">{{ title }}</h1>
            <p class="text-xs font-semibold uppercase mt-1">SATUAN KERJA: {{ unit }}</p>
        </div>

        <!-- TABEL DATA KODE VERIFIKASI & TOKEN AKTIVASI -->
        <table class="w-full border-collapse border-2 border-black text-xs mt-4">
            <thead>
                <tr class="bg-gray-100 uppercase font-extrabold text-center">
                    <th class="border-2 border-black p-2 w-10">NO</th>
                    <th class="border-2 border-black p-2 text-left">NAMA PERSONEL</th>
                    <th class="border-2 border-black p-2">PANGKAT / JABATAN</th>
                    <th class="border-2 border-black p-2">NRP / PNS</th>
                    <th class="border-2 border-black p-2 text-left">EMAIL REGISTRASI</th>
                    <th class="border-2 border-black p-2 font-mono text-sm">KODE VERIFIKASI</th>
                    <th class="border-2 border-black p-2 text-xs">TAUTAN AKTIVASI</th>
                    <th class="border-2 border-black p-2 w-28">PARAF / TTD</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(u, i) in users" :key="u.id" class="border-b border-black">
                    <td class="border-2 border-black p-2 text-center font-bold">{{ i + 1 }}</td>
                    <td class="border-2 border-black p-2 text-left font-bold uppercase">{{ u.name }}</td>
                    <td class="border-2 border-black p-2 text-center uppercase">{{ u.pangkat }}</td>
                    <td class="border-2 border-black p-2 text-center font-mono font-bold">{{ u.nrp }}</td>
                    <td class="border-2 border-black p-2 text-left font-mono text-[11px]">{{ u.email }}</td>
                    <td class="border-2 border-black p-2 text-center font-mono font-black text-sm text-blue-900 bg-gray-50">{{ u.activation_token || 'SINDEN-XQOWES' }}</td>
                    <td class="border-2 border-black p-2 text-center font-mono text-[10px]">https://sisinden.my.id/aktivasi</td>
                    <td class="border-2 border-black p-2"></td>
                </tr>
                <tr v-if="!users || users.length === 0">
                    <td colspan="8" class="p-6 text-center italic text-gray-500">Tidak ada data token aktivasi.</td>
                </tr>
            </tbody>
        </table>

        <!-- PETUNJUK AKTIVASI -->
        <div class="mt-6 p-4 border border-black rounded text-[11px] leading-relaxed space-y-1 bg-gray-50">
            <p class="font-bold underline">PETUNJUK AKTIVASI PERSONEL:</p>
            <p>1. Personel membuka tautan aktivasi resmi: <b>https://sisinden.my.id/aktivasi</b></p>
            <p>2. Masukkan <b>NRP / PNS</b> dan <b>KODE VERIFIKASI</b> sesuai tabel di atas.</p>
            <p>3. Buat Kata Sandi (Password) baru minimal 8 karakter lalu konfirmasi.</p>
            <p>4. Harap menjaga kerahasiaan Kode Verifikasi demi keamanan data kedinasan.</p>
        </div>

        <!-- KOLOM TANDA TANGAN KOMANDAN -->
        <div class="mt-12 flex justify-end">
            <div class="text-center w-64 text-xs">
                <p>Surabaya, {{ date }}</p>
                <p class="font-bold uppercase mt-1">Komandan Detasemen Intelijen Kodaeral V</p>
                <div class="h-20"></div>
                <p class="font-bold underline uppercase">I GUSTI SULTAN H.A, A.Md.Kom</p>
                <p class="font-mono">MAYOR LAUT (P) NRP. 12000018012200216</p>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 1cm;
    }
    body {
        background: white;
        -webkit-print-color-adjust: exact;
    }
}
</style>