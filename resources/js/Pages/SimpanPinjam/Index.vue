<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    myAccount: Object,
    myActiveLoan: Object,
    myLoanHistory: Array,
    mySavingsTransactions: Array,
    isPengurus: Boolean,
    metrics: Object,
    allLoans: Object,
    allMembers: Object,
    allMutations: Array,
    allInstallments: Array,
    personels: Array,
    filters: Object,
});

// Active Tab
const activeTab = ref('overview'); // 'overview', 'savings', 'admin_loans', 'admin_installments', 'admin_savings', 'admin_ledger'

// Modal States
const showApplyModal = ref(false);
const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showRecordInstallmentModal = ref(false);
const showDepositSavingModal = ref(false);
const showWithdrawSavingModal = ref(false);
const showLoanDetailModal = ref(false);

const selectedLoan = ref(null);

// Form Pengajuan Pinjaman Mandiri
const applyForm = useForm({
    loan_type: 'reguler',
    amount_requested: 5000000,
    duration_months: 10,
    purpose: '',
    document: null,
});

// Kalkulator Cicilan Realtime
const calculatedMonthlyInstallment = computed(() => {
    const amount = Number(applyForm.amount_requested) || 0;
    const months = Number(applyForm.duration_months) || 1;
    return Math.round(amount / months);
});

const submitApplyLoan = () => {
    applyForm.post(route('simpan-pinjam.apply-loan'), {
        onSuccess: () => {
            showApplyModal.value = false;
            applyForm.reset();
            Swal.fire('BERHASIL DIAJUKAN', 'Pengajuan pinjaman Anda telah diterima dan akan diverifikasi oleh pengurus koperasi.', 'success');
        },
        onError: (err) => {
            Swal.fire('GAGAL', Object.values(err)[0] || 'Periksa kembali formulir pengajuan Anda.', 'error');
        }
    });
};

// Form Persetujuan Pinjaman (Pengurus)
const approveForm = useForm({
    amount_approved: 0,
    duration_months: 10,
    interest_rate_percent: 0,
    notes: '',
});

const openApproveModal = (loan) => {
    selectedLoan.value = loan;
    approveForm.amount_approved = loan.amount_requested;
    approveForm.duration_months = loan.duration_months;
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

// Form Penolakan Pinjaman (Pengurus)
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
            installmentForm.amount_paid = found.monthly_installment;
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

// Helpers
const formatRupiah = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Progress Pelunasan
const loanRepaymentProgress = computed(() => {
    if (!props.myActiveLoan) return 0;
    const total = Number(props.myActiveLoan.total_loan_amount) || 1;
    const paid = Number(props.myActiveLoan.total_paid) || 0;
    return Math.min(100, Math.round((paid / total) * 100));
});

// Daftar Pinjaman Aktif untuk Select Picker
const activeLoansList = computed(() => {
    if (!props.allLoans?.data) return [];
    return props.allLoans.data.filter(l => l.status === 'active');
});
</script>

<template>
    <Head title="Unit Simpan Pinjam (Koperasi SINDEN)" />

    <AuthenticatedLayout>
        <div class="space-y-8 font-sans pb-16 max-w-7xl mx-auto">

            <!-- ================================================================= -->
            <!-- HEADER MODUL & BANNER RESMI KEDINASAN                             -->
            <!-- ================================================================= -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 rounded-3xl shadow-xl text-white flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute left-1/3 -top-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="space-y-2 relative z-10">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-black uppercase rounded-full tracking-wider">
                            Koperasi Primkopal / SINDEN
                        </span>
                        <span v-if="isPengurus" class="px-3 py-1 bg-violet-500/20 text-violet-300 border border-violet-500/30 text-[10px] font-black uppercase rounded-full tracking-wider">
                            Otoritas Pengurus Aktif
                        </span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white uppercase">
                        Unit Simpan Pinjam Personel
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 max-w-2xl font-medium leading-relaxed">
                        Sistem keuangan & pinjaman terintegrasi satuan berstandar perbankan modern. Pengajuan mandiri, buku tabungan anggota, serta pencatatan cicilan angsuran transparan.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3 relative z-10 w-full md:w-auto">
                    <button 
                        @click="showApplyModal = true"
                        type="button"
                        class="flex-1 md:flex-none px-5 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-900/40 transition active:scale-95 flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                        <span>Ajukan Pinjaman</span>
                    </button>

                    <button 
                        v-if="isPengurus"
                        @click="activeTab = 'admin_installments'; showRecordInstallmentModal = true"
                        type="button"
                        class="flex-1 md:flex-none px-5 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-indigo-900/40 transition active:scale-95 flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Catat Cicilan</span>
                    </button>

                    <a 
                        v-if="isPengurus"
                        :href="route('simpan-pinjam.export-ledger')"
                        target="_blank"
                        class="px-4 py-3 bg-slate-800/80 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs uppercase rounded-2xl transition flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Cetak Rekap</span>
                    </a>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- HERO FINANSIAL: DIGITAL MEMBER CARD & ACTIVE LOAN PROGRESS        -->
            <!-- ================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- 1. KARTU ANGGOTA DIGITAL / VIRTUAL BANKING CARD (5 COLS) -->
                <div class="lg:col-span-5 bg-gradient-to-br from-[#0F172A] via-[#1E293B] to-[#0A2540] rounded-3xl p-6 sm:p-7 shadow-2xl text-white relative overflow-hidden flex flex-col justify-between border border-slate-700/60 min-h-[240px]">
                    <div class="absolute right-0 top-0 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <!-- Header Kartu -->
                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-2.5">
                            <!-- Emblem Logo Koperasi -->
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center font-black text-slate-950 text-xs shadow-md">
                                KOP
                            </div>
                            <div>
                                <span class="text-[11px] font-black uppercase tracking-wider text-slate-200 block leading-none">Koperasi Sinden</span>
                                <span class="text-[9px] text-emerald-400 font-bold tracking-widest uppercase">TNI Angkatan Laut</span>
                            </div>
                        </div>

                        <!-- Chip IC Graphic -->
                        <div class="w-10 h-8 rounded-lg bg-gradient-to-r from-amber-300 via-amber-200 to-yellow-400 border border-amber-400/80 shadow-inner flex items-center justify-center relative">
                            <div class="w-6 h-5 border border-amber-600/40 rounded-sm"></div>
                        </div>
                    </div>

                    <!-- Nomor Rekening Anggota -->
                    <div class="my-5 relative z-10">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Nomor Rekening Anggota</div>
                        <div class="text-xl sm:text-2xl font-mono font-black tracking-widest text-emerald-300">
                            {{ myAccount?.member_number || 'KOP-000000' }}
                        </div>
                    </div>

                    <!-- Saldo & Identitas Pemegang -->
                    <div class="pt-3 border-t border-slate-700/60 flex items-end justify-between relative z-10">
                        <div>
                            <div class="text-[9px] uppercase font-bold text-slate-400">Total Simpanan Saya</div>
                            <div class="text-lg font-black text-white">
                                {{ formatRupiah(myAccount?.total_simpanan) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] uppercase font-bold text-slate-400">Pemilik Rekening</div>
                            <div class="text-xs font-bold text-slate-200 uppercase tracking-tight">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-[10px] text-emerald-400 font-mono">
                                {{ $page.props.auth.user.pangkat }} {{ $page.props.auth.user.nrp }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. STATUS PINJAMAN BERJALAN & PROGRESS BAR (7 COLS) -->
                <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-7 shadow-xs border border-slate-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Status Pinjaman Saya</span>
                            <h3 class="text-lg font-black text-slate-900">
                                {{ myActiveLoan ? myActiveLoan.loan_code : 'Tidak Ada Pinjaman Aktif' }}
                            </h3>
                        </div>

                        <div v-if="myActiveLoan">
                            <span v-if="myActiveLoan.status === 'active'" class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-black uppercase rounded-full tracking-wider">
                                Aktif Berjalan
                            </span>
                            <span v-else-if="myActiveLoan.status === 'pending'" class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 text-xs font-black uppercase rounded-full tracking-wider">
                                Menunggu Persetujuan
                            </span>
                            <span v-else class="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-black uppercase rounded-full tracking-wider">
                                {{ myActiveLoan.status }}
                            </span>
                        </div>

                        <div v-else>
                            <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-black uppercase rounded-full tracking-wider">
                                Bebas Tanggungan
                            </span>
                        </div>
                    </div>

                    <!-- Jika Ada Pinjaman Aktif -->
                    <div v-if="myActiveLoan && myActiveLoan.status === 'active'" class="space-y-4 my-4">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100">
                                <div class="text-[10px] text-slate-500 font-bold uppercase">Plafon Pinjaman</div>
                                <div class="text-sm font-black text-slate-900 mt-0.5">{{ formatRupiah(myActiveLoan.total_loan_amount) }}</div>
                            </div>
                            <div class="p-3 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                                <div class="text-[10px] text-emerald-700 font-bold uppercase">Sudah Dibayar</div>
                                <div class="text-sm font-black text-emerald-800 mt-0.5">{{ formatRupiah(myActiveLoan.total_paid) }}</div>
                            </div>
                            <div class="p-3 rounded-2xl bg-orange-50/60 border border-orange-100">
                                <div class="text-[10px] text-orange-700 font-bold uppercase">Sisa Pokok</div>
                                <div class="text-sm font-black text-orange-800 mt-0.5">{{ formatRupiah(myActiveLoan.remaining_amount) }}</div>
                            </div>
                            <div class="p-3 rounded-2xl bg-blue-50/60 border border-blue-100">
                                <div class="text-[10px] text-blue-700 font-bold uppercase">Angsuran / Bln</div>
                                <div class="text-sm font-black text-blue-800 mt-0.5">{{ formatRupiah(myActiveLoan.monthly_installment) }}</div>
                            </div>
                        </div>

                        <!-- Progress Bar Pelunasan -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center text-xs font-extrabold text-slate-700">
                                <span>Progres Pelunasan</span>
                                <span class="text-emerald-600">{{ loanRepaymentProgress }}% Terbayar</span>
                            </div>
                            <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden p-0.5">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500" :style="{ width: loanRepaymentProgress + '%' }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Jika Ada Pinjaman Sedang Pending Review -->
                    <div v-else-if="myActiveLoan && myActiveLoan.status === 'pending'" class="py-6 text-center space-y-2">
                        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h4 class="font-extrabold text-slate-900 text-sm">Pengajuan Sedang Ditinjau Pengurus</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            Pengajuan sebesar {{ formatRupiah(myActiveLoan.amount_requested) }} tenor {{ myActiveLoan.duration_months }} bulan sedang dalam proses evaluasi oleh pengurus koperasi.
                        </p>
                    </div>

                    <!-- Jika Tidak Ada Pinjaman -->
                    <div v-else class="py-6 text-center space-y-2">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h4 class="font-extrabold text-slate-900 text-sm">Rekening Pinjaman Bersih</h4>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">
                            Anda tidak memiliki tanggungan cicilan pinjaman berjalan saat ini. Butuh dana darurat atau renovasi? Anda dapat mengajukan pinjaman dengan mudah.
                        </p>
                    </div>

                    <!-- Footer Action Card -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs text-slate-500 font-medium">Batas Maksimal Plafon: Rp 50.000.000</span>
                        <button 
                            v-if="myActiveLoan && myActiveLoan.status === 'active'"
                            @click="showLoanDetailModal = true"
                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-1"
                        >
                            <span>Lihat Kartu Angsuran</span>
                            <span>&rarr;</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- ================================================================= -->
            <!-- METRIK STATISTIK PENGURUS & KOPERASI (HANYA PENGURUS / ADMIN)     -->
            <!-- ================================================================= -->
            <div v-if="isPengurus && metrics" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Kas Koperasi</span>
                    <span class="text-base font-black text-slate-900 block">{{ formatRupiah(metrics.kas_koperasi) }}</span>
                    <span class="text-[10px] text-emerald-600 font-bold">Saldo Terkini</span>
                </div>

                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Pinjaman Beredar</span>
                    <span class="text-base font-black text-orange-600 block">{{ formatRupiah(metrics.pinjaman_aktif_total) }}</span>
                    <span class="text-[10px] text-slate-500 font-medium">Sisa Pokok Anggota</span>
                </div>

                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Simpanan Anggota</span>
                    <span class="text-base font-black text-emerald-700 block">{{ formatRupiah(metrics.simpanan_anggota_total) }}</span>
                    <span class="text-[10px] text-slate-500 font-medium">Pokok + Wajib + Rela</span>
                </div>

                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Cicilan Bulan Ini</span>
                    <span class="text-base font-black text-indigo-600 block">{{ formatRupiah(metrics.cicilan_masuk_bulan_ini) }}</span>
                    <span class="text-[10px] text-emerald-600 font-bold">Arus Kas Masuk</span>
                </div>

                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Butuh Approval</span>
                    <span class="text-base font-black text-amber-600 block">{{ metrics.pengajuan_pending_count }} Pengajuan</span>
                    <span class="text-[10px] text-amber-600 font-bold">Antrean Review</span>
                </div>

                <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-xs space-y-1">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Anggota Aktif</span>
                    <span class="text-base font-black text-slate-900 block">{{ metrics.total_anggota_count }} Personel</span>
                    <span class="text-[10px] text-slate-500 font-medium">Buku Rekening</span>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- TAB NAVIGASI MODUL SIMPAN PINJAM                                  -->
            <!-- ================================================================= -->
            <div class="flex items-center gap-2 overflow-x-auto border-b border-slate-200 pb-2">
                <button 
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-white text-slate-600 hover:bg-slate-100'"
                    class="px-5 py-2.5 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition whitespace-nowrap"
                >
                    Dasbor & Riwayat Saya
                </button>

                <button 
                    @click="activeTab = 'savings'"
                    :class="activeTab === 'savings' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-white text-slate-600 hover:bg-slate-100'"
                    class="px-5 py-2.5 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition whitespace-nowrap"
                >
                    Simpanan & Tabungan
                </button>

                <!-- TAB KHUSUS PENGURUS -->
                <template v-if="isPengurus">
                    <button 
                        @click="activeTab = 'admin_loans'"
                        :class="activeTab === 'admin_loans' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="px-5 py-2.5 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition whitespace-nowrap flex items-center gap-1.5"
                    >
                        <span>Kelola Pinjaman</span>
                        <span v-if="metrics?.pengajuan_pending_count > 0" class="w-5 h-5 rounded-full bg-amber-400 text-slate-950 font-black text-[10px] flex items-center justify-center">
                            {{ metrics.pengajuan_pending_count }}
                        </span>
                    </button>

                    <button 
                        @click="activeTab = 'admin_installments'"
                        :class="activeTab === 'admin_installments' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="px-5 py-2.5 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition whitespace-nowrap"
                    >
                        Pencatatan Cicilan
                    </button>

                    <button 
                        @click="activeTab = 'admin_savings'"
                        :class="activeTab === 'admin_savings' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="px-5 py-2.5 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition whitespace-nowrap"
                    >
                        Rekening Anggota
                    </button>

                    <button 
                        @click="activeTab = 'admin_ledger'"
                        :class="activeTab === 'admin_ledger' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="px-5 py-2.5 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition whitespace-nowrap"
                    >
                        Buku Kas Koperasi
                    </button>
                </template>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 1: DASBOR & RIWAYAT SAYA                               -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <!-- Tabel Riwayat Pinjaman Saya -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">Riwayat Pengajuan & Pinjaman Saya</h3>
                            <p class="text-xs text-slate-500">Daftar seluruh transaksi pembiayaan dan pinjaman yang pernah Anda ajukan.</p>
                        </div>
                        <button 
                            @click="showApplyModal = true"
                            class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 rounded-xl font-bold text-xs uppercase transition"
                        >
                            + Pengajuan Baru
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                    <th class="py-3 px-3">Kode Pinjaman</th>
                                    <th class="py-3 px-3">Jenis</th>
                                    <th class="py-3 px-3 text-right">Plafon</th>
                                    <th class="py-3 px-3 text-center">Tenor</th>
                                    <th class="py-3 px-3 text-right">Angsuran/Bln</th>
                                    <th class="py-3 px-3 text-right">Sisa Hutang</th>
                                    <th class="py-3 px-3 text-center">Status</th>
                                    <th class="py-3 px-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="loan in myLoanHistory" :key="loan.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3.5 px-3 font-mono font-bold text-indigo-600">{{ loan.loan_code }}</td>
                                    <td class="py-3.5 px-3 font-bold uppercase text-slate-800">{{ loan.loan_type }}</td>
                                    <td class="py-3.5 px-3 text-right font-bold text-slate-900">{{ formatRupiah(loan.amount_approved || loan.amount_requested) }}</td>
                                    <td class="py-3.5 px-3 text-center font-bold">{{ loan.duration_months }} Bln</td>
                                    <td class="py-3.5 px-3 text-right font-bold text-slate-700">{{ formatRupiah(loan.monthly_installment) }}</td>
                                    <td class="py-3.5 px-3 text-right font-extrabold text-orange-600">{{ formatRupiah(loan.remaining_amount) }}</td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span v-if="loan.status === 'active'" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full font-extrabold text-[10px] uppercase">Aktif</span>
                                        <span v-else-if="loan.status === 'pending'" class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full font-extrabold text-[10px] uppercase">Review</span>
                                        <span v-else-if="loan.status === 'paid_off'" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-full font-extrabold text-[10px] uppercase">Lunas</span>
                                        <span v-else class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-full font-extrabold text-[10px] uppercase">{{ loan.status }}</span>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        <button 
                                            @click="selectedLoan = loan; showLoanDetailModal = true"
                                            class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] uppercase rounded-lg transition"
                                        >
                                            Kartu Angsuran
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="myLoanHistory.length === 0">
                                    <td colspan="8" class="py-8 text-center text-slate-400 font-medium">Belum ada riwayat pengajuan pinjaman.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 2: SIMPANAN & TABUNGAN                                 -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'savings'" class="space-y-6">
                <!-- 3 Kartu Saldo Simpanan -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-2">
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-black">1</div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 block">Simpanan Pokok</span>
                        <div class="text-2xl font-black text-slate-900">{{ formatRupiah(myAccount?.balance_simpanan_pokok) }}</div>
                        <p class="text-[11px] text-slate-500">Dibayarkan 1 kali pada saat pendaftaran keanggotaan koperasi.</p>
                    </div>

                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-2">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-black">2</div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 block">Simpanan Wajib</span>
                        <div class="text-2xl font-black text-emerald-700">{{ formatRupiah(myAccount?.balance_simpanan_wajib) }}</div>
                        <p class="text-[11px] text-slate-500">Iuran berkala bulanan anggota untuk memperkuat modal usaha bersama.</p>
                    </div>

                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-2">
                        <div class="w-10 h-10 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center font-black">3</div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400 block">Simpanan Sukarela</span>
                        <div class="text-2xl font-black text-violet-700">{{ formatRupiah(myAccount?.balance_simpanan_sukarela) }}</div>
                        <p class="text-[11px] text-slate-500">Tabungan bebas yang dapat disetor atau ditarik sewaktu-waktu sesuai kebutuhan.</p>
                    </div>
                </div>

                <!-- Mutasi Tabungan Saya -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <h3 class="text-base font-extrabold text-slate-900">Buku Mutasi Simpanan</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                    <th class="py-3 px-3">Tanggal</th>
                                    <th class="py-3 px-3">Kode Transaksi</th>
                                    <th class="py-3 px-3">Jenis Simpanan</th>
                                    <th class="py-3 px-3">Tipe</th>
                                    <th class="py-3 px-3 text-right">Nominal</th>
                                    <th class="py-3 px-3 text-right">Saldo Akhir</th>
                                    <th class="py-3 px-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="tx in mySavingsTransactions" :key="tx.id">
                                    <td class="py-3 px-3 font-mono">{{ formatDate(tx.created_at) }}</td>
                                    <td class="py-3 px-3 font-mono font-bold text-slate-700">{{ tx.transaction_code }}</td>
                                    <td class="py-3 px-3 font-bold uppercase">{{ tx.saving_type }}</td>
                                    <td class="py-3 px-3">
                                        <span :class="tx.type === 'deposit' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'" class="px-2 py-0.5 rounded-full font-black text-[10px] uppercase">
                                            {{ tx.type === 'deposit' ? 'SETOR' : 'TARIK' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-black text-slate-900">{{ formatRupiah(tx.amount) }}</td>
                                    <td class="py-3 px-3 text-right font-bold text-emerald-700">{{ formatRupiah(tx.balance_after) }}</td>
                                    <td class="py-3 px-3 text-slate-500">{{ tx.notes || '-' }}</td>
                                </tr>
                                <tr v-if="mySavingsTransactions.length === 0">
                                    <td colspan="7" class="py-8 text-center text-slate-400">Belum ada mutasi simpanan tercatat.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 3: KELOLA PINJAMAN & PERSETUJUAN (PENGURUS)            -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_loans' && isPengurus" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">Antrean & Riwayat Pinjaman Seluruh Anggota</h3>
                            <p class="text-xs text-slate-500">Tinjau permohonan baru, setujui plafon & tenor, serta pantau pelunasan anggota.</p>
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
                                    <td class="py-3.5 px-3 font-mono font-bold text-indigo-600">{{ loan.loan_code }}</td>
                                    <td class="py-3.5 px-3 font-bold uppercase text-slate-900">{{ loan.user?.name || '-' }}</td>
                                    <td class="py-3.5 px-3 text-slate-600">{{ loan.user?.pangkat }} ({{ loan.user?.nrp }})</td>
                                    <td class="py-3.5 px-3 text-right font-black text-slate-900">{{ formatRupiah(loan.amount_requested) }}</td>
                                    <td class="py-3.5 px-3 text-center font-bold">{{ loan.duration_months }} Bln</td>
                                    <td class="py-3.5 px-3 text-right font-black text-orange-600">{{ formatRupiah(loan.remaining_amount) }}</td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span v-if="loan.status === 'active'" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full font-black text-[10px] uppercase">Aktif</span>
                                        <span v-else-if="loan.status === 'pending'" class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full font-black text-[10px] uppercase">Review</span>
                                        <span v-else-if="loan.status === 'paid_off'" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-full font-black text-[10px] uppercase">Lunas</span>
                                        <span v-else class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-full font-black text-[10px] uppercase">{{ loan.status }}</span>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Tombol Approval Jika Pending -->
                                            <template v-if="loan.status === 'pending'">
                                                <button 
                                                    @click="openApproveModal(loan)"
                                                    class="px-2.5 py-1 bg-emerald-600 text-white font-black text-[10px] uppercase rounded-lg hover:bg-emerald-700 transition shadow-xs"
                                                >
                                                    Setujui
                                                </button>
                                                <button 
                                                    @click="openRejectModal(loan)"
                                                    class="px-2.5 py-1 bg-rose-100 text-rose-700 font-bold text-[10px] uppercase rounded-lg hover:bg-rose-600 hover:text-white transition"
                                                >
                                                    Tolak
                                                </button>
                                            </template>

                                            <!-- Tombol Catat Cicilan Langsung Jika Aktif -->
                                            <template v-else-if="loan.status === 'active'">
                                                <button 
                                                    @click="openRecordInstallmentDirect(loan)"
                                                    class="px-2.5 py-1 bg-indigo-600 text-white font-black text-[10px] uppercase rounded-lg hover:bg-indigo-700 transition shadow-xs flex items-center gap-1"
                                                >
                                                    <span>Bayar Cicilan</span>
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
                                    <td colspan="8" class="py-8 text-center text-slate-400">Tidak ada data pinjaman.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 4: PENCATATAN CICILAN ANGSURAN (FITUR UTAMA PETUGAS)  -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_installments' && isPengurus" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">Pencatatan Pembayaran Cicilan Anggota</h3>
                            <p class="text-xs text-slate-500">Input setoran cicilan dengan nominal berapa pun. Sistem otomatis menghitung sisa hutang dan menerbitkan kuitansi.</p>
                        </div>
                        <button 
                            @click="showRecordInstallmentModal = true"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-md shadow-indigo-500/20 transition flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                            <span>Form Input Cicilan Baru</span>
                        </button>
                    </div>

                    <!-- Riwayat Seluruh Pembayaran Cicilan Terkini -->
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
                                            class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-200 font-extrabold text-[10px] uppercase rounded-lg transition"
                                        >
                                            PDF Slip
                                        </a>
                                    </td>
                                </tr>
                                <tr v-if="!allInstallments || allInstallments.length === 0">
                                    <td colspan="9" class="py-8 text-center text-slate-400">Belum ada riwayat pembayaran cicilan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 5: REKENING & SIMPANAN ANGGOTA (PENGURUS)              -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_savings' && isPengurus" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">Buku Rekening Simpanan Seluruh Anggota</h3>
                            <p class="text-xs text-slate-500">Pantau akumulasi simpanan pokok, wajib, dan sukarela per personel.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button 
                                @click="showDepositSavingModal = true"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs uppercase rounded-xl transition"
                            >
                                + Setor Simpanan
                            </button>
                            <button 
                                @click="showWithdrawSavingModal = true"
                                class="px-4 py-2 bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 font-extrabold text-xs uppercase rounded-xl transition"
                            >
                                Tarik Sukarela
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
                                    <th class="py-3 px-3 text-right">Simp. Sukarela</th>
                                    <th class="py-3 px-3 text-right">Total Tabungan</th>
                                    <th class="py-3 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="acc in allMembers?.data" :key="acc.id" class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-900">{{ acc.member_number }}</td>
                                    <td class="py-3 px-3 font-bold uppercase">{{ acc.user?.name }}</td>
                                    <td class="py-3 px-3 text-slate-600">{{ acc.user?.pangkat }} ({{ acc.user?.nrp }})</td>
                                    <td class="py-3 px-3 text-right font-mono">{{ formatRupiah(acc.balance_simpanan_pokok) }}</td>
                                    <td class="py-3 px-3 text-right font-mono text-emerald-700">{{ formatRupiah(acc.balance_simpanan_wajib) }}</td>
                                    <td class="py-3 px-3 text-right font-mono text-violet-700">{{ formatRupiah(acc.balance_simpanan_sukarela) }}</td>
                                    <td class="py-3 px-3 text-right font-black text-slate-900">{{ formatRupiah(acc.total_simpanan) }}</td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 font-extrabold text-[10px] rounded-full uppercase">Aktif</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KONTEN TAB 6: BUKU KAS & MUTASI KOPERASI (PENGURUS)              -->
            <!-- ================================================================= -->
            <div v-if="activeTab === 'admin_ledger' && isPengurus" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900">Arus Kas Masuk & Keluar Koperasi</h3>
                            <p class="text-xs text-slate-500">Pencatatan real-time arus dana simpanan, pencairan pinjaman, dan cicilan angsuran.</p>
                        </div>
                        <a 
                            :href="route('simpan-pinjam.export-ledger')" 
                            target="_blank"
                            class="px-4 py-2 bg-slate-900 text-white font-bold text-xs uppercase rounded-xl shadow-xs transition"
                        >
                            Unduh Laporan PDF
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
                                <tr v-for="mut in allMutations" :key="mut.id">
                                    <td class="py-3 px-3 font-mono">{{ formatDate(mut.date) }}</td>
                                    <td class="py-3 px-3 font-mono font-bold text-slate-700">{{ mut.transaction_code }}</td>
                                    <td class="py-3 px-3 uppercase text-[10px] font-bold text-slate-700">{{ mut.category?.replace('_', ' ') }}</td>
                                    <td class="py-3 px-3 text-slate-600">{{ mut.description }}</td>
                                    <td class="py-3 px-3 text-right font-black text-rose-600">{{ mut.type === 'out' ? formatRupiah(mut.amount) : '-' }}</td>
                                    <td class="py-3 px-3 text-right font-black text-emerald-600">{{ mut.type === 'in' ? formatRupiah(mut.amount) : '-' }}</td>
                                    <td class="py-3 px-3 text-right font-black text-slate-900">{{ formatRupiah(mut.balance) }}</td>
                                </tr>
                                <tr v-if="!allMutations || allMutations.length === 0">
                                    <td colspan="7" class="py-8 text-center text-slate-400">Belum ada mutasi arus kas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: FORMULIR PENGAJUAN PINJAMAN MANDIRI PERSONEL                   -->
        <!-- ===================================================================== -->
        <div v-if="showApplyModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6 animate-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase text-emerald-600 tracking-wider">Formulir Mandiri</span>
                        <h3 class="text-lg font-black text-slate-900">Pengajuan Pinjaman Koperasi</h3>
                    </div>
                    <button @click="showApplyModal = false" class="text-slate-400 hover:text-slate-700 text-xl font-black">&times;</button>
                </div>

                <form @submit.prevent="submitApplyLoan" class="space-y-5 text-xs">
                    <!-- Jenis Pinjaman -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Kategori Pinjaman</label>
                        <select v-model="applyForm.loan_type" class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-bold focus:ring-2 focus:ring-emerald-500">
                            <option value="reguler">Pinjaman Reguler (Kesejahteraan Umum)</option>
                            <option value="darurat">Pinjaman Darurat (Kesehatan / Medis)</option>
                            <option value="barang">Pinjaman Pengadaan Barang / Elektronik</option>
                            <option value="pendidikan">Pinjaman Biaya Pendidikan Anak</option>
                        </select>
                    </div>

                    <!-- Nominal Pinjaman -->
                    <div class="space-y-1">
                        <div class="flex justify-between items-center">
                            <label class="font-bold text-slate-700 block">Nominal Pinjaman Yang Diajukan</label>
                            <span class="font-black text-emerald-600 text-sm">{{ formatRupiah(applyForm.amount_requested) }}</span>
                        </div>
                        <input 
                            v-model.number="applyForm.amount_requested"
                            type="number"
                            step="100000"
                            min="500000"
                            max="50000000"
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-slate-900 focus:ring-2 focus:ring-emerald-500"
                            required
                        />
                    </div>

                    <!-- Pilihan Tenor Bulan -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Jangka Waktu Angsuran (Tenor)</label>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            <button 
                                v-for="m in [3, 6, 10, 12, 18, 24, 36]" :key="m"
                                type="button"
                                @click="applyForm.duration_months = m"
                                :class="applyForm.duration_months === m ? 'bg-emerald-600 text-white font-black' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold'"
                                class="py-2.5 rounded-xl text-center text-xs transition"
                            >
                                {{ m }} Bln
                            </button>
                        </div>
                    </div>

                    <!-- Kalkulator Simulasi Cicilan Interaktif -->
                    <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-emerald-800 block">Estimasi Angsuran / Bulan</span>
                            <span class="text-xs text-emerald-600">Tanpa biaya administrasi tersembunyi</span>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-black text-emerald-900">
                                {{ formatRupiah(calculatedMonthlyInstallment) }}
                            </div>
                            <span class="text-[10px] text-emerald-700 font-bold">x {{ applyForm.duration_months }} Bulan</span>
                        </div>
                    </div>

                    <!-- Keperluan Pinjaman -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Alasan & Keperluan Pinjaman</label>
                        <textarea 
                            v-model="applyForm.purpose"
                            rows="2"
                            placeholder="Contoh: Biaya renovasi tempat tinggal / biaya pendaftaran sekolah anak..."
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500"
                            required
                        ></textarea>
                    </div>

                    <!-- Upload Berkas (Opsional) -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Lampiran Pendukung (Opsional)</label>
                        <input 
                            @change="applyForm.document = $event.target.files[0]"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                        />
                    </div>

                    <div class="flex gap-3 pt-3">
                        <button type="button" @click="showApplyModal = false" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl transition uppercase text-xs">Batal</button>
                        <button type="submit" :disabled="applyForm.processing" class="flex-[2] py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-emerald-600/30">Kirim Pengajuan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: PENCATATAN CICILAN OLEH PETUGAS (NOMINAL FLEKSIBEL)            -->
        <!-- ===================================================================== -->
        <div v-if="showRecordInstallmentModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-xl rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6 animate-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase text-indigo-600 tracking-wider">Modul Petugas Koperasi</span>
                        <h3 class="text-lg font-black text-slate-900">Pencatatan Pembayaran Cicilan</h3>
                    </div>
                    <button @click="showRecordInstallmentModal = false" class="text-slate-400 hover:text-slate-700 text-xl font-black">&times;</button>
                </div>

                <form @submit.prevent="submitRecordInstallment" class="space-y-4 text-xs">
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

                    <!-- Ringkasan Info Pinjaman Terpilih -->
                    <div v-if="targetLoanForInstallment" class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Plafon Awal</span>
                            <span class="text-xs font-black text-slate-900">{{ formatRupiah(targetLoanForInstallment.total_loan_amount) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-indigo-700 uppercase block">Angsuran / Bln</span>
                            <span class="text-xs font-black text-indigo-900">{{ formatRupiah(targetLoanForInstallment.monthly_installment) }}</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-orange-700 uppercase block">Sisa Hutang Saat Ini</span>
                            <span class="text-xs font-black text-orange-800">{{ formatRupiah(targetLoanForInstallment.remaining_amount) }}</span>
                        </div>
                    </div>

                    <!-- Input Nominal Pembayaran (Bebas / Fleksibel) -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="font-bold text-slate-700 block">Nominal Cicilan Yang Disetor (Rp)</label>
                            <span class="text-[10px] text-slate-500">Nominal bebas / fleksibel</span>
                        </div>

                        <!-- Tombol Shortcut Cepat -->
                        <div v-if="targetLoanForInstallment" class="flex flex-wrap gap-2">
                            <button 
                                type="button" 
                                @click="setQuickAmount('monthly')"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl text-[11px] transition"
                            >
                                Sesuai Angsuran ({{ formatRupiah(targetLoanForInstallment.monthly_installment) }})
                            </button>
                            <button 
                                type="button" 
                                @click="setQuickAmount('double')"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl text-[11px] transition"
                            >
                                Bayar 2 Bulan
                            </button>
                            <button 
                                type="button" 
                                @click="setQuickAmount('full')"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-emerald-100 text-emerald-700 font-bold rounded-xl text-[11px] transition"
                            >
                                Pelunasan Penuh
                            </button>
                        </div>

                        <input 
                            v-model.number="installmentForm.amount_paid"
                            type="number"
                            step="1000"
                            min="1000"
                            class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-300 font-black text-lg text-indigo-900 focus:ring-2 focus:ring-indigo-500"
                            placeholder="Ketik nominal cicilan..."
                            required
                        />
                    </div>

                    <!-- Tanggal & Metode Pembayaran -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700 block">Tanggal Pembayaran</label>
                            <input 
                                v-model="installmentForm.payment_date"
                                type="date"
                                class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-medium text-xs focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700 block">Metode Pembayaran</label>
                            <select 
                                v-model="installmentForm.payment_method"
                                class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-bold text-xs focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="potong_gaji">Potong Gaji Otomatis</option>
                                <option value="transfer">Transfer Bank / VA</option>
                                <option value="tunai">Tunai / Cash Bendahara</option>
                            </select>
                        </div>
                    </div>

                    <!-- Catatan Kuitansi -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Keterangan / Catatan Bukti</label>
                        <input 
                            v-model="installmentForm.notes"
                            type="text"
                            placeholder="Contoh: Pembayaran cicilan tunai diterima bendahara..."
                            class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="flex gap-3 pt-3">
                        <button type="button" @click="showRecordInstallmentModal = false" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl transition uppercase text-xs">Batal</button>
                        <button type="submit" :disabled="installmentForm.processing" class="flex-[2] py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl transition uppercase text-xs shadow-md shadow-indigo-600/30">Simpan & Terbitkan Kuitansi</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: SETOR SIMPANAN OLEH PETUGAS                                    -->
        <!-- ===================================================================== -->
        <div v-if="showDepositSavingModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-6 sm:p-8 space-y-5 animate-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">Catat Setoran Simpanan Anggota</h3>
                    <button @click="showDepositSavingModal = false" class="text-slate-400 hover:text-slate-700 text-xl font-black">&times;</button>
                </div>

                <form @submit.prevent="submitDepositSaving" class="space-y-4 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Pilih Anggota</label>
                        <select v-model="savingDepositForm.user_id" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-bold" required>
                            <option value="">-- Pilih Anggota --</option>
                            <option v-for="p in personels" :key="p.id" :value="p.id">{{ p.name }} ({{ p.pangkat }} - {{ p.nrp }})</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Jenis Simpanan</label>
                        <select v-model="savingDepositForm.saving_type" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-bold">
                            <option value="pokok">Simpanan Pokok (Pendaftaran)</option>
                            <option value="wajib">Simpanan Wajib (Bulanan)</option>
                            <option value="sukarela">Simpanan Sukarela (Bebas)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Nominal Setoran (Rp)</label>
                        <input v-model.number="savingDepositForm.amount" type="number" step="1000" min="1000" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-black text-slate-900" required />
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Metode</label>
                        <select v-model="savingDepositForm.payment_method" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-bold">
                            <option value="potong_gaji">Potong Gaji</option>
                            <option value="transfer">Transfer</option>
                            <option value="tunai">Tunai</option>
                        </select>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showDepositSavingModal = false" class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl uppercase text-xs">Batal</button>
                        <button type="submit" class="flex-[2] py-2.5 bg-emerald-600 text-white font-black rounded-xl uppercase text-xs shadow-md">Simpan Setoran</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: APPROVAL PINJAMAN OLEH PENGURUS                                -->
        <!-- ===================================================================== -->
        <div v-if="showApproveModal && selectedLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-lg rounded-3xl shadow-2xl p-6 sm:p-8 space-y-5 animate-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">Persetujuan & Pencairan Pinjaman</h3>
                    <button @click="showApproveModal = false" class="text-slate-400 hover:text-slate-700 text-xl font-black">&times;</button>
                </div>

                <div class="p-3 bg-slate-50 rounded-2xl text-xs space-y-1">
                    <div class="text-slate-500 font-bold">Pemohon: <span class="text-slate-900 font-black uppercase">{{ selectedLoan.user?.name }}</span> ({{ selectedLoan.user?.pangkat }} {{ selectedLoan.user?.nrp }})</div>
                    <div class="text-slate-500">Nominal Diajukan: <b class="text-slate-900">{{ formatRupiah(selectedLoan.amount_requested) }}</b> (Tenor: {{ selectedLoan.duration_months }} Bulan)</div>
                    <div class="text-slate-500 italic">"{{ selectedLoan.purpose }}"</div>
                </div>

                <form @submit.prevent="submitApproveLoan" class="space-y-4 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 block">Nominal Plafon Disetujui (Rp)</label>
                        <input v-model.number="approveForm.amount_approved" type="number" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-black text-emerald-800" required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700 block">Tenor (Bulan)</label>
                            <input v-model.number="approveForm.duration_months" type="number" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-bold" required />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700 block">Jasa / Margin (%)</label>
                            <input v-model.number="approveForm.interest_rate_percent" type="number" step="0.1" class="w-full p-2.5 rounded-xl bg-slate-50 border border-slate-300 font-bold" />
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showApproveModal = false" class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl uppercase text-xs">Batal</button>
                        <button type="submit" class="flex-[2] py-2.5 bg-emerald-600 text-white font-black rounded-xl uppercase text-xs shadow-md">Setujui & Cairkan Dana</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: PENOLAKAN PINJAMAN                                             -->
        <!-- ===================================================================== -->
        <div v-if="showRejectModal && selectedLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl p-6 space-y-4">
                <h3 class="text-sm font-black text-rose-600 uppercase tracking-wider">Tolak Pengajuan Pinjaman</h3>
                <p class="text-xs text-slate-600">Berikan alasan penolakan resmi untuk personel {{ selectedLoan.user?.name }}:</p>
                <form @submit.prevent="submitRejectLoan" class="space-y-3 text-xs">
                    <textarea v-model="rejectForm.rejection_reason" rows="3" class="w-full p-2.5 rounded-xl border border-slate-300" placeholder="Alasan penolakan..." required></textarea>
                    <div class="flex gap-2">
                        <button type="button" @click="showRejectModal = false" class="flex-1 py-2 bg-slate-100 font-bold rounded-xl text-xs">Batal</button>
                        <button type="submit" class="flex-1 py-2 bg-rose-600 text-white font-bold rounded-xl text-xs">Tolak Permohonan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- MODAL: DETAIL KARTU ANGSURAN (INSTALLMENT SCHEDULE LEDGER)            -->
        <!-- ===================================================================== -->
        <div v-if="showLoanDetailModal && selectedLoan" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-3xl rounded-3xl shadow-2xl p-6 sm:p-8 space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-mono font-black text-indigo-600">{{ selectedLoan.loan_code }}</span>
                        <h3 class="text-base font-black text-slate-900">Kartu Jadwal Angsuran Pinjaman</h3>
                    </div>
                    <button @click="showLoanDetailModal = false" class="text-slate-400 hover:text-slate-700 text-xl font-black">&times;</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center bg-slate-50 p-3.5 rounded-2xl text-xs">
                    <div>
                        <span class="text-[9px] text-slate-500 font-bold uppercase">Plafon Pinjaman</span>
                        <div class="font-black text-slate-900">{{ formatRupiah(selectedLoan.total_loan_amount) }}</div>
                    </div>
                    <div>
                        <span class="text-[9px] text-emerald-700 font-bold uppercase">Total Terbayar</span>
                        <div class="font-black text-emerald-800">{{ formatRupiah(selectedLoan.total_paid) }}</div>
                    </div>
                    <div>
                        <span class="text-[9px] text-orange-700 font-bold uppercase">Sisa Hutang</span>
                        <div class="font-black text-orange-800">{{ formatRupiah(selectedLoan.remaining_amount) }}</div>
                    </div>
                    <div>
                        <span class="text-[9px] text-blue-700 font-bold uppercase">Angsuran / Bln</span>
                        <div class="font-black text-blue-900">{{ formatRupiah(selectedLoan.monthly_installment) }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-100">
                                <th class="py-2.5 px-3 text-center">Ke</th>
                                <th class="py-2.5 px-3">Jatuh Tempo</th>
                                <th class="py-2.5 px-3 text-right">Tagihan</th>
                                <th class="py-2.5 px-3 text-right">Terbayar</th>
                                <th class="py-2.5 px-3">Tgl Bayar</th>
                                <th class="py-2.5 px-3">No. Kuitansi</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="inst in selectedLoan.installments" :key="inst.id">
                                <td class="py-2 px-3 text-center font-bold">{{ inst.installment_no }}</td>
                                <td class="py-2 px-3 font-mono text-slate-600">{{ formatDate(inst.due_date) }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ formatRupiah(inst.amount_due) }}</td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700">{{ formatRupiah(inst.amount_paid) }}</td>
                                <td class="py-2 px-3 font-mono">{{ formatDate(inst.payment_date) }}</td>
                                <td class="py-2 px-3 font-mono text-[10px] text-slate-500">{{ inst.receipt_number || '-' }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span v-if="inst.status === 'paid'" class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-full font-black text-[9px] uppercase">Lunas</span>
                                    <span v-else class="px-2 py-0.5 bg-slate-100 text-slate-500 rounded-full font-bold text-[9px] uppercase">Belum</span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <a 
                                        v-if="inst.status === 'paid' && inst.receipt_number"
                                        :href="route('simpan-pinjam.receipt', inst.id)"
                                        target="_blank"
                                        class="text-indigo-600 hover:underline font-bold text-[10px]"
                                    >
                                        Slip PDF
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <button @click="showLoanDetailModal = false" class="px-5 py-2.5 bg-slate-900 text-white font-bold text-xs uppercase rounded-xl">Tutup</button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
