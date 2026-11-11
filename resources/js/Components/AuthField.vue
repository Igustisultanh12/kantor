<script setup>
// Input bergaya Login.vue: label mengambang, ikon di kiri, tombol lihat sandi.
// Atribut tambahan (required, autofocus, maxlength, autocomplete, inputmode, @input)
// diteruskan ke <input>.
import { computed, ref, useAttrs, useId } from 'vue';

defineOptions({ inheritAttrs: false });

// class/style/data-field menempel di pembungkus (tata letak & animasi), sisanya ke <input>.
const attrs = useAttrs();
const wrapAttrs = computed(() => {
    const out = {};
    for (const k of ['class', 'style', 'data-field']) if (k in attrs) out[k] = attrs[k];
    return out;
});
const inputAttrs = computed(() => {
    const out = { ...attrs };
    for (const k of ['class', 'style', 'data-field']) delete out[k];
    return out;
});

const model = defineModel({ type: [String, Number], default: '' });
const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    icon: { type: String, default: 'user' }, // user | lock | mail | key | badge | search | star
    error: { type: String, default: '' },
    inputClass: { type: String, default: '' },
});

const id = useId();
const reveal = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && reveal.value ? 'text' : props.type));

const ICONS = {
    user: '<circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21a8 8 0 0 1 16 0"/>',
    lock: '<rect x="4" y="10" width="16" height="11" rx="2.5"/><path stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/>',
    key: '<circle cx="8" cy="15" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="m11 12 9-9m-4 4 3 3m-6 0 2 2"/>',
    badge: '<rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="11" r="2"/><path stroke-linecap="round" d="M6 16a3 3 0 0 1 6 0M15 10h3M15 13h3"/>',
    search: '<circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/>',
    star: '<path stroke-linejoin="round" d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
};
</script>

<template>
    <div v-bind="wrapAttrs">
        <div class="auth-field" :class="{ 'has-error': error, 'has-toggle': isPassword }">
            <input
                :id="id"
                v-model="model"
                :type="inputType"
                placeholder=" "
                :class="inputClass"
                v-bind="inputAttrs"
            />
            <label :for="id">{{ label }}</label>
            <svg class="auth-ico" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true" v-html="ICONS[icon] || ICONS.user"></svg>
            <button
                v-if="isPassword"
                type="button"
                class="auth-toggle"
                @click="reveal = !reveal"
                :aria-label="reveal ? 'Sembunyikan kata kunci' : 'Tampilkan kata kunci'"
                :aria-pressed="reveal"
            >
                <svg v-if="!reveal" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/></svg>
            </button>
        </div>
        <p v-if="error" class="mt-1.5 text-xs text-rose-300 font-medium">{{ error }}</p>
    </div>
</template>
