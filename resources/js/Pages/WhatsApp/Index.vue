<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    sessions: {
        type: Array,
        default: () => []
    },
    personnel: {
        type: Array,
        default: () => []
    },
    gatewayOnline: {
        type: Boolean,
        default: false
    },
    isAdmin: Boolean,
    isCommander: Boolean,
});

// Tab Navigasi Samping Kiri Ala WhatsApp Web: 'chats' | 'calls'
const activeLeftNav = ref('chats');

// Filter Tab Obrolan: 'all' | 'unread' | 'personal' | 'groups'
const activeChatFilter = ref('all');

// Sesi Aktif (Multi-Akun)
const activeSessionId = ref(props.sessions.length > 0 ? props.sessions[0].session_id : 'dinas');
const activeSession = computed(() => {
    return props.sessions.find(s => s.session_id === activeSessionId.value) || null;
});

// State Obrolan & Pesan
const chats = ref([]);
const isLoadingChats = ref(false);
const activeChat = ref(null); // { jid, name, number, isGroup }
const messages = ref([]);
const isLoadingMessages = ref(false);
const messageInput = ref('');
const isSending = ref(false);
const messagesContainer = ref(null);

// State Riwayat Telepon / Panggilan
const calls = ref([]);
const isLoadingCalls = ref(false);

// State Pratinjau Media Foto Penuh
const previewMedia = ref(null); // { url, name, type }

// State Pencarian Obrolan
const chatSearchQuery = ref('');
const filteredChats = computed(() => {
    let list = chats.value;

    // Filter Kategori (Semua / Belum Dibaca / Pribadi / Grup)
    if (activeChatFilter.value === 'unread') {
        list = list.filter(c => (c.unreadCount || 0) > 0);
    } else if (activeChatFilter.value === 'personal') {
        list = list.filter(c => !c.isGroup);
    } else if (activeChatFilter.value === 'groups') {
        list = list.filter(c => c.isGroup);
    }

    // Filter Kata Kunci Pencarian
    const q = chatSearchQuery.value.toLowerCase().trim();
    if (!q) return list;

    return list.filter(c => {
        const name = (c.name || '').toLowerCase();
        const number = (c.number || '').toLowerCase();
        const lastMsg = (c.lastMessage || '').toLowerCase();
        return name.includes(q) || number.includes(q) || lastMsg.includes(q);
    });
});

// State Modal Tambah Sesi (Scan QR)
const showCreateModal = ref(false);
const createForm = ref({
    label: '',
    role_access: 'all'
});
const qrCodeData = ref(null);
const qrStatus = ref('');
const isRequestingQr = ref(false);
let qrPollTimer = null;

// State Modal Obrolan Baru
const showNewChatModal = ref(false);
const newChatTab = ref('personnel'); // 'personnel' | 'manual'
const personnelSearchQuery = ref('');
const manualNumber = ref('');
const manualName = ref('');

const filteredPersonnel = computed(() => {
    const q = personnelSearchQuery.value.toLowerCase().trim();
    if (!q) return props.personnel;
    return props.personnel.filter(p => {
        const name = (p.name || '').toLowerCase();
        const nrp = (p.nrp || '').toLowerCase();
        const pangkat = (p.pangkat || '').toLowerCase();
        const phone = (p.phone || '').toLowerCase();
        return name.includes(q) || nrp.includes(q) || pangkat.includes(q) || phone.includes(q);
    });
});

// Warna Acak Konsisten untuk Nama Pengirim di Dalam Grup
const getParticipantColor = (name) => {
    const colors = ['#1f7aec', '#029046', '#e542a3', '#d13b3b', '#8b5cf6', '#d97706', '#0d9488'];
    if (!name) return colors[0];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
};

// URL Media dari Gateway melalui Laravel Proxy
const getMediaUrl = (msgId) => {
    if (!activeSessionId.value || !msgId) return '#';
    return route('whatsapp.sessions.media', {
        session: activeSessionId.value,
        msgId: msgId
    });
};

const openMediaPreview = (url, type, name) => {
    previewMedia.value = { url, type, name };
};

// Format Ukuran File
const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return 'Dokumen';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const getFileExtension = (filename) => {
    if (!filename || !filename.includes('.')) return 'BERKAS';
    return filename.split('.').pop().toUpperCase();
};

const isDefaultMediaLabel = (body, media) => {
    if (!body) return true;
    const lower = body.toLowerCase().trim();
    return lower === '[media/lampiran]' || 
           lower === '[image]' || 
           lower === '[video]' || 
           lower === '[document]' || 
           lower === '[audio]' || 
           lower === '[sticker]' || 
           (media && body === media.filename);
};

const formatDisplayName = (chat) => {
    if (!chat) return '';
    const name = chat.name || chat.number || '';
    if (name.includes('@lid')) {
        return 'Kontak WhatsApp';
    }
    return name;
};

const cleanPhoneNumber = (val) => {
    if (!val) return '';
    return val.replace('@s.whatsapp.net', '').replace('@g.us', '').replace('@lid', '');
};

const formatChatPreview = (lastMessage) => {
    if (!lastMessage) return 'Pesan baru';
    const lower = lastMessage.toLowerCase().trim();
    if (lower === '[image]') return 'Foto';
    if (lower === '[video]') return 'Video';
    if (lower === '[audio]') return 'Pesan Suara';
    if (lower === '[document]') return 'Dokumen Berkas';
    return lastMessage;
};

// Template Cepat Kedinasan
const quickTemplates = [
    { title: 'Pemberitahuan Sprin', text: 'Yth. Personel Denintel, terdapat Surat Perintah (Sprin) dinas baru yang telah diterbitkan pada sistem SINDEN. Harap segera memeriksa tugas Anda. Terima kasih.' },
    { title: 'Koordinasi Tugas', text: 'Selamat bertugas. Mohon konfirmasi kesiapan dan perkembangan situasi terkini untuk dilaporkan ke pimpinan. Terima kasih.' },
    { title: 'Panggilan Piket', text: 'Panggilan dinas jaga/piket Denintel. Harap segera merapat ke pos komando untuk serah terima tugas dinas. Terima kasih.' },
];

const insertTemplate = (text) => {
    messageInput.value = text;
};

// -------------------------------------------------------------------
// API FUNCTIONS
// -------------------------------------------------------------------

// 1. Ambil Daftar Obrolan (Pribadi & Grup)
const fetchChats = async (sessionId, silent = false) => {
    if (!sessionId) return;
    if (!silent) isLoadingChats.value = true;
    try {
        const res = await fetch(route('whatsapp.sessions.chats', sessionId));
        const data = await res.json();
        if (data.ok && Array.isArray(data.chats)) {
            chats.value = data.chats;
            if (activeChat.value) {
                const current = data.chats.find(c => c.jid === activeChat.value.jid);
                if (current && current.name && current.name !== activeChat.value.name) {
                    activeChat.value.name = current.name;
                }
            }
        } else if (!silent) {
            chats.value = [];
        }
    } catch (e) {
        if (!silent) chats.value = [];
    } finally {
        if (!silent) isLoadingChats.value = false;
    }
};

// 2. Ambil Riwayat Telepon / Panggilan
const fetchCalls = async (sessionId, silent = false) => {
    if (!sessionId) return;
    if (!silent) isLoadingCalls.value = true;
    try {
        const res = await fetch(route('whatsapp.sessions.calls', sessionId));
        const data = await res.json();
        if (data.ok && Array.isArray(data.calls)) {
            calls.value = data.calls;
        } else if (!silent) {
            calls.value = [];
        }
    } catch (e) {
        if (!silent) calls.value = [];
    } finally {
        if (!silent) isLoadingCalls.value = false;
    }
};

// 3. Pilih Percakapan & Ambil Riwayat Pesan
const selectChat = async (chat) => {
    activeChat.value = chat;
    await fetchMessages(chat.jid, false);
};

const fetchMessages = async (jid, silent = false) => {
    if (!activeSessionId.value || !jid) return;
    if (!silent) isLoadingMessages.value = true;
    try {
        const res = await fetch(route('whatsapp.sessions.messages', {
            session: activeSessionId.value,
            jid: jid
        }));
        const data = await res.json();
        if (data.ok && Array.isArray(data.messages)) {
            const prevCount = messages.value.length;
            const newCount = data.messages.length;
            const prevLastId = prevCount > 0 ? messages.value[prevCount - 1].id : null;
            const newLastId = newCount > 0 ? data.messages[newCount - 1].id : null;

            const isUpdated = newCount !== prevCount || prevLastId !== newLastId;
            if (isUpdated || !silent) {
                messages.value = data.messages;
                scrollToBottom();
            }
        } else if (!silent) {
            messages.value = [];
        }
    } catch (e) {
        if (!silent) messages.value = [];
    } finally {
        if (!silent) isLoadingMessages.value = false;
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

// 4. Kirim Pesan
const sendMessage = async () => {
    const text = messageInput.value.trim();
    if (!text || !activeChat.value || !activeSessionId.value) return;

    isSending.value = true;
    const targetNumber = activeChat.value.jid;

    // Optimistic UI update
    const tempId = 'temp-' + Date.now();
    const tempMsg = {
        id: tempId,
        fromMe: true,
        isGroup: activeChat.value.isGroup,
        body: text,
        media: null,
        timestamp: new Date().toISOString(),
        status: 'PENDING'
    };
    messages.value.push(tempMsg);
    messageInput.value = '';
    scrollToBottom();

    try {
        const res = await fetch(route('whatsapp.sessions.send', activeSessionId.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                number: targetNumber,
                message: text
            })
        });

        const data = await res.json();
        if (data.ok) {
            tempMsg.status = 'SENT';
            // Langsung perbarui obrolan dan pesan tanpa jeda
            fetchChats(activeSessionId.value, true);
            fetchMessages(targetNumber, true);
        } else {
            tempMsg.status = 'FAILED';
            Swal.fire('Gagal Kirim', data.error || 'Terjadi gangguan pengiriman pesan.', 'error');
        }
    } catch (e) {
        tempMsg.status = 'FAILED';
        Swal.fire('Kesalahan', 'Gagal menghubungi server: ' + e.message, 'error');
    } finally {
        isSending.value = false;
    }
};

// 5. Ganti Akun Sesi Aktif
const switchSession = (sessionId) => {
    activeSessionId.value = sessionId;
    activeChat.value = null;
    messages.value = [];
    const sess = props.sessions.find(s => s.session_id === sessionId);
    if (sess && sess.status === 'connected') {
        fetchChats(sessionId, false);
        fetchCalls(sessionId, false);
    } else {
        chats.value = [];
        calls.value = [];
    }
};

// 6. Modal Tambah Sesi & Minta QR
const openCreateModal = () => {
    createForm.value = {
        label: '',
        role_access: 'all'
    };
    qrCodeData.value = null;
    qrStatus.value = '';
    showCreateModal.value = true;
};

const submitCreateSession = async () => {
    if (!createForm.value.label.trim()) {
        Swal.fire('Peringatan', 'Harap isi nama label akun WhatsApp.', 'warning');
        return;
    }

    isRequestingQr.value = true;
    try {
        const res = await fetch(route('whatsapp.sessions.create'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(createForm.value)
        });

        const data = await res.json();
        if (data.ok) {
            qrCodeData.value = data.qr;
            qrStatus.value = data.status;
            startQrPolling(data.session.session_id);
        } else {
            Swal.fire('Gagal', data.error || 'Gagal membuat sesi WhatsApp.', 'error');
        }
    } catch (e) {
        Swal.fire('Kesalahan', 'Gagal memproses permintaan: ' + e.message, 'error');
    } finally {
        isRequestingQr.value = false;
    }
};

const startQrPolling = (sessionId) => {
    stopQrPolling();
    qrPollTimer = setInterval(async () => {
        try {
            const res = await fetch(route('whatsapp.sessions.qr', sessionId));
            const data = await res.json();
            if (data.ok) {
                if (data.qr) {
                    qrCodeData.value = data.qr;
                }
                qrStatus.value = data.status;

                if (data.status === 'CONNECTED') {
                    stopQrPolling();
                    showCreateModal.value = false;
                    Swal.fire('Berhasil Terhubung', 'Perangkat WhatsApp berhasil ditautkan ke sistem SINDEN.', 'success');
                    router.reload({ preserveScroll: true });
                }
            }
        } catch (e) {}
    }, 2500);
};

const stopQrPolling = () => {
    if (qrPollTimer) {
        clearInterval(qrPollTimer);
        qrPollTimer = null;
    }
};

const closeCreateModal = () => {
    stopQrPolling();
    showCreateModal.value = false;
};

// 7. Logout Sesi
const logoutSession = (sessionId) => {
    Swal.fire({
        title: 'Putuskan Akun WhatsApp?',
        text: 'Sesi perangkat tertaut akan diputuskan dan kredensial dihapus secara bersih dari server.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Putuskan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await fetch(route('whatsapp.sessions.logout', sessionId), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                const data = await res.json();
                if (data.ok) {
                    Swal.fire('Terputus', 'Akun WhatsApp telah diputuskan.', 'success');
                    router.reload({ preserveScroll: true });
                }
            } catch (e) {
                Swal.fire('Kesalahan', 'Gagal memutus sesi: ' + e.message, 'error');
            }
        }
    });
};

// 8. Hapus Sesi
const deleteSession = (sessionId) => {
    Swal.fire({
        title: 'Hapus Sesi Akun?',
        text: 'Riwayat data sesi ini akan dihapus secara permanen dari sistem.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Permanen',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const res = await fetch(route('whatsapp.sessions.delete', sessionId), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });
                const data = await res.json();
                if (data.ok) {
                    Swal.fire('Terhapus', 'Sesi berhasil dihapus.', 'success');
                    router.reload({ preserveScroll: true });
                }
            } catch (e) {
                Swal.fire('Kesalahan', 'Gagal menghapus sesi: ' + e.message, 'error');
            }
        }
    });
};

// 9. Mulai Chat Baru
const startChatWithPersonnel = (person) => {
    if (!person.phone) {
        Swal.fire('Peringatan', 'Personel ini belum memiliki nomor telepon tersimpan.', 'warning');
        return;
    }
    const cleanNumber = person.phone.replace(/[^0-9]/g, '');
    activeChat.value = {
        jid: cleanNumber + '@s.whatsapp.net',
        isGroup: false,
        number: cleanNumber,
        name: `${person.pangkat ? person.pangkat + ' ' : ''}${person.name}`
    };
    showNewChatModal.value = false;
    fetchMessages(activeChat.value.jid, false);
};

const startChatManual = () => {
    const num = manualNumber.value.trim().replace(/[^0-9]/g, '');
    if (!num) {
        Swal.fire('Peringatan', 'Masukkan nomor WhatsApp tujuan.', 'warning');
        return;
    }
    activeChat.value = {
        jid: num + '@s.whatsapp.net',
        isGroup: false,
        number: num,
        name: manualName.value.trim() || num
    };
    showNewChatModal.value = false;
    manualNumber.value = '';
    manualName.value = '';
    fetchMessages(activeChat.value.jid, false);
};

// Sinkronisasi Terus-Menerus Tanpa Refresh (Polling Lancar Tiap 2000ms)
let syncTimer = null;
const startSyncTimer = () => {
    if (syncTimer) clearInterval(syncTimer);
    syncTimer = setInterval(async () => {
        if (!activeSessionId.value) return;

        // 1. Perbarui daftar obrolan di sidebar kiri secara senyap
        await fetchChats(activeSessionId.value, true);

        // 2. Jika ada obrolan aktif yang dibuka, perbarui riwayat pesannya secara senyap
        if (activeChat.value) {
            await fetchMessages(activeChat.value.jid, true);
        }

        // 3. Jika sedang membuka tab riwayat panggilan, perbarui secara senyap
        if (activeLeftNav.value === 'calls') {
            await fetchCalls(activeSessionId.value, true);
        }
    }, 2000);
};

onMounted(() => {
    if (activeSession.value && activeSession.value.status === 'connected') {
        fetchChats(activeSession.value.session_id, false);
        fetchCalls(activeSession.value.session_id, false);
    }
    startSyncTimer();
});

onUnmounted(() => {
    stopQrPolling();
    if (syncTimer) clearInterval(syncTimer);
});

const formatTime = (isoString) => {
    if (!isoString) return '';
    try {
        const d = new Date(isoString);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return '';
    }
};

const formatDateFull = (isoString) => {
    if (!isoString) return '';
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return '';
    }
};
</script>

<template>
    <Head title="WhatsApp Web - SINDEN" />
    <AuthenticatedLayout>
        <div class="space-y-3 font-sans">
            
            <!-- Bilah Atas: Pemilih Akun (Multi-Akun Switcher) & Status Gateway -->
            <div class="bg-white p-3 px-4 rounded-2xl shadow-xs border border-[#E2E8F0] flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-black uppercase text-slate-500 tracking-wider me-1">Pilih Akun:</span>
                    
                    <button 
                        v-for="s in sessions" 
                        :key="s.id"
                        @click="switchSession(s.session_id)"
                        :class="activeSessionId === s.session_id ? 'bg-[#00a884] text-white shadow-xs font-extrabold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold'"
                        class="px-3.5 py-1.5 rounded-full text-xs transition flex items-center gap-2 cursor-pointer"
                    >
                        <span 
                            :class="s.status === 'connected' ? 'bg-emerald-300' : 'bg-slate-400'"
                            class="w-2 h-2 rounded-full inline-block"
                        ></span>
                        <span>{{ s.label }}</span>
                        <span v-if="s.phone_number" class="text-[10px] opacity-80 font-mono">({{ s.phone_number }})</span>
                    </button>

                    <!-- Tombol Tambah Sesi Akun Baru (Scan QR) -->
                    <button 
                        @click="openCreateModal"
                        class="px-3 py-1.5 border border-dashed border-slate-300 hover:border-[#00a884] text-slate-600 hover:text-[#00a884] rounded-full text-xs font-bold transition flex items-center gap-1.5 cursor-pointer bg-white"
                    >
                        <span class="text-sm leading-none font-black">+</span> Tambah Nomor (Scan QR)
                    </button>
                </div>

                <div class="flex items-center gap-2 text-xs font-bold">
                    <!-- Indikator Sinkronisasi Otomatis Aktif -->
                    <div v-if="activeSession && activeSession.status === 'connected'" class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg text-[10px] font-black uppercase border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Sinkronisasi Otomatis
                    </div>

                    <span v-if="activeSession" :class="activeSession.status === 'connected' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'" class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg border">
                        Status: {{ activeSession.status }}
                    </span>
                    <button 
                        v-if="activeSession && activeSession.status === 'connected'" 
                        @click="logoutSession(activeSession.session_id)"
                        class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-lg text-[11px] font-bold transition cursor-pointer"
                    >
                        Logout
                    </button>
                    <button 
                        v-if="activeSession" 
                        @click="deleteSession(activeSession.session_id)"
                        class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-[11px] font-bold transition cursor-pointer"
                    >
                        Hapus
                    </button>
                </div>
            </div>

            <!-- CONTAINER UTAMA TAMPILAN WHATSAPP WEB ASLI -->
            <div class="bg-[#ffffff] rounded-2xl shadow-sm border border-[#d1d7db] overflow-hidden flex h-[calc(100vh-12rem)] min-h-[620px] max-h-[880px]">
                
                <!-- 1. VERTICAL ICON RAIL (Bilah Ikon Samping Kiri Ala WA Web) -->
                <div class="w-14 bg-[#f0f2f5] border-r border-[#d1d7db] flex flex-col items-center justify-between py-3 shrink-0 select-none">
                    <div class="space-y-4 flex flex-col items-center w-full">
                        <!-- Tombol Tab Obrolan (Chats) -->
                        <button 
                            @click="activeLeftNav = 'chats'"
                            :class="activeLeftNav === 'chats' ? 'bg-[#d9fdd3] text-[#00a884]' : 'text-[#54656f] hover:bg-slate-200/80'"
                            class="w-10 h-10 rounded-xl flex items-center justify-center transition cursor-pointer relative"
                            title="Obrolan"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </button>

                        <!-- Tombol Tab Riwayat Panggilan (Calls / Telepon) -->
                        <button 
                            @click="activeLeftNav = 'calls'; fetchCalls(activeSessionId, false)"
                            :class="activeLeftNav === 'calls' ? 'bg-[#d9fdd3] text-[#00a884]' : 'text-[#54656f] hover:bg-slate-200/80'"
                            class="w-10 h-10 rounded-xl flex items-center justify-center transition cursor-pointer relative"
                            title="Riwayat Panggilan / Telepon"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3 flex flex-col items-center">
                        <!-- Tombol Tambah Sesi Baru dari Rail -->
                        <button 
                            @click="openCreateModal"
                            class="w-10 h-10 rounded-full hover:bg-slate-200 text-[#54656f] flex items-center justify-center transition cursor-pointer"
                            title="Tautkan Akun WhatsApp Baru"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- 2. PANEL DAFTAR OBROLAN / PANGGILAN (KIRI) -->
                <div class="w-80 sm:w-96 border-r border-[#d1d7db] flex flex-col bg-white shrink-0">
                    
                    <!-- KONDISI A: TAMPILAN TAB OBROLAN (CHATS) -->
                    <template v-if="activeLeftNav === 'chats'">
                        <!-- Header Panel Obrolan -->
                        <div class="p-3 px-4 bg-[#ffffff] border-b border-[#f0f2f5] flex items-center justify-between shrink-0">
                            <h2 class="font-extrabold text-lg text-[#111b21] tracking-tight">Obrolan</h2>
                            <div class="flex items-center gap-1 text-[#54656f]">
                                <button 
                                    @click="showNewChatModal = true"
                                    class="w-9 h-9 rounded-full hover:bg-[#f0f2f5] flex items-center justify-center transition cursor-pointer"
                                    title="Mulai Obrolan Baru"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </button>
                                <button 
                                    @click="fetchChats(activeSessionId, false)"
                                    class="w-9 h-9 rounded-full hover:bg-[#f0f2f5] flex items-center justify-center transition cursor-pointer"
                                    title="Segarkan Obrolan"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Kolom Pencarian Kontak Ala WhatsApp Web -->
                        <div class="p-2.5 px-3 bg-[#ffffff] border-b border-[#f0f2f5] shrink-0">
                            <div class="relative flex items-center bg-[#f0f2f5] rounded-lg px-3 py-1.5 focus-within:bg-white focus-within:ring-1 focus-within:ring-[#00a884] transition">
                                <svg class="w-4 h-4 text-[#54656f] me-2.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input 
                                    v-model="chatSearchQuery"
                                    type="text" 
                                    placeholder="Cari atau mulai obrolan baru" 
                                    class="w-full bg-transparent border-none text-xs text-[#111b21] placeholder-[#54656f] focus:ring-0 p-0"
                                />
                            </div>
                        </div>

                        <!-- Filter Tab Kategori (Semua, Belum dibaca, Pribadi, Grup) -->
                        <div class="px-3 py-2 bg-[#ffffff] border-b border-[#f0f2f5] flex items-center gap-1.5 overflow-x-auto shrink-0 select-none">
                            <button 
                                @click="activeChatFilter = 'all'"
                                :class="activeChatFilter === 'all' ? 'bg-[#d9fdd3] text-[#008069] font-black' : 'bg-[#f0f2f5] text-[#54656f] font-semibold hover:bg-slate-200'"
                                class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition cursor-pointer"
                            >
                                Semua
                            </button>
                            <button 
                                @click="activeChatFilter = 'unread'"
                                :class="activeChatFilter === 'unread' ? 'bg-[#d9fdd3] text-[#008069] font-black' : 'bg-[#f0f2f5] text-[#54656f] font-semibold hover:bg-slate-200'"
                                class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition cursor-pointer"
                            >
                                Belum dibaca
                            </button>
                            <button 
                                @click="activeChatFilter = 'personal'"
                                :class="activeChatFilter === 'personal' ? 'bg-[#d9fdd3] text-[#008069] font-black' : 'bg-[#f0f2f5] text-[#54656f] font-semibold hover:bg-slate-200'"
                                class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition cursor-pointer"
                            >
                                Pribadi
                            </button>
                            <button 
                                @click="activeChatFilter = 'groups'"
                                :class="activeChatFilter === 'groups' ? 'bg-[#d9fdd3] text-[#008069] font-black' : 'bg-[#f0f2f5] text-[#54656f] font-semibold hover:bg-slate-200'"
                                class="px-3 py-1 rounded-full text-[11px] whitespace-nowrap transition cursor-pointer"
                            >
                                Grup
                            </button>
                        </div>

                        <!-- Daftar Percakapan (List of Chats) -->
                        <div class="flex-1 overflow-y-auto divide-y divide-[#f0f2f5]">
                            <div v-if="isLoadingChats" class="p-8 text-center text-xs text-[#54656f]">
                                Memuat daftar obrolan...
                            </div>

                            <div v-else-if="filteredChats.length === 0" class="p-8 text-center text-xs text-[#54656f]">
                                Tidak ada obrolan dalam kategori ini.
                            </div>

                            <div 
                                v-for="c in filteredChats" 
                                :key="c.jid"
                                @click="selectChat(c)"
                                :class="activeChat && activeChat.jid === c.jid ? 'bg-[#f0f2f5]' : 'hover:bg-[#f5f6f6]'"
                                class="p-3 px-3.5 flex items-center gap-3 transition cursor-pointer"
                            >
                                <!-- Avatar: Grup vs Kontak Pribadi -->
                                <div 
                                    :class="c.isGroup ? 'bg-[#00a884]/15 text-[#00a884]' : 'bg-[#dfe5e7] text-[#54656f]'"
                                    class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 uppercase font-black text-xs relative"
                                >
                                    <template v-if="c.isGroup">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 12.75c1.63 0 3.07.39 4.24.9 1.08.48 1.76 1.56 1.76 2.73V18H6v-1.61c0-1.18.68-2.26 1.76-2.74 1.17-.52 2.61-.9 4.24-.9zM4 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm16 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm-8-3c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3z"/>
                                        </svg>
                                    </template>
                                    <template v-else>
                                        {{ (formatDisplayName(c) || 'W').slice(0, 2) }}
                                    </template>
                                </div>

                                <!-- Ringkasan Pesan -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5 truncate">
                                            <h4 class="text-xs font-bold text-[#111b21] truncate">{{ formatDisplayName(c) }}</h4>
                                            <span v-if="c.isGroup" class="px-1.5 py-0.2 bg-[#00a884]/10 text-[#008069] rounded text-[9px] font-black uppercase">Grup</span>
                                        </div>
                                        <span class="text-[10px] text-[#667781] font-semibold shrink-0">{{ formatTime(c.timestamp) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between mt-0.5">
                                        <p class="text-[11px] text-[#667781] truncate">
                                            <span v-if="c.isGroup && c.lastSender" class="font-bold text-[#111b21]">{{ c.lastSender }}: </span>
                                            {{ formatChatPreview(c.lastMessage) }}
                                        </p>
                                        <span v-if="c.unreadCount > 0" class="px-1.5 py-0.5 bg-[#25d366] text-white rounded-full text-[9px] font-black shrink-0">
                                            {{ c.unreadCount }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- KONDISI B: TAMPILAN TAB RIWAYAT TELEPON (CALLS) -->
                    <template v-else-if="activeLeftNav === 'calls'">
                        <div class="p-3 px-4 bg-[#ffffff] border-b border-[#f0f2f5] flex items-center justify-between shrink-0">
                            <h2 class="font-extrabold text-lg text-[#111b21] tracking-tight">Panggilan</h2>
                            <button 
                                @click="fetchCalls(activeSessionId, false)"
                                class="w-9 h-9 rounded-full hover:bg-[#f0f2f5] text-[#54656f] flex items-center justify-center transition cursor-pointer"
                                title="Segarkan Riwayat Panggilan"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto divide-y divide-[#f0f2f5]">
                            <div v-if="isLoadingCalls" class="p-8 text-center text-xs text-[#54656f]">
                                Memuat riwayat panggilan...
                            </div>

                            <div v-else-if="calls.length === 0" class="p-8 text-center text-xs text-[#54656f]">
                                Belum ada riwayat panggilan telepon masuk atau keluar pada sesi ini.
                            </div>

                            <div 
                                v-for="call in calls" 
                                :key="call.id"
                                class="p-3 px-4 flex items-center justify-between hover:bg-[#f5f6f6] transition"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#dfe5e7] text-[#54656f] flex items-center justify-center font-bold text-xs uppercase">
                                        {{ (call.name || call.number || 'W').slice(0, 2) }}
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-[#111b21]">{{ call.name || call.number }}</h4>
                                        <div class="flex items-center gap-1.5 text-[10px] text-[#667781] mt-0.5">
                                            <!-- Ikon Arah Panggilan -->
                                            <span v-if="call.status === 'timeout' || call.status === 'reject'" class="text-rose-600 font-bold flex items-center gap-1">
                                                <svg class="w-3 h-3 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                                Panggilan Tak Terjawab
                                            </span>
                                            <span v-else class="text-emerald-600 font-bold flex items-center gap-1">
                                                <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                                Panggilan Masuk
                                            </span>
                                            <span>&bull;</span>
                                            <span>{{ formatDateFull(call.timestamp) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ikon Tipe Panggilan: Suara / Video -->
                                <div class="text-[#54656f]">
                                    <svg v-if="call.isVideo" class="w-5 h-5 text-[#00a884]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-[#00a884]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- 3. PANEL KANAN: RUANG OBROLAN (CHAT CANVAS) -->
                <div class="flex-1 flex flex-col bg-[#efeae2] relative overflow-hidden">
                    
                    <!-- Motif Latar Belakang Khas WhatsApp Web -->
                    <div class="absolute inset-0 opacity-40 pointer-events-none bg-[radial-gradient(#cbd5e1_1px,transparent_1px)] [background-size:16px_16px]"></div>

                    <!-- KONDISI A: TIDAK ADA CHAT AKTIF YANG DIPILIH -->
                    <div v-if="!activeChat" class="flex-1 flex flex-col items-center justify-center p-8 text-center relative z-10 select-none">
                        <div class="w-20 h-20 rounded-full bg-white shadow-xs border border-black/5 flex items-center justify-center mb-4">
                            <svg class="w-10 h-10 text-[#25d366]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-extrabold text-[#111b21] tracking-tight">WhatsApp Web SINDEN</h2>
                        <p class="text-xs text-[#667781] max-w-sm mt-1 leading-relaxed">
                            Kirim dan terima pesan kedinasan secara aman. Pilih obrolan di sebelah kiri atau klik tombol + untuk memulai obrolan baru.
                        </p>
                    </div>

                    <!-- KONDISI B: OBROLAN AKTIF TERPILIH -->
                    <template v-else>
                        <!-- Header Obrolan Atas -->
                        <div class="p-3 px-4 bg-[#f0f2f5] border-b border-[#d1d7db] flex items-center justify-between shrink-0 relative z-10">
                            <div class="flex items-center gap-3">
                                <!-- Avatar Obrolan -->
                                <div 
                                    :class="activeChat.isGroup ? 'bg-[#00a884]/20 text-[#00a884]' : 'bg-[#dfe5e7] text-[#54656f]'"
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs uppercase shrink-0"
                                >
                                    <template v-if="activeChat.isGroup">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 12.75c1.63 0 3.07.39 4.24.9 1.08.48 1.76 1.56 1.76 2.73V18H6v-1.61c0-1.18.68-2.26 1.76-2.74 1.17-.52 2.61-.9 4.24-.9zM4 13c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm16 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm-8-3c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3z"/>
                                        </svg>
                                    </template>
                                    <template v-else>
                                        {{ (formatDisplayName(activeChat) || 'W').slice(0, 2) }}
                                    </template>
                                </div>

                                <!-- Nama Kontak / Grup -->
                                <div>
                                    <h3 class="text-xs font-bold text-[#111b21]">{{ formatDisplayName(activeChat) }}</h3>
                                    <p class="text-[10px] text-[#667781] truncate">
                                        {{ activeChat.isGroup ? 'Grup WhatsApp' : cleanPhoneNumber(activeChat.number || activeChat.jid) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 text-[#54656f]">
                                <button @click="fetchMessages(activeChat.jid, false)" class="w-8 h-8 rounded-full hover:bg-slate-200/80 flex items-center justify-center transition cursor-pointer" title="Perbarui Pesan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Ruang Obrolan (Bubbles) -->
                        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-2 relative z-10">
                            <!-- Pemisah Tanggal WhatsApp -->
                            <div class="flex justify-center my-2">
                                <span class="bg-[#ffffff]/90 shadow-xs border border-black/5 text-[#54656f] text-[10px] font-bold px-3 py-1 rounded-md uppercase">
                                    Pesan Terenkripsi Kedinasan SINDEN
                                </span>
                            </div>

                            <div v-if="isLoadingMessages" class="text-center text-xs text-[#54656f] py-4">
                                Mengambil riwayat pesan...
                            </div>

                            <div v-else-if="messages.length === 0" class="text-center text-xs text-[#54656f] py-6">
                                Belum ada riwayat pesan dalam percakapan ini.
                            </div>

                            <div 
                                v-for="m in messages" 
                                :key="m.id"
                                :class="m.fromMe ? 'justify-end' : 'justify-start'"
                                class="flex items-end gap-1"
                            >
                                <!-- Gelembung Obrolan (Bubble) -->
                                <div 
                                    :class="m.fromMe ? 'bg-[#d9fdd3] text-[#111b21] rounded-lg rounded-tr-none' : 'bg-[#ffffff] text-[#111b21] rounded-lg rounded-tl-none'"
                                    class="max-w-[85%] sm:max-w-[70%] p-2 px-3 shadow-xs border border-black/5 text-xs relative space-y-1.5"
                                >
                                    <!-- Nama Pengirim jika di dalam Grup dan bukan pesan dari diri sendiri -->
                                    <p 
                                        v-if="m.isGroup && !m.fromMe && m.participantName" 
                                        class="text-[11px] font-extrabold pb-0.5"
                                        :style="{ color: getParticipantColor(m.participantName) }"
                                    >
                                        {{ m.participantName }}
                                    </p>

                                    <!-- TAMPILAN BERKAS MEDIA JIKA ADA -->
                                    <template v-if="m.media">
                                        <!-- 1. Tipe Gambar / Foto -->
                                        <div v-if="m.media.type === 'image'" class="rounded-lg overflow-hidden bg-black/5 max-w-sm">
                                            <img 
                                                :src="getMediaUrl(m.id)" 
                                                alt="Foto WhatsApp" 
                                                class="w-full max-h-80 object-cover cursor-pointer hover:opacity-95 transition"
                                                loading="lazy"
                                                @click="openMediaPreview(getMediaUrl(m.id), 'image', m.media.filename)"
                                            />
                                        </div>

                                        <!-- 2. Tipe Video -->
                                        <div v-else-if="m.media.type === 'video'" class="rounded-lg overflow-hidden bg-black max-w-sm">
                                            <video 
                                                :src="getMediaUrl(m.id)" 
                                                controls 
                                                class="w-full max-h-80 rounded-lg"
                                                preload="metadata"
                                            ></video>
                                        </div>

                                        <!-- 3. Tipe Audio / Pesan Suara -->
                                        <div v-else-if="m.media.type === 'audio'" class="py-1 min-w-[220px]">
                                            <audio :src="getMediaUrl(m.id)" controls class="w-full h-8"></audio>
                                        </div>

                                        <!-- 4. Tipe Dokumen / PDF -->
                                        <div v-else-if="m.media.type === 'document'" class="flex items-center gap-2.5 p-2.5 bg-black/[0.04] rounded-lg border border-black/5 hover:bg-black/[0.07] transition max-w-sm">
                                            <div class="w-9 h-9 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-xs font-bold text-[#111b21] truncate" :title="m.media.filename">{{ m.media.filename || 'Dokumen' }}</h4>
                                                <p class="text-[10px] text-[#667781] font-semibold">{{ formatFileSize(m.media.fileSize) }} &bull; {{ getFileExtension(m.media.filename) }}</p>
                                            </div>
                                            <!-- Tombol Unduh Dokumen -->
                                            <a 
                                                :href="getMediaUrl(m.id)" 
                                                :download="m.media.filename || 'dokumen.pdf'"
                                                target="_blank"
                                                class="w-8 h-8 rounded-full bg-white hover:bg-slate-100 text-[#111b21] shadow-xs flex items-center justify-center shrink-0 transition"
                                                title="Unduh Dokumen"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                </svg>
                                            </a>
                                        </div>

                                        <!-- Teks Keterangan / Caption Media -->
                                        <p v-if="m.body && !isDefaultMediaLabel(m.body, m.media)" class="whitespace-pre-wrap leading-relaxed text-[12.5px] select-text">
                                            {{ m.body }}
                                        </p>
                                    </template>

                                    <!-- TAMPILAN PESAN TEKS BIASA -->
                                    <template v-else>
                                        <p class="whitespace-pre-wrap leading-relaxed text-[12.5px] select-text">{{ m.body }}</p>
                                    </template>
                                    
                                    <!-- Waktu & Centang Ganda Khas WhatsApp -->
                                    <div class="flex items-center justify-end gap-1 text-[9px] text-[#667781] select-none pt-0.5">
                                        <span>{{ formatTime(m.timestamp) }}</span>
                                        <template v-if="m.fromMe">
                                            <span class="text-[#53bdeb] font-black">✓✓</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Template Cepat Kedinasan -->
                        <div class="bg-[#f0f2f5] px-4 py-1.5 border-t border-[#d1d7db] flex items-center gap-1.5 overflow-x-auto relative z-10 shrink-0">
                            <span class="text-[10px] font-bold text-[#54656f] uppercase shrink-0">Cepat:</span>
                            <button 
                                v-for="t in quickTemplates" 
                                :key="t.title"
                                @click="insertTemplate(t.text)"
                                class="px-2.5 py-0.5 bg-white hover:bg-[#d9fdd3] text-[#111b21] border border-black/5 rounded-md text-[10px] font-bold whitespace-nowrap transition cursor-pointer"
                            >
                                {{ t.title }}
                            </button>
                        </div>

                        <!-- Bilah Pengetikan Pesan Bawah Ala WhatsApp Web Asli -->
                        <div class="p-2.5 px-4 bg-[#f0f2f5] border-t border-[#d1d7db] flex items-center gap-2.5 shrink-0 relative z-10">
                            <!-- Input Kapsul Putih -->
                            <div class="flex-1 bg-white rounded-lg px-3 py-2 border border-transparent focus-within:border-[#00a884] shadow-xs flex items-center">
                                <textarea 
                                    v-model="messageInput"
                                    @keydown.enter.exact.prevent="sendMessage"
                                    rows="1"
                                    placeholder="Ketik pesan"
                                    class="w-full bg-transparent border-none text-xs text-[#111b21] placeholder-[#54656f] focus:ring-0 p-0 resize-none max-h-24"
                                ></textarea>
                            </div>

                            <!-- Tombol Kirim Pesan -->
                            <button 
                                @click="sendMessage" 
                                :disabled="!messageInput.trim() || isSending"
                                class="w-10 h-10 rounded-full bg-[#00a884] hover:bg-[#008f6f] disabled:opacity-50 text-white flex items-center justify-center transition shadow-xs cursor-pointer shrink-0"
                                title="Kirim Pesan"
                            >
                                <svg class="w-4 h-4 rotate-45 -mr-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL PRATINJAU MEDIA FOTO -->
        <div v-if="previewMedia" class="fixed inset-0 z-[300] flex items-center justify-center bg-black/80 p-4">
            <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center">
                <button 
                    @click="previewMedia = null" 
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 text-2xl font-bold cursor-pointer"
                >
                    &times;
                </button>
                <img :src="previewMedia.url" :alt="previewMedia.name" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl" />
                <div class="mt-3 flex items-center gap-3">
                    <a 
                        :href="previewMedia.url" 
                        :download="previewMedia.name || 'foto.jpg'"
                        class="px-4 py-2 bg-[#00a884] text-white rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-[#008f6f] transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Unduh Foto
                    </a>
                </div>
            </div>
        </div>

        <!-- MODAL 1: TAMBAH SESI WHATSAPP BARU (SCAN QR CODE) -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 text-left">
            <div class="bg-white w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-100">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="font-extrabold text-sm text-slate-900">Tautkan Perangkat WhatsApp Baru</h3>
                    <button @click="closeCreateModal" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <!-- FORM PEMBUATAN SESI -->
                <div v-if="!qrCodeData" class="space-y-4">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Label / Nama Akun</label>
                        <input 
                            v-model="createForm.label" 
                            type="text" 
                            placeholder="Contoh: WA Komandan, WA Piket Jaga, WA Subden 1"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold py-2.5 mt-1 focus:ring-[#00a884] focus:border-[#00a884]"
                        />
                    </div>

                    <div v-if="isAdmin">
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Akses Penggunaan Akun</label>
                        <select 
                            v-model="createForm.role_access"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold py-2.5 mt-1 focus:ring-[#00a884] focus:border-[#00a884]"
                        >
                            <option value="all">Semua Operator / Personel Terdaftar</option>
                            <option value="komandan">Hanya Komandan & Pimpinan</option>
                            <option value="admin">Khusus Administrator Sistem</option>
                        </select>
                    </div>

                    <button 
                        @click="submitCreateSession" 
                        :disabled="isRequestingQr"
                        class="w-full py-2.5 bg-[#00a884] text-white rounded-xl font-bold uppercase text-xs hover:bg-[#008f6f] transition shadow-xs cursor-pointer disabled:opacity-50"
                    >
                        {{ isRequestingQr ? 'Menghasilkan QR Code...' : 'Buat Sesi & Tampilkan QR Code' }}
                    </button>
                </div>

                <!-- TAMPILAN QR CODE UNTUK DIPINDAI -->
                <div v-else class="text-center space-y-4 py-2">
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Buka <b>WhatsApp</b> di ponsel Anda &rarr; Pilih <b>Perangkat Tertaut</b> &rarr; Pilih <b>Tautkan Perangkat</b> lalu arahkan kamera ke QR Code di bawah:
                    </p>

                    <div class="bg-white p-3 rounded-2xl border border-slate-200 inline-block shadow-md">
                        <img :src="qrCodeData" alt="WhatsApp QR Code" class="w-56 h-56 mx-auto rounded-lg" />
                    </div>

                    <div class="flex items-center justify-center gap-2 text-xs font-bold text-slate-500">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                        <span>Status: {{ qrStatus || 'Menunggu Pemindaian...' }}</span>
                    </div>

                    <button type="button" @click="closeCreateModal" class="w-full py-2 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs hover:bg-slate-200 transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 2: MULAI OBROLAN BARU -->
        <div v-if="showNewChatModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 text-left">
            <div class="bg-white w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-3 border border-slate-100 max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3 shrink-0">
                    <h3 class="font-extrabold text-sm text-slate-900">Mulai Obrolan Baru</h3>
                    <button @click="showNewChatModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <div class="flex border-b border-slate-200 shrink-0">
                    <button 
                        @click="newChatTab = 'personnel'"
                        :class="newChatTab === 'personnel' ? 'border-b-2 border-[#00a884] text-[#00a884] font-black' : 'text-slate-400 font-bold'"
                        class="flex-1 py-2 text-xs uppercase tracking-wider text-center transition cursor-pointer"
                    >
                        Personel SINDEN
                    </button>
                    <button 
                        @click="newChatTab = 'manual'"
                        :class="newChatTab === 'manual' ? 'border-b-2 border-[#00a884] text-[#00a884] font-black' : 'text-slate-400 font-bold'"
                        class="flex-1 py-2 text-xs uppercase tracking-wider text-center transition cursor-pointer"
                    >
                        Ketik Nomor Manual
                    </button>
                </div>

                <div v-if="newChatTab === 'personnel'" class="flex-1 overflow-y-auto space-y-2 min-h-0 pt-1">
                    <input 
                        type="text" 
                        v-model="personnelSearchQuery"
                        placeholder="Cari nama, pangkat, atau NRP..."
                        class="w-full bg-[#f0f2f5] border-none rounded-lg text-xs py-2 px-3 text-slate-800 focus:ring-1 focus:ring-[#00a884]"
                    />

                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                        <div 
                            v-for="p in filteredPersonnel" 
                            :key="p.id"
                            @click="startChatWithPersonnel(p)"
                            class="p-2 hover:bg-[#f0f2f5] rounded-lg flex items-center justify-between transition cursor-pointer"
                        >
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-[#dfe5e7] text-[#54656f] font-bold text-xs flex items-center justify-center uppercase">
                                    {{ p.name.slice(0, 2) }}
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-[#111b21]">{{ p.pangkat ? p.pangkat + ' ' : '' }}{{ p.name }}</h4>
                                    <p class="text-[10px] text-[#667781]">WA: <b class="text-[#00a884]">{{ p.phone || '-' }}</b></p>
                                </div>
                            </div>
                            <span class="text-[10px] text-[#00a884] font-bold uppercase">Pilih</span>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-3 pt-2">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nomor WhatsApp Tujuan</label>
                        <input 
                            v-model="manualNumber" 
                            type="text" 
                            placeholder="Contoh: 08123456789 atau 628123456789"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold py-2.5 mt-1 focus:ring-[#00a884] focus:border-[#00a884]"
                        />
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nama Kontak (Opsional)</label>
                        <input 
                            v-model="manualName" 
                            type="text" 
                            placeholder="Contoh: Kasat Intelijen, Komandan Lantamal"
                            class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs font-bold py-2.5 mt-1 focus:ring-[#00a884] focus:border-[#00a884]"
                        />
                    </div>
                    <button 
                        @click="startChatManual" 
                        class="w-full py-2.5 bg-[#00a884] text-white rounded-xl font-bold uppercase text-xs hover:bg-[#008f6f] transition cursor-pointer"
                    >
                        Buka Obrolan
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
