<script setup>
// Kerangka "Nova" (PRD v1.1 bagian 7–8 & 15): sidebar 5 grup (260 / rail 72),
// topbar dengan breadcrumb + Command Palette (Ctrl+K), tema Terang/Gelap/Sistem,
// skip link, fokus ke konten setelah pindah halaman, drawer di layar < 1024 px.
// Isi halaman (slot) tidak diubah.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import GlobalCommandPalette from '@/Components/GlobalCommandPalette.vue';
import NovaIcon from '@/Components/nova/NovaIcon.vue';
import { buildNovaGroups, useNovaAccess } from '@/nova/navigation';
import { setNovaPreference } from '@/nova/flag';
import { animate } from 'framer-motion/dom';
import { animatePageEnter, applyMotionSetting, installLivelyUI, reduced } from '@/motion';

// Posisi penanda menu aktif dari kunjungan sebelumnya (layout dipasang ulang tiap halaman).
let lastPillY = null;

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const settings = computed(() => page.props.settings || {});
const agencyName = computed(() => settings.value.agency_name || 'DENINTEL KODAERAL V');
const appLogo = computed(() => {
    const logo = settings.value.agency_logo || settings.value.logo;
    if (!logo) return null;
    return (logo.startsWith('http') || logo.startsWith('/storage')) ? logo : '/storage/' + logo;
});
const motionOn = computed(() => String(settings.value.ui_motion_enabled ?? '1') !== '0');

/* ---------- Latar & kaca lama (Pengaturan → Tampilan Web), dipertahankan ---------- */
const ui = computed(() => {
    const s = settings.value;
    const num = (v, d, min, max) => { const n = Number(v); return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : d; };
    const image = s.ui_background ? '/storage/' + s.ui_background : null;
    let style = ['aurora', 'polos', 'gambar'].includes(s.ui_bg_style) ? s.ui_bg_style : 'aurora';
    if (style === 'gambar' && !image) style = 'aurora';
    return {
        style,
        image,
        bgBlur: num(s.ui_bg_blur, 0, 0, 40),
        glassBlur: num(s.ui_glass_blur, 26, 0, 40),
        glassOpacity: num(s.ui_glass_opacity, 42, 20, 100),
        animated: String(s.ui_bg_animated ?? '1') !== '0',
    };
});
const shellVars = computed(() => ({
    '--sinden-glass-a': (ui.value.glassOpacity / 100).toFixed(2),
    '--sinden-blur': `${ui.value.glassBlur}px`,
}));

/* ---------- Tema (FR-1) ---------- */
const THEME_KEY = 'sinden.nova.theme';
const themePref = ref(readLocal(THEME_KEY) || 'system');
const systemDark = ref(false);
const theme = computed(() => (themePref.value === 'system' ? (systemDark.value ? 'dark' : 'light') : themePref.value));
const setTheme = (t) => { themePref.value = t; writeLocal(THEME_KEY, t); };
let mql = null;
const onScheme = (e) => { systemDark.value = e.matches; };

/* ---------- Navigasi ---------- */
const access = useNovaAccess(user);
const unread = ref(0);
const groups = computed(() => buildNovaGroups(access, { unread: unread.value }));
const isCurrent = (item) => { try { return route().current(item.match); } catch (e) { return false; } };
const homeHref = computed(() => safeRoute(access.isAnggotaSintel.value ? 'sc-submissions.index' : 'dashboard'));
const crumb = computed(() => {
    for (const g of groups.value) {
        const it = g.items.find(isCurrent);
        if (it) return { group: g.title, label: it.label };
    }
    try { if (route().current('profile.*')) return { group: 'Akun', label: 'Profil' }; } catch (e) {}
    return null;
});

const RAIL_KEY = 'sinden.nova.rail';
const rail = ref(readLocal(RAIL_KEY) === '1');
const toggleRail = () => { rail.value = !rail.value; writeLocal(RAIL_KEY, rail.value ? '1' : '0'); };

const COLLAPSE_KEY = 'sinden.nova.groups';
const collapsed = ref(new Set(JSON.parse(readLocal(COLLAPSE_KEY) || '[]')));
const isOpen = (g) => !collapsed.value.has(g.id) || g.items.some(isCurrent);
const toggleGroup = (g) => {
    const s = new Set(collapsed.value);
    s.has(g.id) ? s.delete(g.id) : s.add(g.id);
    collapsed.value = s;
    writeLocal(COLLAPSE_KEY, JSON.stringify([...s]));
};

const drawer = ref(false);
const sideEl = ref(null);
const menuBtnEl = ref(null);
const openDrawer = async () => {
    drawer.value = true;
    await nextTick();
    sideEl.value?.querySelector('a, button')?.focus();
};
const closeDrawer = () => { drawer.value = false; menuBtnEl.value?.focus(); };

/* ---------- Command palette & menu profil ---------- */
const paletteRef = ref(null);
const openPalette = () => paletteRef.value?.openPalette?.();

const profileOpen = ref(false);
const profileEl = ref(null);
const onDocClick = (e) => { if (profileOpen.value && profileEl.value && !profileEl.value.contains(e.target)) profileOpen.value = false; };

const onKey = (e) => {
    if (e.key === 'Escape') {
        if (profileOpen.value) { profileOpen.value = false; return; }
        if (drawer.value) { closeDrawer(); return; }
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        const t = e.target;
        if (t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) return;
        e.preventDefault();
        toggleRail();
    }
};

const backToClassic = () => {
    setNovaPreference('0');
    router.reload();
};

/* ---------- Notifikasi (jumlah belum dibaca) ---------- */
let notifTimer = null;
const fetchUnread = async () => {
    if (document.visibilityState !== 'visible') return;
    try {
        const res = await fetch(route('notifications.api'), { headers: { Accept: 'application/json' } });
        if (res.ok) unread.value = (await res.json()).unreadCount || 0;
    } catch (e) { /* jaringan lemah: biarkan angka terakhir */ }
};
const unreadLabel = computed(() => (unread.value ? `Notifikasi, ${unread.value} belum dibaca` : 'Notifikasi'));

/* ---------- Animasi interaktif lama (motion.js) + penanda menu meluncur ---------- */
const topbarEl = ref(null);
const progressEl = ref(null);
const orbsEl = ref(null);
const navEl = ref(null);
const pillEl = ref(null);
let stopLively = null;

const placePill = (animateMove = true) => {
    const nav = navEl.value, pill = pillEl.value;
    if (!nav || !pill) return;
    const active = nav.querySelector('.nv-item[aria-current="page"]');
    if (!active || !active.offsetParent) { pill.style.opacity = '0'; return; }
    const y = active.offsetTop;
    pill.style.height = `${active.offsetHeight}px`;
    pill.style.opacity = '1';
    if (!animateMove || lastPillY === null || lastPillY === y || reduced()) {
        pill.style.transform = `translateY(${y}px)`;
    } else {
        animate(pill, { y: [lastPillY, y] }, { type: 'spring', stiffness: 380, damping: 32 });
    }
    lastPillY = y;
};
watch([rail, collapsed], () => nextTick(() => setTimeout(() => placePill(false), 260)));

/* ---------- Fokus ke konten setelah pindah halaman (audit A9) ---------- */
const mainEl = ref(null);
let removeNav = null;

onMounted(() => {
    applyMotionSetting(motionOn.value);
    nextTick(() => {
        placePill();
        animatePageEnter(mainEl.value);
        stopLively = installLivelyUI({
            main: mainEl.value,
            header: topbarEl.value,
            progressBar: progressEl.value,
            parallax: ui.value.animated ? orbsEl.value : null,
            nav: null,
            navPill: null,
        });
    });
    mql = window.matchMedia('(prefers-color-scheme: dark)');
    systemDark.value = mql.matches;
    mql.addEventListener?.('change', onScheme);
    window.addEventListener('keydown', onKey);
    document.addEventListener('click', onDocClick);
    fetchUnread();
    notifTimer = setInterval(fetchUnread, 15000);
    removeNav = router.on('navigate', () => {
        drawer.value = false;
        profileOpen.value = false;
        const a = document.activeElement;
        if (!a || a === document.body || a.closest?.('.nv-side')) mainEl.value?.focus({ preventScroll: true });
    });
});
onBeforeUnmount(() => {
    stopLively?.();
    mql?.removeEventListener?.('change', onScheme);
    window.removeEventListener('keydown', onKey);
    document.removeEventListener('click', onDocClick);
    clearInterval(notifTimer);
    removeNav?.();
});
watch(drawer, (v) => { document.documentElement.style.overflow = v ? 'hidden' : ''; });

function safeRoute(name, params) { try { return route(name, params); } catch (e) { return '#'; } }
function readLocal(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
function writeLocal(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* mode privat */ } }
const initials = computed(() => (user.value.name || 'US').split(/\s+/).slice(0, 2).map((s) => s[0]).join('').toUpperCase());
</script>

<template>
    <div
        class="nova nv-shell sinden-shell"
        :data-theme="theme"
        :data-motion="motionOn ? null : 'off'"
        :data-bg="ui.style"
        :data-bg-static="ui.animated && motionOn ? null : ''"
        :style="shellVars"
    >
        <!-- Latar dekoratif lama (Aurora / Polos / Gambar), sama dengan tampilan klasik -->
        <div class="sinden-bg" aria-hidden="true">
            <div
                v-if="ui.style === 'gambar'"
                class="sinden-bg-image"
                :style="{ backgroundImage: `url(${ui.image})`, filter: ui.bgBlur ? `blur(${ui.bgBlur}px)` : null }"
            ></div>
            <div v-if="ui.style === 'aurora'" ref="orbsEl" class="sinden-orbs"><i></i><i></i><i></i><i></i></div>
        </div>

        <a href="#nv-main" class="nv-skip">Lewati ke konten</a>

        <div v-if="drawer" class="nv-scrim" @click="closeDrawer" aria-hidden="true"></div>

        <aside
            id="nv-side"
            ref="sideEl"
            class="nv-side"
            :data-rail="rail ? 'true' : 'false'"
            :data-open="drawer ? 'true' : 'false'"
            aria-label="Navigasi utama"
        >
            <div class="nv-side-head">
                <Link :href="homeHref" class="nv-brand">
                    <span class="nv-brand-mark">
                        <img v-if="appLogo" :src="appLogo" alt="" />
                        <template v-else>S</template>
                    </span>
                    <span class="nv-brand-text"><b>SISINDEN</b><span>{{ agencyName }}</span></span>
                </Link>
                <button
                    type="button"
                    class="nv-icon-btn nv-desktop-only"
                    @click="toggleRail"
                    :aria-label="rail ? 'Bentangkan sidebar' : 'Lipat sidebar'"
                    :aria-expanded="!rail"
                    :title="(rail ? 'Bentangkan' : 'Lipat') + ' sidebar (Ctrl+B)'"
                ><NovaIcon name="panel" /></button>
            </div>

            <button type="button" class="nv-search" @click="openPalette" aria-label="Cari menu (Ctrl+K)" title="Cari menu (Ctrl+K)">
                <NovaIcon name="search" :size="16" />
                <span class="nv-search-text">Cari menu…</span>
                <kbd class="nv-kbd">Ctrl K</kbd>
            </button>

            <nav ref="navEl" class="nv-nav has-pill">
                <span ref="pillEl" class="nv-pill" aria-hidden="true"></span>
                <div v-for="g in groups" :key="g.id" class="nv-group" :data-open="isOpen(g) ? 'true' : 'false'">
                    <button
                        type="button"
                        class="nv-group-title"
                        @click="toggleGroup(g)"
                        :aria-expanded="isOpen(g)"
                        :aria-controls="'nv-g-' + g.id"
                    >
                        <span>{{ g.title }}</span>
                        <NovaIcon name="chevron" :size="14" />
                    </button>
                    <div :id="'nv-g-' + g.id" class="nv-items">
                        <Link
                            v-for="it in g.items"
                            :key="it.route"
                            :href="safeRoute(it.route)"
                            class="nv-item"
                            :aria-current="isCurrent(it) ? 'page' : undefined"
                            :title="rail ? it.label : undefined"
                            :aria-label="rail ? it.label + (it.count ? `, ${it.count} baru` : '') : undefined"
                        >
                            <NovaIcon :name="it.icon" />
                            <span class="nv-label">{{ it.label }}</span>
                            <span v-if="it.count" class="nv-count" :data-tone="it.tone">{{ it.count > 99 ? '99+' : it.count }}</span>
                            <span v-if="it.count && rail" class="nv-dot" aria-hidden="true"></span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="nv-side-foot">
                <Link :href="safeRoute('profile.edit')" class="nv-item" :aria-current="crumb?.label === 'Profil' ? 'page' : undefined" :title="rail ? 'Profil' : undefined">
                    <span class="nv-avatar" style="width:24px;height:24px;font-size:10px">
                        <img v-if="user.avatar" :src="'/storage/' + user.avatar" alt="" />
                        <template v-else>{{ initials }}</template>
                    </span>
                    <span class="nv-label">{{ user.name }}</span>
                </Link>
            </div>
        </aside>

        <div class="nv-body">
            <header ref="topbarEl" class="nv-topbar">
                <span ref="progressEl" class="nv-progress" aria-hidden="true"></span>
                <button
                    ref="menuBtnEl"
                    type="button"
                    class="nv-icon-btn nv-menu-btn"
                    @click="openDrawer"
                    aria-label="Buka menu"
                    aria-controls="nv-side"
                    :aria-expanded="drawer"
                ><NovaIcon name="menu" :size="20" /></button>

                <nav v-if="crumb" class="nv-crumbs" aria-label="Breadcrumb">
                    <span>{{ crumb.group }}</span>
                    <NovaIcon name="chevronRight" :size="14" />
                    <b aria-current="page">{{ crumb.label }}</b>
                </nav>

                <button type="button" class="nv-cmd" @click="openPalette">
                    <NovaIcon name="search" :size="16" />
                    <span>{{ access.isAnggotaSintel.value ? 'Cari berkas SC, SP Jaga…' : 'Cari menu, surat, personel…' }}</span>
                    <kbd class="nv-kbd">Ctrl K</kbd>
                </button>

                <button
                    type="button"
                    class="nv-icon-btn"
                    @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                    :aria-label="theme === 'dark' ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'"
                    :title="theme === 'dark' ? 'Tema terang' : 'Tema gelap'"
                ><NovaIcon :name="theme === 'dark' ? 'sun' : 'moon'" /></button>

                <Link :href="safeRoute('notifications.index')" class="nv-icon-btn" :aria-label="unreadLabel" :title="unreadLabel">
                    <NovaIcon name="bell" />
                    <span v-if="unread" class="nv-badge" aria-hidden="true">{{ unread > 9 ? '9+' : unread }}</span>
                </Link>
                <span class="sr-only" aria-live="polite">{{ unread ? unreadLabel : '' }}</span>

                <div ref="profileEl" style="position:relative">
                    <button
                        type="button"
                        class="nv-icon-btn"
                        style="width:auto;padding:0 4px"
                        @click="profileOpen = !profileOpen"
                        :aria-expanded="profileOpen"
                        aria-haspopup="menu"
                        aria-label="Menu akun"
                    >
                        <span class="nv-avatar">
                            <img v-if="user.avatar" :src="'/storage/' + user.avatar" alt="" />
                            <template v-else>{{ initials }}</template>
                        </span>
                    </button>
                    <div v-if="profileOpen" class="nv-menu" role="menu">
                        <div class="nv-menu-head">
                            <b>{{ user.name }}</b>
                            <span class="nv-mono">NRP {{ user.nrp || '--------' }}</span>
                        </div>
                        <div class="nv-seg" role="group" aria-label="Tema">
                            <button type="button" :aria-pressed="themePref === 'light'" @click="setTheme('light')">Terang</button>
                            <button type="button" :aria-pressed="themePref === 'dark'" @click="setTheme('dark')">Gelap</button>
                            <button type="button" :aria-pressed="themePref === 'system'" @click="setTheme('system')">Sistem</button>
                        </div>
                        <Link :href="safeRoute('profile.edit')" role="menuitem"><NovaIcon name="user" :size="16" />Profil</Link>
                        <button type="button" role="menuitem" @click="backToClassic"><NovaIcon name="undo" :size="16" />Kembali ke tampilan lama</button>
                        <hr />
                        <Link :href="safeRoute('logout')" method="post" as="button" role="menuitem" class="nv-danger"><NovaIcon name="logout" :size="16" />Keluar</Link>
                    </div>
                </div>
            </header>

            <main id="nv-main" ref="mainEl" class="nv-main" tabindex="-1">
                <slot />
            </main>
        </div>

        <GlobalCommandPalette ref="paletteRef" />
    </div>
</template>
