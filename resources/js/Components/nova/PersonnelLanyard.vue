<script setup>
// Lanyard kartu identitas personel yang login (Pangkat, Nama, NRP, Jabatan).
// Kartu menggantung di tali bertuliskan nama satuan; bisa ditarik dan berayun
// kembali (pegas teredam), klik / Enter untuk membalik. Reduced motion: diam.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
    agency: { type: String, default: 'DENINTEL KODAERAL V' },
    logo: { type: String, default: null },
});

const ROLE_LABEL = {
    admin: 'Administrator Sistem',
    komandan: 'Komandan',
    anggotasintel: 'Anggota Sintel',
    danunitteknis: 'Dan Unit Teknis',
    user: 'Personel',
};
const jabatan = computed(() => {
    if (props.user.jabatan) return props.user.jabatan;
    const key = String(props.user.role || '').toLowerCase().replace(/[\s_-]+/g, '');
    return ROLE_LABEL[key] || (props.user.role ? String(props.user.role) : 'Personel');
});
const photo = computed(() => props.user.avatar_url || (props.user.avatar ? '/storage/' + props.user.avatar : null));
const initials = computed(() => (props.user.name || 'P').split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase());
const strapText = computed(() => props.agency);

/* ---------- Ayunan pegas ---------- */
const angle = ref(0);      // derajat
const stretch = ref(0);    // px tarikan ke bawah
const flipped = ref(false);
const dragging = ref(false);
let vel = 0, raf = 0, startX = 0, startY = 0, startAngle = 0, moved = false;
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const tick = () => {
    // Pegas teredam: kembali ke 0 derajat.
    const k = 38, c = 4.2, dt = 1 / 60;
    vel += (-k * angle.value - c * vel) * dt;
    angle.value += vel * dt;
    stretch.value *= 0.82;
    if (Math.abs(angle.value) < 0.05 && Math.abs(vel) < 0.05 && stretch.value < 0.2) {
        angle.value = 0; stretch.value = 0; vel = 0; raf = 0;
        return;
    }
    raf = requestAnimationFrame(tick);
};
const release = () => { if (!raf && !reduced()) raf = requestAnimationFrame(tick); };

const onDown = (e) => {
    if (reduced()) return;
    cancelAnimationFrame(raf); raf = 0;
    dragging.value = true; moved = false;
    startX = e.clientX; startY = e.clientY; startAngle = angle.value;
    e.currentTarget.setPointerCapture?.(e.pointerId);
};
const onMove = (e) => {
    if (!dragging.value) return;
    const dx = e.clientX - startX, dy = e.clientY - startY;
    if (Math.abs(dx) + Math.abs(dy) > 4) moved = true;
    angle.value = Math.max(-38, Math.min(38, startAngle + dx * 0.22));
    stretch.value = Math.max(0, Math.min(28, dy * 0.35));
};
const onUp = () => {
    if (!dragging.value) return;
    dragging.value = false;
    if (!moved) flipped.value = !flipped.value;
    vel = 0;
    release();
};
const onKey = (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flipped.value = !flipped.value; }
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); vel += e.key === 'ArrowLeft' ? -60 : 60; release(); }
};

onMounted(() => {
    // Satu ayunan sambutan saat dashboard dibuka.
    if (!reduced()) { angle.value = 14; release(); }
});
onBeforeUnmount(() => cancelAnimationFrame(raf));

const label = computed(() =>
    `Kartu identitas: ${props.user.pangkat || ''} ${props.user.name}, NRP ${props.user.nrp || '-'}, ${jabatan.value}. ` +
    (flipped.value ? 'Sisi belakang. ' : '') + 'Tekan Enter untuk membalik.'
);
</script>

<template>
    <div class="ly-stage">
        <div class="ly-swing" :style="{ transform: `rotate(${angle}deg)` }">
            <!-- Tali: dua sisi bertemu di klip, bertuliskan nama satuan -->
            <svg class="ly-strap" viewBox="0 0 200 150" aria-hidden="true" preserveAspectRatio="none" :style="{ height: 150 + stretch + 'px' }">
                <defs>
                    <path id="ly-l" d="M62 -4 L96 146" />
                    <path id="ly-r" d="M138 -4 L104 146" />
                </defs>
                <path d="M62 -4 L96 146" class="ly-band" />
                <path d="M138 -4 L104 146" class="ly-band ly-band-r" />
                <text class="ly-band-text"><textPath href="#ly-l" startOffset="50%" text-anchor="middle">{{ strapText }}</textPath></text>
                <text class="ly-band-text"><textPath href="#ly-r" startOffset="50%" text-anchor="middle">{{ strapText }}</textPath></text>
            </svg>
            <div class="ly-clip" aria-hidden="true"><i></i></div>

            <button
                type="button"
                class="ly-card"
                :class="{ 'is-flipped': flipped, 'is-drag': dragging }"
                :aria-label="label"
                :aria-pressed="flipped"
                @pointerdown="onDown"
                @pointermove="onMove"
                @pointerup="onUp"
                @pointercancel="onUp"
                @keydown="onKey"
            >
                <span class="ly-inner">
                    <!-- Depan -->
                    <span class="ly-face ly-front">
                        <span class="ly-head">
                            <span v-if="logo" class="ly-logo"><img :src="logo" alt="" /></span>
                            <span class="ly-agency">{{ agency }}</span>
                        </span>
                        <span class="ly-photo">
                            <img v-if="photo" :src="photo" alt="" draggable="false" />
                            <template v-else>{{ initials }}</template>
                        </span>
                        <span class="ly-pangkat">{{ user.pangkat || 'Pangkat belum diisi' }}</span>
                        <span class="ly-name">{{ user.name }}</span>
                        <span class="ly-nrp"><small>NRP</small>{{ user.nrp || '—' }}</span>
                        <span class="ly-rule"></span>
                        <span class="ly-jabatan"><small>Jabatan</small>{{ jabatan }}</span>
                        <span class="ly-foot">
                            <span class="ly-status">Aktif</span>
                            <span>Kartu identitas digital</span>
                        </span>
                    </span>
                    <!-- Belakang -->
                    <span class="ly-face ly-back">
                        <span v-if="logo" class="ly-back-logo"><img :src="logo" alt="" /></span>
                        <span class="ly-back-title">{{ agency }}</span>
                        <span class="ly-rule"></span>
                        <span class="ly-back-note">Kartu ini sah selama pemegang masih aktif bertugas dan hanya untuk penggunaan internal.</span>
                        <span class="ly-back-hint">Klik untuk membalik</span>
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.ly-stage { font-family: 'Figtree', system-ui, sans-serif; position: relative; width: 236px; height: 470px; margin: -40px auto 0; user-select: none; }
.ly-swing { position: absolute; left: 0; top: 0; width: 100%; transform-origin: 50% 0; will-change: transform; }

.ly-strap { display: block; width: 200px; margin: 0 auto; overflow: visible; }
.ly-band { stroke: #1E3A5F; stroke-width: 18; stroke-linecap: butt; fill: none; }
.ly-band-r { stroke: #23446F; }
.ly-band-text { font: 800 9px/1 'Figtree', system-ui, sans-serif; letter-spacing: .16em; fill: #CBD5E1; dominant-baseline: middle; }

.ly-clip { position: relative; width: 34px; height: 30px; margin: -10px auto 0; z-index: 2; }
.ly-clip::before { content: ''; position: absolute; left: 7px; top: 0; width: 20px; height: 20px; border-radius: 50%; border: 3px solid #9CA3AF; background: transparent; }
.ly-clip i { position: absolute; left: 10px; top: 16px; width: 14px; height: 16px; border-radius: 3px; background: linear-gradient(#D1D5DB, #6B7280); box-shadow: 0 1px 2px rgba(0,0,0,.3); }

.ly-card { position: relative; display: block; width: 220px; height: 312px; margin: -2px auto 0; padding: 0; border: 0; background: none;
           perspective: 900px; cursor: grab; touch-action: none; border-radius: 16px; }
.ly-card.is-drag { cursor: grabbing; }
.ly-card:focus-visible { outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, #2563EB 45%, transparent); }
.ly-inner { position: relative; display: block; width: 100%; height: 100%; transform-style: preserve-3d; transition: transform .6s cubic-bezier(.16, 1, .3, 1); }
.ly-card.is-flipped .ly-inner { transform: rotateY(180deg); }

.ly-face { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; text-align: center; overflow: hidden;
           backface-visibility: hidden; -webkit-backface-visibility: hidden; border-radius: 16px; background: #FFFFFF; color: #09090B;
           border: 1px solid #E4E4E7; box-shadow: 0 18px 40px -16px rgba(9, 9, 11, .45), 0 2px 6px rgba(9, 9, 11, .08); }
/* Lubang slot tali */
.ly-face::before { content: ''; position: absolute; top: 9px; left: 50%; width: 38px; height: 7px; margin-left: -19px; border-radius: 4px; background: #0B1A2E; opacity: .9; z-index: 2; }

.ly-head { position: relative; width: 100%; height: 86px; padding: 22px 14px 0; display: flex; align-items: flex-start; justify-content: center; gap: 8px;
           background: linear-gradient(135deg, #1E3A5F, #0F2240); color: #fff; }
.ly-logo { width: 26px; height: 26px; flex: none; display: grid; place-items: center; font-weight: 700; font-size: 12px; }
.ly-logo img { width: 100%; height: 100%; object-fit: contain; }
.ly-agency { font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; line-height: 1.2; text-align: left; }

.ly-photo { width: 80px; height: 80px; margin-top: -30px; border-radius: 18px; overflow: hidden; display: grid; place-items: center; flex: none; z-index: 1;
            background: linear-gradient(135deg, #2563EB, #7C3AED); color: #fff; font-size: 26px; font-weight: 700;
            border: 3px solid #fff; box-shadow: 0 6px 16px -6px rgba(9, 9, 11, .35); }
.ly-photo img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }

.ly-pangkat { margin-top: 10px; font-size: 12px; font-weight: 600; color: #1D4ED8; }
.ly-name { margin-top: 2px; padding: 0 14px; font-size: 16px; font-weight: 700; line-height: 1.25; letter-spacing: -.01em;
           display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ly-nrp { margin-top: 6px; font: 700 14px 'Figtree', system-ui, sans-serif; letter-spacing: .04em; font-variant-numeric: tabular-nums; color: #27272A; }
.ly-nrp small, .ly-jabatan small { display: block; font: 500 11px 'Figtree', system-ui, sans-serif; color: #6B6B74; margin-bottom: 1px; }
.ly-rule { width: 70%; height: 1px; margin: 10px 0 8px; background: #E4E4E7; flex: none; }
.ly-jabatan { padding: 0 14px; font-size: 13px; font-weight: 600; color: #27272A; line-height: 1.3; }
.ly-foot { margin-top: auto; width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px;
           border-top: 1px solid #F4F4F5; font-size: 11px; color: #6B6B74; background: #FAFAFA; }
.ly-status { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: #15803D; }
.ly-status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #16A34A; }

.ly-back { transform: rotateY(180deg); justify-content: center; padding: 28px 18px 18px; background: linear-gradient(160deg, #1E3A5F, #0B1A2E); color: #E2E8F0; border-color: #1E3A5F; }
.ly-back::before { background: #050B1A; }
.ly-back-logo { width: 72px; height: 72px; display: grid; place-items: center; font-size: 28px; font-weight: 700; }
.ly-back-logo img { width: 100%; height: 100%; object-fit: contain; }
.ly-back-title { margin-top: 12px; font-size: 15px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #fff; }
.ly-back-sub { margin-top: 2px; font-size: 11.5px; color: #CBD5E1; }
.ly-back .ly-rule { background: rgba(255, 255, 255, .15); }
.ly-back-note { font-size: 12px; line-height: 1.45; color: #CBD5E1; }
.ly-back-hint { margin-top: auto; font-size: 11px; color: #94A3B8; }

@media (prefers-reduced-motion: reduce) {
    .ly-inner { transition: none; }
}
</style>
