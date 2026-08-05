<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    skhpp: Object,
    verificationUrl: String,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isCommander = computed(() => user.value.role === 'admin' || user.value.role === 'komandan' || user.value.name === 'I Gusti Sultan H.A, A.Md.Kom');

// QR Code URL generator (Using Google Chart / QRServer API)
const qrApiUrl = computed(() => {
    const url = props.verificationUrl || window.location.href;
    return `https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=5&data=${encodeURIComponent(url)}`;
});

const isExporting = ref(false);

const downloadPdf = () => {
    isExporting.value = true;
    const element = document.getElementById('area-skhpp-cetak');

    const opt = {
        margin: [0.3, 0.4, 0.3, 0.4],
        filename: `SKHPP_${props.skhpp.nama.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, windowWidth: 720 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };

    if (window.html2pdf) {
        window.html2pdf().set(opt).from(element).save().then(() => {
            isExporting.value = false;
        });
    } else {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = () => {
            window.html2pdf().set(opt).from(element).save().then(() => {
                isExporting.value = false;
            });
        };
        document.head.appendChild(script);
    }
};

const approveSkhpp = () => {
    const defaultSeq = (props.skhpp.nomor_urut || 1);

    Swal.fire({
        title: 'OTORISASI TTD KOMANDAN',
        html: `
            <div class="text-left text-xs space-y-3 font-sans">
                <p class="text-slate-600">Nomor SKHPP akan diterbitkan & tersinkronisasi otomatis ke <b>Buku Agenda Surat SINDEN (Letter Logs)</b>:</p>
                
                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700">Nomor Urut SKHPP (Bisa diisi manual jika ada arsip terlewat)</label>
                    <input id="swal-nomor-urut-show" type="number" value="${defaultSeq}" class="w-full text-xs font-bold p-2.5 border rounded-xl" placeholder="Masukkan nomor urut (contoh: 171)" />
                </div>

                <div>
                    <label class="block font-bold uppercase mb-1 text-slate-700">Derajat Kecepatan / Prioritas (Logika Penyamaran Kode SKHPP)</label>
                    <select id="swal-priority-show" class="w-full text-xs font-bold p-2.5 border rounded-xl">
                        <option value="R" selected>R (RAHASIA) — Format: R / [NOMOR] / SKHPP / [BULAN] / [TAHUN]</option>
                        <option value="B">B (BIASA) — Format: B / [NOMOR] / SKHPP / [BULAN] / [TAHUN]</option>
                        <option value="K">K (KILAT) — Format: K / [NOMOR] / SKHPP / [BULAN] / [TAHUN]</option>
                    </select>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'YA, SETUJU & TTD',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#059669',
        preConfirm: () => {
            const seq = document.getElementById('swal-nomor-urut-show').value;
            const prio = document.getElementById('swal-priority-show').value;
            return { custom_nomor_urut: seq, priority: prio };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            router.post(route('skhpp.approve', props.skhpp.id), result.value, {
                onSuccess: () => Swal.fire('SUKSES', 'SKHPP Resmi disetujui & ditandatangani Komandan.', 'success')
            });
        }
    });
};

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
};
</script>

<template>
    <Head :title="`Detail SKHPP - ${skhpp.nama}`" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Top Control Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-3xl shadow-xs border border-slate-100">
                <div class="flex items-center gap-3">
                    <Link :href="route('skhpp.index')" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-all">
                        ← Halaman Utama
                    </Link>
                    <div>
                        <h1 class="text-base font-black text-slate-900 uppercase">Dokumen SKHPP {{ skhpp.nama }}</h1>
                        <p class="text-[10px] text-slate-500 font-bold uppercase">Status: 
                            <span :class="skhpp.status === 'approved' ? 'text-emerald-600' : 'text-amber-600'">
                                {{ skhpp.status === 'approved' ? 'RESMI TERBIT (TTD DIGITAL)' : 'PENDING TTD KOMANDAN' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button v-if="isCommander && skhpp.status === 'pending'" @click="approveSkhpp"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all">
                        ✓ TTD Komandan Sekarang
                    </button>

                    <button @click="downloadPdf" :disabled="isExporting"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>{{ isExporting ? 'Mengeksport PDF...' : 'Unduh Dokumen PDF' }}</span>
                    </button>
                </div>
            </div>

            <!-- Main Printable Document Container -->
            <div class="flex justify-center">
                <div id="area-skhpp-cetak" class="bg-white text-black p-10 font-serif leading-relaxed shadow-2xl border border-slate-200" style="width: 670px; min-height: 950px; box-sizing: border-box;">
                    
                    <!-- Page 1: Main SKHPP Document -->
                    <div class="skhpp-page">
                        
                        <!-- Kop Header -->
                        <div class="border-b-2 border-black pb-1 mb-6 text-left" style="font-family: 'Times New Roman', Times, serif;">
                            <div class="text-[12px] font-bold uppercase tracking-wide leading-tight">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
                            <div class="text-[12px] font-bold uppercase tracking-wide leading-tight pl-6">DETASEMEN INTELIJEN</div>
                        </div>

                        <!-- Judul Surat -->
                        <div class="text-center my-6" style="font-family: 'Times New Roman', Times, serif;">
                            <div class="text-[13px] font-bold uppercase tracking-wide">
                                SURAT KETERANGAN HASIL PENELITIAN PERSONEL(SKHPP)
                            </div>
                            <div v-if="skhpp.kategori_personel === 'sipil'" class="text-[13px] font-bold uppercase tracking-wide">
                                MITRA KERJA TNI ANGKATAN LAUT
                            </div>
                            <div class="text-[12px] font-bold mt-1">
                                Nomor : R/ {{ skhpp.nomor_urut || '      ' }} /SKHPP/{{ skhpp.bulan_romawi || 'VIII' }}/{{ skhpp.tahun || '2026' }}
                            </div>
                        </div>

                        <!-- Poin Rincian SKHPP -->
                        <div class="text-[12px] space-y-3" style="font-family: 'Times New Roman', Times, serif; text-align: justify;">
                            
                            <!-- Poin 1: Dasar -->
                            <div class="flex items-start">
                                <span class="w-6 font-bold shrink-0">1.</span>
                                <div class="flex-1">
                                    <div class="font-bold">Dasar :</div>
                                    <div class="space-y-1.5 mt-1">
                                        <div class="flex items-start">
                                            <span class="w-5 shrink-0">a.</span>
                                            <div class="flex-1">Peraturan Kasal Nomor Perkasal/50/XII/2007 tanggal 04 Desember 2007 tentang Petunjuk Pelaksanaan Penelitian Personel di lingkungan TNI AL;</div>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="w-5 shrink-0">b.</span>
                                            <div class="flex-1">Prosedur Tetap Nomor Protap/01/VIII/2024 tanggal 26 Agustus 2024 tentang Pengurusan Surat Keterangan Hasil Penelitian Personel (SKHPP) di Lingkungan Tentara Nasional Indonesia Angkatan Laut; dan</div>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="w-5 shrink-0">c.</span>
                                            <div class="flex-1">{{ skhpp.surat_pengantar }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Poin 2: Data Personel -->
                            <div class="flex items-start pt-1">
                                <span class="w-6 font-bold shrink-0">2.</span>
                                <div class="flex-1">
                                    <div>Dengan ini menerangkan bahwa hasil penelitian terhadap :</div>
                                    
                                    <!-- Militer Format -->
                                    <table v-if="skhpp.kategori_personel === 'militer'" class="w-full mt-2 text-[12px]" style="line-height: 1.5;">
                                        <tr>
                                            <td class="w-5 font-normal">a.</td>
                                            <td class="w-36 font-normal">Nama</td>
                                            <td class="w-3">:</td>
                                            <td class="font-bold">{{ skhpp.nama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">b.</td>
                                            <td class="font-normal">Pangkat/Korp/NRP</td>
                                            <td>:</td>
                                            <td>{{ skhpp.pangkat_korps_nrp }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">c.</td>
                                            <td class="font-normal">Jabatan</td>
                                            <td>:</td>
                                            <td>{{ skhpp.jabatan_pekerjaan }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">d.</td>
                                            <td class="font-normal">Tempat/Tgl. lahir</td>
                                            <td>:</td>
                                            <td>{{ skhpp.tempat_lahir }}, {{ formatDateIndo(skhpp.tanggal_lahir) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">e.</td>
                                            <td class="font-normal">Jenis kelamin</td>
                                            <td>:</td>
                                            <td>{{ skhpp.jenis_kelamin }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">f.</td>
                                            <td class="font-normal">Agama</td>
                                            <td>:</td>
                                            <td>{{ skhpp.agama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">g.</td>
                                            <td class="font-normal">Alamat rumah</td>
                                            <td>:</td>
                                            <td>{{ skhpp.alamat }}</td>
                                        </tr>
                                    </table>

                                    <!-- Sipil Format -->
                                    <table v-else class="w-full mt-2 text-[12px]" style="line-height: 1.5;">
                                        <tr>
                                            <td class="w-5 font-normal">a.</td>
                                            <td class="w-36 font-normal">Nama</td>
                                            <td class="w-3">:</td>
                                            <td class="font-bold">{{ skhpp.nama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">b.</td>
                                            <td class="font-normal">NIK</td>
                                            <td>:</td>
                                            <td>{{ skhpp.nik }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">c.</td>
                                            <td class="font-normal">Tempat/Tgl. lahir</td>
                                            <td>:</td>
                                            <td>{{ skhpp.tempat_lahir }}, {{ formatDateIndo(skhpp.tanggal_lahir) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">d.</td>
                                            <td class="font-normal">Jenis kelamin</td>
                                            <td>:</td>
                                            <td>{{ skhpp.jenis_kelamin }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">e.</td>
                                            <td class="font-normal">Agama</td>
                                            <td>:</td>
                                            <td>{{ skhpp.agama }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">f.</td>
                                            <td class="font-normal">Pekerjaan</td>
                                            <td>:</td>
                                            <td>{{ skhpp.jabatan_pekerjaan }}</td>
                                        </tr>
                                        <tr>
                                            <td class="font-normal">g.</td>
                                            <td class="font-normal">Alamat rumah</td>
                                            <td>:</td>
                                            <td>{{ skhpp.alamat }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Poin 3: Hasil -->
                            <div class="flex items-start pt-1">
                                <span class="w-6 font-bold shrink-0">3.</span>
                                <div class="flex-1">
                                    Hasil Penelitian Personel <span class="font-bold">Memenuhi Syarat</span>
                                </div>
                            </div>

                            <!-- Poin 4: Peruntukan -->
                            <div class="flex items-start pt-1">
                                <span class="w-6 font-bold shrink-0">4.</span>
                                <div class="flex-1">
                                    SKHPP ini diberikan {{ skhpp.peruntukan }}.
                                </div>
                            </div>

                            <!-- Poin 5: Penutup -->
                            <div class="flex items-start pt-1">
                                <span class="w-6 font-bold shrink-0">5.</span>
                                <div class="flex-1">
                                    Apabila kemudian terdapat kekeliruan, SKHPP ini akan dicabut dan diadakan pembetulan seperlunya.
                                </div>
                            </div>
                        </div>

                        <!-- Pas Foto & Signature Block Komandan -->
                        <div class="mt-8 flex justify-between items-start text-[12px]" style="font-family: 'Times New Roman', Times, serif;">
                            
                            <!-- Pas Foto Section -->
                            <div class="flex items-center gap-3">
                                <div v-if="skhpp.foto_1" class="text-center">
                                    <img :src="'/storage/' + skhpp.foto_1" class="w-28 h-36 object-cover border border-black shadow-xs" />
                                    <div v-if="skhpp.is_pernikahan" class="text-[9px] font-bold mt-1 uppercase">Suami</div>
                                </div>
                                <div v-if="skhpp.is_pernikahan && skhpp.foto_2" class="text-center">
                                    <img :src="'/storage/' + skhpp.foto_2" class="w-28 h-36 object-cover border border-black shadow-xs" />
                                    <div class="text-[9px] font-bold mt-1 uppercase">Istri</div>
                                </div>
                            </div>

                            <!-- TTD Block Komandan & QR Code -->
                            <div class="text-left w-72">
                                <div>Dikeluarkan di Surabaya</div>
                                <div class="border-b border-black pb-1">
                                    pada tanggal <span class="ml-4">{{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at || skhpp.created_at) }}</span>
                                </div>
                                <div class="mt-2 font-bold leading-snug">
                                    Komandan Detasemen Intelijen Kodaeral V,
                                </div>

                                <!-- QR Code Digital Signature -->
                                <div class="my-2 py-1">
                                    <div v-if="skhpp.status === 'approved'" class="flex items-center gap-2">
                                        <img :src="qrApiUrl" class="w-20 h-20 border border-slate-300 p-0.5 rounded-sm" />
                                        <div class="text-[8px] leading-tight font-sans text-slate-600">
                                            <div class="font-bold text-emerald-700">✓ DITANDATANGANI SECARA DIGITAL</div>
                                            <div>Detasemen Intelijen V</div>
                                            <div class="text-[7px] text-slate-400 mt-0.5 truncate max-w-[130px]">{{ skhpp.verification_code }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="h-20 flex items-center justify-center border border-dashed border-slate-300 text-[10px] text-slate-400 font-sans italic">
                                        [ PENDING TTD KOMANDAN ]
                                    </div>
                                </div>

                                <div class="font-bold underline mt-1">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                                <div class="font-bold">Kolonel Laut (E) NRP 16085/P</div>
                            </div>
                        </div>

                        <!-- Footer Kepada -->
                        <div class="mt-8 text-[12px]" style="font-family: 'Times New Roman', Times, serif;">
                            <div>Kepada :</div>
                            <div class="font-bold border-b border-black inline-block">Yth. Asintel Dankodaeral V</div>
                        </div>
                    </div>

                    <!-- Page 2: Lampiran SKHPP (If members exist > 1) -->
                    <template v-if="skhpp.has_pengikut && skhpp.members && skhpp.members.length > 0">
                        
                        <div class="html2pdf__page-break" style="page-break-before: always; height: 1px;"></div>

                        <div class="skhpp-page pt-6">
                            
                            <!-- Header Lampiran -->
                            <div class="flex justify-between items-start border-b-2 border-black pb-2 mb-6" style="font-family: 'Times New Roman', Times, serif;">
                                <div>
                                    <div class="text-[12px] font-bold uppercase">KOMANDO DAERAH TNI ANGKATAN LAUT V</div>
                                    <div class="text-[12px] font-bold uppercase pl-6">DETASEMEN INTELIJEN</div>
                                </div>
                                <div class="text-right text-[11px] font-bold">
                                    <div>Lampiran SKHPP Den Intel Kodaeral V</div>
                                    <div>Nomor SKHPP/ {{ skhpp.nomor_urut || '   ' }} /{{ skhpp.bulan_romawi || 'VIII' }}/{{ skhpp.tahun || '2026' }}</div>
                                    <div>Tanggal {{ formatDateIndo(skhpp.tanggal_skhpp || skhpp.approved_at || skhpp.created_at) }}</div>
                                </div>
                            </div>

                            <!-- Judul Lampiran -->
                            <div class="text-center my-6" style="font-family: 'Times New Roman', Times, serif;">
                                <div class="text-[13px] font-bold uppercase tracking-wider underline">
                                    DAFTAR NAMA-NAMA PERSONEL / ANGGOTA PENGIKUT
                                </div>
                            </div>

                            <!-- Tabel Anggota Pengikut -->
                            <table class="w-full border-collapse border border-black text-[11px] my-6" style="font-family: 'Times New Roman', Times, serif;">
                                <thead>
                                    <tr class="bg-slate-100 font-bold uppercase border-b border-black text-center">
                                        <th class="border border-black p-2 w-10">NO</th>
                                        <th class="border border-black p-2">NAMA LENGKAP</th>
                                        <th class="border border-black p-2">NRP / NIK</th>
                                        <th class="border border-black p-2">JABATAN / PEKERJAAN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(m, idx) in skhpp.members" :key="idx" class="border-b border-black">
                                        <td class="border border-black p-2 text-center font-bold">{{ idx + 1 }}.</td>
                                        <td class="border border-black p-2 font-bold">{{ m.nama }}</td>
                                        <td class="border border-black p-2 text-center">{{ m.pangkat_nrp_nik }}</td>
                                        <td class="border border-black p-2">{{ m.jabatan }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- TTD Komandan Lampiran -->
                            <div class="mt-8 flex justify-end text-[12px]" style="font-family: 'Times New Roman', Times, serif;">
                                <div class="text-left w-72">
                                    <div class="font-bold leading-snug">
                                        Komandan Detasemen Intelijen Kodaeral V,
                                    </div>

                                    <div class="my-2 py-1">
                                        <div v-if="skhpp.status === 'approved'" class="flex items-center gap-2">
                                            <img :src="qrApiUrl" class="w-20 h-20 border border-slate-300 p-0.5 rounded-sm" />
                                            <div class="text-[8px] leading-tight font-sans text-slate-600">
                                                <div class="font-bold text-emerald-700">✓ TERVERIFIKASI LAMPIRAN</div>
                                                <div>Detasemen Intelijen V</div>
                                            </div>
                                        </div>
                                        <div v-else class="h-20 flex items-center justify-center border border-dashed border-slate-300 text-[10px] text-slate-400 font-sans italic">
                                            [ PENDING TTD KOMANDAN ]
                                        </div>
                                    </div>

                                    <div class="font-bold underline mt-1">Hari Bagio Wijayanto, M.Tr.Opsla.</div>
                                    <div class="font-bold">Kolonel Laut (E) NRP 16085/P</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
