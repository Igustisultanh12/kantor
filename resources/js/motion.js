// Lapisan animasi SISINDEN (web) di atas Framer Motion.
// Dipakai lewat entry `framer-motion/dom` — API DOM murni (tanpa React), jadi aman untuk Vue/Inertia.
//
// Prinsip (ui-ux-pro-max): gerak bermakna & hemat, spring untuk interaksi, exit lebih
// cepat dari enter, tanpa efek mantul di tabel data, animasi bisa dibatalkan, dan
// SELALU hormati prefers-reduced-motion (langsung tampilkan keadaan akhir).
//
// Catatan penting: setiap animasi transform dibersihkan setelah selesai, karena
// `transform` yang tertinggal pada elemen induk membuat modal `position: fixed`
// di dalam halaman ikut bergeser.
import { animate, stagger, inView, press, hover } from 'framer-motion/dom';

const mq = typeof window !== 'undefined' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
// Gerak dimatikan bila pengguna memilih "kurangi gerak" di sistem, ATAU Admin
// mematikan "Animasi Interaktif" di Pengaturan (data-motion="off" di <html>).
export const reduced = () =>
    Boolean(mq?.matches) || (typeof document !== 'undefined' && document.documentElement.dataset.motion === 'off');

/** Terapkan pengaturan Admin ke <html> (dipanggil layout sebelum animasi berjalan). */
export function applyMotionSetting(enabled) {
    if (typeof document === 'undefined') return;
    if (enabled) delete document.documentElement.dataset.motion;
    else document.documentElement.dataset.motion = 'off';
}

// Spring bergaya iOS: dinyatakan sebagai durasi + bounce (mirip SwiftUI
// .spring(response:dampingFraction:)) — lembut, nyaris tanpa mantul, tidak "kaku".
export const T = {
    enter: { type: 'spring', duration: 0.6, bounce: 0.12 },   // muncul (seperti sheet iOS)
    soft: { type: 'spring', duration: 0.5, bounce: 0.18 },    // hover / lift
    snap: { type: 'spring', duration: 0.32, bounce: 0.28 },   // tekan tombol (membal halus)
    smooth: { type: 'spring', duration: 0.7, bounce: 0 },     // gerak besar tanpa mantul
    exit: { duration: 0.2, ease: [0.32, 0.72, 0, 1] },        // kurva keluar ala iOS
};

const clear = (els) => {
    (Array.isArray(els) ? els : [els]).forEach((el) => {
        if (!el?.style) return;
        el.style.removeProperty('transform');
        el.style.removeProperty('opacity');
        el.style.removeProperty('filter');
    });
};

// Blur-in khas iOS: elemen datang dari sedikit buram → tajam.
const BLUR_IN = { filter: ['blur(8px)', 'blur(0px)'] };

/* ---------------- Transisi halaman Inertia ---------------- */

let pageAnim = null;

/**
 * Animasikan masuknya halaman: kontainer fade-up lalu bagian-bagian utama
 * halaman muncul bertahap. Hanya opacity/transform — tidak menyentuh state.
 */
export function animatePageEnter(container) {
    if (!container || reduced()) return;
    pageAnim?.cancel?.();

    // Satu anak (div pembungkus halaman) → stagger isinya; banyak anak → stagger langsung.
    const root = container.children.length === 1 ? container.firstElementChild : container;
    const kids = root
        ? Array.from(root.children)
            .filter((el) => el.offsetParent !== null && getComputedStyle(el).position !== 'fixed' && getComputedStyle(el).transform === 'none')
            .slice(0, 8)
        : [];
    const targets = kids.length ? kids : [container];

    pageAnim = animate(
        targets,
        { opacity: [0, 1], y: [18, 0], scale: [0.985, 1], ...BLUR_IN },
        { ...T.enter, delay: stagger(0.06), filter: { duration: 0.45, ease: [0.32, 0.72, 0, 1] } },
    );
    pageAnim.then(() => clear(targets), () => clear(targets));
}

/* ---------------- Umpan balik tekan global ---------------- */

const PRESSABLE = 'button:not(:disabled), a[href], [role="button"], input[type="submit"], input[type="button"], label[for]';
const SKIP = '[data-no-press], .leaflet-container, .swal2-container, input[type="range"], [draggable="true"], table tbody';

/**
 * Semua tombol/tautan mendapat efek "ditekan" bergaya spring tanpa perlu
 * mengubah setiap halaman. Elemen bisa menolak dengan atribut data-no-press.
 */
export function installGlobalPress() {
    if (typeof document === 'undefined') return;
    document.addEventListener('pointerdown', (e) => {
        if (reduced() || e.button !== 0) return;
        const el = e.target.closest?.(PRESSABLE);
        if (!el || el.closest(SKIP)) return;
        // Jangan menekan elemen besar (kartu/baris panjang) — terasa berlebihan.
        const r = el.getBoundingClientRect();
        if (r.width > 520 || r.height > 160) return;
        // Elemen yang sudah punya transform (mis. -translate-y-1/2) dilewati agar posisinya tidak bergeser.
        if (getComputedStyle(el).transform !== 'none') return;

        const down = animate(el, { scale: 0.95, opacity: 0.85 }, { type: 'spring', duration: 0.25, bounce: 0 });
        const up = () => {
            down.cancel?.();
            animate(el, { scale: 1, opacity: 1 }, T.snap).then(() => clear(el), () => clear(el));
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
        };
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    }, { passive: true });
}

/* ---------------- Directive opsional ---------------- */

/** v-reveal — anak elemen muncul bertahap saat terlihat di layar. */
export const vReveal = {
    mounted(el, { value = {} }) {
        if (reduced()) return;
        const kids = value.self ? [el] : Array.from(el.children);
        if (!kids.length) return;
        kids.forEach((k) => { k.style.opacity = '0'; });
        el._revealStop = inView(el, () => {
            animate(kids, { opacity: [0, 1], y: [12, 0] }, { ...T.enter, delay: stagger(value.gap ?? 0.05) })
                .then(() => clear(kids), () => clear(kids));
        }, { amount: 0.1 });
    },
    unmounted(el) { el._revealStop?.(); },
};

/** v-lift — kartu terangkat halus saat disorot. */
export const vLift = {
    mounted(el) {
        if (reduced()) return;
        el._liftStop = hover(el, (target) => {
            animate(target, { y: -3 }, T.soft);
            return () => animate(target, { y: 0 }, T.soft).then(() => clear(target));
        });
    },
    unmounted(el) { el._liftStop?.(); },
};

/** v-press — versi eksplisit dari efek tekan (untuk elemen non-tombol). */
export const vPress = {
    mounted(el, { value = 0.96 }) {
        if (reduced()) return;
        el.setAttribute('data-no-press', ''); // hindari dobel dengan handler global
        el._pressStop = press(el, (target) => {
            animate(target, { scale: value }, T.snap);
            return () => animate(target, { scale: 1 }, T.snap).then(() => clear(target));
        });
    },
    unmounted(el) { el._pressStop?.(); },
};

/** Laci menu mobile: latar memudar, panel meluncur dari kiri. */
export const drawerHooks = {
    onEnter(el, done) {
        if (reduced()) return done();
        const [scrim, panel] = [el.querySelector('[data-drawer-scrim]'), el.querySelector('[data-drawer-panel]')];
        if (scrim) animate(scrim, { opacity: [0, 1] }, { duration: 0.2 });
        if (!panel) return done();
        animate(panel, { x: ['-100%', '0%'] }, T.enter).then(() => { clear(panel); done(); }, done);
    },
    onLeave(el, done) {
        if (reduced()) return done();
        const [scrim, panel] = [el.querySelector('[data-drawer-scrim]'), el.querySelector('[data-drawer-panel]')];
        if (scrim) animate(scrim, { opacity: 0 }, T.exit);
        if (!panel) return done();
        animate(panel, { x: '-100%' }, { duration: 0.2, ease: [0.4, 0, 1, 1] }).then(done, done);
    },
};

/* =====================================================================
   INTERAKSI "HIDUP" GLOBAL — dipasang sekali oleh layout, berlaku ke semua
   halaman. Aturan aman: tidak mengubah position/overflow/layout elemen,
   transform selalu dibersihkan, dan semua mati saat reduced-motion.
   ===================================================================== */

const CARD = '.bg-white[class*="rounded-"][class*="border"], .bg-white[class*="rounded-"][class*="shadow"]';
const inFixed = (el) => {
    for (let n = el.parentElement; n && n !== document.body; n = n.parentElement) {
        const p = getComputedStyle(n).position;
        if (p === 'fixed') return true;
    }
    return false;
};

/** Sorotan kursor pada kartu (background-image, tanpa mengubah layout). */
function installCardSpotlight(root) {
    let last = null;
    const onMove = (e) => {
        const card = e.target.closest?.(CARD);
        if (last && last !== card) last.classList.remove('sinden-lit');
        if (!card || !root.contains(card)) { last = null; return; }
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${e.clientX - r.left}px`);
        card.style.setProperty('--my', `${e.clientY - r.top}px`);
        card.classList.add('sinden-card', 'sinden-lit');
        last = card;
    };
    const onLeave = () => { last?.classList.remove('sinden-lit'); last = null; };
    root.addEventListener('pointermove', onMove, { passive: true });
    root.addEventListener('pointerleave', onLeave);
    return () => { root.removeEventListener('pointermove', onMove); root.removeEventListener('pointerleave', onLeave); };
}

/** Kartu di bawah lipatan layar muncul saat di-scroll. */
function installScrollReveal(root) {
    const vh = window.innerHeight;
    const cards = Array.from(root.querySelectorAll(CARD))
        .filter((c) => c.getBoundingClientRect().top > vh && !inFixed(c))
        .slice(0, 60);
    const stops = cards.map((c) => {
        c.style.opacity = '0';
        return inView(c, () => {
            animate(c, { opacity: [0, 1], y: [28, 0], scale: [0.97, 1], ...BLUR_IN }, { ...T.enter, filter: { duration: 0.5 } })
                .then(() => clear(c), () => clear(c));
        }, { amount: 0.12 });
    });
    return () => stops.forEach((s) => s?.());
}

/** Baris tabel muncul berurutan (opacity saja — tabel data tidak boleh "mantul"). */
function animateTableRows(root) {
    const rows = Array.from(root.querySelectorAll('tbody tr')).filter((r) => !inFixed(r)).slice(0, 24);
    if (!rows.length) return;
    animate(rows, { opacity: [0, 1] }, { duration: 0.35, delay: stagger(0.025, { startDelay: 0.15 }) })
        .then(() => clear(rows), () => clear(rows));
}

/** Angka statistik besar menghitung naik dari 0. */
function countUpStats(root) {
    const re = /^\s*(\d{1,3}(?:\.\d{3})+|\d{1,7})\s*$/;
    const els = Array.from(root.querySelectorAll('h1,h2,h3,h4,p,span,div'))
        .filter((el) => el.children.length === 0 && re.test(el.textContent) && !inFixed(el))
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) >= 22)
        .slice(0, 16);
    els.forEach((el, i) => {
        const original = el.textContent;
        const dotted = original.includes('.');
        const target = parseInt(original.replace(/\D/g, ''), 10);
        if (!target || target < 2) return;
        if (!dotted && original.trim().length === 4 && target >= 1900 && target <= 2100) return; // tahun, bukan statistik
        let written = original;
        animate(0, target, {
            duration: 1.1, delay: 0.1 + i * 0.05, ease: [0.16, 1, 0.3, 1],
            onUpdate: (v) => {
                if (el.textContent !== written) return; // Vue memperbarui → berhenti menyentuh
                const n = Math.round(v);
                written = dotted ? n.toLocaleString('id-ID') : String(n);
                el.textContent = written;
            },
            onComplete: () => { if (el.textContent === written) el.textContent = original; },
        });
    });
}

/** Bar progres scroll + bayangan header saat halaman digulir. */
function installScrollProgress(header, bar, main) {
    const update = () => {
        const winMax = document.documentElement.scrollHeight - window.innerHeight;
        const mainMax = main ? main.scrollHeight - main.clientHeight : 0;
        const useMain = mainMax > 4 && main.scrollTop > 0;
        const y = useMain ? main.scrollTop : window.scrollY;
        const max = useMain ? mainMax : winMax;
        const p = max > 4 ? Math.min(1, y / max) : 0;
        if (bar) bar.style.transform = `scaleX(${p})`;
        header?.classList.toggle('is-scrolled', y > 8);
    };
    window.addEventListener('scroll', update, { passive: true });
    main?.addEventListener('scroll', update, { passive: true });
    update();
    return () => { window.removeEventListener('scroll', update); main?.removeEventListener('scroll', update); };
}

/** Latar paralaks mengikuti kursor (hanya elemen dekoratif fixed). */
function installParallax(layer) {
    if (!layer || window.matchMedia('(pointer: coarse)').matches) return () => {};
    let raf = 0;
    const onMove = (e) => {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => {
            const x = (e.clientX / window.innerWidth - 0.5) * 36;
            const y = (e.clientY / window.innerHeight - 0.5) * 28;
            animate(layer, { x, y }, { type: 'spring', stiffness: 40, damping: 18 });
        });
    };
    window.addEventListener('pointermove', onMove, { passive: true });
    return () => window.removeEventListener('pointermove', onMove);
}

/** Penanda menu aktif yang "meluncur" dari posisi menu sebelumnya (antar kunjungan). */
let lastPillY = null;
function installNavPill(nav, pill) {
    if (!nav || !pill) return;
    const active = nav.querySelector('a.font-bold');
    if (!active) { pill.style.opacity = '0'; return; }
    const y = active.offsetTop;
    const h = active.offsetHeight;
    pill.style.height = `${h}px`;
    pill.style.opacity = '1';
    if (lastPillY === null || lastPillY === y || reduced()) {
        pill.style.transform = `translateY(${y}px)`;
    } else {
        animate(pill, { y: [lastPillY, y] }, { type: 'spring', stiffness: 380, damping: 32 });
    }
    lastPillY = y;
}

/**
 * Pasang semua interaksi untuk satu kunjungan halaman.
 * Mengembalikan fungsi pembersih untuk onUnmounted.
 */
export function installLivelyUI({ main, header, progressBar, parallax, nav, navPill }) {
    installNavPill(nav, navPill);
    if (reduced() || !main) return () => {};
    const stops = [
        installCardSpotlight(main),
        installScrollProgress(header, progressBar, main),
        installParallax(parallax),
    ];
    requestAnimationFrame(() => {
        stops.push(installScrollReveal(main));
        animateTableRows(main);
        countUpStats(main);
    });
    return () => stops.forEach((s) => s?.());
}

/** Riak lingkaran di titik klik (elemen overlay terpisah — tidak menyentuh tombol). */
export function installClickBurst() {
    if (typeof document === 'undefined') return;
    document.addEventListener('pointerdown', (e) => {
        if (reduced() || e.button !== 0) return;
        const el = e.target.closest?.(PRESSABLE);
        if (!el || el.closest('.leaflet-container')) return;
        const dot = document.createElement('span');
        dot.className = 'sinden-burst';
        dot.style.left = `${e.clientX}px`;
        dot.style.top = `${e.clientY}px`;
        document.body.appendChild(dot);
        animate(dot, { scale: [0, 1], opacity: [0.45, 0] }, { duration: 0.55, ease: [0.22, 1, 0.36, 1] })
            .then(() => dot.remove(), () => dot.remove());
    }, { passive: true });
}

/** Animasi pop-in untuk popover/panel kecil (notifikasi, dropdown). */
export const popHooks = {
    onEnter(el, done) {
        if (reduced()) return done();
        animate(el, { opacity: [0, 1], scale: [0.95, 1], y: [-6, 0] }, T.snap)
            .then(() => { clear(el); done(); }, done);
    },
    onLeave(el, done) {
        if (reduced()) return done();
        animate(el, { opacity: [1, 0], scale: [1, 0.97] }, T.exit).then(done, done);
    },
};
