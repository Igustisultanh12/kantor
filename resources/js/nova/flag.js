// Feature flag `ui_nova` (PRD bagian 12): Admin menyalakannya di Pengaturan,
// dan setiap pengguna bisa mencoba / kembali ke tampilan lama di perangkatnya.
//   ?nova=1  → pakai Nova di perangkat ini
//   ?nova=0  → pakai tampilan lama di perangkat ini
//   ?nova=auto → ikuti Pengaturan admin lagi
const KEY = 'sinden.ui.nova';

export function readNovaPreference() {
    try {
        const q = new URLSearchParams(window.location.search).get('nova');
        if (q === '1' || q === '0') localStorage.setItem(KEY, q);
        if (q === 'auto') localStorage.removeItem(KEY);
        return localStorage.getItem(KEY);
    } catch (e) {
        return null;
    }
}

export function setNovaPreference(v) {
    try {
        if (v === null) localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, v);
    } catch (e) { /* mode privat */ }
}

export function isNovaOn(settings) {
    const pref = readNovaPreference();
    if (pref === '1') return true;
    if (pref === '0') return false;
    return String(settings?.ui_nova ?? '0') === '1';
}
