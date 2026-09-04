<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    metrics: Object,
    allLoans: Object,
    allMembers: Object,
    allMutations: Array,
    allInstallments: Array,
    personels: Array,
    filters: Object,
});

// Active Tab
const activeTab = ref('admin_loans'); // 'admin_loans', 'admin_installments', 'admin_savings', 'admin_ledger'

// Modal States
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showRecordInstallmentModal = ref(false);
const showDepositSavingModal = ref(false);
const showWithdrawSavingModal = ref(false);
const showLoanDetailModal = ref(false);

const selectedLoan = ref(null);

// Form Persetujuan Pinjaman (Tenor Baku 10 Bulan, Bunga 0%)
const approveForm = useForm({
    amount_approved: 0,
    duration_months: 10,
    interest_rate_percent: 0,
    notes: '',
});

const openApproveModal = (loan) => {
    selectedLoan.value = loan;
    approveForm.amount_approved = loan.amount_requested;
    approveForm.duration_months = 10;
    approveForm.interest_rate_percent = 0;
    approveForm.notes = '';
    showApproveModal.value = true;
};

const submitApproveLoan = () => {
    if (!selectedLoan.value) return;
    approveForm.post(route('simpan-pinjam.approve-loan', selectedLoan.value.id), {
        onSuccess: () => {
            showApproveModal.value = false;
            Swal.fire('DISETUJUI', 'Pinjaman telah disetujui dan dicairkan ke anggota.', 'success');
        },
        onError: (err) => {
            Swal.fire('GAGAL', Object.values(err)[0] || 'Gagal memproses persetujuan.', 'error');
        }
    });
};

// Form Penolakan Pinjaman
const rejectForm = useForm({
    rejection_reason: '',
});

const openRejectModal = (loan) => {
    selectedLoan.value = loan;
    rejectForm.rejection_reason = '';
    showRejectModal.value = true;
};

const submitRejectLoan = () => {
    if (!selectedLoan.value) return;
    rejectForm.post(route('simpan-pinjam.reject-loan', selectedLoan.value.id), {
        onSuccess: () => {
            showRejectModal.value = false;
            Swal.fire('DITOLAK', 'Pengajuan pinjaman telah ditolak.', 'info');
        }
    });
};

// Form Pencatatan Cicilan oleh Petugas (Nominal Bebas / Fleksibel)
const installmentForm = useForm({
    loan_id: '',
    amount_paid: 0,
    payment_date: new Date().toISOString().substring(0, 10),
    payment_method: 'potong_gaji',
    notes: '',
});

const targetLoanForInstallment = ref(null);

const onSelectLoanForInstallment = (loanId) => {
    installmentForm.loan_id = loanId;
    if (props.allLoans?.data) {
        const found = props.allLoans.data.find(l => l.id === Number(loanId));
        targetLoanForInstallment.value = found || null;
        if (found) {
            installmentForm.amount_paid = Math.min(Number(found.remaining_amount), Number(found.monthly_installment));
        }
    }
};

const openRecordInstallmentDirect = (loan) => {
    targetLoanForInstallment.value = loan;
    installmentForm.loan_id = loan.id;
    installmentForm.amount_paid = Math.min(Number(loan.remaining_amount), Number(loan.monthly_installment));
    installmentForm.payment_date = new Date().toISOString().substring(0, 10);
    installmentForm.payment_method = 'potong_gaji';
    installmentForm.notes = '';
    showRecordInstallmentModal.value = true;
};

const setQuickAmount = (type) => {
    if (!targetLoanForInstallment.value) return;
    const monthly = Number(targetLoanForInstallment.value.monthly_installment) || 0;
    const remaining = Number(targetLoanForInstallment.value.remaining_amount) || 0;

    if (type === 'monthly') {
        installmentForm.amount_paid = Math.min(remaining, monthly);
    } else if (type === 'double') {
        installmentForm.amount_paid = Math.min(remaining, monthly * 2);
    } else if (type === 'full') {
        installmentForm.amount_paid = remaining;
    }
};

const submitRecordInstallment = () => {
    if (!installmentForm.loan_id) {
        Swal.fire('PILIH PINJAMAN', 'Silakan pilih pinjaman anggota yang ingin dicatat cicilannya.', 'warning');
        return;
    }
    installmentForm.post(route('simpan-pinjam.record-installment', installmentForm.loan_id), {
        onSuccess: (page) => {
            showRecordInstallmentModal.value = false;
            const receiptId = page.props.flash?.receipt_id;
            Swal.fire({
                title: 'PEMBAYARAN DICATAT',
                html: `Pembayaran cicilan berhasil dibukukan.<br><span class="text-xs text-slate-500">Kuitansi dan notifikasi WhatsApp resmi telah diterbitkan.</span>`,
                icon: 'success',
                showCancelButton: Boolean(receiptId),
                confirmButtonText: 'Tutup',
                cancelButtonText: 'Cetak Kuitansi PDF',
                cancelButtonColor: '#059669',
            }).then((res) => {
                if (res.dismiss === Swal.DismissReason.cancel && receiptId) {
                    window.open(route('simpan-pinjam.receipt', receiptId), '_blank');
                }
            });
        },
        onError: (err) => {
            Swal.fire('GAGAL', Object.values(err)[0] || 'Kendala saat mencatat pembayaran.', 'error');
        }
    });
};

// Form Setoran Simpanan (Pengurus)
const savingDepositForm = useForm({
    user_id: '',
    saving_type: 'wajib',
    amount: 100000,
    payment_method: 'potong_gaji',
    notes: '',
});

const submitDepositSaving = () => {
    savingDepositForm.post(route('simpan-pinjam.deposit-saving'), {
        onSuccess: () => {
            showDepositSavingModal.value = false;
            savingDepositForm.reset();
            Swal.fire('SETORAN DICATAT', 'Setoran simpanan anggota berhasil dibukukan.', 'success');
        }
    });
};

// Form Penarikan Simpanan Sukarela
const withdrawSavingForm = useForm({
    user_id: '',
    amount: 100000,
    notes: '',
});

const submitWithdrawSaving = () => {
    withdrawSavingForm.post(route('simpan-pinjam.withdraw-saving'), {
        onSuccess: () => {
            showWithdrawSavingModal.value = false;
            withdrawSavingForm.reset();
            Swal.fire('PENARIKAN BERHASIL', 'Penarikan simpanan sukarela telah dibukukan.', 'success');
        }
    });
};

// Pemicu Broadcast Pengingat Tanggal 1
const triggerBroadcastReminders = () => {
    Swal.fire({
        title: 'SIARKAN PENGINGAT TANGGAL 1?',
        html: `Sistem akan memancarkan notifikasi lonceng SINDEN dan pesan WhatsApp kepada seluruh personel yang memiliki pinjaman aktif berjalan.<br><br><span class="text-xs text-slate-500">Jadwal otomatis juga aktif berjalan setiap tanggal 1 pukul 07.00 WIB.</span>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Kirim Sekarang',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d97706',
    }).then((res) => {
        if (res.isConfirmed) {
            router.post(route('simpan-pinjam.broadcast-reminders'), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire('BERHASIL DISIARKAN', 'Notifikasi pengingat pembayaran tanggal 1 telah dikirimkan ke personel.', 'success');
                },
                onError: (err) => {
                    Swal.fire('GAGAL', Object.values(err)[0] || 'Kendala saat mengirim siaran pengingat.', 'error');
                }
            });
        }
    });
};

// Format Helpers
const formatRupiah = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Filter Loans List
const activeLoansList = computed(() => {
    if (!props.allLoans?.data) return [];
    return props.allLoans.data.filter(l => l.status === 'active');
});
</script>

<template>
    <Head title="Kelola Simpan Pinjam - SINDEN" />

    <AuthenticatedLayout>
        <div class="space-y-6 sm:space-y-8 font-sans max-w-7xl mx-auto pb-12">
            
            <!-- Header Halaman Pusat Kelola -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-1 bg-violet-50 text-violet-700 font-extrabold text-[10px] uppercase rounded-full tracking-wider border border-violet-200">
                            Pusat Kendali Pengurus & Admin
                        </span>
                        <span class="text-slate-400 text-xs font-semibold">Otoritas Koperasi SINDEN</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        Kelola Simpan Pinjam
                    </h1>
                    <p class="text-xs text-slate-500 font-medium mt-1">
                        Manajemen verifikasi pinjaman, pencatatan angsuran, pembukuan kas, dan rekapitulasi anggota.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <Link 
                        :href="route('simpan-pinjam.index')"
                        class="w-full sm:w-auto px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5"
                    >
                        <span>&larr; Simpan Pinjam Pribadi</span>
                    </Link>
                </div>
            </div>

            <!-- Metrik Keuangan Koperasi -->
            <div v-if="metrics" class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Saldo Kas Riil</span>
                    <div class="text-lg sm:text-xl font-black text-emerald-700">{{ formatRupiah(metrics.kas_koperasi) }}</div>
                    <div class="text-[10px] text-emerald-600 font-medium mt-1">Kas operasional aktif</div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Total Piutang Pinjaman</span>
                    <div class="text-lg sm:text-xl font-black text-orange-600">{{ formatRupiah(metrics.pinjaman_aktif_total) }}</div>
                    <div class="text-[10px] text-slate-400 font-medium mt-1">Sisa pinjaman berjalan</div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Simpanan Anggota</span>
                    <div class="text-lg sm:text-xl font-black text-indigo-700">{{ formatRupiah(metrics.simpanan_anggota_total) }}</div>
                    <div class="text-[10px] text-slate-400 font-medium mt-1">Total dana simpanan</div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Cicilan Masuk Bln Ini</span>
                    <div class="text-lg sm:text-xl font-black text-blue-600">{{ formatRupiah(metrics.cicilan_masuk_bulan_ini) }}</div>
                    <div class="text-[10px] text-slate-400 font-medium mt-1">Setoran bulan berjalan</div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs col-span-2 lg:col-span-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Antrean Review</span>
                    <div class="text-lg sm:text-xl font-black text-amber-600">{{ metrics.pengajuan_pending_count }} Pengajuan</div>
                    <div class="text-[10px] text-slate-400 font-medium mt-1">Menunggu persetujuan</div>
                </div>
            </div>

            <!-- Tab Navigasi Pengurus -->
            <div class="flex border-b border-slate-200 gap-2 sm:gap-4 overflow-x-auto pb-1 text-xs">
                <button 
                    @click="activeTab = 'admin_loans'" 
                    :class="activeTab === 'admin_loans' ? 'text-violet-700 border-violet-600 font-black' : 'text-slate-500 border-transparent hover:text-slate-800 font-bold'"
                    class="py-3 px-3 sm:px-5 border-b-2 transition whitespace-nowrap flex items-center gap-2"
                >
                    <span>Verifikasi Pinjaman</span>
                    <span v-if="metrics?.pengajuan_pending_count > 0" class="px-1.5 py-0.5 rounded-full bg-amber-500 text-white text-[9px] font-black">
                        {{ metrics.pengajuan_pending_count }}
                    </span>
                </button>

                <button 
                    @click="activeTab = 'admin_installments'" 
                    :class="activeTab === 'admin_installments' ? 'text-violet-700 border-violet-600 font-black' : 'text-slate-500 border-transparent hover:text-slate-800 font-bold'"
                    class="py-3 px-3 sm:px-5 border-b-2 transition whitespace-nowrap flex items-center gap-2"
                >
                    <span>Pencatatan Cicilan Anggota</span>
                </button>

                <button 
                    @click="activeTab = 'admin_savings'" 
                    :class="activeTab === 'admin_savings' ? 'text-violet-700 border-violet-600 font-black' : 'text-slate-500 border-transparent hover:text-slate-800 font-bold'"
                    class="py-3 px-3 sm:px-5 border-b-2 transition whitespace-nowrap flex items-center gap-2"
                >
                    <span>Rekening Simpanan Anggota</span>
                </button>

                <button 
                    @click="activeTab = 'admin_ledger'" 
                    :class="activeTab === 'admin_ledger' ? 'text-violet-700 border-violet-600 font-black' : 'text-slate-500 border-transparent hover:text-slate-800 font-bold'"
                    class="py-3 px-3 sm:px-5 border-b-2 transition whitespace-nowrap flex items-center gap-2"
                >
                    <span>Buku Kas & Mutasi Koperasi</span>
                </button>
            </div>

            <!-- ================================================================= -->
            <!-- TAB 1: VERIFIKASI & PERSETUJUAN PINJAMAN                          -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_loans'" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Antrean & Riwayat Pinjaman Seluruh Anggota</h3>
                            <p class="text-xs text-slate-500">Tinjau permohonan baru, setujui plafon & tenor 10 bulan, serta pantau pelunasan anggota.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                    <th class="py-3 px-3">Kode Pinjaman</th>
                                    <th class="py-3 px-3">Nama Anggota</th>
                                    <th class="py-3 px-3">Pangkat / NRP</th>
                                    <th class="py-3 px-3 text-right">Diajukan</th>
                                    <th class="py-3 px-3 text-center">Tenor</th>
                                    <th class="py-3 px-3 text-right">Sisa Hutang</th>
                                    <th class="py-3 px-3 text-center">Status</th>
                                    <th class="py-3 px-3 text-center">Tindakan Otoritas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="loan in allLoans?.data" :key="loan.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-3 font-mono font-bold text-slate-900">{{ loan.loan_code }}</td>
                                    <td class="py-3.5 px-3 font-bold uppercase text-slate-800">{{ loan.user?.name }}</td>
                                    <td class="py-3.5 px-3 text-slate-600">{{ loan.user?.pangkat }} ({{ loan.user?.nrp }})</td>
                                    <td class="py-3.5 px-3 text-right font-black text-slate-900">{{ formatRupiah(loan.amount_requested) }}</td>
                                    <td class="py-3.5 px-3 text-center font-bold">{{ loan.duration_months }} Bln</td>
                                    <td class="py-3.5 px-3 text-right font-black text-orange-600">{{ formatRupiah(loan.remaining_amount) }}</td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span :class="{
                                            'bg-amber-100 text-amber-800': loan.status === 'pending',
                                            'bg-emerald-100 text-emerald-800': loan.status === 'active',
                                            'bg-rose-100 text-rose-800': loan.status === 'rejected',
                                            'bg-blue-100 text-blue-800': loan.status === 'paid_off',
                                        }" class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase">
                                            {{ loan.status }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <template v-if="loan.status === 'pending'">
                                                <button 
                                                    @click="openApproveModal(loan)" 
                                                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[10px] uppercase rounded-lg transition"
                                                >
                                                    Setujui
                                                </button>
                                                <button 
                                                    @click="openRejectModal(loan)" 
                                                    class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-extrabold text-[10px] uppercase rounded-lg transition"
                                                >
                                                    Tolak
                                                </button>
                                            </template>

                                            <template v-if="loan.status === 'active'">
                                                <button 
                                                    @click="openRecordInstallmentDirect(loan)" 
                                                    class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-[10px] uppercase rounded-lg transition"
                                                >
                                                    Catat Cicilan
                                                </button>
                                            </template>

                                            <button 
                                                @click="selectedLoan = loan; showLoanDetailModal = true"
                                                class="p-1 text-slate-400 hover:text-slate-700 rounded-md transition"
                                                title="Detail Kartu Angsuran"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!allLoans?.data || allLoans.data.length === 0">
                                    <td colspan="8" class="py-8 text-center text-slate-400">Tidak ada data permohonan pinjaman.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- TAB 2: PENCATATAN CICILAN ANGSURAN ANGGOTA                        -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_installments'" class="space-y-6">
                <!-- Banner Jadwal Otomatis Pengingat Tanggal 1 -->
                <div class="p-4 bg-amber-50/80 border border-amber-200 rounded-2xl flex items-center justify-between text-xs text-amber-950">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                        </span>
                        <div>
                            <span class="font-black text-amber-900">Jadwal Pengingat Otomatis Aktif:</span>
                            <span class="text-amber-800 ml-1">Sistem SINDEN terjadwal otomatis memancarkan pengingat via Bell Notifikasi dan WhatsApp setiap <b>tanggal 1 pukul 07.00 WIB</b> kepada seluruh personel peminjam.</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Pencatatan Pembayaran Cicilan Anggota</h3>
                            <p class="text-xs text-slate-500">Input setoran cicilan dengan nominal berapa pun. Sistem otomatis menghitung sisa hutang dan menerbitkan kuitansi.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button 
                                @click="triggerBroadcastReminders"
                                type="button"
                                class="px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-black text-xs uppercase tracking-wider rounded-2xl transition flex items-center gap-2 shadow-xs"
                                title="Kirim pengingat pembayaran cicilan ke seluruh personel yang memiliki pinjaman aktif"
                            >
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                <span>Broadcast Pengingat Tgl 1</span>
                            </button>
                            <button 
                                @click="showRecordInstallmentModal = true"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-md shadow-indigo-500/20 transition flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                                <span>Form Input Cicilan Baru</span>
                            </button>
                        </div>
                    </div>

                    <!-- Riwayat Pembayaran Cicilan -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                    <th class="py-3 px-3">No. Kuitansi</th>
                                    <th class="py-3 px-3">Tgl Bayar</th>
                                    <th class="py-3 px-3">Nama Anggota</th>
                                    <th class="py-3 px-3">No. Pinjaman</th>
                                    <th class="py-3 px-3 text-right">Nominal Bayar</th>
                                    <th class="py-3 px-3 text-right">Sisa Hutang</th>
                                    <th class="py-3 px-3">Metode</th>
                                    <th class="py-3 px-3">Petugas</th>
                                    <th class="py-3 px-3 text-center">Kuitansi PDF</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="inst in allInstallments" :key="inst.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-900">{{ inst.receipt_number }}</td>
                                    <td class="py-3 px-3 font-mono">{{ formatDate(inst.payment_date) }}</td>
                                    <td class="py-3 px-3 font-bold uppercase text-slate-800">{{ inst.user?.name }}</td>
                                    <td class="py-3 px-3 font-mono text-indigo-600">{{ inst.loan?.loan_code }}</td>
                                    <td class="py-3 px-3 text-right font-black text-emerald-700">{{ formatRupiah(inst.amount_paid) }}</td>
                                    <td class="py-3 px-3 text-right font-extrabold text-orange-600">{{ formatRupiah(inst.remaining_loan_after) }}</td>
                                    <td class="py-3 px-3 uppercase text-[10px] font-bold text-slate-600">{{ inst.payment_method?.replace('_', ' ') }}</td>
                                    <td class="py-3 px-3 text-slate-500 text-[11px]">{{ inst.recorder?.name || '-' }}</td>
                                    <td class="py-3 px-3 text-center">
                                        <a 
                                            :href="route('simpan-pinjam.receipt', inst.id)" 
                                            target="_blank"
                                            class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-200 font-black text-[10px] uppercase rounded-lg transition"
                                        >
                                            PDF Slip
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="!allInstallments || allInstallments.length === 0">
                                    <td colspan="9" class="py-8 text-center text-slate-400">Belum ada riwayat pembayaran cicilan tercatat.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- TAB 3: REKENING SIMPANAN SELURUH ANGGOTA                          -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_savings'" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Rekening Simpanan Seluruh Anggota</h3>
                            <p class="text-xs text-slate-500">Kelola buku tabungan simpanan pokok, simpanan wajib, dan sukarela personel.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button 
                                @click="showDepositSavingModal = true"
                                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-sm transition flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                                <span>Input Setoran</span>
                            </button>
                            <button 
                                @click="showWithdrawSavingModal = true"
                                class="px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300 font-black text-xs uppercase tracking-wider rounded-2xl transition flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path></svg>
                                <span>Tarik Sukarela</span>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                    <th class="py-3 px-3">No. Rekening</th>
                                    <th class="py-3 px-3">Nama Anggota</th>
                                    <th class="py-3 px-3">Pangkat / NRP</th>
                                    <th class="py-3 px-3 text-right">Simp. Pokok</th>
                                    <th class="py-3 px-3 text-right">Simp. Wajib</th>
                                    <th class="py-3 px-3 text-right">Sukarela</th>
                                    <th class="py-3 px-3 text-right">Total Simpanan</th>
                                    <th class="py-3 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="acc in allMembers?.data" :key="acc.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-3 font-mono font-bold text-slate-900">{{ acc.member_number }}</td>
                                    <td class="py-3.5 px-3 font-bold uppercase text-slate-800">{{ acc.user?.name }}</td>
                                    <td class="py-3.5 px-3 text-slate-600">{{ acc.user?.pangkat }} ({{ acc.user?.nrp }})</td>
                                    <td class="py-3.5 px-3 text-right font-bold text-slate-700">{{ formatRupiah(acc.balance_simpanan_pokok) }}</td>
                                    <td class="py-3.5 px-3 text-right font-bold text-emerald-700">{{ formatRupiah(acc.balance_simpanan_wajib) }}</td>
                                    <td class="py-3.5 px-3 text-right font-bold text-indigo-700">{{ formatRupiah(acc.balance_simpanan_sukarela) }}</td>
                                    <td class="py-3.5 px-3 text-right font-black text-slate-900">{{ formatRupiah(acc.total_simpanan) }}</td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-800">
                                            {{ acc.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!allMembers?.data || allMembers.data.length === 0">
                                    <td colspan="8" class="py-8 text-center text-slate-400">Belum ada data rekening anggota.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- TAB 4: BUKU KAS & MUTASI KOPERASI                                 -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_ledger'" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Arus Kas Masuk & Keluar Koperasi</h3>
                            <p class="text-xs text-slate-500">Pencatatan real-time arus dana simpanan, pencairan pinjaman, dan cicilan angsuran.</p>
                        </div>
                        <a 
                            :href="route('simpan-pinjam.export-ledger')" 
                            target="_blank"
                            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs uppercase rounded-xl shadow-xs transition"
                        >
                            Unduh Laporan PDF (A4)
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                    <th class="py-3 px-3">Tanggal</th>
                                    <th class="py-3 px-3">Kode Mutasi</th>
                                    <th class="py-3 px-3">Kategori</th>
                                    <th class="py-3 px-3">Uraian / Keterangan</th>
                                    <th class="py-3 px-3 text-right">Debet / Keluar</th>
                                    <th class="py-3 px-3 text-right">Kredit / Masuk</th>
                                    <th class="py-3 px-3 text-right">Saldo Kas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="mut in allMutations" :key="mut.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-3 font-mono">{{ formatDate(mut.date) }}</td>
                                    <td class="py-3 px-3 font-mono font-bold text-slate-700">{{ mut.transaction_code }}</td>
                                    <td class="py-3 px-3 uppercase text-[10px] font-bold text-slate-700">{{ mut.category?.replace('_', ' ') }}</td>
                                    <td class="py-3 px-3 text-slate-600">{{ mut.description }}</td>
                                    <td class="py-3 px-3 text-right font-black text-rose-600">{{ mut.type === 'out' ? formatRupiah(mut.amount) : '-' }}</td>
                                    <td class="py-3 px-3 text-right font-black text-emerald-600">{{ mut.type === 'in' ? formatRupiah(mut.amount) : '-' }}</td>
                                    <td class="py-3 px-3 text-right font-black text-slate-900">{{ formatRupiah(mut.balance) }}</td>
                                </tr>
                                <tr v-if="!allMutations || allMutations.length === 0">
                                    <td colspan="7" class="py-8 text-center text-slate-400">Belum ada mutasi arus kas tercatat.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: PENCATATAN CICILAN OLEH PETUGAS (ANTI-TERPOTONG / SCROLL)      -->
        <!-- ===================================================================== -->
        <div v-if="showRecordInstallmentModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                
                <!-- Fixed Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/70">
                    <div>
                        <span class="text-[10px] font-black uppercase text-indigo-600 tracking-wider">Modul Petugas Koperasi</span>
                        <h3 class="text-base font-black text-slate-900">Pencatatan Pembayaran Cicilan</h3>
                    </div>
                    <button @click="showRecordInstallmentModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <!-- Scrollable Body -->
                <form id="recordInstallmentForm" @submit.prevent="submitRecordInstallment" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <!-- Pilih Pinjaman / Anggota -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Pilih Pinjaman Anggota</label>
                        <select 
                            :value="installmentForm.loan_id"
                            @change="onSelectLoanForInstallment($event.target.value)"
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold focus:ring-2 focus:ring-indigo-500"
                            required
                        >
                            <option value="">-- Pilih Anggota / Pinjaman Aktif --</option>
                            <option v-for="l in activeLoansList" :key="l.id" :value="l.id">
                                {{ l.loan_code }} - {{ l.user?.name }} (Sisa: {{ formatRupiah(l.remaining_amount) }})
                            </option>
                        </select>
                    </div>

                    <!-- Info Ringkasan Pinjaman Terpilih -->
                    <div v-if="targetLoanForInstallment" class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Plafon Awal</span>
                            <span class="text-xs font-black text-indigo-950">{{ formatRupiah(targetLoanForInstallment.amount_approved) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Cicilan / Bln</span>
                            <span class="text-xs font-black text-emerald-700">{{ formatRupiah(targetLoanForInstallment.monthly_installment) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Sisa Pokok</span>
                            <span class="text-xs font-black text-rose-700">{{ formatRupiah(targetLoanForInstallment.remaining_amount) }}</span>
                        </div>
                    </div>

                    <!-- Nominal Pembayaran Cicilan (Fleksibel / Bebas) -->
                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <label class="font-bold text-slate-700 block">Nominal Cicilan Dibayarkan (Bebas)</label>
                            <span class="font-black text-emerald-700 text-sm">{{ formatRupiah(installmentForm.amount_paid) }}</span>
                        </div>
                        <input 
                            v-model.number="installmentForm.amount_paid"
                            type="number"
                            step="10000"
                            min="10000"
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-slate-900 focus:ring-2 focus:ring-indigo-500"
                            required
                        />
                        <!-- Shortcut Buttons -->
                        <div v-if="targetLoanForInstallment" class="flex gap-2 pt-1">
                            <button type="button" @click="setQuickAmount('monthly')" class="flex-1 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold transition">
                                Sesuai Angsuran (1 Bln)
                            </button>
                            <button type="button" @click="setQuickAmount('double')" class="flex-1 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[10px] font-bold transition">
                                Bayar 2 Bulan
                            </button>
                            <button type="button" @click="setQuickAmount('full')" class="flex-1 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-[10px] font-black transition">
                                Pelunasan Penuh
                            </button>
                        </div>
                    </div>

                    <!-- Tanggal Bayar & Metode -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700 block">Tanggal Pembayaran</label>
                            <input v-model="installmentForm.payment_date" type="date" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold" required />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700 block">Metode Pembayaran</label>
                            <select v-model="installmentForm.payment_method" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold">
                                <option value="potong_gaji">Potong Gaji Dinas</option>
                                <option value="tunai">Setor Tunai</option>
                                <option value="transfer">Transfer Bank</option>
                            </select>
                        </div>
                    </div>

                    <!-- Catatan -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Catatan / Keterangan Pembayaran</label>
                        <input v-model="installmentForm.notes" type="text" placeholder="Contoh: Cicilan gaji September 2026..." class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs" />
                    </div>
                </form>

                <!-- Fixed Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex gap-3 shrink-0">
                    <button type="button" @click="showRecordInstallmentModal = false" class="flex-1 py-3 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-2xl border border-slate-200 transition uppercase text-xs">
                        Batal
                    </button>
                    <button form="recordInstallmentForm" type="submit" :disabled="installmentForm.processing" class="flex-[2] py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-indigo-600/30">
                        {{ installmentForm.processing ? 'Menyimpan...' : 'Simpan & Terbitkan Kuitansi' }}
                    </button>
                </div>

            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: PERSETUJUAN PINJAMAN (ANTI-TERPOTONG / SCROLL)                  -->
        <!-- ===================================================================== -->
        <div v-if="showApproveModal && selectedLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                
                <!-- Fixed Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/70">
                    <div>
                        <span class="text-[10px] font-black uppercase text-emerald-600 tracking-wider">Otoritas Persetujuan</span>
                        <h3 class="text-base font-black text-slate-900">Setujui & Cairkan Pinjaman</h3>
                    </div>
                    <button @click="showApproveModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <!-- Scrollable Body -->
                <form id="approveLoanForm" @submit.prevent="submitApproveLoan" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <div class="p-4 bg-slate-50 rounded-2xl text-xs space-y-1 border border-slate-200">
                        <div class="text-slate-500 font-bold">Pemohon: <span class="text-slate-900 font-black uppercase">{{ selectedLoan.user?.name }}</span> ({{ selectedLoan.user?.pangkat }} {{ selectedLoan.user?.nrp }})</div>
                        <div class="text-slate-500">Nominal Diajukan: <b class="text-slate-900">{{ formatRupiah(selectedLoan.amount_requested) }}</b></div>
                        <div class="text-slate-500 italic">"{{ selectedLoan.purpose }}"</div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Nominal Plafon Disetujui (Rp)</label>
                        <input v-model.number="approveForm.amount_approved" type="number" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-emerald-800 focus:ring-2 focus:ring-emerald-500" required />
                    </div>

                    <div class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-xs font-black text-emerald-900 block">Tenor Otomatis: 10 Bulan</span>
                            <span class="text-[10px] text-emerald-700">Bunga 0% (Jatuh tempo setiap tanggal 1)</span>
                        </div>
                        <span class="text-xs font-black text-emerald-800 bg-emerald-100 px-3 py-1 rounded-xl">
                            {{ formatRupiah(Math.round(approveForm.amount_approved / 10)) }} / Bln
                        </span>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Catatan Otoritas (Opsional)</label>
                        <input v-model="approveForm.notes" type="text" placeholder="Disetujui untuk pembiayaan kedinasan..." class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs" />
                    </div>
                </form>

                <!-- Fixed Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex gap-3 shrink-0">
                    <button type="button" @click="showApproveModal = false" class="flex-1 py-3 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-2xl border border-slate-200 transition uppercase text-xs">
                        Batal
                    </button>
                    <button form="approveLoanForm" type="submit" :disabled="approveForm.processing" class="flex-[2] py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-emerald-600/30">
                        {{ approveForm.processing ? 'Menyimpan...' : 'Setujui & Cairkan Dana' }}
                    </button>
                </div>

            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: PENOLAKAN PINJAMAN (ANTI-TERPOTONG / SCROLL)                   -->
        <!-- ===================================================================== -->
        <div v-if="showRejectModal && selectedLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-rose-50/50">
                    <div>
                        <span class="text-[10px] font-black uppercase text-rose-600 tracking-wider">Otoritas Penolakan</span>
                        <h3 class="text-base font-black text-rose-900">Tolak Permohonan Pinjaman</h3>
                    </div>
                    <button @click="showRejectModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <form id="rejectLoanForm" @submit.prevent="submitRejectLoan" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <p class="text-xs text-slate-600">Berikan catatan resmi penolakan untuk personel <b>{{ selectedLoan.user?.name }}</b>:</p>
                    <textarea v-model="rejectForm.rejection_reason" rows="3" placeholder="Alasan penolakan..." class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs focus:ring-2 focus:ring-rose-500" required></textarea>
                </form>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex gap-3 shrink-0">
                    <button type="button" @click="showRejectModal = false" class="flex-1 py-3 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-2xl border border-slate-200 transition uppercase text-xs">
                        Batal
                    </button>
                    <button form="rejectLoanForm" type="submit" :disabled="rejectForm.processing" class="flex-[2] py-3 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-rose-600/30">
                        {{ rejectForm.processing ? 'Menyimpan...' : 'Tolak Pinjaman' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: SETORAN SIMPANAN ANGGOTA                                       -->
        <!-- ===================================================================== -->
        <div v-if="showDepositSavingModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-emerald-50/50">
                    <div>
                        <span class="text-[10px] font-black uppercase text-emerald-600 tracking-wider">Buku Tabungan</span>
                        <h3 class="text-base font-black text-slate-900">Catat Setoran Simpanan Anggota</h3>
                    </div>
                    <button @click="showDepositSavingModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <form id="depositSavingForm" @submit.prevent="submitDepositSaving" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Pilih Anggota</label>
                        <select v-model="savingDepositForm.user_id" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold focus:ring-2 focus:ring-emerald-500" required>
                            <option value="">-- Pilih Anggota --</option>
                            <option v-for="p in personels" :key="p.id" :value="p.id">{{ p.name }} ({{ p.pangkat }} {{ p.nrp }})</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Jenis Simpanan</label>
                        <select v-model="savingDepositForm.saving_type" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold">
                            <option value="wajib">Simpanan Wajib Bulanan</option>
                            <option value="pokok">Simpanan Pokok Anggota</option>
                            <option value="sukarela">Simpanan Sukarela Bebas</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Nominal Setoran (Rp)</label>
                        <input v-model.number="savingDepositForm.amount" type="number" step="50000" min="10000" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-emerald-800" required />
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Catatan / Keterangan</label>
                        <input v-model="savingDepositForm.notes" type="text" placeholder="Setoran via potong gaji..." class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs" />
                    </div>
                </form>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex gap-3 shrink-0">
                    <button type="button" @click="showDepositSavingModal = false" class="flex-1 py-3 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-2xl border border-slate-200 transition uppercase text-xs">
                        Batal
                    </button>
                    <button form="depositSavingForm" type="submit" :disabled="savingDepositForm.processing" class="flex-[2] py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-emerald-600/30">
                        {{ savingDepositForm.processing ? 'Menyimpan...' : 'Simpan Setoran' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: PENARIKAN SIMPANAN SUKARELA                                    -->
        <!-- ===================================================================== -->
        <div v-if="showWithdrawSavingModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-rose-50/50">
                    <div>
                        <span class="text-[10px] font-black uppercase text-rose-600 tracking-wider">Tabungan Bebas</span>
                        <h3 class="text-base font-black text-slate-900">Penarikan Simpanan Sukarela</h3>
                    </div>
                    <button @click="showWithdrawSavingModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <form id="withdrawSavingForm" @submit.prevent="submitWithdrawSaving" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Pilih Anggota</label>
                        <select v-model="withdrawSavingForm.user_id" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold focus:ring-2 focus:ring-rose-500" required>
                            <option value="">-- Pilih Anggota --</option>
                            <option v-for="p in personels" :key="p.id" :value="p.id">{{ p.name }} ({{ p.pangkat }} {{ p.nrp }})</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Nominal Penarikan (Rp)</label>
                        <input v-model.number="withdrawSavingForm.amount" type="number" step="50000" min="50000" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-rose-700" required />
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Keperluan Penarikan</label>
                        <input v-model="withdrawSavingForm.notes" type="text" placeholder="Kebutuhan mendesak..." class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs" />
                    </div>
                </form>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex gap-3 shrink-0">
                    <button type="button" @click="showWithdrawSavingModal = false" class="flex-1 py-3 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-2xl border border-slate-200 transition uppercase text-xs">
                        Batal
                    </button>
                    <button form="withdrawSavingForm" type="submit" :disabled="withdrawSavingForm.processing" class="flex-[2] py-3 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-rose-600/30">
                        {{ withdrawSavingForm.processing ? 'Menyimpan...' : 'Bukukan Penarikan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: DETAIL KARTU ANGSURAN PINJAMAN ANGGOTA                         -->
        <!-- ===================================================================== -->
        <div v-if="showLoanDetailModal && selectedLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
            <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl flex flex-col max-h-[92vh] overflow-hidden my-auto border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/70">
                    <div>
                        <span class="text-[10px] font-black uppercase text-indigo-600 tracking-wider">Kartu Angsuran 10 Bulan</span>
                        <h3 class="text-base font-black text-slate-900">Rincian Angsuran {{ selectedLoan.loan_code }}</h3>
                    </div>
                    <button @click="showLoanDetailModal = false" class="w-8 h-8 rounded-xl bg-slate-200/60 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-black transition">
                        &times;
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200 grid grid-cols-4 gap-3 text-center">
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Anggota</span>
                            <span class="text-xs font-black text-indigo-950 truncate block">{{ selectedLoan.user?.name }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Total Pinjaman</span>
                            <span class="text-xs font-black text-indigo-950">{{ formatRupiah(selectedLoan.total_loan_amount) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Terbayar</span>
                            <span class="text-xs font-black text-emerald-700">{{ formatRupiah(selectedLoan.total_paid) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Sisa Hutang</span>
                            <span class="text-xs font-black text-rose-700">{{ formatRupiah(selectedLoan.remaining_amount) }}</span>
                        </div>
                    </div>

                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                <th class="py-2.5 px-3 text-center">Bulan</th>
                                <th class="py-2.5 px-3">Jatuh Tempo</th>
                                <th class="py-2.5 px-3 text-right">Tagihan</th>
                                <th class="py-2.5 px-3 text-right">Dibayar</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Kuitansi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="inst in selectedLoan.installments" :key="inst.id">
                                <td class="py-2.5 px-3 text-center font-bold">#{{ inst.installment_no }}</td>
                                <td class="py-2.5 px-3 font-mono font-bold text-slate-800">{{ formatDate(inst.due_date) }}</td>
                                <td class="py-2.5 px-3 text-right font-black text-slate-900">{{ formatRupiah(inst.amount_due) }}</td>
                                <td class="py-2.5 px-3 text-right font-black text-emerald-700">{{ formatRupiah(inst.amount_paid) }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span :class="inst.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase">
                                        {{ inst.status === 'paid' ? 'LUNAS' : 'BELUM' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <a v-if="inst.status === 'paid'" :href="route('simpan-pinjam.receipt', inst.id)" target="_blank" class="text-[10px] font-black text-emerald-700 underline">
                                        Kuitansi PDF
                                    </a>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex justify-end shrink-0">
                    <button type="button" @click="showLoanDetailModal = false" class="px-6 py-2.5 bg-slate-900 text-white font-black rounded-xl text-xs uppercase transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
