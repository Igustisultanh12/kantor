<template>
  <AuthenticatedLayout>
    <template #header-title>Pusat Komunikasi Dinas &amp; VC</template>

    <div class="space-y-5 font-sans">
      <!-- 1. Statistik Ringkasan Obrolan -->
      <div :class="selectedThread ? 'hidden lg:grid' : 'grid'" class="grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sesi Terbuka (Aktif)</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">{{ chatStats?.total_open || 0 }}</h3>
          </div>
          <div class="w-11 h-11 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pesan Baru Belum Dibalas</p>
            <h3 class="text-2xl font-black text-blue-600 mt-1">{{ chatStats?.total_unread || 0 }}</h3>
          </div>
          <div class="w-11 h-11 rounded-2xl bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Utas Terdaftar</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">{{ totalThreadsCount }}</h3>
          </div>
          <div class="w-11 h-11 rounded-2xl bg-slate-50 border border-slate-200 text-slate-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- 2. Antarmuka Split Obrolan (Daftar Kiri & Pesan Kanan) -->
      <div 
        :class="selectedThread ? 'h-[calc(100dvh-5.5rem)]' : 'h-[calc(100dvh-13.5rem)] sm:h-[calc(100vh-13.5rem)]'" 
        class="bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[480px] sm:min-h-[580px] max-h-[850px]"
      >
        <!-- KOLOM KIRI: Daftar Percakapan Personel (lg:col-span-5) -->
        <div :class="selectedThread ? 'hidden lg:flex' : 'flex'" class="lg:col-span-5 border-r border-slate-200 flex-col h-full min-h-0 bg-slate-50/40">
          
          <!-- Filter & Pencarian -->
          <div class="p-4 border-b border-slate-200 bg-white space-y-3 shrink-0">
            <!-- Tombol Mulai Chat Baru dengan Personel -->
            <button 
              @click="openNewChatModal" 
              type="button"
              class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              <span>Mulai Chat dengan Personel</span>
            </button>

            <div class="relative">
              <input 
                v-model="searchQuery" 
                @keyup.enter="applyFilters"
                type="text" 
                placeholder="Cari nama, NRP, atau pangkat..." 
                class="w-full text-xs pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600"
              />
              <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>

            <div class="flex items-center gap-2">
              <button 
                @click="setStatusFilter('all')" 
                type="button" 
                :class="statusFilter === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer"
              >
                Semua
              </button>
              <button 
                @click="setStatusFilter('OPEN')" 
                type="button" 
                :class="statusFilter === 'OPEN' ? 'bg-emerald-600 text-white font-bold' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer"
              >
                Terbuka
              </button>
              <button 
                @click="setStatusFilter('CLOSED')" 
                type="button" 
                :class="statusFilter === 'CLOSED' ? 'bg-slate-600 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-lg text-xs transition cursor-pointer"
              >
                Ditutup
              </button>
            </div>
          </div>

          <!-- Daftar Utas Obrolan -->
          <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-slate-100">
            <div v-if="!threadsList || threadsList.length === 0" class="p-8 text-center text-slate-400 text-xs space-y-2">
              <p>Tidak ada sesi obrolan yang sesuai kriteria.</p>
              <button 
                v-if="searchQuery"
                @click="openNewChatModalWithQuery(searchQuery)"
                type="button"
                class="text-blue-600 hover:underline font-bold text-xs inline-flex items-center gap-1 cursor-pointer"
              >
                <span>Cari "{{ searchQuery }}" di Master Pengguna &rarr;</span>
              </button>
            </div>

            <div 
              v-for="th in threadsList" 
              :key="th.uuid || th.id"
              @click="selectThread(th)"
              :class="selectedThread?.uuid === th.uuid ? 'bg-blue-50/70 border-l-4 border-blue-600' : 'hover:bg-slate-50 border-l-4 border-transparent'"
              class="p-4 cursor-pointer transition flex items-start gap-3 text-left"
            >
              <!-- Avatar Personel dengan Indikator Online/Offline -->
              <div class="relative shrink-0 mt-0.5">
                <img 
                  :src="getAvatarUrl(th.user)" 
                  class="w-11 h-11 object-cover rounded-xl border border-slate-200" 
                />
                <span 
                  :class="th.is_online ? 'bg-emerald-500 ring-white' : 'bg-slate-300 ring-white'"
                  class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full ring-2 shadow-xs"
                  :title="th.is_online ? 'Online' : 'Offline'"
                ></span>
              </div>

              <!-- Info Personel & Pesan Terakhir -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 mb-0.5">
                  <h4 class="text-xs font-bold text-slate-900 truncate">
                    {{ th.user?.name }}
                  </h4>
                  <span class="text-[10px] text-slate-400 shrink-0 font-medium">
                    {{ formatTimestamp(th.last_message_at) }}
                  </span>
                </div>

                <div class="flex items-center gap-2 text-[10px] text-slate-500 mb-1">
                  <span class="font-bold text-blue-600">{{ th.user?.pangkat || 'Prajurit' }}</span>
                  <span>&bull;</span>
                  <span>NRP: {{ th.user?.nrp || '-' }}</span>
                  <span v-if="th.status === 'CLOSED'" class="px-1.5 py-0.2 rounded bg-slate-200 text-slate-700 text-[9px] font-bold uppercase">
                    Ditutup
                  </span>
                </div>

                <p class="text-xs text-slate-600 truncate">
                  <span v-if="th.latest_message?.sender_type !== 'USER'" class="font-semibold text-slate-500">Anda: </span>
                  {{ th.latest_message?.message || (th.latest_message?.has_attachments ? '[Lampiran Berkas]' : 'Belum ada pesan') }}
                </p>
              </div>

              <!-- Lencana Pesan Baru dari Personel -->
              <span 
                v-if="th.unread_admin > 0" 
                class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] font-black flex items-center justify-center shrink-0 mt-1 shadow-xs"
              >
                {{ th.unread_admin }}
              </span>
            </div>
          </div>
        </div>

        <!-- KOLOM KANAN: Ruang Obrolan Aktif (lg:col-span-7) -->
        <div :class="selectedThread ? 'flex' : 'hidden lg:flex'" class="lg:col-span-7 flex-col h-full min-h-0 bg-white">
          
          <template v-if="selectedThread">
            <!-- Header Utas Aktif -->
            <div class="px-3 sm:px-6 py-2.5 sm:py-4 border-b border-slate-200 flex items-center justify-between bg-white shrink-0 gap-2">
              <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <!-- Tombol Kembali ke Daftar Utas (Layar HP) -->
                <button 
                  @click="backToThreadList"
                  type="button"
                  class="lg:hidden p-1.5 -ml-1 text-slate-600 hover:text-slate-900 rounded-xl hover:bg-slate-100 transition cursor-pointer shrink-0"
                  title="Kembali ke Daftar Utas"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                  </svg>
                </button>

                <div class="relative shrink-0">
                  <img 
                    :src="getAvatarUrl(selectedThread.user)" 
                    class="w-9 h-9 sm:w-11 sm:h-11 object-cover rounded-xl border border-slate-200" 
                  />
                  <span 
                    :class="selectedPersonelOnline ? 'bg-emerald-500 ring-white' : 'bg-slate-300 ring-white'"
                    class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 rounded-full ring-2 shadow-xs"
                    :title="selectedPersonelOnline ? 'Online' : 'Offline'"
                  ></span>
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5 sm:gap-2">
                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate">
                      {{ selectedThread.user?.pangkat ? selectedThread.user.pangkat + ' ' : '' }}{{ selectedThread.user?.name }}
                    </h3>
                    <span 
                      v-if="selectedPersonelOnline" 
                      class="inline-flex items-center gap-1 text-[9px] sm:text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 sm:px-2 py-0.5 rounded-full shrink-0"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                      Online
                    </span>
                    <span 
                      v-else 
                      class="inline-flex items-center gap-1 text-[9px] sm:text-[10px] font-medium text-slate-500 bg-slate-100 border border-slate-200 px-1.5 sm:px-2 py-0.5 rounded-full shrink-0"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                      Offline
                    </span>
                  </div>
                  <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] sm:text-[11px] text-slate-500 mt-0.5">
                    <span class="truncate">NRP: <strong>{{ selectedThread.user?.nrp || '-' }}</strong></span>
                    <span class="hidden sm:inline">&bull;</span>
                    <span v-if="isPersonelTyping" class="text-blue-600 font-bold animate-pulse">
                      sedang mengetik...
                    </span>
                    <span v-else :class="selectedThread.status === 'OPEN' ? 'text-emerald-600 font-bold' : 'text-slate-500 font-bold'">
                      {{ selectedThread.status === 'OPEN' ? 'Sesi Terbuka' : 'Sesi Ditutup' }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Tombol Mulai Panggilan Video Dinas (Vicon) -->
                <button 
                  v-if="selectedThread.status === 'OPEN'"
                  @click="openVideoCall"
                  type="button" 
                  class="p-2 sm:px-3 sm:py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                  title="Mulai Panggilan Video Dinas"
                >
                  <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                  <span class="hidden sm:inline">Panggilan Video</span>
                </button>

                <button 
                  @click="toggleStatus(selectedThread.uuid)" 
                  type="button" 
                  :class="selectedThread.status === 'OPEN' ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 border-amber-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border-emerald-200'"
                  class="px-2.5 sm:px-3 py-1.5 rounded-xl border text-[11px] sm:text-xs font-bold transition cursor-pointer shrink-0"
                >
                  {{ selectedThread.status === 'OPEN' ? 'Tutup Sesi' : 'Buka Sesi' }}
                </button>
              </div>
            </div>

            <!-- Wadah Pesan Obrolan -->
            <div 
              ref="adminChatContainer" 
              class="flex-1 min-h-0 p-4 overflow-y-auto space-y-4 bg-slate-50/50 scroll-smooth"
            >
              <div v-if="activeMessagesList.length === 0" class="flex flex-col items-center justify-center h-48 text-slate-400 text-xs">
                Belum ada pesan dalam sesi obrolan ini.
              </div>

              <template v-for="msg in activeMessagesList" :key="msg.id">
                <!-- Pesan Sistem (Tengah) -->
                <div v-if="msg.sender_type === 'SYSTEM'" class="flex justify-center my-2">
                  <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-200/80 border border-slate-300 text-slate-700 text-[11px] font-semibold shadow-2xs">
                    <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <span>{{ msg.message }}</span>
                    <span class="text-[10px] text-slate-500">&bull; {{ formatTime(msg.created_at) }}</span>
                  </div>
                </div>

                <!-- Pesan Masuk dari Personel (Kiri) -->
                <div v-else-if="msg.sender_type === 'USER'" class="flex gap-3 max-w-[85%]">
                  <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 text-xs font-bold shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                  </div>
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-bold text-slate-800">{{ msg.sender_name }}</span>
                      <span class="text-[10px] text-slate-400">{{ formatTime(msg.created_at) }}</span>
                    </div>
                    <div class="bg-white border border-slate-200 text-slate-800 text-xs p-3.5 rounded-2xl rounded-tl-xs shadow-xs leading-relaxed">
                      <p v-if="msg.message" class="whitespace-pre-wrap">{{ msg.message }}</p>

                      <!-- Lampiran Personel -->
                      <div v-if="msg.attachments && msg.attachments.length > 0" class="mt-2.5 pt-2 border-t border-slate-100 space-y-2">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lampiran Berkas ({{ msg.attachments.length }}):</p>
                        <div class="grid grid-cols-1 gap-1.5">
                          <div 
                            v-for="(att, idx) in msg.attachments" 
                            :key="idx" 
                            class="flex items-center justify-between gap-2 p-2 rounded-xl bg-slate-50 border border-slate-200 text-[11px]"
                          >
                            <div class="flex items-center gap-2 truncate min-w-0">
                              <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                              </svg>
                              <span class="truncate text-slate-700 font-medium">{{ att.original_name }}</span>
                              <span class="text-[9px] text-slate-400 shrink-0">({{ formatBytes(att.size) }})</span>
                            </div>
                            <span v-if="att.purged || !att.file_path" class="shrink-0 text-slate-400 text-[10px] font-semibold italic">
                              Berkas Terhapus
                            </span>
                            <a 
                              v-else
                              :href="`/live-chat/attachment/download?path=${encodeURIComponent(att.file_path)}&name=${encodeURIComponent(att.original_name)}`" 
                              target="_blank" 
                              class="shrink-0 text-blue-600 hover:text-blue-800 font-bold underline text-[10px]"
                            >
                              Unduh
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Pesan Keluar dari Operator / Admin (Kanan) -->
                <div v-else class="flex justify-end">
                  <div class="max-w-[85%] space-y-1 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <span class="text-[10px] text-slate-400">{{ formatTime(msg.created_at) }}</span>
                      <span class="text-xs font-bold text-slate-800">{{ msg.sender_name }}</span>
                    </div>
                    <div class="bg-slate-900 text-white text-xs p-3.5 rounded-2xl rounded-tr-xs shadow-md text-left leading-relaxed">
                      <p v-if="msg.message" class="whitespace-pre-wrap">{{ msg.message }}</p>

                      <!-- Lampiran Petugas -->
                      <div v-if="msg.attachments && msg.attachments.length > 0" class="mt-2.5 pt-2 border-t border-slate-700 space-y-2">
                        <p class="text-[10px] font-bold text-slate-300 uppercase tracking-wider">Lampiran Berkas ({{ msg.attachments.length }}):</p>
                        <div class="grid grid-cols-1 gap-1.5">
                          <div 
                            v-for="(att, idx) in msg.attachments" 
                            :key="idx" 
                            class="flex items-center justify-between gap-2 p-2 rounded-xl bg-slate-800 border border-slate-700 text-[11px]"
                          >
                            <div class="flex items-center gap-2 truncate min-w-0">
                              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                              </svg>
                              <span class="truncate text-white font-medium">{{ att.original_name }}</span>
                              <span class="text-[9px] text-slate-300 shrink-0">({{ formatBytes(att.size) }})</span>
                            </div>
                            <span v-if="att.purged || !att.file_path" class="shrink-0 text-amber-300 text-[10px] font-semibold italic">
                              Berkas Terhapus
                            </span>
                            <a 
                              v-else
                              :href="`/live-chat/attachment/download?path=${encodeURIComponent(att.file_path)}&name=${encodeURIComponent(att.original_name)}`" 
                              target="_blank" 
                              class="shrink-0 text-blue-400 hover:text-blue-300 font-bold underline text-[10px]"
                            >
                              Unduh
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Animasi Indikator Personel Sedang Mengetik -->
              <div v-if="isPersonelTyping" class="flex items-end gap-2.5 max-w-[85%] transition-all">
                <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 text-xs font-bold shadow-xs">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <div class="space-y-1">
                  <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-semibold">
                    <span>{{ personelTypingName }}</span>
                  </div>
                  <div class="bg-white border border-slate-200 text-slate-800 text-xs px-4 py-2.5 rounded-2xl rounded-tl-xs shadow-xs flex items-center gap-2.5">
                    <div class="flex items-center gap-1 py-1">
                      <span class="w-2 h-2 rounded-full bg-blue-600 animate-bounce [animation-delay:-0.3s]"></span>
                      <span class="w-2 h-2 rounded-full bg-blue-600 animate-bounce [animation-delay:-0.15s]"></span>
                      <span class="w-2 h-2 rounded-full bg-blue-600 animate-bounce"></span>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-600 italic">sedang mengetik...</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bilah Input Balasan Chat -->
            <div class="p-3 sm:p-4 bg-white border-t border-slate-200 shrink-0 space-y-2">
              <!-- Pratinjau Berkas Lampiran Siap Kirim -->
              <div v-if="adminStagedFiles.length > 0" class="flex flex-wrap gap-2 pt-1 pb-2">
                <div 
                  v-for="(f, idx) in adminStagedFiles" 
                  :key="idx" 
                  class="flex items-center gap-2 bg-slate-100 border border-slate-200 rounded-xl px-2.5 py-1 text-xs text-slate-700"
                >
                  <svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                  <span class="max-w-[150px] truncate font-medium">{{ f.name }}</span>
                  <span class="text-[10px] text-slate-400">({{ formatBytes(f.size) }})</span>
                  <button 
                    @click="removeAdminStagedFile(idx)" 
                    type="button" 
                    class="text-red-500 hover:text-red-700 font-bold ml-1 cursor-pointer"
                  >
                    &times;
                  </button>
                </div>
              </div>

              <div class="flex items-end gap-2">
                <!-- Tombol Lampirkan Dokumen -->
                <input 
                  ref="adminFileInputRef" 
                  type="file" 
                  multiple 
                  accept=".png,.jpg,.jpeg,.pdf,.doc,.docx,.xls,.xlsx" 
                  class="hidden" 
                  @change="handleAdminFileChange" 
                />
                <button 
                  @click="adminFileInputRef.click()" 
                  type="button" 
                  class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 transition cursor-pointer shrink-0"
                  title="Lampirkan Dokumen (PDF, Word, Excel, Gambar)"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                </button>

                <!-- Kolom Teks Balasan -->
                <div class="flex-1 min-w-0">
                  <textarea 
                    v-model="adminReplyMessage" 
                    @input="onAdminTypingInput"
                    @keydown.enter.prevent="submitAdminReply"
                    rows="1" 
                    placeholder="Tuliskan balasan dinas... (Tekan Enter untuk kirim)" 
                    class="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600 resize-none max-h-28 leading-relaxed"
                  ></textarea>
                </div>

                <!-- Tombol Kirim Pesan -->
                <button 
                  @click="submitAdminReply" 
                  :disabled="isAdminSending || (!adminReplyMessage.trim() && adminStagedFiles.length === 0)"
                  type="button" 
                  class="p-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl shadow-xs transition cursor-pointer shrink-0 flex items-center justify-center"
                  title="Kirim Balasan Dinas"
                >
                  <svg v-if="isAdminSending" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                  </svg>
                  <svg v-else class="w-5 h-5 rotate-90" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                  </svg>
                </button>
              </div>
            </div>
          </template>

          <!-- Tampilan saat belum ada utas dipilih -->
          <div v-else class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400 space-y-3">
            <div class="w-16 h-16 rounded-3xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-300">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
            </div>
            <div>
              <h4 class="text-sm font-bold text-slate-700">Pilih Percakapan Dinas</h4>
              <p class="text-xs text-slate-400 mt-1 max-w-sm leading-relaxed">
                Silakan pilih salah satu percakapan di kolom kiri untuk melihat riwayat komunikasi atau memulai sesi baru dengan personel.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Modal Mulai Chat Baru dengan Personel -->
      <div v-if="showNewChatModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-xs p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200 flex flex-col max-h-[90vh]">
          <!-- Header Modal -->
          <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 shrink-0">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-xs font-bold shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
              </div>
              <h3 class="text-sm font-black text-slate-900">Mulai Obrolan Baru</h3>
            </div>
            <button 
              @click="closeNewChatModal" 
              type="button" 
              class="text-slate-400 hover:text-slate-600 transition text-lg font-bold p-1 cursor-pointer"
            >
              &times;
            </button>
          </div>

          <!-- Body Modal: Langkah 1 (Cari Personel) -->
          <div v-if="!selectedPersonelToChat" class="p-6 space-y-4 flex-1 overflow-y-auto">
            <div class="space-y-1">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                Cari Personel SINDEN
              </label>
              <div class="relative">
                <input 
                  v-model="newChatSearchQuery" 
                  @input="onSearchPersonelInput"
                  type="text" 
                  placeholder="Ketik Nama, NRP, atau Pangkat..." 
                  class="w-full text-xs pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600"
                />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
            </div>

            <!-- Hasil Pencarian -->
            <div class="space-y-2">
              <div v-if="isSearchingPersonel" class="py-8 text-center text-slate-400 text-xs">
                <div class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                <span>Mencari data personel...</span>
              </div>

              <div v-else-if="searchedPersonelList.length === 0" class="py-8 text-center text-slate-400 text-xs">
                <span v-if="newChatSearchQuery">Tidak ada personel yang cocok dengan kata kunci pencarian.</span>
                <span v-else>Ketik minimal 2 karakter untuk memulai pencarian personel.</span>
              </div>

              <div v-else class="divide-y divide-slate-100 max-h-64 overflow-y-auto border border-slate-200 rounded-2xl">
                <div 
                  v-for="p in searchedPersonelList" 
                  :key="p.id"
                  @click="choosePersonel(p)"
                  class="p-3 hover:bg-blue-50/60 cursor-pointer transition flex items-center justify-between gap-3 text-left"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <img 
                      :src="getAvatarUrl(p)" 
                      class="w-9 h-9 object-cover rounded-xl border border-slate-200 shrink-0" 
                    />
                    <div class="min-w-0">
                      <h4 class="text-xs font-bold text-slate-900 truncate">
                        {{ p.pangkat ? p.pangkat + ' ' : '' }}{{ p.name }}
                      </h4>
                      <p class="text-[10px] text-slate-500">
                        NRP: {{ p.nrp || '-' }}
                      </p>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <span 
                      v-if="p.is_online" 
                      class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold"
                    >
                      Online
                    </span>
                    <button 
                      type="button" 
                      class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold text-xs cursor-pointer shadow-xs transition"
                    >
                      Pilih
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Body Modal: Langkah 2 (Konfirmasi & Pesan Pembuka) -->
          <div v-else class="p-6 space-y-4 flex-1 overflow-y-auto">
            <!-- Kartu Personel Terpilih -->
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <img 
                  :src="getAvatarUrl(selectedPersonelToChat)" 
                  class="w-11 h-11 object-cover rounded-xl border border-slate-200 shrink-0" 
                />
                <div class="min-w-0">
                  <h4 class="text-xs font-bold text-slate-900 truncate">
                    {{ selectedPersonelToChat.pangkat ? selectedPersonelToChat.pangkat + ' ' : '' }}{{ selectedPersonelToChat.name }}
                  </h4>
                  <div class="flex items-center gap-2 text-[10px] text-slate-500 mt-0.5">
                    <span>NRP: <strong>{{ selectedPersonelToChat.nrp || '-' }}</strong></span>
                  </div>
                </div>
              </div>

              <button 
                @click="selectedPersonelToChat = null" 
                type="button" 
                class="text-xs font-bold text-blue-600 hover:text-blue-800 underline cursor-pointer shrink-0"
              >
                Ganti
              </button>
            </div>

            <!-- Input Pesan Pembuka -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide">
                Pesan Pembuka (Opsional)
              </label>
              <textarea 
                v-model="newChatInitialMessage" 
                rows="3" 
                placeholder="Tuliskan pesan pembuka percakapan dinas (opsional)..."
                class="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600 leading-relaxed"
              ></textarea>
              <p class="text-[10px] text-slate-400">
                Pesan ini akan langsung terkirim sebagai pesan pertama dari pengelola.
              </p>
            </div>
          </div>

          <!-- Footer Modal -->
          <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
            <button 
              @click="closeNewChatModal" 
              type="button" 
              class="px-4 py-2 border border-slate-200 rounded-xl hover:bg-slate-100 text-slate-600 font-bold text-xs transition cursor-pointer"
            >
              Tutup
            </button>

            <div v-if="selectedPersonelToChat" class="flex items-center gap-2">
              <button 
                @click="selectedPersonelToChat = null" 
                type="button" 
                class="px-4 py-2 border border-slate-200 rounded-xl hover:bg-slate-100 text-slate-600 font-bold text-xs transition cursor-pointer"
              >
                Kembali
              </button>
              <button 
                @click="executeStartChat" 
                :disabled="isStartingChat" 
                type="button" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-500/20 transition cursor-pointer flex items-center gap-1.5"
              >
                <svg v-if="isStartingChat" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>{{ isStartingChat ? 'Memulai Sesi...' : 'Mulai Chat' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Komponen Ruang Panggilan Video Dinas (Vicon) -->
    <VideoCallModal 
      :show="showVideoCallModal"
      :thread-uuid="activeIncomingCallData?.thread_uuid || selectedThread?.uuid"
      user-role="ADMIN"
      :current-user="currentUserInfo"
      :partner-user="callPartnerInfo"
      :incoming-call-data="activeIncomingCallData"
      url-prefix=""
      @close="closeVideoCallModal"
      @call-ended="onCallEnded"
      @call-accepted="onCallAccepted"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VideoCallModal from '@/Components/Chat/VideoCallModal.vue';
import { playNotificationSound } from '@/Utils/sound';
import axios from 'axios';
import Swal from 'sweetalert2';

const page = usePage();

const props = defineProps({
  threads: Object,
  activeThread: Object,
  activeMessages: Array,
  initialCall: Object,
  filters: Object,
  chatStats: Object,
  currentUser: Object,
});

const showVideoCallModal = ref(Boolean(props.initialCall));
const activeIncomingCallData = ref(props.initialCall || null);

const currentUserInfo = computed(() => ({
  id: props.currentUser?.id || page.props.auth?.user?.id,
  name: props.currentUser?.name || page.props.auth?.user?.name || 'Operator Dinas',
  pangkat: props.currentUser?.pangkat || 'Pengelola Dinas',
  avatar: props.currentUser?.avatar || page.props.auth?.user?.avatar || null,
}));

const callPartnerInfo = computed(() => {
  if (activeIncomingCallData.value?.caller) {
    return {
      id: activeIncomingCallData.value.caller.id,
      name: activeIncomingCallData.value.caller.name,
      pangkat: activeIncomingCallData.value.caller.pangkat,
      avatar: activeIncomingCallData.value.caller.avatar,
      nrp: selectedThread.value?.user?.nrp,
      is_online: selectedThread.value?.is_online ?? true,
    };
  }
  return {
    id: selectedThread.value?.user?.id,
    name: selectedThread.value?.user?.name,
    pangkat: selectedThread.value?.user?.pangkat,
    avatar: selectedThread.value?.user?.avatar,
    nrp: selectedThread.value?.user?.nrp,
    is_online: selectedThread.value?.is_online ?? true,
  };
});

const getAvatarUrl = (user) => {
  if (!user) return 'https://ui-avatars.com/api/?name=User&background=e2e8f0&color=334155';
  if (user.avatar) {
    return user.avatar.startsWith('http') ? user.avatar : `/storage/${user.avatar}`;
  }
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'P')}&background=e2e8f0&color=334155`;
};

const openVideoCall = () => {
  if (!selectedThread.value || selectedThread.value.status !== 'OPEN') return;
  activeIncomingCallData.value = null;
  showVideoCallModal.value = true;
};

const closeVideoCallModal = () => {
  showVideoCallModal.value = false;
  activeIncomingCallData.value = null;
};

const onCallEnded = () => {
  if (selectedThread.value?.uuid) {
    loadThreadMessages(selectedThread.value.uuid);
  }
};

const onCallAccepted = () => {};

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const selectedThread = ref(props.activeThread || null);
const activeMessagesList = ref(props.activeMessages || []);

// State reaktif daftar utas dan statistik obrolan
const threadsList = ref([...(props.threads?.data || [])]);
const totalThreadsCount = ref(props.threads?.total || props.threads?.data?.length || 0);
const chatStats = ref(props.chatStats || { total_open: 0, total_unread: 0, total_threads: 0 });

watch(
  () => props.threads?.data,
  (newVal) => {
    if (newVal) {
      threadsList.value = [...newVal];
    }
  },
  { deep: true }
);

watch(
  () => props.threads?.total,
  (newTotal) => {
    if (typeof newTotal === 'number') {
      totalThreadsCount.value = newTotal;
    }
  }
);

watch(
  () => props.chatStats,
  (newStats) => {
    if (newStats) {
      chatStats.value = { ...newStats };
    }
  },
  { deep: true }
);

const adminReplyMessage = ref('');
const adminStagedFiles = ref([]);
const adminFileInputRef = ref(null);
const adminChatContainer = ref(null);
const isAdminSending = ref(false);

// State Indikator Sedang Mengetik (Personel)
const isPersonelTyping = ref(false);
const personelTypingName = ref('');
let personelTypingTimer = null;
let lastAdminTypingSentAt = 0;

// State Presensi Online / Offline Personel
const selectedPersonelOnline = ref(props.activeThread?.is_online ?? false);

const updatePersonelTypingStatus = (typing) => {
  if (typing) {
    isPersonelTyping.value = true;
    personelTypingName.value = selectedThread.value?.user?.name || 'Personel';
    if (personelTypingTimer) clearTimeout(personelTypingTimer);
    personelTypingTimer = setTimeout(() => {
      isPersonelTyping.value = false;
    }, 4500);
  } else {
    isPersonelTyping.value = false;
    if (personelTypingTimer) {
      clearTimeout(personelTypingTimer);
      personelTypingTimer = null;
    }
  }
};

const onAdminTypingInput = () => {
  const now = Date.now();
  if (now - lastAdminTypingSentAt > 2000 && selectedThread.value?.uuid) {
    lastAdminTypingSentAt = now;
    axios.post(`/live-chat/${selectedThread.value.uuid}/typing`).catch(() => {});
  }
};

// Modal Chat Baru
const showNewChatModal = ref(false);
const newChatSearchQuery = ref('');
const searchedPersonelList = ref([]);
const isSearchingPersonel = ref(false);
const selectedPersonelToChat = ref(null);
const newChatInitialMessage = ref('');
const isStartingChat = ref(false);
let searchDebounceTimer = null;

const openNewChatModal = () => {
  showNewChatModal.value = true;
  newChatSearchQuery.value = '';
  searchedPersonelList.value = [];
  selectedPersonelToChat.value = null;
  newChatInitialMessage.value = '';
};

const openNewChatModalWithQuery = (query) => {
  openNewChatModal();
  newChatSearchQuery.value = query;
  searchPersonel(query);
};

const closeNewChatModal = () => {
  showNewChatModal.value = false;
  selectedPersonelToChat.value = null;
  newChatInitialMessage.value = '';
};

const onSearchPersonelInput = () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    searchPersonel(newChatSearchQuery.value);
  }, 350);
};

const searchPersonel = async (query) => {
  const q = (query || '').trim();
  if (q.length < 2) {
    searchedPersonelList.value = [];
    return;
  }
  isSearchingPersonel.value = true;
  try {
    const res = await axios.get('/live-chat/search-users', {
      params: { q },
    });
    searchedPersonelList.value = res.data.users || [];
  } catch (err) {
    console.error('Gagal mencari personel:', err);
    searchedPersonelList.value = [];
  } finally {
    isSearchingPersonel.value = false;
  }
};

const choosePersonel = (personel) => {
  selectedPersonelToChat.value = personel;
};

const executeStartChat = async () => {
  if (!selectedPersonelToChat.value) return;
  isStartingChat.value = true;

  try {
    const res = await axios.post('/live-chat/start', {
      user_id: selectedPersonelToChat.value.id,
      message: newChatInitialMessage.value,
    });

    if (res.data.success) {
      const newThread = res.data.thread;
      closeNewChatModal();

      Swal.fire({
        icon: 'success',
        title: 'Sesi Chat Dimulai',
        text: res.data.message || 'Sesi obrolan dibuka.',
        confirmButtonColor: '#2563EB',
        customClass: { popup: 'rounded-2xl' },
      });

      router.visit(window.location.pathname, {
        data: {
          uuid: newThread.uuid,
          search: searchQuery.value,
          status: statusFilter.value,
        },
        preserveScroll: true,
      });
    }
  } catch (err) {
    console.error('Gagal memulai sesi chat:', err);
    Swal.fire({
      icon: 'error',
      title: 'Gagal Memulai Chat',
      text: err.response?.data?.message || err.response?.data?.error || 'Terjadi kesalahan saat memulai sesi obrolan.',
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
  } finally {
    isStartingChat.value = false;
  }
};

const maxAllowedBytes = 15 * 1024 * 1024;

const formatBytes = (bytes) => {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const formatTime = (isoString) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  return `${h}:${m} WIB`;
};

const formatTimestamp = (isoString) => {
  if (!isoString) return '-';
  const d = new Date(isoString);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
};

const scrollAdminChatToBottom = async () => {
  await nextTick();
  if (adminChatContainer.value) {
    adminChatContainer.value.scrollTop = adminChatContainer.value.scrollHeight;
  }
};

const applyFilters = () => {
  router.get(
    window.location.pathname,
    {
      search: searchQuery.value,
      status: statusFilter.value,
      uuid: selectedThread.value?.uuid,
    },
    { preserveState: true, replace: true }
  );
};

const setStatusFilter = (st) => {
  statusFilter.value = st;
  applyFilters();
};

const selectThread = (th) => {
  selectedThread.value = th;
  th.unread_admin = 0;
  isPersonelTyping.value = false;
  selectedPersonelOnline.value = th.is_online ?? false;
  if (typeof window !== 'undefined') {
    const url = new URL(window.location.href);
    url.searchParams.set('uuid', th.uuid);
    window.history.replaceState({}, '', url.toString());
  }
  loadThreadMessages(th.uuid);
};

const backToThreadList = () => {
  selectedThread.value = null;
  activeMessagesList.value = [];
  if (typeof window !== 'undefined') {
    const url = new URL(window.location.href);
    url.searchParams.delete('uuid');
    window.history.replaceState({}, '', url.toString());
  }
};

const loadThreadMessages = async (uuid) => {
  try {
    const res = await axios.get(`/live-chat/${uuid}/messages`);
    activeMessagesList.value = res.data.messages || [];
    if (res.data.is_typing) {
      updatePersonelTypingStatus(res.data.is_typing);
    }
    if (res.data.presence) {
      selectedPersonelOnline.value = res.data.presence.is_online;
    }
    scrollAdminChatToBottom();
  } catch (err) {
    console.error('Gagal mengambil pesan utas:', err);
  }
};

let adminPollingTimer = null;

const pollAdminSync = async () => {
  const activeUuid = selectedThread.value?.uuid || null;

  try {
    const res = await axios.get('/live-chat/sync', {
      params: {
        search: searchQuery.value || '',
        status: statusFilter.value || 'all',
        uuid: activeUuid,
      },
    });

    // 1. Sinkronisasi daftar utas
    if (res.data.threads) {
      const incomingThreads = res.data.threads;
      let shouldPlaySound = false;

      incomingThreads.forEach((inTh) => {
        const oldTh = threadsList.value.find((t) => t.uuid === inTh.uuid);
        if (!oldTh) {
          if (inTh.latest_message?.sender_type === 'USER') {
            shouldPlaySound = true;
          }
        } else {
          if (inTh.unread_admin > (oldTh.unread_admin || 0) && inTh.uuid !== activeUuid) {
            shouldPlaySound = true;
          } else if (
            inTh.last_message_at !== oldTh.last_message_at &&
            inTh.latest_message?.sender_type === 'USER' &&
            inTh.uuid !== activeUuid
          ) {
            shouldPlaySound = true;
          }
        }
      });

      threadsList.value = incomingThreads;

      if (shouldPlaySound) {
        playNotificationSound();
      }
    }

    // 2. Sinkronisasi pesan pada utas aktif
    if (res.data.active_messages && activeUuid) {
      const prevLength = activeMessagesList.value.length;
      activeMessagesList.value = res.data.active_messages;

      if (res.data.active_messages.length > prevLength) {
        const newMsg = res.data.active_messages[res.data.active_messages.length - 1];
        if (newMsg?.sender_type === 'USER') {
          playNotificationSound();
        }
        scrollAdminChatToBottom();
      }
    }

    // 3. Status pengetikan & presensi
    if (res.data.is_typing !== undefined) {
      updatePersonelTypingStatus(res.data.is_typing);
    }
    if (res.data.presence) {
      selectedPersonelOnline.value = res.data.presence.is_online;
    }

    // 4. Deteksi Panggilan Masuk
    if (res.data.incoming_call && ['RINGING', 'ACCEPTED', 'CONNECTING', 'CONNECTED'].includes(res.data.incoming_call.status)) {
      if (!showVideoCallModal.value) {
        activeIncomingCallData.value = res.data.incoming_call;
        showVideoCallModal.value = true;
      }
    } else if (res.data.call) {
      const activeCall = res.data.call;
      if (['RINGING', 'ACCEPTED', 'CONNECTING', 'CONNECTED'].includes(activeCall.status) && !showVideoCallModal.value) {
        activeIncomingCallData.value = activeCall;
        showVideoCallModal.value = true;
      } else if (['ENDED', 'REJECTED'].includes(activeCall.status) && showVideoCallModal.value) {
        showVideoCallModal.value = false;
        activeIncomingCallData.value = null;
      }
    }

    // 5. Statistik
    if (res.data.stats) {
      chatStats.value = res.data.stats;
      totalThreadsCount.value = res.data.stats.total_threads || threadsList.value.length;
    }
  } catch (err) {
    console.debug('Penyelarasan pesan terhambat:', err);
  }
};

const startAdminPolling = () => {
  stopAdminPolling();
  adminPollingTimer = setInterval(() => {
    pollAdminSync();
  }, 2500);
};

const stopAdminPolling = () => {
  if (adminPollingTimer) {
    clearInterval(adminPollingTimer);
    adminPollingTimer = null;
  }
};

const handleAdminFileChange = (e) => {
  const selected = Array.from(e.target.files || []);
  if (!selected.length) return;
  adminStagedFiles.value.push(...selected);
  if (adminFileInputRef.value) {
    adminFileInputRef.value.value = '';
  }
};

const removeAdminStagedFile = (idx) => {
  adminStagedFiles.value.splice(idx, 1);
};

const submitAdminReply = async () => {
  if (isAdminSending.value || !selectedThread.value) return;
  const msgText = adminReplyMessage.value.trim();

  if (!msgText && adminStagedFiles.value.length === 0) return;

  const totalBytes = adminStagedFiles.value.reduce((sum, f) => sum + (f.size || 0), 0);
  if (totalBytes > maxAllowedBytes) {
    Swal.fire({
      icon: 'error',
      title: 'Ukuran Terlalu Besar',
      text: 'Total seluruh lampiran berkas tidak boleh melebihi 15 MB.',
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
    return;
  }

  isAdminSending.value = true;
  const formData = new FormData();
  if (msgText) {
    formData.append('message', msgText);
  }

  adminStagedFiles.value.forEach((file) => {
    formData.append('attachments[]', file);
  });

  try {
    const res = await axios.post(`/live-chat/${selectedThread.value.uuid}/send`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (res.data.message) {
      activeMessagesList.value.push(res.data.message);
      adminReplyMessage.value = '';
      adminStagedFiles.value = [];
      scrollAdminChatToBottom();

      const target = threadsList.value.find((t) => t.uuid === selectedThread.value?.uuid);
      if (target) {
        target.latest_message = res.data.message;
        target.last_message_at = res.data.message.created_at;
        threadsList.value = [target, ...threadsList.value.filter((t) => t.uuid !== target.uuid)];
      }
    }
  } catch (err) {
    const errMsg = err.response?.data?.error || err.response?.data?.message || 'Gagal mengirim pesan balasan dinas.';
    Swal.fire({
      icon: 'error',
      title: 'Pesan Gagal Terkirim',
      text: errMsg,
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
  } finally {
    isAdminSending.value = false;
  }
};

const toggleStatus = (uuid) => {
  const isClosing = selectedThread.value?.status === 'OPEN';
  if (isClosing) {
    Swal.fire({
      icon: 'warning',
      title: 'Tutup Sesi Obrolan?',
      text: 'Apakah Anda yakin ingin menutup sesi obrolan ini? Seluruh berkas lampiran yang ada dalam obrolan akan otomatis dihapus permanen dari server.',
      showCancelButton: true,
      confirmButtonText: 'Ya, Tutup Sesi & Hapus Berkas',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#DC2626',
      cancelButtonColor: '#64748B',
      customClass: { popup: 'rounded-2xl', confirmButton: 'rounded-xl', cancelButton: 'rounded-xl' },
    }).then((result) => {
      if (result.isConfirmed) {
        executeToggleStatus(uuid);
      }
    });
  } else {
    executeToggleStatus(uuid);
  }
};

const executeToggleStatus = async (uuid) => {
  try {
    const res = await axios.post(`/live-chat/${uuid}/status`);
    if (res.data.success) {
      if (selectedThread.value) {
        selectedThread.value.status = res.data.status;
        if (res.data.status === 'CLOSED') {
          activeMessagesList.value.forEach((msg) => {
            if (msg.attachments) {
              msg.attachments.forEach((att) => {
                att.purged = true;
                att.file_path = null;
              });
            }
          });
        }
      }

      const targetThread = threadsList.value.find((t) => t.uuid === uuid);
      if (targetThread) {
        targetThread.status = res.data.status;
      }

      Swal.fire({
        icon: 'success',
        title: 'Status Diperbarui',
        text: res.data.message,
        confirmButtonColor: '#2563EB',
        customClass: { popup: 'rounded-2xl' },
      });
    }
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Terjadi Hambatan',
      text: err.response?.data?.error || 'Gagal mengubah status sesi percakapan.',
      confirmButtonColor: '#2563EB',
      customClass: { popup: 'rounded-2xl' },
    });
  }
};

onMounted(() => {
  scrollAdminChatToBottom();
  startAdminPolling();
});

onUnmounted(() => {
  stopAdminPolling();
  if (personelTypingTimer) clearTimeout(personelTypingTimer);
});
</script>
