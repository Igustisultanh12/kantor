<script setup>
// Kerangka visual halaman auth & publik, meniru Login.vue:
// latar aurora gelap (atau foto latar dari Pengaturan) + sorotan kursor,
// kolom identitas di kiri, dan kartu kaca yang sedikit miring mengikuti kursor.
// Mode `bare`: hanya latar + slot penuh (untuk halaman publik seperti Cek SC).
// Hanya tampilan — logika form tetap di halaman masing-masing.
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { animate, stagger } from 'framer-motion/dom';
import { reduced, T, applyMotionSetting } from '@/motion';

const props = defineProps({
    tagline: { type: String, default: 'Sistem Informasi Detasemen Intelijen Integrasi Otoritas Akses, Pengawasan Lokasi, Administrasi Surat & Logistik Terpadu.' },
    cardClass: { type: String, default: 'max-w-md' },
    bare: { type: Boolean, default: false },
    settings: { type: Object, default: null },        // timpa page.props.settings bila halaman mengirim sendiri
    fallbackLogo: { type: String, default: null },    // logo bila Pengaturan belum punya logo
});

const page = usePage();
const settings = computed(() => props.settings || page.props.settings || {});
const appName = computed(() => settings.value.agency_name || settings.value.app_name || 'SINDEN');
const toUrl = (v) => (!v ? null : (v.startsWith('http') || v.startsWith('/storage') || v.startsWith('/images')) ? v : '/storage/' + v);
const logo = computed(() => toUrl(settings.value.agency_logo || settings.value.logo || settings.value.logo_tni) || props.fallbackLogo);
const bg = computed(() => toUrl(settings.value.login_background));

const sceneEl = ref(null);
const cardEl = ref(null);
const tiltEl = ref(null);
const logoEl = ref(null);

const playEntrance = () => {
    if (reduced() || !sceneEl.value) return;
    const blur = { filter: ['blur(12px)', 'blur(0px)'] };
    const scene = sceneEl.value;
    animate(scene.querySelectorAll('[data-aurora]'), { opacity: [0, 1] }, { duration: 1.2 });
    if (logoEl.value) animate(logoEl.value, { opacity: [0, 1], scale: [0.6, 1], ...blur }, { type: 'spring', duration: 0.9, bounce: 0.3 });
    animate(scene.querySelectorAll('[data-hero]'), { opacity: [0, 1], y: [16, 0], ...blur }, { ...T.enter, delay: stagger(0.08, { startDelay: 0.25 }) });
    if (cardEl.value) {
        animate(cardEl.value, { opacity: [0, 1], y: [48, 0], scale: [0.94, 1], ...blur }, { ...T.enter, duration: 0.8, delay: 0.15 })
            .then(() => ['transform', 'opacity', 'filter'].forEach((p) => cardEl.value?.style.removeProperty(p)));
    }
    const fields = Array.from(scene.querySelectorAll('[data-field]')).slice(0, 14);
    if (fields.length) {
        animate(fields, { opacity: [0, 1], y: [14, 0] }, { ...T.enter, delay: stagger(0.05, { startDelay: 0.35 }) })
            .then(() => fields.forEach((f) => f.style.removeProperty('transform')));
    }
};

let raf = 0;
const onMove = (e) => {
    if (reduced() || window.matchMedia('(pointer: coarse)').matches) return;
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
        const nx = e.clientX / window.innerWidth - 0.5;
        const ny = e.clientY / window.innerHeight - 0.5;
        if (tiltEl.value) animate(tiltEl.value, { rotateY: nx * 5, rotateX: -ny * 5 }, { type: 'spring', duration: 0.8, bounce: 0 });
        if (logoEl.value) animate(logoEl.value, { x: nx * 22, y: ny * 18 }, { type: 'spring', duration: 1, bounce: 0 });
        sceneEl.value?.style.setProperty('--px', `${(nx + 0.5) * 100}%`);
        sceneEl.value?.style.setProperty('--py', `${(ny + 0.5) * 100}%`);
    });
};
const onLeave = () => {
    if (tiltEl.value) animate(tiltEl.value, { rotateX: 0, rotateY: 0 }, { type: 'spring', duration: 0.8, bounce: 0.1 });
};

/** Getar kartu — dipanggil halaman saat validasi gagal. */
const shake = () => {
    if (!cardEl.value || reduced()) return;
    animate(cardEl.value, { x: [0, -10, 9, -6, 4, 0] }, { duration: 0.45, ease: 'easeOut' })
        .then(() => cardEl.value?.style.removeProperty('transform'));
};
defineExpose({ shake });

onMounted(() => {
    applyMotionSetting(String(settings.value.ui_motion_enabled ?? '1') !== '0');
    playEntrance();
    window.addEventListener('pointermove', onMove, { passive: true });
    document.addEventListener('pointerleave', onLeave);
});
onUnmounted(() => {
    window.removeEventListener('pointermove', onMove);
    document.removeEventListener('pointerleave', onLeave);
    cancelAnimationFrame(raf);
});
</script>

<template>
    <div
        ref="sceneEl"
        class="min-h-screen flex text-slate-100 font-sans bg-cover bg-center relative overflow-x-hidden bg-[#050b1a]"
        :class="bare ? 'bg-fixed' : 'overflow-hidden'"
        :style="bg ? { backgroundImage: `url(${bg})` } : {}"
    >
        <!-- Latar: aurora bergerak + sorotan mengikuti kursor -->
        <div class="fixed inset-0 z-0 pointer-events-none" aria-hidden="true">
            <div class="absolute inset-0" :class="bg ? 'bg-[#050b1a]/80 backdrop-blur-[3px]' : ''"></div>
            <div data-aurora class="auth-aurora auth-aurora-1"></div>
            <div data-aurora class="auth-aurora auth-aurora-2"></div>
            <div data-aurora class="auth-aurora auth-aurora-3"></div>
            <div class="auth-spotlight"></div>
        </div>

        <!-- Mode polos: halaman mengatur isinya sendiri -->
        <div v-if="bare" class="relative z-10 w-full min-h-screen flex flex-col">
            <slot :app-name="appName" :logo="logo" />
        </div>

        <div v-else class="relative z-10 w-full min-h-screen flex flex-col lg:flex-row">
            <!-- Kolom kiri: identitas -->
            <div class="hidden lg:flex lg:w-1/2 flex-col justify-between p-16">
                <div></div>
                <div class="my-auto max-w-lg flex flex-col items-center text-center mx-auto">
                    <div ref="logoEl" class="relative mb-8">
                        <div class="auth-halo" aria-hidden="true"></div>
                        <img v-if="logo" :src="logo" class="relative h-40 lg:h-44 object-contain drop-shadow-[0_18px_30px_rgba(0,0,0,0.55)] select-none" alt="Logo SINDEN" draggable="false" />
                        <div v-else class="relative w-32 h-32 rounded-[2rem] bg-white/10 backdrop-blur-xl border border-white/20 flex items-center justify-center shadow-[0_20px_50px_-12px_rgba(0,0,0,0.6)]">
                            <span class="font-black text-white text-5xl">S</span>
                        </div>
                    </div>
                    <h2 data-hero class="text-[2.6rem] leading-[1.05] font-black tracking-tight uppercase text-white text-balance">{{ appName }}</h2>
                    <p data-hero class="mt-4 text-sm leading-relaxed max-w-md mx-auto text-slate-300/90 font-medium text-pretty">{{ tagline }}</p>
                </div>
                <p data-hero class="text-xs text-slate-400 text-center font-medium">
                    © {{ new Date().getFullYear() }} {{ appName }}. All Rights Reserved.
                </p>
            </div>

            <!-- Kolom kanan: kartu kaca -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-5 sm:p-10 my-auto auth-stage">
                <div ref="tiltEl" class="w-full auth-tilt" :class="cardClass">
                    <div ref="cardEl" class="auth-card w-full p-7 sm:p-9 rounded-[1.75rem] text-white">
                        <!-- Logo kecil untuk layar sempit -->
                        <div data-field class="lg:hidden flex items-center gap-3 mb-6">
                            <img v-if="logo" :src="logo" class="h-11 w-11 object-contain" alt="" />
                            <div v-else class="h-11 w-11 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center font-black">S</div>
                            <span class="font-black tracking-tight uppercase text-sm truncate">{{ appName }}</span>
                        </div>
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
