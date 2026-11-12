<script setup>
// Kartu identitas personel (gaya lanyard) untuk animasi masuk.
//  - verified=false: kartu kosong + garis pemindai + kilau kerangka
//  - verified=true : kartu berputar 3D menampilkan foto, nama, pangkat/NRP, bilah verifikasi terisi
import { computed } from 'vue';

const props = defineProps({
    verified: { type: Boolean, default: false },
    user: { type: Object, default: null },
    label: { type: String, default: '' },
});

const initials = computed(() => (props.user?.name || '?')
    .split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase());
const sub = computed(() => [props.user?.pangkat, props.user?.nrp ? `NRP ${props.user.nrp}` : null].filter(Boolean).join(' / '));
</script>

<template>
    <div class="idc-wrap" role="status" aria-live="polite">
        <div class="idc-card" :class="{ 'is-flipped': verified }">
            <!-- Sisi depan: kerangka + pemindai -->
            <div class="idc-face idc-front">
                <div class="idc-ribbon"></div>
                <div class="idc-body">
                    <div class="idc-sk idc-sk-avatar"></div>
                    <div class="idc-lines">
                        <div class="idc-sk" style="width: 90%; height: 14px"></div>
                        <div class="idc-sk" style="width: 65%; height: 10px"></div>
                        <div class="idc-sk" style="width: 45%; height: 10px"></div>
                    </div>
                </div>
                <div class="idc-scan"></div>
            </div>
            <!-- Sisi belakang: identitas terverifikasi -->
            <div class="idc-face idc-back">
                <div class="idc-ribbon"></div>
                <div class="idc-body">
                    <div class="idc-avatar">
                        <span>{{ initials }}</span>
                        <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" @error="$event.target.remove()" />
                    </div>
                    <div class="idc-info">
                        <div class="idc-name">{{ (user?.name || 'Akun terverifikasi').toUpperCase() }}</div>
                        <div v-if="sub" class="idc-sub">{{ sub }}</div>
                        <div v-if="user?.jabatan" class="idc-role">{{ user.jabatan }}</div>
                    </div>
                    <svg class="idc-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 2l2.4 1.8 3 .2.9 2.9 2.2 2-1 2.8 1 2.8-2.2 2-.9 2.9-3 .2L12 22l-2.4-1.8-3-.2-.9-2.9-2.2-2 1-2.8-1-2.8 2.2-2 .9-2.9 3-.2z" stroke-linejoin="round"/></svg>
                </div>
                <div class="idc-verify">
                    <span>VERIFIKASI</span>
                    <div class="idc-bar"><div class="idc-fill" :class="{ go: verified }"></div></div>
                </div>
            </div>
        </div>
        <p v-if="label" class="idc-label">{{ label }}</p>
    </div>
</template>

<style scoped>
.idc-wrap { display: flex; flex-direction: column; align-items: center; gap: 18px; perspective: 1100px; }
.idc-card { position: relative; width: min(340px, 82vw); aspect-ratio: 1.58; transform-style: preserve-3d;
    transition: transform .75s cubic-bezier(.2,.8,.2,1); animation: idc-in .5s cubic-bezier(.2,.8,.2,1) both; }
.idc-card.is-flipped { transform: rotateY(180deg); }
.idc-face { position: absolute; inset: 0; border-radius: 20px; overflow: hidden; backface-visibility: hidden;
    background: linear-gradient(135deg, #0b1b3f, #1e40af); border: 1px solid rgba(255,255,255,.16);
    box-shadow: 0 30px 60px -20px rgba(2,6,23,.75), inset 0 1px 0 rgba(255,255,255,.12); }
.idc-back { transform: rotateY(180deg); }
.idc-ribbon { height: 6px; background: linear-gradient(90deg, #60a5fa, #34d399); }
.idc-body { display: flex; align-items: center; gap: 16px; padding: 22px 20px 0; }
.idc-lines { flex: 1; display: flex; flex-direction: column; gap: 10px; }
.idc-sk { border-radius: 999px; background: rgba(255,255,255,.10); position: relative; overflow: hidden; }
.idc-sk::after { content: ''; position: absolute; inset: 0; transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.22), transparent); animation: idc-shimmer 1.5s infinite; }
.idc-sk-avatar { width: 64px; height: 64px; flex: none; }
.idc-scan { position: absolute; left: 0; right: 0; height: 56px; top: -28px;
    background: linear-gradient(180deg, transparent, rgba(96,165,250,.35), transparent); animation: idc-scan 1.6s ease-in-out infinite alternate; }
.idc-scan::after { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: #60a5fa; box-shadow: 0 0 12px #60a5fa; }
.idc-avatar { position: relative; width: 64px; height: 64px; flex: none; border-radius: 999px; border: 2px solid #34d399;
    background: rgba(255,255,255,.12); display: grid; place-items: center; color: #fff; font-weight: 900; font-size: 20px; overflow: hidden; }
.idc-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.idc-info { flex: 1; min-width: 0; color: #fff; }
.idc-name { font-weight: 900; font-size: 15px; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.idc-sub { font: 600 11px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; color: rgba(255,255,255,.78); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.idc-role { font-size: 10px; font-weight: 800; color: #93c5fd; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.idc-check { width: 28px; height: 28px; color: #34d399; flex: none; }
.idc-verify { position: absolute; left: 20px; right: 20px; bottom: 16px; }
.idc-verify span { font-size: 9px; font-weight: 800; letter-spacing: .2em; color: rgba(255,255,255,.6); }
.idc-bar { margin-top: 4px; height: 6px; border-radius: 999px; background: rgba(255,255,255,.12); overflow: hidden; }
.idc-fill { height: 100%; width: 0; background: linear-gradient(90deg, #60a5fa, #34d399); }
.idc-fill.go { width: 100%; transition: width .65s linear .7s; }
.idc-label { color: #e2e8f0; font-weight: 700; font-size: 14px; letter-spacing: .02em; animation: idc-pulse 1.6s ease-in-out infinite; }
@keyframes idc-in { from { opacity: 0; transform: translateY(14px) scale(.96) rotateX(8deg); } to { opacity: 1; transform: none; } }
.idc-card.is-flipped { animation: none; }
@keyframes idc-shimmer { to { transform: translateX(100%); } }
@keyframes idc-scan { from { top: -28px; } to { top: calc(100% - 28px); } }
@keyframes idc-pulse { 50% { opacity: .55; } }
@media (prefers-reduced-motion: reduce) {
    .idc-card, .idc-fill.go { transition-duration: .01s !important; transition-delay: 0s !important; }
    .idc-scan, .idc-sk::after, .idc-label, .idc-card { animation: none !important; }
}
</style>
