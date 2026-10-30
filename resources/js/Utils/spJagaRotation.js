/**
 * Utilitas Rotasi Jadwal SP Jaga Siaga Sintel (mengikuti alur SP Jaga resmi Feb–Okt 2026)
 *
 * Perwira (7 orang atau lebih): satu siklus = 1 putaran penuh (semua perwira berurutan)
 * disusul N putaran yang masing-masing meng-off-kan satu perwira secara bergiliran
 * (perwira ke-1, ke-2, ... ke-N). Untuk 7 perwira = 7 + 7 x 6 = 49 hari, rata-rata jaga tiap 7 hari.
 * Perwira 6 orang atau kurang: giliran berurutan tanpa putaran off (seperti SP Feb/Mar 2026).
 *
 * Rotasi bersambung antar-bulan: posisi siklus dilanjutkan dari SP sebelumnya.
 * Divisi anggota: giliran berurutan Div 1..N, bersambung antar-bulan.
 */

export const mod = (n, m) => ((n % m) + m) % m;

// 5 Agustus 2026 = awal putaran penuh pada SP resmi; acuan bila belum ada SP sebelumnya
export const PATTERN_REF_DATE = '2026-08-05';

const toUtc = (dateStr) => {
    const [y, m, d] = String(dateStr).substring(0, 10).split('-').map(v => parseInt(v, 10));
    return Date.UTC(y, m - 1, d);
};

export const daysBetween = (fromDate, toDate) => Math.round((toUtc(toDate) - toUtc(fromDate)) / 86400000);

const normalize = (v) => String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();

const findPerson = (perwiras, person) => {
    let idx = perwiras.findIndex(p => person?.nrp && normalize(p.nrp) === normalize(person.nrp));
    if (idx < 0) idx = perwiras.findIndex(p => person?.nama && normalize(p.nama) === normalize(person.nama));
    return idx;
};

/** Urutan index perwira dalam satu siklus penuh. */
export const perwiraPattern = (count) => {
    const all = [...Array(count).keys()];
    if (count < 7) return all;
    const seq = [...all];
    for (let k = 0; k < count; k++) seq.push(...all.filter(i => i !== k));
    return seq;
};

/**
 * Cari posisi siklus yang paling cocok dengan jadwal yang sudah ada
 * (days = [['YYYY-MM-DD', index], ...]), lalu kembalikan posisi siklus pada targetDate.
 * Hari-hari terakhir diberi bobot lebih besar agar sambungan ke bulan berikutnya tepat.
 */
const matchPhase = (pattern, startDate, days, targetDate) => {
    const L = pattern.length;
    let best = -1;
    let bestPhase = null;
    for (let p = 0; p < L; p++) {
        let score = 0;
        for (const [date, idx] of days) {
            const off = daysBetween(startDate, date);
            if (pattern[mod(p + off, L)] === idx) score += 1 + off;
        }
        if (score > best) {
            best = score;
            bestPhase = p;
        }
    }
    return bestPhase === null ? null : mod(bestPhase + daysBetween(startDate, targetDate), L);
};

/**
 * Posisi siklus perwira pada startDate.
 * 1) Personel sama dengan SP sebelumnya: cocokkan seluruh jadwal SP sebelumnya lalu lanjutkan.
 * 2) Jumlah/susunan perwira berubah: mulai dari perwira setelah yang jaga di hari terakhir.
 * 3) Belum ada SP sebelumnya: hitung dari tanggal acuan siklus resmi.
 */
export const resolvePerwiraPhase = (anchor, perwiras, startDate) => {
    const count = perwiras.length;
    if (!count || !startDate) return 0;
    const pattern = perwiraPattern(count);
    const L = pattern.length;

    const prevList = anchor?.perwiras || [];
    if (anchor?.perwira_days?.length && anchor.start_date && prevList.length === count) {
        const map = prevList.map(p => findPerson(perwiras, p));
        if (map.every(i => i >= 0) && new Set(map).size === count) {
            const days = anchor.perwira_days.map(([date, i]) => [date, map[i]]);
            const phase = matchPhase(pattern, anchor.start_date, days, startDate);
            if (phase !== null) return phase;
        }
    }

    if (anchor?.perwira && anchor.end_date) {
        let base = findPerson(perwiras, anchor.perwira);
        if (base < 0) base = mod(anchor.perwira.index, count);
        // Posisi base pada putaran penuh (index awal pola) lalu maju sesuai selisih hari
        return mod(base + daysBetween(anchor.end_date, startDate) - 1, L);
    }

    return mod(daysBetween(PATTERN_REF_DATE, startDate), L);
};

/** Posisi siklus dari tabel yang sedang diedit (dipakai Edit bila tidak ada SP sebelumnya). */
export const phaseFromTable = (perwiras, year, month, startDate) => {
    const pattern = perwiraPattern(perwiras.length);
    const mm = String(month).padStart(2, '0');
    const days = [];
    perwiras.forEach((p, i) => {
        for (let k = 1; k <= 7; k++) {
            const d = parseInt(p[`tgl_${k}`], 10);
            if (d >= 1 && d <= 31) days.push([`${year}-${mm}-${String(d).padStart(2, '0')}`, i]);
        }
    });
    if (!days.length) return null;
    return matchPhase(pattern, `${year}-${mm}-01`, days, startDate);
};

/** Majukan posisi siklus ke giliran berikutnya milik perwira `idx` (pilihan manual "jaga hari pertama"). */
export const phaseForPerwira = (count, currentPhase, idx) => {
    const pattern = perwiraPattern(count);
    for (let t = 0; t < pattern.length; t++) {
        if (pattern[mod(currentPhase + t, pattern.length)] === idx) return mod(currentPhase + t, pattern.length);
    }
    return currentPhase;
};

export const perwiraAtPhase = (count, phase) => {
    const pattern = perwiraPattern(count);
    return pattern.length ? pattern[mod(phase, pattern.length)] : 0;
};

/**
 * Susun tabel tanggal perwira format lampiran resmi: kolom baru setiap giliran kembali ke atas daftar,
 * sehingga perwira yang off pada suatu putaran bernilai '-' di kolom tersebut.
 * @returns {{ rows: string[][], columns: number }} rows[i] berisi 7 nilai tgl_1..tgl_7
 */
export const buildPerwiraTable = (count, phase, startDay, endDay) => {
    const rows = Array.from({ length: count }, () => Array(7).fill('-'));
    if (!count) return { rows, columns: 0 };
    const pattern = perwiraPattern(count);
    let col = 0;
    let prev = -1;
    for (let d = startDay; d <= endDay; d++) {
        const idx = pattern[mod(phase + d - startDay, pattern.length)];
        if (prev >= 0 && idx <= prev) col++;
        if (col < 7) rows[idx][col] = String(d).padStart(2, '0');
        prev = idx;
    }
    return { rows, columns: col + 1 };
};

/** Index divisi yang jaga pada tanggal startDate, melanjutkan anchor dari SP sebelumnya. */
export const resolveDivisiStart = (anchor, divisiCount, startDate) => {
    if (!divisiCount || !anchor?.divisi || !anchor.end_date || !startDate) return null;
    return mod(anchor.divisi.index + daysBetween(anchor.end_date, startDate) - 1, divisiCount);
};

/** Acuan divisi bila belum ada SP sebelumnya: 1 Agustus 2026 dijaga Divisi 4 (SP resmi Agustus). */
export const defaultDivisiStart = (divisiCount, startDate) =>
    divisiCount ? mod(3 + daysBetween('2026-08-01', startDate), divisiCount) : 0;

/** Teks tanggal jaga tiap divisi, contoh "01, 06, 11, 16, 21, 26, 31 MARET 2026". */
export const buildDivisiTexts = (count, startIdx, startDay, endDay, monthNameUpper, year) => {
    const dates = Array.from({ length: count }, () => []);
    if (!count) return [];
    let idx = mod(startIdx, count);
    for (let d = startDay; d <= endDay; d++) {
        dates[idx].push(String(d).padStart(2, '0'));
        idx = (idx + 1) % count;
    }
    return dates.map(list => `${list.join(', ')} ${monthNameUpper} ${year}`);
};

/** Index divisi yang memegang tanggal `day` berdasarkan teks tanggal (fallback saat tidak ada anchor). */
export const findDivisiOnDay = (anggotas, day) => {
    const target = parseInt(day, 10);
    return anggotas.findIndex(a => (String(a.tanggal_list_text || '').match(/\b\d{1,2}\b/g) || [])
        .some(v => parseInt(v, 10) === target));
};
