<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  notifications: Object
});

const page = usePage();
const activeFilter = ref('all'); // 'all' | 'unread' | 'read'

const filteredNotifs = computed(() => {
  if (!props.notifications?.data) return [];
  if (activeFilter.value === 'unread') {
    return props.notifications.data.filter(n => !n.is_read);
  }
  if (activeFilter.value === 'read') {
    return props.notifications.data.filter(n => n.is_read);
  }
  return props.notifications.data;
});

const unreadCount = computed(() => {
  return props.notifications?.data?.filter(n => !n.is_read).length || 0;
});

const readCount = computed(() => {
  return props.notifications?.data?.filter(n => n.is_read).length || 0;
});

const formatTimeAgo = (dateStr) => {
  if (!dateStr) return 'Baru saja';
  const date = new Date(dateStr);
  const now = new Date();
  const diffSec = Math.floor((now - date) / 1000);
  if (diffSec < 60) return 'Baru saja';
  const diffMin = Math.floor(diffSec / 60);
  if (diffMin < 60) return `${diffMin} menit yang lalu`;
  const diffHour = Math.floor(diffMin / 60);
  if (diffHour < 24) return `${diffHour} jam yang lalu`;
  return date.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const markSingleRead = (notif) => {
  router.post(route('notifications.read', notif.id), {}, {
    preserveScroll: true
  });
};

const markAllRead = () => {
  router.post(route('notifications.read-all'), {}, {
    onSuccess: () => {
      Swal.fire('Berhasil', 'Semua notifikasi telah ditandai dibaca.', 'success');
    }
  });
};

const deleteNotif = (id) => {
  Swal.fire({
    title: 'Hapus Notifikasi?',
    text: "Notifikasi ini akan dihapus dari riwayat.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: 'Ya, Hapus'
  }).then((res) => {
    if (res.isConfirmed) {
      router.delete(route('notifications.destroy', id), {
        preserveScroll: true
      });
    }
  });
};

const handleNotifClick = (notif) => {
  if (!notif.is_read) {
    markSingleRead(notif);
  }
  if (notif.link) {
    router.visit(notif.link);
  }
};
</script>

<template>
  <Head title="Pusat Notifikasi System SINDEN" />

  <AuthenticatedLayout>
    <div class="space-y-6 font-sans max-w-6xl mx-auto">
      
      <!-- Page Header Card -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-xs border border-[#E2E8F0] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <div class="flex items-center gap-2.5 mb-1">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 text-lg font-black shadow-xs">
              🔔
            </div>
            <div>
              <h2 class="font-extrabold text-xl text-slate-900 uppercase tracking-tight">Pusat Notifikasi & Pemberitahuan</h2>
              <p class="text-xs text-slate-500 font-semibold">Riwayat lengkap aktivitas, otorisasi TTD, SKHPP, & agenda naskah dinas</p>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap gap-2.5 w-full md:w-auto">
          <button v-if="unreadCount > 0" @click="markAllRead" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl font-extrabold text-xs uppercase shadow-md shadow-blue-500/20 transition tracking-wider text-center">
            ✓ Tandai Semua Dibaca
          </button>
          <Link href="/dashboard" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-3 rounded-2xl font-extrabold text-xs uppercase border border-slate-200 transition tracking-wider text-center">
            ← Kembali Ke Dashboard
          </Link>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
          <div>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Notifikasi</span>
            <span class="text-2xl font-black text-slate-800">{{ notifications?.data?.length || 0 }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-lg">
            📋
          </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-blue-200 bg-blue-50/20 shadow-xs flex items-center justify-between">
          <div>
            <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider block">Belum Dibaca</span>
            <span class="text-2xl font-black text-blue-700">{{ unreadCount }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-lg">
            📩
          </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-emerald-200 bg-emerald-50/20 shadow-xs flex items-center justify-between">
          <div>
            <span class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-wider block">Sudah Dibaca</span>
            <span class="text-2xl font-black text-emerald-700">{{ readCount }}</span>
          </div>
          <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-lg">
            ✅
          </div>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="flex border-b border-slate-200 gap-4">
        <button 
          @click="activeFilter = 'all'"
          :class="activeFilter === 'all' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span>SEMUA NOTIFIKASI</span>
          <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">{{ notifications?.data?.length || 0 }}</span>
        </button>

        <button 
          @click="activeFilter = 'unread'"
          :class="activeFilter === 'unread' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span>BELUM DIBACA</span>
          <span v-if="unreadCount > 0" class="px-2 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-black">{{ unreadCount }}</span>
        </button>

        <button 
          @click="activeFilter = 'read'"
          :class="activeFilter === 'read' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold hover:text-slate-700'"
          class="py-3 px-4 border-b-2 text-xs uppercase tracking-wider transition flex items-center gap-2"
        >
          <span>SUDAH DIBACA</span>
        </button>
      </div>

      <!-- Notifications List Card -->
      <div class="bg-white rounded-3xl shadow-xs border border-[#E2E8F0] overflow-hidden">
        <div class="divide-y divide-slate-100">
          <div 
            v-for="notif in filteredNotifs" 
            :key="notif.id"
            :class="!notif.is_read ? 'bg-blue-50/40 hover:bg-blue-50/80' : 'hover:bg-slate-50/80'"
            class="p-5 transition flex flex-col sm:flex-row items-start justify-between gap-4"
          >
            <div class="flex items-start gap-4 flex-1 min-w-0">
              <!-- Type Icon Badge -->
              <div class="mt-0.5 shrink-0">
                <div v-if="notif.type === 'success'" class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-base shadow-xs">
                  ✅
                </div>
                <div v-else-if="notif.type === 'warning'" class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-base shadow-xs">
                  ⚠️
                </div>
                <div v-else-if="notif.type === 'primary'" class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-base shadow-xs">
                  📢
                </div>
                <div v-else class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-base shadow-xs">
                  ℹ️
                </div>
              </div>

              <!-- Notification Content -->
              <div class="flex-1 min-w-0 space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <h4 class="font-extrabold text-sm text-slate-900 leading-snug">{{ notif.title }}</h4>
                  <span v-if="!notif.is_read" class="bg-blue-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full shadow-xs">
                    BARU
                  </span>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed font-medium whitespace-pre-line">{{ notif.message }}</p>
                <span class="text-[10px] font-bold text-slate-400 block pt-1">{{ formatTimeAgo(notif.created_at) }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
              <button 
                v-if="notif.link" 
                @click="handleNotifClick(notif)" 
                class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-3.5 py-2 rounded-xl text-xs font-black uppercase transition flex items-center gap-1"
              >
                <span>Buka Halaman</span> →
              </button>

              <button 
                v-if="!notif.is_read" 
                @click="markSingleRead(notif)" 
                class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold uppercase transition"
                title="Tandai Sudah Dibaca"
              >
                ✓ Dibaca
              </button>

              <button 
                @click="deleteNotif(notif.id)" 
                class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white px-2.5 py-2 rounded-xl text-xs font-bold transition"
                title="Hapus Notifikasi"
              >
                🗑️
              </button>
            </div>
          </div>

          <div v-if="filteredNotifs.length === 0" class="p-12 text-center text-slate-400 text-xs font-medium space-y-2">
            <div class="text-3xl">📭</div>
            <p>Tidak ada notifikasi dalam kategori ini.</p>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>
