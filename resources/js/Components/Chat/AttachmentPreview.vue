<template>
  <Teleport to="body">
    <div
      v-if="attachment"
      class="fixed inset-0 z-[120] flex flex-col bg-slate-950/90 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      :aria-label="`Pratinjau ${attachment.original_name}`"
      @keydown.esc="$emit('close')"
    >
      <!-- Bilah atas: nama berkas, unduh, tutup -->
      <div class="flex items-center gap-3 px-4 py-3 bg-slate-900/95 border-b border-slate-800 text-white shrink-0">
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold truncate">{{ attachment.original_name }}</p>
          <p v-if="attachment.size" class="text-[11px] text-slate-400">{{ sizeLabel }}</p>
        </div>
        <a
          :href="downloadUrl"
          :download="attachment.original_name"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-xs font-semibold transition"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
          </svg>
          Unduh
        </a>
        <button
          ref="closeBtn"
          type="button"
          class="p-2 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition cursor-pointer"
          aria-label="Tutup pratinjau"
          @click="$emit('close')"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Isi pratinjau -->
      <div class="flex-1 min-h-0 flex items-center justify-center p-3 sm:p-6" @click.self="$emit('close')">
        <img
          v-if="kind === 'image'"
          :src="inlineUrl"
          :alt="attachment.original_name"
          class="max-w-full max-h-full object-contain rounded-lg shadow-2xl bg-white"
        />
        <iframe
          v-else-if="kind === 'pdf' || kind === 'text'"
          :src="inlineUrl"
          :title="attachment.original_name"
          class="w-full h-full max-w-5xl rounded-lg bg-white shadow-2xl"
        ></iframe>
        <div v-else class="max-w-sm text-center text-slate-200 space-y-3">
          <div class="mx-auto w-16 h-16 rounded-2xl bg-slate-800 flex items-center justify-center">
            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p class="text-sm font-semibold">Berkas {{ extLabel }} belum bisa dipratinjau</p>
          <p class="text-xs text-slate-400">Unduh berkas untuk membukanya di perangkat Anda.</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
  attachment: { type: Object, default: null },
});
defineEmits(['close']);

const closeBtn = ref(null);

const ext = computed(() => (props.attachment?.original_name || props.attachment?.file_path || '').split('.').pop().toLowerCase());
const extLabel = computed(() => ext.value.toUpperCase());
const kind = computed(() => {
  if (['jpg', 'jpeg', 'png', 'webp'].includes(ext.value)) return 'image';
  if (ext.value === 'pdf') return 'pdf';
  if (ext.value === 'txt') return 'text';
  return 'other';
});

const query = computed(() => {
  const a = props.attachment || {};
  return `path=${encodeURIComponent(a.file_path || '')}&name=${encodeURIComponent(a.original_name || '')}`;
});
const downloadUrl = computed(() => `/live-chat/attachment/download?${query.value}`);
const inlineUrl = computed(() => `/live-chat/attachment/download?${query.value}&inline=1`);

const sizeLabel = computed(() => {
  const b = Number(props.attachment?.size || 0);
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / 1024 / 1024).toFixed(1)} MB`;
});

// Fokus ke tombol tutup agar Esc langsung bekerja.
watch(() => props.attachment, async (a) => {
  if (a) { await nextTick(); closeBtn.value?.focus(); }
});
</script>
