<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted } from 'vue';
import axios from 'axios';
import html2pdf from 'html2pdf.js';
import Swal from 'sweetalert2';

/*
 * Agenda Surat (desain Figma "Web / Agenda Surat"):
 * satu buku agenda untuk surat KELUAR (booking nomor, LetterLog) dan surat MASUK (bernomor agenda, wajib disposisi).
 * Tab: Semua · Masuk · Keluar · Disposisi. Chip jenis surat (kategori) untuk menampilkan mis. hanya SKHPP.
 */

const toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true });

const props = defineProps({
    categories: Array,
    sub_categories: Array,
    logs: Object,
    priorities: Object,
    incoming: { type: Array, default: () => [] },
    disposition_options: { type: Object, default: () => ({ roles: {}, instructions: {}, urgencies: {} }) },
    can: { type: Object, default: () => ({}) },
    incoming_format: { type: String, default: 'SM / {urut} / {romawi} / {tahun}' },
    telegram_format: { type: String, default: 'TG / {urut} / {romawi} / {tahun}' },
});

const user = computed(() => usePage().props.auth.user);

// ---------------------------------------------------------------- Filter (sinkron ke URL)
const qs = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
const TABS = ['semua', 'masuk', 'telegram', 'keluar', 'disposisi'];
const tab = ref(TABS.includes(qs.get('tab')) ? qs.get('tab') : 'semua');
const filterCategoryId = ref(qs.get('cat') || '');
const search = ref('');
const startDate = ref('');
const endDate = ref('');
const statusFilter = ref('');
const page = ref(1);
const PER_PAGE = 20;

watch([tab, filterCategoryId], () => {
    const url = new URL(window.location.href);
    tab.value === 'semua' ? url.searchParams.delete('tab') : url.searchParams.set('tab', tab.value);
    filterCategoryId.value ? url.searchParams.set('cat', filterCategoryId.value) : url.searchParams.delete('cat');
    window.history.replaceState(window.history.state, '', url);
});
watch([tab, filterCategoryId, search, startDate, endDate, statusFilter], () => { page.value = 1; });

// ---------------------------------------------------------------- Data baris terpadu
const STATUS = {
    menunggu: { label: 'Belum disposisi', cls: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    didisposisi: { label: 'Didisposisi', cls: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' },
    selesai: { label: 'Selesai', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
    lama: { label: 'Arsip lama', cls: 'bg-slate-50 text-slate-600 border-slate-200', dot: 'bg-slate-400' },
    pending: { label: 'Belum upload', cls: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
    terarsip: { label: 'Terarsip', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
};
const STATUS_FILTERS = {
    semua: ['menunggu', 'didisposisi', 'selesai', 'lama', 'pending', 'terarsip'],
    masuk: ['menunggu', 'didisposisi', 'selesai', 'lama'],
    telegram: ['menunggu', 'didisposisi', 'selesai', 'lama'],
    keluar: ['pending', 'terarsip'],
    disposisi: ['menunggu', 'didisposisi', 'selesai'],
};

const dateOnly = (d) => (d ? String(d).slice(0, 10) : '');
const myRole = computed(() => String(user.value?.role || '').toLowerCase().replace(/[\s_-]+/g, ''));

const rows = computed(() => {
    const out = [];
    for (const l of props.incoming || []) {
        const targets = l.disposition?.targets || [];
        const myOpen = targets.filter((t) => t.mine && t.status !== 'selesai');
        out.push({
            key: `m${l.id}`, kind: l.type === 'telegram' ? 'telegram' : 'masuk', id: l.id,
            number: l.agenda_number || l.letter_number, sub: l.agenda_number ? l.letter_number : null,
            subject: l.subject, party: l.issuer, partyLabel: 'Dari',
            date: dateOnly(l.received_at || l.date), category_id: l.category_id, category: l.category,
            status: l.status, raw: l,
            needsMe: (props.can.give_disposition && l.status === 'menunggu') || myOpen.length > 0,
        });
    }
    for (const g of props.logs?.data || []) {
        out.push({
            key: `k${g.id}`, kind: 'keluar', id: g.id,
            number: g.full_number, sub: null,
            subject: g.subject, party: g.recipient, partyLabel: 'Kepada',
            date: dateOnly(g.date), category_id: g.category_id, category: g.category?.name,
            status: g.is_archived ? 'terarsip' : 'pending', raw: g, needsMe: false,
        });
    }
    return out.sort((a, b) => (b.date || '').localeCompare(a.date || '') || b.key.localeCompare(a.key));
});

const inTab = (r, t) => {
    if (t === 'masuk' || t === 'telegram' || t === 'keluar') return r.kind === t;
    if (t === 'disposisi') return r.kind !== 'keluar' && r.status !== 'lama';
    return true;
};

const baseFiltered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter((r) => {
        if (q && ![r.number, r.sub, r.subject, r.party].some((v) => v && String(v).toLowerCase().includes(q))) return false;
        if (startDate.value && r.date < startDate.value) return false;
        if (endDate.value && r.date > endDate.value) return false;
        if (statusFilter.value && r.status !== statusFilter.value) return false;
        return true;
    });
});

const tabCounts = computed(() => {
    const c = {};
    for (const t of TABS) c[t] = baseFiltered.value.filter((r) => inTab(r, t) && (!filterCategoryId.value || r.category_id == filterCategoryId.value)).length;
    return c;
});

const categoryCounts = computed(() => {
    const c = {};
    for (const r of baseFiltered.value) if (inTab(r, tab.value)) c[r.category_id] = (c[r.category_id] || 0) + 1;
    return c;
});

const filtered = computed(() => {
    const list = baseFiltered.value.filter((r) => inTab(r, tab.value) && (!filterCategoryId.value || r.category_id == filterCategoryId.value));
    // Di tab Disposisi, yang butuh tindakan saya tampil paling atas.
    return tab.value === 'disposisi' ? [...list].sort((a, b) => Number(b.needsMe) - Number(a.needsMe)) : list;
});

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));
const pageRows = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));

const awaitingCount = computed(() => (props.incoming || []).filter((l) => l.status === 'menunggu').length);
const myTasks = computed(() => {
    const out = [];
    for (const l of props.incoming || []) {
        for (const t of l.disposition?.targets || []) {
            if (t.mine && t.status !== 'selesai') out.push({ letter: l, target: t });
        }
    }
    return out;
});

const fmtDate = (d) => (d ? new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-');
const formatLongDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '');
const selectedCategoryName = computed(() => {
    const cat = props.categories.find((c) => c.id == filterCategoryId.value);
    return cat ? cat.name.toUpperCase() : 'SEMUA KATEGORI';
});
const tabLabel = { semua: 'Semua', masuk: 'Surat masuk', telegram: 'Telegram', keluar: 'Keluar', disposisi: 'Disposisi' };
const KIND = {
    masuk: { label: 'MASUK', judul: 'Surat masuk', cls: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    telegram: { label: 'TELEGRAM', judul: 'Telegram', cls: 'bg-violet-50 text-violet-700 border-violet-200' },
    keluar: { label: 'KELUAR', judul: 'Surat keluar', cls: 'bg-slate-50 text-slate-600 border-slate-200' },
};

// Panel geser dirender di dalam .nv-main (Nova) agar ikut tema gelap; di Classic ke <body>.
const teleportTo = ref(null);
onMounted(() => { teleportTo.value = document.querySelector('.nv-main') ? '.nv-main' : 'body'; });

// ---------------------------------------------------------------- Detail (drawer)
const selectedKey = ref(null);
const selected = computed(() => rows.value.find((r) => r.key === selectedKey.value) || null);
const openRow = (r) => { selectedKey.value = r.key; resetDispose(); };
const closeDrawer = () => { selectedKey.value = null; };

// ---------------------------------------------------------------- Booking nomor surat keluar (logika lama dipertahankan)
const getTodayLocalDate = () => {
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
};
const toRoman = (m) => ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][m - 1];

const showBooking = ref(false);
const form = useForm({ priority: 'B', category_id: '', sub_category_id: '', subject: '', recipient: '', date: getTodayLocalDate(), sequence: '' });
const isCalculating = ref(false);
const filteredSubCategories = computed(() => (form.category_id ? (props.sub_categories || []).filter((s) => s.category_id == form.category_id) : []));
const previewNumber = computed(() => {
    if (!form.category_id || !form.date) return 'Pilih kategori…';
    const category = props.categories.find((c) => c.id === form.category_id);
    if (!category) return '…';
    const d = new Date(form.date);
    const seq = String(form.sequence || '0');
    const code = category.code.split('-')[0];
    if (code === 'R' || code === 'Sprin') return `${code} / ${seq} / ${toRoman(d.getMonth() + 1)} / ${d.getFullYear()}`;
    return `${form.priority} / ${seq} / ${code} / ${toRoman(d.getMonth() + 1)} / ${d.getFullYear()}`;
});
watch(() => form.category_id, async (id) => {
    form.sub_category_id = '';
    if (!id) return;
    isCalculating.value = true;
    try {
        const res = await axios.get(route('letter-logs.next-number', { category_id: id }));
        form.sequence = String(res.data.sequence);
    } finally { isCalculating.value = false; }
});
watch(() => form.sub_category_id, (subId) => {
    const sub = (props.sub_categories || []).find((s) => s.id === subId);
    if (sub && !form.subject.startsWith(sub.name.toUpperCase())) form.subject = `${sub.name.toUpperCase()}: ${form.subject}`;
});
const submitBooking = () => {
    form.post(route('letter-logs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'recipient', 'sub_category_id', 'sequence');
            showBooking.value = false;
            tab.value = 'keluar';
            toast.fire({ icon: 'success', title: 'Nomor surat berhasil di-booking' });
        },
    });
};

const cancelBooking = async (log) => {
    const res = await Swal.fire({
        icon: 'warning', title: 'Batalkan booking nomor?',
        html: `<div style="font-family:monospace;font-weight:800;margin-bottom:6px">${log.full_number}</div><div style="font-size:13px">Nomor ini akan dihapus dari Agenda Surat. Tindakan ini tercatat di audit log.</div>`,
        showCancelButton: true, confirmButtonText: 'Ya, batalkan', cancelButtonText: 'Kembali', confirmButtonColor: '#e11d48', reverseButtons: true, focusCancel: true,
    });
    if (!res.isConfirmed) return;
    router.delete(route('letter-logs.destroy', log.id), {
        preserveScroll: true,
        onSuccess: (p) => {
            const err = p.props.flash?.error;
            if (err) Swal.fire({ icon: 'error', title: 'Gagal', text: err });
            else { toast.fire({ icon: 'success', title: 'Booking nomor dibatalkan' }); closeDrawer(); }
        },
        onError: () => Swal.fire({ icon: 'error', title: 'Gagal', text: 'Anda tidak berhak membatalkan nomor ini.' }),
    });
};

// Unggah PDF untuk nomor surat keluar
const isUploading = ref(false);
const activeLogId = ref(null);
const fileInput = ref(null);
const triggerUpload = (id) => { activeLogId.value = id; fileInput.value.click(); };
const handleFileUpload = (e) => {
    const file = e.target.files[0];
    if (!file || file.type !== 'application/pdf') return Swal.fire({ icon: 'warning', title: 'Pilih file PDF', text: 'Hanya berkas PDF yang dapat diarsipkan.' });
    router.post(route('letters.store-direct'), { letter_log_id: activeLogId.value, file }, {
        preserveScroll: true,
        onBefore: () => { isUploading.value = true; },
        onFinish: () => { isUploading.value = false; e.target.value = ''; },
        onSuccess: () => toast.fire({ icon: 'success', title: 'Berhasil diarsipkan' }),
    });
};

// ---------------------------------------------------------------- Catat surat masuk (wajib PDF)
const showIncoming = ref(false);
const inForm = useForm({
    type: 'masuk', twu: '',
    letter_number: '', issuer: '', subject: '', date: getTodayLocalDate(), received_at: getTodayLocalDate(),
    security_level: 'biasa', category_id: '', sub_category_id: '', agenda_sequence: '', file: null,
});
const inPreview = ref('');
const inSeqAuto = ref('');
const inFileName = ref('');
const loadIncomingNumber = async () => {
    if (!inForm.received_at) return;
    try {
        const res = await axios.get(route('letters.incoming.next-number', { received_at: inForm.received_at, type: inForm.type }));
        inSeqAuto.value = res.data.sequence;
        inPreview.value = res.data.formatted;
    } catch { inPreview.value = ''; }
};
const incomingPreview = computed(() => {
    if (!inForm.received_at) return '…';
    const seq = String(inForm.agenda_sequence || inSeqAuto.value || '…').toLowerCase();
    const d = new Date(`${inForm.received_at}T00:00:00`);
    return (inForm.type === 'telegram' ? props.telegram_format : props.incoming_format)
        .replace('{urut}', seq).replace('{romawi}', toRoman(d.getMonth() + 1))
        .replace('{bulan}', String(d.getMonth() + 1).padStart(2, '0')).replace('{tahun}', String(d.getFullYear()));
});
watch(() => inForm.received_at, loadIncomingNumber);
watch(() => inForm.type, () => { inForm.category_id = ''; inForm.clearErrors(); loadIncomingNumber(); });
const openIncoming = (type = 'masuk') => { inForm.type = type; showIncoming.value = true; loadIncomingNumber(); };
const incomingCategories = computed(() => (props.categories || []).filter((c) => Boolean(c.is_telegram) === (inForm.type === 'telegram')));
const onIncomingFile = (e) => {
    const f = e.target.files[0];
    if (f && f.type !== 'application/pdf') { e.target.value = ''; return Swal.fire({ icon: 'warning', title: 'Pilih file PDF', text: 'Surat masuk harus diunggah dalam format PDF.' }); }
    inForm.file = f || null;
    inFileName.value = f ? f.name : '';
};
const submitIncoming = () => {
    inForm.post(route('letters.incoming.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            inForm.reset('letter_number', 'issuer', 'subject', 'agenda_sequence', 'file', 'sub_category_id', 'twu');
            inFileName.value = '';
            showIncoming.value = false;
            tab.value = 'disposisi';
            toast.fire({ icon: 'success', title: `${inForm.type === 'telegram' ? 'Telegram' : 'Surat masuk'} dicatat, menunggu disposisi` });
        },
    });
};

// ---------------------------------------------------------------- Disposisi
const dispForm = useForm({ roles: [], instructions: [], note: '', urgency: 'biasa', due_date: '' });
const resetDispose = () => { dispForm.reset(); dispForm.clearErrors(); };
const toggle = (arr, v) => { const i = arr.indexOf(v); i >= 0 ? arr.splice(i, 1) : arr.push(v); };
const submitDispose = async () => {
    const r = selected.value;
    if (!r) return;
    const tujuan = dispForm.roles.map((k) => props.disposition_options.roles[k]).join(', ');
    const ok = await Swal.fire({
        icon: 'question', title: 'Teruskan disposisi?', text: `Disposisi surat ${r.number} diteruskan ke ${tujuan || '-'}.`,
        showCancelButton: true, confirmButtonText: 'Teruskan', cancelButtonText: 'Periksa lagi', confirmButtonColor: '#2563eb', reverseButtons: true,
    });
    if (!ok.isConfirmed) return;
    dispForm.post(route('letters.disposition.store', r.id), {
        preserveScroll: true,
        onSuccess: () => { resetDispose(); toast.fire({ icon: 'success', title: 'Disposisi diteruskan' }); },
    });
};
const receiveTarget = (t) => {
    router.post(route('dispositions.receive', t.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.fire({ icon: 'success', title: 'Disposisi diterima' }),
        onError: (e) => Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(e)[0] || 'Tidak dapat menerima disposisi ini.' }),
    });
};
const completeTarget = async (t, letter) => {
    const res = await Swal.fire({
        title: 'Lapor tindak lanjut selesai',
        html: `<div style="font-size:13px;color:#475569;margin-bottom:4px">${letter.agenda_number || letter.letter_number}</div><div style="font-size:13px">${t.role_label}</div>`,
        input: 'textarea', inputPlaceholder: 'Tuliskan hasil tindak lanjut…', inputAttributes: { maxlength: 2000 },
        showCancelButton: true, confirmButtonText: 'Kirim laporan', cancelButtonText: 'Batal', confirmButtonColor: '#059669', reverseButtons: true,
        inputValidator: (v) => (!v || v.trim().length < 3 ? 'Laporan minimal 3 karakter.' : undefined),
    });
    if (!res.isConfirmed) return;
    router.post(route('dispositions.complete', t.id), { report_note: res.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => toast.fire({ icon: 'success', title: 'Laporan tersimpan' }),
        onError: (e) => Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(e)[0] || 'Laporan tidak tersimpan.' }),
    });
};
const canRespond = (t) => t.mine || props.can.respond_any;
const urgencyCls = { biasa: 'bg-slate-50 text-slate-600 border-slate-200', segera: 'bg-amber-50 text-amber-700 border-amber-200', sangat_segera: 'bg-rose-50 text-rose-700 border-rose-200' };
const targetCls = { baru: 'bg-slate-50 text-slate-600 border-slate-200', diterima: 'bg-blue-50 text-blue-700 border-blue-200', selesai: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
const targetLabel = { baru: 'Belum diterima', diterima: 'Diproses', selesai: 'Selesai' };

// ---------------------------------------------------------------- Laporan (pratinjau & unduh)
const showPreview = ref(false);
const previewImage = ref(null);
const openPreview = () => {
    const el = document.getElementById('area-laporan-cetak');
    html2pdf().set({ margin: [0.5, 0.5, 0.5, 0.5], html2canvas: { scale: 2, useCORS: true, width: 800 } }).from(el).outputImg().then((img) => {
        previewImage.value = img.src;
        showPreview.value = true;
    });
};
const downloadPDF = () => {
    window.open(route('letter-logs.pdf', { category_id: filterCategoryId.value, start_date: startDate.value, end_date: endDate.value }), '_blank');
};
const reportTitle = computed(() => ({ masuk: 'LAPORAN AGENDA SURAT MASUK', telegram: 'LAPORAN AGENDA TELEGRAM', keluar: 'LAPORAN REKAPITULASI PENOMORAN SURAT', disposisi: 'LAPORAN DISPOSISI SURAT MASUK' }[tab.value] || 'LAPORAN AGENDA SURAT'));
</script>

<template>
    <Head title="Agenda Surat" />

    <AuthenticatedLayout>
        <div class="space-y-5 font-sans print:hidden">
            <!-- Header -->
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-[28px] font-semibold tracking-tight text-slate-900">Agenda Surat</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ rows.length.toLocaleString('id-ID') }} surat tercatat
                        <template v-if="awaitingCount"> · <span class="text-amber-700 font-medium">{{ awaitingCount }} belum didisposisi</span></template>
                        <template v-if="myTasks.length"> · <span class="text-blue-700 font-medium">{{ myTasks.length }} disposisi untuk Anda</span></template>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="openPreview" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/></svg>
                        Laporan
                    </button>
                    <button type="button" @click="showBooking = true" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4zM9 9h6M9 13h6M9 17h3"/></svg>
                        Booking nomor
                    </button>
                    <button v-if="can.record_incoming" type="button" @click="openIncoming('telegram')" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl border border-violet-200 bg-white text-sm font-medium text-violet-700 hover:bg-violet-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Catat telegram
                    </button>
                    <button v-if="can.record_incoming" type="button" @click="openIncoming('masuk')" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Catat surat masuk
                    </button>
                </div>
            </div>

            <!-- Tugas disposisi saya -->
            <div v-if="myTasks.length" class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-semibold text-blue-700 mb-3">Disposisi untuk jabatan Anda</p>
                <div class="grid gap-3 md:grid-cols-2">
                    <div v-for="task in myTasks" :key="task.target.id" class="rounded-xl border border-slate-200 bg-white p-4 flex flex-col gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs text-slate-500">{{ task.letter.agenda_number }}</p>
                            <p class="text-sm font-medium text-slate-900 truncate">{{ task.letter.subject }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ task.target.role_label }} · {{ (task.letter.disposition?.instructions || []).join(', ') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="h-8 px-3 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="openRow(rows.find(r => r.key === 'm' + task.letter.id))">Buka surat</button>
                            <button v-if="task.target.status === 'baru'" type="button" class="h-8 px-3 rounded-lg bg-blue-600 text-xs font-medium text-white hover:bg-blue-700" @click="receiveTarget(task.target)">Terima</button>
                            <button v-else type="button" class="h-8 px-3 rounded-lg bg-emerald-600 text-xs font-medium text-white hover:bg-emerald-700" @click="completeTarget(task.target, task.letter)">Lapor selesai</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toolbar: tab + pencarian -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex shrink-0 p-[3px] rounded-xl bg-slate-100" role="tablist">
                    <button v-for="t in TABS" :key="t" type="button" role="tab" :aria-selected="tab === t" @click="tab = t"
                        :class="['h-[34px] px-3.5 rounded-[10px] text-sm whitespace-nowrap transition inline-flex items-center gap-1.5', tab === t ? 'bg-white text-slate-900 font-medium shadow-sm' : 'text-slate-600 hover:text-slate-900']">
                        {{ tabLabel[t] }}
                        <span :class="['text-[11px] font-mono', tab === t ? 'text-slate-500' : 'text-slate-400']">{{ tabCounts[t] }}</span>
                    </button>
                </div>
                <div class="flex-1"></div>
                <label class="relative w-full sm:w-[340px]">
                    <input v-model="search" type="search" placeholder="Cari nomor, perihal, atau asal surat…" class="w-full h-10 pl-9 pr-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500" />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                </label>
            </div>

            <!-- Jenis surat + filter status & tanggal -->
            <div class="flex flex-wrap items-center gap-3 -mt-1">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 flex-1 min-w-0">
                    <span class="text-[11px] font-mono uppercase tracking-wider text-slate-400 shrink-0 mr-1">Jenis</span>
                    <button type="button" @click="filterCategoryId = ''"
                        :class="['h-8 px-3 rounded-full border text-xs whitespace-nowrap transition shrink-0', !filterCategoryId ? 'bg-slate-900 border-slate-900 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50']">Semua jenis</button>
                    <button v-for="c in categories" :key="c.id" type="button" @click="filterCategoryId = String(c.id)"
                        :class="['h-8 px-3 rounded-full border text-xs whitespace-nowrap transition inline-flex items-center gap-1.5 shrink-0', filterCategoryId == c.id ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50']">
                        {{ c.name }}
                        <span :class="['font-mono text-[10px]', filterCategoryId == c.id ? 'text-blue-100' : 'text-slate-400']">{{ categoryCounts[c.id] || 0 }}</span>
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="statusFilter" aria-label="Status" class="h-9 rounded-xl border border-slate-200 bg-white text-sm text-slate-700 pl-3 pr-8 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua status</option>
                        <option v-for="s in STATUS_FILTERS[tab]" :key="s" :value="s">{{ STATUS[s].label }}</option>
                    </select>
                    <input v-model="startDate" type="date" aria-label="Dari tanggal" title="Dari tanggal" class="h-9 w-[150px] rounded-xl border border-slate-200 bg-white text-sm text-slate-700 px-3 focus:border-blue-500 focus:ring-blue-500" />
                    <span class="text-slate-400 text-sm">–</span>
                    <input v-model="endDate" type="date" aria-label="Sampai tanggal" title="Sampai tanggal" class="h-9 w-[150px] rounded-xl border border-slate-200 bg-white text-sm text-slate-700 px-3 focus:border-blue-500 focus:ring-blue-500" />
                </div>
            </div>

            <input type="file" ref="fileInput" class="hidden" accept="application/pdf" @change="handleFileUpload">

            <!-- Tabel -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[860px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-mono uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-2.5 font-medium w-[220px]">No. agenda / surat</th>
                                <th class="px-4 py-2.5 font-medium">Perihal</th>
                                <th class="px-4 py-2.5 font-medium w-[190px]">Asal / tujuan</th>
                                <th class="px-4 py-2.5 font-medium w-[120px]">Tanggal</th>
                                <th class="px-4 py-2.5 font-medium w-[150px]">Status</th>
                                <th class="px-4 py-2.5 w-[44px]"><span class="sr-only">Buka</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="r in pageRows" :key="r.key" @click="openRow(r)"
                                :class="['cursor-pointer transition hover:bg-slate-50', selectedKey === r.key ? 'bg-blue-50' : '', r.needsMe ? 'bg-amber-50/40' : '']">
                                <td class="px-4 py-3 align-top">
                                    <span :class="['inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded-md border mb-1', KIND[r.kind].cls]">{{ KIND[r.kind].label }}</span>
                                    <div class="font-mono text-[13px] font-medium text-slate-900 break-words">{{ r.number }}</div>
                                    <div v-if="r.sub" class="font-mono text-[11px] text-slate-500 mt-0.5 break-words">{{ r.sub }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p class="text-sm font-medium text-slate-900 line-clamp-2">{{ r.subject }}</p>
                                    <p v-if="r.category" class="text-xs text-slate-500 mt-0.5">{{ r.category }}</p>
                                </td>
                                <td class="px-4 py-3 align-top text-[13px] text-slate-600">
                                    <span class="text-[11px] text-slate-400 block">{{ r.partyLabel }}</span>
                                    <span class="line-clamp-2">{{ r.party || '-' }}</span>
                                </td>
                                <td class="px-4 py-3 align-top font-mono text-[13px] text-slate-600 whitespace-nowrap">{{ fmtDate(r.date) }}</td>
                                <td class="px-4 py-3 align-top">
                                    <span :class="['inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full border text-xs font-medium whitespace-nowrap', STATUS[r.status]?.cls]">
                                        <span :class="['w-1.5 h-1.5 rounded-full', STATUS[r.status]?.dot]"></span>{{ STATUS[r.status]?.label }}
                                    </span>
                                    <p v-if="r.needsMe" class="text-[11px] text-amber-700 font-medium mt-1">Perlu tindakan Anda</p>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-400">
                                    <svg class="w-4 h-4 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                                </td>
                            </tr>
                            <tr v-if="!pageRows.length">
                                <td colspan="6" class="px-4 py-14 text-center">
                                    <p class="text-sm font-medium text-slate-700">Tidak ada surat yang cocok</p>
                                    <p class="text-xs text-slate-500 mt-1">Ubah tab, jenis surat, atau kata kunci pencarian.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center gap-3 px-4 sm:px-5 py-3 border-t border-slate-200">
                    <p class="text-[13px] text-slate-500 flex-1">
                        Menampilkan {{ filtered.length ? (page - 1) * PER_PAGE + 1 : 0 }}–{{ Math.min(page * PER_PAGE, filtered.length) }} dari {{ filtered.length.toLocaleString('id-ID') }} surat
                    </p>
                    <span class="font-mono text-[13px] text-slate-600">Halaman {{ page }} dari {{ pageCount }}</span>
                    <button type="button" :disabled="page <= 1" @click="page--" class="h-9 px-3.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">Sebelumnya</button>
                    <button type="button" :disabled="page >= pageCount" @click="page++" class="h-9 px-3.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">Berikutnya</button>
                </div>
            </div>
        </div>

        <!-- Drawer detail surat -->
        <Teleport v-if="teleportTo" :to="teleportTo">
            <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-if="selected" class="fixed inset-0 z-[60] flex justify-end print:hidden">
                    <div class="absolute inset-0 bg-slate-900/40" @click="closeDrawer"></div>
                    <aside class="relative w-full max-w-[520px] h-full bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-label="Detail surat">
                        <div class="flex items-start gap-3 px-5 py-4 border-b border-slate-200">
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-mono uppercase tracking-wider text-slate-400">{{ KIND[selected.kind].judul }}</p>
                                <p class="font-mono text-[15px] font-medium text-slate-900 break-all mt-0.5">{{ selected.number }}</p>
                                <span :class="['inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full border text-xs font-medium mt-2', STATUS[selected.status]?.cls]">
                                    <span :class="['w-1.5 h-1.5 rounded-full', STATUS[selected.status]?.dot]"></span>{{ STATUS[selected.status]?.label }}
                                </span>
                            </div>
                            <button type="button" @click="closeDrawer" class="w-9 h-9 rounded-xl hover:bg-slate-100 text-slate-500 flex items-center justify-center" aria-label="Tutup">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6">
                            <!-- Metadata -->
                            <section>
                                <h3 class="text-base font-semibold text-slate-900 leading-snug">{{ selected.subject }}</h3>
                                <dl class="mt-4 grid grid-cols-[130px_1fr] gap-y-2.5 text-sm">
                                    <template v-if="selected.kind !== 'keluar'">
                                        <dt class="text-slate-500">{{ selected.kind === 'telegram' ? 'Nomor telegram' : 'Nomor surat asal' }}</dt><dd class="font-mono text-slate-900 break-all">{{ selected.raw.letter_number }}</dd>
                                        <template v-if="selected.kind === 'telegram'"><dt class="text-slate-500">TWU</dt><dd class="font-mono text-slate-900">{{ selected.raw.twu || '-' }}</dd></template>
                                        <dt class="text-slate-500">Dari</dt><dd class="text-slate-900">{{ selected.raw.issuer }}</dd>
                                        <dt class="text-slate-500">Tanggal surat</dt><dd class="text-slate-900">{{ fmtDate(selected.raw.date) }}</dd>
                                        <dt class="text-slate-500">Diterima</dt><dd class="text-slate-900">{{ fmtDate(selected.raw.received_at) }}</dd>
                                        <dt class="text-slate-500">Sifat</dt><dd class="text-slate-900 capitalize">{{ selected.raw.security_level }}</dd>
                                        <dt class="text-slate-500">Jenis</dt><dd class="text-slate-900">{{ selected.category || '-' }}</dd>
                                        <dt class="text-slate-500">Dicatat oleh</dt><dd class="text-slate-900">{{ selected.raw.recorder || '-' }}</dd>
                                    </template>
                                    <template v-else>
                                        <dt class="text-slate-500">Kepada</dt><dd class="text-slate-900">{{ selected.raw.recipient || '-' }}</dd>
                                        <dt class="text-slate-500">Tanggal</dt><dd class="text-slate-900">{{ fmtDate(selected.raw.date) }}</dd>
                                        <dt class="text-slate-500">Sifat</dt><dd class="text-slate-900">{{ priorities?.[selected.raw.priority] || selected.raw.priority }}</dd>
                                        <dt class="text-slate-500">Jenis</dt><dd class="text-slate-900">{{ selected.category || '-' }}<template v-if="selected.raw.sub_category"> · {{ selected.raw.sub_category.name }}</template></dd>
                                    </template>
                                </dl>
                            </section>

                            <!-- Berkas -->
                            <section v-if="selected.kind !== 'keluar'" class="space-y-2">
                                <a v-if="selected.raw.file_url" :href="selected.raw.file_url" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition">
                                    <span class="w-9 h-9 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center text-[10px] font-bold">PDF</span>
                                    <span class="text-sm font-medium text-slate-900 flex-1">Buka berkas surat</span>
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>
                                </a>
                                <div v-else class="p-3 rounded-xl border border-amber-200 bg-amber-50 text-sm text-amber-700">
                                    Berkas dikunci sampai Komandan memberi disposisi.
                                </div>
                                <p v-if="selected.raw.sheet_merged" class="text-xs text-slate-500">Lembar disposisi (A5) sudah tergabung di halaman pertama berkas.</p>
                                <a v-if="selected.raw.sheet_url" :href="selected.raw.sheet_url" target="_blank" rel="noopener" class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 transition">
                                    <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center text-[10px] font-bold">A5</span>
                                    <span class="flex-1"><span class="block text-sm font-medium text-slate-900">Lembar disposisi</span><span class="block text-xs text-slate-500">Disimpan terpisah karena PDF unggahan tidak bisa digabung.</span></span>
                                </a>
                            </section>
                            <section v-else class="flex flex-wrap gap-2">
                                <template v-if="selected.status === 'pending'">
                                    <button type="button" :disabled="isUploading" @click="triggerUpload(selected.id)" class="h-10 px-4 rounded-xl bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">{{ isUploading ? 'Mengunggah…' : 'Upload PDF' }}</button>
                                    <button v-if="selected.raw.can_cancel" type="button" @click="cancelBooking(selected.raw)" class="h-10 px-4 rounded-xl border border-rose-200 bg-white text-sm font-medium text-rose-700 hover:bg-rose-50">Batalkan booking</button>
                                </template>
                                <p v-else class="text-sm text-emerald-700">PDF sudah terarsip di Arsip Surat.</p>
                            </section>

                            <!-- Form disposisi (Komandan) -->
                            <section v-if="selected.kind !== 'keluar' && selected.status === 'menunggu'">
                                <template v-if="can.give_disposition">
                                    <h4 class="text-sm font-semibold text-slate-900">Beri disposisi</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Pilih jabatan tujuan dan instruksi. Setiap jabatan wajib melapor bila selesai.</p>
                                    <form class="mt-4 space-y-4" @submit.prevent="submitDispose">
                                        <div>
                                            <p class="text-xs font-medium text-slate-700 mb-2">Teruskan kepada</p>
                                            <div class="flex flex-wrap gap-2">
                                                <button v-for="(label, key) in disposition_options.roles" :key="key" type="button" @click="toggle(dispForm.roles, key)"
                                                    :class="['h-8 px-3 rounded-full border text-xs transition', dispForm.roles.includes(key) ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50']">{{ label }}</button>
                                            </div>
                                            <p v-if="dispForm.errors.roles" class="text-xs text-rose-700 mt-1">{{ dispForm.errors.roles }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium text-slate-700 mb-2">Instruksi</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <label v-for="(label, key) in disposition_options.instructions" :key="key" class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                                    <input type="checkbox" :value="key" v-model="dispForm.instructions" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />{{ label }}
                                                </label>
                                            </div>
                                            <p v-if="dispForm.errors.instructions" class="text-xs text-rose-700 mt-1">{{ dispForm.errors.instructions }}</p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <label class="block">
                                                <span class="text-xs font-medium text-slate-700">Urgensi</span>
                                                <select v-model="dispForm.urgency" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                                    <option v-for="(label, key) in disposition_options.urgencies" :key="key" :value="key">{{ label }}</option>
                                                </select>
                                            </label>
                                            <label class="block">
                                                <span class="text-xs font-medium text-slate-700">Batas waktu (opsional)</span>
                                                <input v-model="dispForm.due_date" type="date" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm px-3 focus:border-blue-500 focus:ring-blue-500" />
                                            </label>
                                        </div>
                                        <label class="block">
                                            <span class="text-xs font-medium text-slate-700">Catatan Komandan</span>
                                            <textarea v-model="dispForm.note" rows="3" maxlength="2000" class="mt-1 w-full rounded-xl border border-slate-200 bg-white text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500" placeholder="Arahan tambahan (opsional)"></textarea>
                                        </label>
                                        <button type="submit" :disabled="dispForm.processing || !dispForm.roles.length || !dispForm.instructions.length" class="w-full h-11 rounded-xl bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Teruskan disposisi</button>
                                    </form>
                                </template>
                                <div v-else class="p-3 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-600">Menunggu disposisi Komandan.</div>
                            </section>

                            <!-- Hasil disposisi + linimasa -->
                            <section v-if="selected.kind !== 'keluar' && selected.raw.disposition">
                                <h4 class="text-sm font-semibold text-slate-900">Disposisi</h4>
                                <div class="mt-3 rounded-xl border border-slate-200 p-4 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span :class="['h-6 px-2.5 rounded-full border text-xs font-medium inline-flex items-center', urgencyCls[selected.raw.disposition.urgency]]">{{ disposition_options.urgencies[selected.raw.disposition.urgency] }}</span>
                                        <span v-if="selected.raw.disposition.due_date" class="text-xs text-slate-500">Batas {{ fmtDate(selected.raw.disposition.due_date) }}</span>
                                    </div>
                                    <p class="text-sm text-slate-900">{{ selected.raw.disposition.instructions.join(' · ') }}</p>
                                    <p v-if="selected.raw.disposition.note" class="text-sm text-slate-600 whitespace-pre-line">"{{ selected.raw.disposition.note }}"</p>
                                    <p class="text-xs text-slate-500">{{ selected.raw.disposition.giver }} · {{ selected.raw.disposition.created_at }}</p>
                                </div>
                                <ol class="mt-4 space-y-3">
                                    <li v-for="t in selected.raw.disposition.targets" :key="t.id" class="rounded-xl border border-slate-200 p-3">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-slate-900 flex-1">{{ t.role_label }}</p>
                                            <span :class="['h-6 px-2.5 rounded-full border text-xs font-medium inline-flex items-center', targetCls[t.status]]">{{ targetLabel[t.status] }}</span>
                                        </div>
                                        <p v-if="t.received_at" class="text-xs text-slate-500 mt-1">Diterima {{ t.received_by }} · {{ t.received_at }}</p>
                                        <p v-if="t.completed_at" class="text-xs text-slate-500">Selesai {{ t.completed_by }} · {{ t.completed_at }}</p>
                                        <p v-if="t.report_note" class="text-sm text-slate-700 mt-2 whitespace-pre-line">{{ t.report_note }}</p>
                                        <div v-if="canRespond(t) && t.status !== 'selesai'" class="flex gap-2 mt-3">
                                            <button v-if="t.status === 'baru'" type="button" @click="receiveTarget(t)" class="h-8 px-3 rounded-lg bg-blue-600 text-xs font-medium text-white hover:bg-blue-700">Terima</button>
                                            <button type="button" @click="completeTarget(t, selected.raw)" class="h-8 px-3 rounded-lg bg-emerald-600 text-xs font-medium text-white hover:bg-emerald-700">Lapor selesai</button>
                                        </div>
                                    </li>
                                </ol>
                            </section>
                        </div>
                    </aside>
                </div>
            </transition>

            <!-- Panel: Booking nomor surat keluar -->
            <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-if="showBooking" class="fixed inset-0 z-[60] flex justify-end print:hidden">
                    <div class="absolute inset-0 bg-slate-900/40" @click="showBooking = false"></div>
                    <aside class="relative w-full max-w-[460px] h-full bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-label="Booking nomor surat keluar">
                        <div class="flex items-center px-5 py-4 border-b border-slate-200">
                            <div class="flex-1"><h2 class="text-base font-semibold text-slate-900">Booking nomor surat keluar</h2><p class="text-xs text-slate-500">Nomor tercatat di Agenda Surat; unggah PDF setelah surat jadi.</p></div>
                            <button type="button" @click="showBooking = false" class="w-9 h-9 rounded-xl hover:bg-slate-100 text-slate-500 flex items-center justify-center" aria-label="Tutup"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </div>
                        <form class="flex-1 overflow-y-auto px-5 py-5 space-y-4" @submit.prevent="submitBooking">
                            <label class="block"><span class="text-xs font-medium text-slate-700">Sifat surat</span>
                                <select v-model="form.priority" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option v-for="(label, code) in priorities" :key="code" :value="code">{{ code }} — {{ label }}</option>
                                </select></label>
                            <label class="block"><span class="text-xs font-medium text-slate-700">Jenis surat</span>
                                <select v-model="form.category_id" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="" disabled>Pilih jenis…</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select></label>
                            <label v-if="filteredSubCategories.length" class="block"><span class="text-xs font-medium text-slate-700">Sub-jenis</span>
                                <select v-model="form.sub_category_id" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">— Pilih —</option>
                                    <option v-for="s in filteredSubCategories" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block"><span class="text-xs font-medium text-slate-700">Nomor urut</span>
                                    <input v-model="form.sequence" type="text" required placeholder="Contoh: 14a" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm font-mono uppercase px-3 focus:border-blue-500 focus:ring-blue-500" /></label>
                                <label class="block"><span class="text-xs font-medium text-slate-700">Tanggal</span>
                                    <input v-model="form.date" type="date" required class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm px-3 focus:border-blue-500 focus:ring-blue-500" /></label>
                            </div>
                            <label class="block"><span class="text-xs font-medium text-slate-700">Perihal</span>
                                <textarea v-model="form.subject" rows="2" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500"></textarea></label>
                            <label class="block"><span class="text-xs font-medium text-slate-700">Tujuan / alamat</span>
                                <textarea v-model="form.recipient" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500"></textarea></label>
                            <div class="p-3 rounded-xl border border-blue-200 bg-blue-50">
                                <p class="text-[11px] font-mono uppercase tracking-wider text-blue-700">Pratinjau nomor</p>
                                <p class="font-mono text-sm font-medium text-blue-700 mt-1">{{ isCalculating ? 'Menghitung…' : previewNumber }}</p>
                            </div>
                            <p v-for="(err, k) in form.errors" :key="k" class="text-xs text-rose-700">{{ err }}</p>
                            <button type="submit" :disabled="form.processing || !form.category_id" class="w-full h-11 rounded-xl bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Booking nomor</button>
                        </form>
                    </aside>
                </div>
            </transition>

            <!-- Panel: Catat surat masuk -->
            <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-if="showIncoming" class="fixed inset-0 z-[60] flex justify-end print:hidden">
                    <div class="absolute inset-0 bg-slate-900/40" @click="showIncoming = false"></div>
                    <aside class="relative w-full max-w-[460px] h-full bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" :aria-label="inForm.type === 'telegram' ? 'Catat telegram' : 'Catat surat masuk'">
                        <div class="flex items-center px-5 py-4 border-b border-slate-200">
                            <div class="flex-1"><h2 class="text-base font-semibold text-slate-900">{{ inForm.type === 'telegram' ? 'Catat telegram' : 'Catat surat masuk' }}</h2><p class="text-xs text-slate-500">Mendapat nomor agenda {{ inForm.type === 'telegram' ? 'telegram' : 'surat masuk' }} lalu menunggu disposisi Komandan.</p></div>
                            <button type="button" @click="showIncoming = false" class="w-9 h-9 rounded-xl hover:bg-slate-100 text-slate-500 flex items-center justify-center" aria-label="Tutup"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                        </div>
                        <form class="flex-1 overflow-y-auto px-5 py-5 space-y-4" @submit.prevent="submitIncoming">
                            <div class="inline-flex p-[3px] rounded-xl bg-slate-100" role="tablist" aria-label="Jenis dokumen masuk">
                                <button v-for="t in ['masuk', 'telegram']" :key="t" type="button" role="tab" :aria-selected="inForm.type === t" @click="inForm.type = t"
                                    :class="['h-8 px-3.5 rounded-[10px] text-sm transition', inForm.type === t ? 'bg-white text-slate-900 font-medium shadow-sm' : 'text-slate-600']">{{ t === 'telegram' ? 'Telegram' : 'Surat masuk' }}</button>
                            </div>
                            <label class="block"><span class="text-xs font-medium text-slate-700">{{ inForm.type === 'telegram' ? 'Nomor telegram' : 'Nomor surat asal' }}</span>
                                <input v-model="inForm.letter_number" type="text" required :placeholder="inForm.type === 'telegram' ? 'Nomor yang tertulis di telegram' : 'Nomor yang tertulis di surat'" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm font-mono px-3 focus:border-blue-500 focus:ring-blue-500" />
                                <span v-if="inForm.errors.letter_number" class="text-xs text-rose-700">{{ inForm.errors.letter_number }}</span></label>
                            <label v-if="inForm.type === 'telegram'" class="block"><span class="text-xs font-medium text-slate-700">TWU</span>
                                <input v-model="inForm.twu" type="text" required maxlength="100" placeholder="Tanggal-waktu-urutan, mis. 201030Z NOV 2026" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm font-mono px-3 focus:border-blue-500 focus:ring-blue-500" />
                                <span v-if="inForm.errors.twu" class="text-xs text-rose-700">{{ inForm.errors.twu }}</span></label>
                            <label class="block"><span class="text-xs font-medium text-slate-700">{{ inForm.type === 'telegram' ? 'Dari' : 'Asal / pengirim' }}</span>
                                <input v-model="inForm.issuer" type="text" required placeholder="Mis. Kodaeral V" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm px-3 focus:border-blue-500 focus:ring-blue-500" /></label>
                            <label class="block"><span class="text-xs font-medium text-slate-700">{{ inForm.type === 'telegram' ? 'Perihal / isi ringkas' : 'Perihal' }}</span>
                                <textarea v-model="inForm.subject" rows="2" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white text-sm px-3 py-2 focus:border-blue-500 focus:ring-blue-500"></textarea></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block"><span class="text-xs font-medium text-slate-700">{{ inForm.type === 'telegram' ? 'Tanggal telegram' : 'Tanggal surat' }}</span>
                                    <input v-model="inForm.date" type="date" required class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm px-3 focus:border-blue-500 focus:ring-blue-500" /></label>
                                <label class="block"><span class="text-xs font-medium text-slate-700">Tanggal diterima</span>
                                    <input v-model="inForm.received_at" type="date" required class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm px-3 focus:border-blue-500 focus:ring-blue-500" /></label>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="block"><span class="text-xs font-medium text-slate-700">Jenis surat</span>
                                    <select v-model="inForm.category_id" required class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="" disabled>Pilih…</option>
                                        <option v-for="c in incomingCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select></label>
                                <label class="block"><span class="text-xs font-medium text-slate-700">Sifat</span>
                                    <select v-model="inForm.security_level" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="biasa">Biasa</option><option value="rahasia">Rahasia</option>
                                    </select></label>
                            </div>
                            <label class="block"><span class="text-xs font-medium text-slate-700">Nomor urut agenda</span>
                                <input v-model="inForm.agenda_sequence" type="text" :placeholder="inSeqAuto ? `Otomatis: ${inSeqAuto}` : 'Otomatis'" class="mt-1 w-full h-10 rounded-xl border border-slate-200 bg-white text-sm font-mono px-3 focus:border-blue-500 focus:ring-blue-500" />
                                <span class="text-[11px] text-slate-500">Kosongkan untuk nomor otomatis. Angka, boleh diakhiri satu huruf.</span>
                                <span v-if="inForm.errors.agenda_sequence" class="block text-xs text-rose-700">{{ inForm.errors.agenda_sequence }}</span></label>
                            <div class="p-3 rounded-xl border border-blue-200 bg-blue-50">
                                <p class="text-[11px] font-mono uppercase tracking-wider text-blue-700">Nomor agenda</p>
                                <p class="font-mono text-sm font-medium text-blue-700 mt-1">{{ incomingPreview }}</p>
                            </div>
                            <label class="block">
                                <span class="text-xs font-medium text-slate-700">PDF {{ inForm.type === 'telegram' ? 'telegram' : 'surat masuk' }} <span class="text-rose-700">*</span></span>
                                <span class="mt-1 flex items-center gap-3 p-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 cursor-pointer hover:bg-slate-100">
                                    <span class="w-9 h-9 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center text-[10px] font-bold shrink-0">PDF</span>
                                    <span class="text-sm text-slate-700 truncate">{{ inFileName || 'Pilih berkas PDF (maks. 20 MB)' }}</span>
                                </span>
                                <input type="file" accept="application/pdf" class="sr-only" @change="onIncomingFile" />
                                <span v-if="inForm.errors.file" class="text-xs text-rose-700">{{ inForm.errors.file }}</span>
                            </label>
                            <p v-for="k in ['type', 'issuer', 'subject', 'date', 'received_at', 'category_id', 'security_level']" :key="k" v-show="inForm.errors[k]" class="text-xs text-rose-700">{{ inForm.errors[k] }}</p>
                            <progress v-if="inForm.progress" :value="inForm.progress.percentage" max="100" class="w-full h-1.5">{{ inForm.progress.percentage }}%</progress>
                            <button type="submit" :disabled="inForm.processing || !inForm.file" class="w-full h-11 rounded-xl bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Catat & ajukan disposisi</button>
                        </form>
                    </aside>
                </div>
            </transition>
        </Teleport>

        <!-- Template laporan cetak (tersembunyi, dipakai pratinjau) -->
        <div style="position: absolute; left: -9999px;">
            <div id="area-laporan-cetak" class="p-10 bg-white text-black" style="width: 790px; font-family: 'Times New Roman', serif;">
                <div style="text-align: left; margin-bottom: 30px;">
                    <div style="display: inline-block; text-align: center;">
                        <p style="margin: 0; font-size: 14px; font-weight: bold; line-height: 1.2; text-transform: uppercase;">KOMANDO DAERAH TNI ANGKATAN LAUT</p>
                        <p style="margin: 0; font-size: 14px; font-weight: bold; line-height: 1.2; text-transform: uppercase;">DETASEMEN INTELIJEN</p>
                        <div style="margin-top: 5px; border-top: 1px solid #000; border-bottom: 3.5px solid #000; height: 2px; width: 320px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                </div>
                <div class="text-center mb-8 uppercase">
                    <h3 class="font-bold underline tracking-widest text-base">{{ reportTitle }}</h3>
                    <p class="font-bold mt-2 text-xs">JENIS: {{ selectedCategoryName }}</p>
                    <p class="font-normal mt-1 text-sm">Periode: {{ startDate ? formatLongDate(startDate) : 'Awal' }} s/d {{ endDate ? formatLongDate(endDate) : 'Sekarang' }}</p>
                </div>
                <table class="w-full table-fixed border-collapse border-[1.5px] border-black text-[9px]" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr class="bg-gray-100 uppercase font-bold text-center">
                            <th class="border border-black px-1 py-3" style="width: 30px;">NO</th>
                            <th class="border border-black px-2 py-3 text-left" style="width: 140px;">NOMOR</th>
                            <th class="border border-black px-2 py-3 text-left" style="width: 130px;">ASAL / TUJUAN</th>
                            <th class="border border-black px-2 py-3 text-left">PERIHAL</th>
                            <th class="border border-black px-1 py-3" style="width: 75px;">TANGGAL</th>
                            <th class="border border-black px-1 py-3" style="width: 80px;">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, index) in filtered" :key="r.key">
                            <td class="border border-black px-1 py-2 text-center" style="vertical-align: top;">{{ index + 1 }}</td>
                            <td class="border border-black px-2 py-2 font-bold uppercase" style="vertical-align: top; word-break: break-all;">{{ r.number }}<div v-if="r.sub" style="font-weight: normal;">{{ r.sub }}</div></td>
                            <td class="border border-black px-2 py-2 uppercase" style="vertical-align: top;">{{ r.party || '-' }}</td>
                            <td class="border border-black px-2 py-2 italic" style="vertical-align: top; line-height: 1.3;">"{{ r.subject }}"</td>
                            <td class="border border-black px-1 py-2 text-center" style="vertical-align: top;">{{ fmtDate(r.date) }}</td>
                            <td class="border border-black px-1 py-2 text-center font-bold uppercase" style="vertical-align: top;">{{ STATUS[r.status]?.label }}</td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="6" class="border border-black px-3 py-10 text-center font-bold italic text-gray-400">TIDAK ADA DATA UNTUK FILTER TERPILIH.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-6 text-sm font-bold uppercase border-l-[4px] border-black pl-4 py-2 bg-gray-50">
                    <p>TOTAL DATA DISARING: {{ filtered.length }} SURAT</p>
                </div>
                <div class="mt-16 flex justify-end">
                    <div class="w-80 text-left text-sm">
                        <p class="mb-1">Dicetak melalui Sinden di Surabaya</p>
                        <p class="mb-4">Pada tanggal {{ formatLongDate(new Date()) }}</p>
                        <div class="text-center">
                            <p class="font-bold">a.n. Komandan Detasemen Intelijen</p>
                            <p class="mb-20 font-bold">Petugas Administrasi,</p>
                            <p class="font-bold underline uppercase">{{ user.name }}</p>
                            <p class="font-normal">{{ user.pangkat || 'Letnan Dua' }}</p>
                            <p class="font-normal">NRP. {{ user.nrp || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal pratinjau laporan -->
        <div v-if="showPreview" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/60 p-4 print:hidden">
            <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center">
                    <h3 class="text-base font-semibold text-slate-900 flex-1">Pratinjau laporan</h3>
                    <button type="button" @click="showPreview = false" class="w-9 h-9 rounded-xl hover:bg-slate-100 text-slate-500 flex items-center justify-center" aria-label="Tutup"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                <div class="flex-1 overflow-y-auto bg-slate-100 p-6 flex justify-center">
                    <img v-if="previewImage" :src="previewImage" alt="Pratinjau laporan" class="shadow-lg bg-white max-w-full h-auto" />
                </div>
                <div class="px-5 py-3 border-t border-slate-200 flex justify-end gap-2">
                    <button type="button" @click="showPreview = false" class="h-10 px-4 rounded-xl text-sm text-slate-600 hover:bg-slate-50">Tutup</button>
                    <button type="button" @click="downloadPDF(); showPreview = false" class="h-10 px-4 rounded-xl bg-blue-600 text-sm font-medium text-white hover:bg-blue-700">Unduh PDF penomoran</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#area-laporan-cetak { word-break: break-word !important; }
#area-laporan-cetak table { border-collapse: collapse !important; width: 100% !important; }
#area-laporan-cetak td { overflow-wrap: break-word !important; word-wrap: break-word !important; white-space: normal !important; }
</style>
