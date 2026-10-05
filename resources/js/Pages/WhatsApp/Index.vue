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

// State Sesi Aktif
const activeSessionId = ref(props.sessions.length > 0 ? props.sessions[0].session_id : null);
const activeSession = computed(() => {
    return props.sessions.find(s => s.session_id === activeSessionId.value) || null;
});

// State Obrolan & Pesan
const chats = ref([]);
const isLoadingChats = ref(false);
const activeChat = ref(null); // { jid, name, number }
const messages = ref([]);
const isLoadingMessages = ref(false);
const messageInput = ref('');
const isSending = ref(false);
const messagesContainer = ref(null);

// State Pencarian Obrolan
const chatSearchQuery = ref('');
const filteredChats = computed(() => {
    const q = chatSearchQuery.value.toLowerCase().trim();
    if (!q) return chats.value;
    return chats.value.filter(c => {
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

// Template Pesan Cepat Kedinasan
const quickTemplates = [
    { title: 'Pemberitahuan Sprin', text: 'Yth. Personel Denintel, diberitahukan terdapat Surat Perintah (Sprin) dinas baru yang telah diterbitkan pada sistem SINDEN. Harap segera memeriksa tugas Anda. Terima kasih.' },
    { title: 'Koordinasi Tugas', text: 'Selamat bertugas. Mohon konfirmasi kesiapan dan perkembangan situasi lapangan untuk dilaporkan ke pimpinan. Terima kasih.' },
    { title: 'Panggilan Piket', text: 'Panggilan dinas jaga/piket Denintel. Harap segera merapat ke pos komando untuk serah terima tugas dinas. Terima kasih.' },
];

const insertTemplate = (text) => {
    messageInput.value = text;
};

// -------------------------------------------------------------------
// API FUNCTIONS
// -------------------------------------------------------------------

// 1. Ambil Percakapan Sesi Terpilih
const fetchChats = async (sessionId) => {
    if (!sessionId) return;
    isLoadingChats.value = true;
    try {
        const res = await fetch(route('whatsapp.sessions.chats', sessionId));
        const data = await res.json();
        if (data.ok && Array.isArray(data.chats)) {
            chats.value = data.chats;
        } else {
            chats.value = [];
        }
    } catch (e) {
        chats.value = [];
    } finally {
        isLoadingChats.value = false;
    }
};

// 2. Pilih Percakapan & Ambil Riwayat Pesan
const selectChat = async (chat) => {
    activeChat.value = chat;
    await fetchMessages(chat.jid);
};

const fetchMessages = async (jid) => {
    if (!activeSessionId.value || !jid) return;
    isLoadingMessages.value = true;
    try {
        const res = await fetch(route('whatsapp.sessions.messages', {
            session: activeSessionId.value,
            jid: jid
        }));
        const data = await res.json();
        if (data.ok && Array.isArray(data.messages)) {
            messages.value = data.messages;
            scrollToBottom();
        }
    } catch (e) {
        messages.value = [];
    } finally {
        isLoadingMessages.value = false;
    }
};

// 3. Gulir Obrolan ke Bawah
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
    const targetNumber = activeChat.value.number || activeChat.value.jid;

    // Optimistic UI update
    const tempId = 'temp-' + Date.now();
    const tempMsg = {
        id: tempId,
        fromMe: true,
        body: text,
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
            // Perbarui daftar chat terakhir
            fetchChats(activeSessionId.value);
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

// 5. Ganti Tab Sesi Aktif
const switchSession = (sessionId) => {
    activeSessionId.value = sessionId;
    activeChat.value = null;
    messages.value = [];
    const sess = props.sessions.find(s => s.session_id === sessionId);
    if (sess && sess.status === 'connected') {
        fetchChats(sessionId);
    } else {
        chats.value = [];
    }
};

// 6. Buat Sesi Baru & Minta QR Code
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

// Polling QR Code sampai Terhubung
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

// 7. Putuskan Sambungan (Logout) Sesi
const logoutSession = (sessionId) => {
    Swal.fire({
        title: 'Putuskan Akun WhatsApp?',
        text: 'Sesi perangkat tertaut akan diputuskan dari ponsel dan token kredensial akan dihapus secara bersih dari server.',
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

// 8. Hapus Sesi Sepenuhnya
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
        number: cleanNumber,
        name: `${person.pangkat ? person.pangkat + ' ' : ''}${person.name}`
    };
    showNewChatModal.value = false;
    fetchMessages(activeChat.value.jid);
};

const startChatManual = () => {
    const num = manualNumber.value.trim().replace(/[^0-9]/g, '');
    if (!num) {
        Swal.fire('Peringatan', 'Masukkan nomor WhatsApp tujuan.', 'warning');
        return;
    }
    activeChat.value = {
        jid: num + '@s.whatsapp.net',
        number: num,
        name: manualName.value.trim() || num
    };
    showNewChatModal.value = false;
    manualNumber.value = '';
    manualName.value = '';
    fetchMessages(activeChat.value.jid);
};

// Interval Sinkronisasi Pesan Berkala
let messageSyncTimer = null;
onMounted(() => {
    if (activeSession.value && activeSession.value.status === 'connected') {
        fetchChats(activeSession.value.session_id);
    }

    messageSyncTimer = setInterval(() => {
        if (activeChat.value && activeSessionId.value) {
            fetchMessages(activeChat.value.jid);
        }
    }, 5000);
});

onUnmounted(() => {
    stopQrPolling();
    if (messageSyncTimer) clearInterval(messageSyncTimer);
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
</script>

<template>
    <Head title="WhatsApp Web Multi-Akun - SINDEN" />
    <AuthenticatedLayout>
        <div class="space-y-5 font-sans">
            
            <!-- Header Card Utama -->
            <div class="bg-white p-4 sm:p-6 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center shadow-lg shadow-emerald-500/20 shrink-0">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 10-7.8-4.5L3 21l4.7-1.2A8.96 8.96 0 0012 21z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-extrabold text-lg sm:text-xl text-slate-900 uppercase tracking-tight">WhatsApp Web Multi-Akun</h2>
                            <span class="px-2.5 py-0.5 text-[9px] font-black uppercase rounded-md bg-emerald-100 text-emerald-700">Dinas Multi-Session</span>
                        </div>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Pusat Kendali Pesan WhatsApp Resmi, Multi-Perangkat & Terintegrasi Personel SINDEN</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full md:w-auto">
                    <!-- Status Gateway Port 3000 -->
                    <div :class="gatewayOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'" class="px-3 py-2 rounded-xl border text-xs font-bold flex items-center gap-2">
                        <span :class="gatewayOnline ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'" class="w-2 h-2 rounded-full"></span>
                        <span>{{ gatewayOnline ? 'Gateway Aktif (Port 3000)' : 'Gateway Tidak Terdeteksi' }}</span>
                    </div>

                    <!-- Tombol Tambah Nomor / Scan QR -->
                    <button 
                        @click="openCreateModal" 
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 sm:px-5 py-2.5 sm:py-3 rounded-2xl text-xs font-extrabold uppercase shadow-sm shadow-emerald-500/20 transition tracking-wider flex items-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <span>Tautkan Nomor Baru</span>
                    </button>
                </div>
            </div>

            <!-- Bilah Pemilih Sesi / Multi-Akun Tabs -->
            <div class="bg-white p-3 sm:p-4 rounded-2xl sm:rounded-3xl shadow-xs border border-[#E2E8F0]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-black uppercase text-slate-500 tracking-wider me-1">Pilih Akun:</span>
                        
                        <div v-if="sessions.length === 0" class="text-xs text-slate-400 italic">
                            Belum ada akun WhatsApp yang ditautkan. Klik tombol "Tautkan Nomor Baru" di atas.
                        </div>

                        <!-- Tombol Tab Setiap Sesi -->
                        <button 
                            v-for="s in sessions" 
                            :key="s.id"
                            @click="switchSession(s.session_id)"
                            :class="activeSessionId === s.session_id ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30 font-black' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold'"
                            class="px-4 py-2 rounded-xl text-xs flex items-center gap-2 transition cursor-pointer"
                        >
                            <span 
                                :class="s.status === 'connected' ? 'bg-emerald-300' : (s.status === 'connecting' || s.status === 'qr_ready' ? 'bg-amber-400 animate-pulse' : 'bg-rose-400')" 
                                class="w-2 h-2 rounded-full"
                            ></span>
                            <span>{{ s.label }}</span>
                            <span v-if="s.phone_number" class="text-[10px] opacity-80 font-mono">({{ s.phone_number }})</span>
                        </button>
                    </div>

                    <!-- Tombol Kontrol Sesi Aktif -->
                    <div v-if="activeSession" class="flex items-center gap-2">
                        <span :class="activeSession.status === 'connected' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'" class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg border">
                            Status: {{ activeSession.status }}
                        </span>
                        
                        <button 
                            v-if="activeSession.status === 'connected'" 
                            @click="logoutSession(activeSession.session_id)"
                            class="px-3 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-lg text-xs font-bold transition cursor-pointer"
                            title="Putuskan sambungan WhatsApp dan bersihkan kredensial"
                        >
                            Logout Akun
                        </button>

                        <button 
                            @click="deleteSession(activeSession.session_id)"
                            class="px-3 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition cursor-pointer"
                            title="Hapus sesi secara permanen"
                        >
                            Hapus Sesi
                        </button>
                    </div>
                </div>
            </div>

            <!-- Antarmuka Utama WhatsApp Web (Split Layout) -->
            <div class="bg-white rounded-2xl sm:rounded-3xl shadow-sm border border-[#E2E8F0] overflow-hidden grid grid-cols-1 lg:grid-cols-12 h-[calc(100vh-17rem)] min-h-[580px] max-h-[850px]">
                
                <!-- KOLOM KIRI: Daftar Percakapan & Kontak (lg:col-span-4) -->
                <div class="lg:col-span-4 border-r border-slate-200 flex flex-col h-full bg-slate-50/60">
                    
                    <!-- Toolbar & Input Pencarian Obrolan -->
                    <div class="p-4 border-b border-slate-200 bg-white space-y-3 shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-xs uppercase tracking-wider text-slate-800">Daftar Obrolan</h3>
                            <button 
                                @click="showNewChatModal = true"
                                :disabled="!activeSession || activeSession.status !== 'connected'"
                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-extrabold flex items-center gap-1.5 transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Mulai Chat</span>
                            </button>
                        </div>

                        <div class="relative">
                            <input 
                                type="text" 
                                v-model="chatSearchQuery"
                                placeholder="Cari kontak / nomor / isi obrolan..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold pl-8 pr-3 py-2 text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Daftar Item Percakapan -->
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                        <div v-if="!activeSession || activeSession.status !== 'connected'" class="p-8 text-center text-slate-400">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <p class="text-xs font-bold text-slate-600">Akun WhatsApp Belum Terhubung</p>
                            <p class="text-[11px] text-slate-400 mt-1">Pilih sesi yang terhubung atau tautkan nomor baru untuk memuat daftar obrolan.</p>
                        </div>

                        <div v-else-if="isLoadingChats" class="p-8 text-center text-slate-400 text-xs font-semibold">
                            Memuat daftar obrolan WhatsApp...
                        </div>

                        <div v-else-if="filteredChats.length === 0" class="p-8 text-center text-slate-400">
                            <p class="text-xs font-bold text-slate-600">Belum Ada Riwayat Obrolan</p>
                            <p class="text-[11px] text-slate-400 mt-1">Klik tombol "+ Mulai Chat" untuk mengirim pesan pertama ke personel SINDEN atau nomor luar.</p>
                        </div>

                        <div 
                            v-for="c in filteredChats" 
                            :key="c.jid"
                            @click="selectChat(c)"
                            :class="activeChat && activeChat.jid === c.jid ? 'bg-emerald-50/70 border-l-4 border-emerald-600' : 'hover:bg-slate-100/80'"
                            class="p-3.5 flex items-center gap-3 transition cursor-pointer"
                        >
                            <!-- Avatar Kontak -->
                            <div class="w-11 h-11 rounded-full bg-emerald-100 text-emerald-800 font-extrabold flex items-center justify-center shrink-0 uppercase text-xs">
                                {{ (c.name || c.number || 'W').slice(0, 2) }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-900 truncate">{{ c.name || c.number }}</h4>
                                    <span class="text-[10px] text-slate-400 font-semibold shrink-0">{{ formatTime(c.timestamp) }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ c.lastMessage || 'Tidak ada pesan teks' }}</p>
                            </div>

                            <span v-if="c.unreadCount > 0" class="px-2 py-0.5 bg-emerald-600 text-white rounded-full text-[9px] font-black shrink-0">
                                {{ c.unreadCount }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- KOLOM KANAN: Ruang Obrolan & Pengiriman Pesan (lg:col-span-8) -->
                <div class="lg:col-span-8 flex flex-col h-full bg-[#EFEAE2]/30">
                    
                    <!-- Jika Belum Ada Chat yang Dipilih -->
                    <div v-if="!activeChat" class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                        <div class="w-20 h-20 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-4">
                            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 10-7.8-4.5L3 21l4.7-1.2A8.96 8.96 0 0012 21z"></path>
                            </svg>
                        </div>
                        <h3 class="text-base font-extrabold text-slate-800 uppercase tracking-tight">SINDEN WhatsApp Web Center</h3>
                        <p class="text-xs text-slate-500 max-w-sm mt-1 leading-relaxed">Pilih salah satu kontak di bilah kiri atau klik tombol <b>Mulai Chat</b> untuk mengirim pesan dinas secara resmi.</p>
                    </div>

                    <!-- Jika Chat Aktif Terpilih -->
                    <template v-else>
                        <!-- Header Chat Terpilih -->
                        <div class="p-3.5 px-5 bg-white border-b border-slate-200 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-600 text-white font-extrabold flex items-center justify-center text-xs uppercase">
                                    {{ (activeChat.name || activeChat.number || 'W').slice(0, 2) }}
                                </div>
                                <div>
                                    <h3 class="text-xs font-extrabold text-slate-900">{{ activeChat.name }}</h3>
                                    <p class="text-[10px] text-slate-400 font-mono">{{ activeChat.number || activeChat.jid }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button @click="fetchMessages(activeChat.jid)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-bold transition cursor-pointer" title="Perbarui Pesan">
                                    Segarkan
                                </button>
                            </div>
                        </div>

                        <!-- Ruang Tampilan Pesan (Bubbles) -->
                        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-3">
                            <div v-if="isLoadingMessages" class="text-center text-slate-400 text-xs font-semibold py-4">
                                Mengambil riwayat pesan...
                            </div>

                            <div v-else-if="messages.length === 0" class="text-center text-slate-400 text-xs italic py-6">
                                Belum ada pesan dalam percakapan ini. Kirim pesan pertama Anda di bawah.
                            </div>

                            <div 
                                v-for="m in messages" 
                                :key="m.id"
                                :class="m.fromMe ? 'justify-end' : 'justify-start'"
                                class="flex items-end gap-2"
                            >
                                <div 
                                    :class="m.fromMe ? 'bg-[#DCF8C6] text-slate-900 rounded-tr-xs' : 'bg-white text-slate-900 rounded-tl-xs shadow-xs'"
                                    class="max-w-[80%] sm:max-w-[70%] p-3 rounded-2xl text-xs font-medium space-y-1 relative border border-black/5"
                                >
                                    <p class="whitespace-pre-wrap leading-relaxed">{{ m.body }}</p>
                                    <div class="flex items-center justify-end gap-1 text-[9px] text-slate-400 font-semibold">
                                        <span>{{ formatTime(m.timestamp) }}</span>
                                        <span v-if="m.fromMe" class="text-emerald-700 font-bold">✓</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Template Cepat Pesan Kedinasan -->
                        <div class="bg-slate-100/80 px-4 py-2 border-t border-slate-200 flex items-center gap-2 overflow-x-auto">
                            <span class="text-[10px] font-black uppercase text-slate-400 shrink-0">Template Cepat:</span>
                            <button 
                                v-for="t in quickTemplates" 
                                :key="t.title"
                                @click="insertTemplate(t.text)"
                                class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 rounded-lg text-[10px] font-bold whitespace-nowrap transition cursor-pointer"
                            >
                                {{ t.title }}
                            </button>
                        </div>

                        <!-- Kolom Input Pesan & Tombol Kirim -->
                        <div class="p-3 bg-white border-t border-slate-200 flex items-end gap-2 shrink-0">
                            <textarea 
                                v-model="messageInput"
                                @keydown.enter.exact.prevent="sendMessage"
                                rows="2"
                                placeholder="Ketik pesan resmi di sini... (Enter untuk kirim, Shift+Enter untuk baris baru)"
                                class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium p-3 text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 resize-none transition"
                            ></textarea>
                            
                            <button 
                                @click="sendMessage"
                                :disabled="!messageInput.trim() || isSending"
                                class="h-12 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-2xl text-xs font-extrabold uppercase transition shadow-sm shadow-emerald-500/20 flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                <span>Kirim</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL 1: TAUTKAN NOMOR BARU (SCAN QR CODE) -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 text-left">
            <div class="bg-white w-full max-w-lg rounded-3xl p-6 sm:p-8 shadow-2xl space-y-5 border border-slate-100">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="font-extrabold uppercase text-sm tracking-wider text-slate-900">Tautkan Akun WhatsApp Baru</h3>
                    <button @click="closeCreateModal" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <!-- Form Label Sesi jika belum memuat QR -->
                <div v-if="!qrCodeData" class="space-y-4">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nama / Label Akun WhatsApp</label>
                        <input 
                            v-model="createForm.label" 
                            type="text" 
                            placeholder="Contoh: WA Dinas Denintel, WA Komandan, WA Piket"
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 uppercase focus:ring-emerald-500 focus:border-emerald-600"
                        />
                    </div>

                    <div v-if="isAdmin">
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Otoritas Hak Akses Akun</label>
                        <select v-model="createForm.role_access" class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 focus:ring-emerald-500 focus:border-emerald-600">
                            <option value="all">Semua Pengguna Terotentikasi</option>
                            <option value="admin">Khusus Administrator SINDEN</option>
                            <option value="komandan">Khusus Komandan & Administrator</option>
                        </select>
                    </div>

                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-2xl text-[11px] text-blue-800 leading-relaxed font-semibold">
                        Koneksi ini menggunakan protokol multi-perangkat resmi WhatsApp. Sesi akan tetap aktif dan tidak mudah ter-logout secara otomatis.
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="closeCreateModal" class="w-1/2 py-3 bg-slate-100 text-slate-600 rounded-2xl font-extrabold uppercase text-xs hover:bg-slate-200 transition cursor-pointer">Batal</button>
                        <button type="button" @click="submitCreateSession" :disabled="isRequestingQr" class="w-1/2 py-3 bg-emerald-600 text-white rounded-2xl font-extrabold uppercase text-xs shadow-md shadow-emerald-500/20 hover:bg-emerald-700 transition cursor-pointer">
                            {{ isRequestingQr ? 'Menyiapkan...' : 'Minta QR Code' }}
                        </button>
                    </div>
                </div>

                <!-- Tampilan QR Code untuk Dipindai -->
                <div v-else class="space-y-4 text-center">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 inline-block mx-auto shadow-inner">
                        <img :src="qrCodeData" alt="Scan QR Code" class="w-64 h-64 mx-auto rounded-xl" />
                    </div>

                    <div class="text-left bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-xs text-emerald-900 space-y-1">
                        <p class="font-extrabold uppercase text-[11px]">Petunjuk Pemindaian WhatsApp:</p>
                        <ol class="list-decimal list-inside space-y-0.5 font-medium text-[11px]">
                            <li>Buka aplikasi WhatsApp di ponsel Anda.</li>
                            <li>Ketuk menu <b>Titik Tiga</b> atau <b>Pengaturan</b>.</li>
                            <li>Pilih <b>Perangkat Tertaut (Linked Devices)</b>.</li>
                            <li>Ketuk <b>Tautkan Perangkat</b> lalu arahkan kamera ke QR Code di atas.</li>
                        </ol>
                    </div>

                    <p class="text-[11px] text-slate-400 font-bold animate-pulse">Menunggu pemindaian dari ponsel...</p>

                    <button type="button" @click="closeCreateModal" class="w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl font-extrabold uppercase text-xs hover:bg-slate-200 transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 2: MULAI OBROLAN BARU -->
        <div v-if="showNewChatModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 text-left">
            <div class="bg-white w-full max-w-lg rounded-3xl p-6 sm:p-8 shadow-2xl space-y-4 border border-slate-100 max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3 shrink-0">
                    <h3 class="font-extrabold uppercase text-sm tracking-wider text-slate-900">Mulai Obrolan WhatsApp Baru</h3>
                    <button @click="showNewChatModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold cursor-pointer">&times;</button>
                </div>

                <!-- Tabs Pilihan -->
                <div class="flex border-b border-slate-200 shrink-0">
                    <button 
                        @click="newChatTab = 'personnel'"
                        :class="newChatTab === 'personnel' ? 'border-b-2 border-emerald-600 text-emerald-700 font-black' : 'text-slate-400 font-bold'"
                        class="flex-1 py-2.5 text-xs uppercase tracking-wider text-center transition cursor-pointer"
                    >
                        Personel SINDEN Terdaftar
                    </button>
                    <button 
                        @click="newChatTab = 'manual'"
                        :class="newChatTab === 'manual' ? 'border-b-2 border-emerald-600 text-emerald-700 font-black' : 'text-slate-400 font-bold'"
                        class="flex-1 py-2.5 text-xs uppercase tracking-wider text-center transition cursor-pointer"
                    >
                        Ketik Nomor Manual
                    </button>
                </div>

                <!-- Konten Tab 1: Personel SINDEN -->
                <div v-if="newChatTab === 'personnel'" class="flex-1 overflow-y-auto space-y-3 min-h-0">
                    <input 
                        type="text" 
                        v-model="personnelSearchQuery"
                        placeholder="Cari nama personel, pangkat, atau NRP..."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold px-3 py-2 text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition"
                    />

                    <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                        <div 
                            v-for="p in filteredPersonnel" 
                            :key="p.id"
                            @click="startChatWithPersonnel(p)"
                            class="p-2.5 hover:bg-emerald-50/70 rounded-xl flex items-center justify-between transition cursor-pointer"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center uppercase">
                                    {{ p.name.slice(0, 2) }}
                                </div>
                                <div>
                                    <h4 class="text-xs font-extrabold text-slate-900">{{ p.pangkat ? p.pangkat + ' ' : '' }}{{ p.name }}</h4>
                                    <p class="text-[10px] text-slate-400">NRP: {{ p.nrp || '-' }} | WA: <b class="text-emerald-600">{{ p.phone || '-' }}</b></p>
                                </div>
                            </div>
                            <button class="px-2.5 py-1 bg-emerald-600 text-white rounded-lg text-[10px] font-black uppercase">
                                Chat
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Konten Tab 2: Nomor Manual -->
                <div v-else class="space-y-4 pt-2">
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nomor WhatsApp Tujuan</label>
                        <input 
                            v-model="manualNumber" 
                            type="text" 
                            placeholder="Contoh: 08123456789 atau 628123456789"
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 focus:ring-emerald-500 focus:border-emerald-600"
                        />
                    </div>
                    <div>
                        <label class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider ms-1">Nama Kontak (Opsional)</label>
                        <input 
                            v-model="manualName" 
                            type="text" 
                            placeholder="Contoh: Kasat Intelijen, Komandan Lantamal"
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 text-xs font-bold py-3 mt-1 focus:ring-emerald-500 focus:border-emerald-600"
                        />
                    </div>
                    <button 
                        @click="startChatManual" 
                        class="w-full py-3 bg-emerald-600 text-white rounded-2xl font-extrabold uppercase text-xs shadow-md shadow-emerald-500/20 hover:bg-emerald-700 transition cursor-pointer"
                    >
                        Buka Obrolan
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
