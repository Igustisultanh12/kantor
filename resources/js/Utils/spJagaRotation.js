/**
 * Utilitas Rotasi Jadwal SP Jaga Siaga Sintel
 * Perwira & divisi anggota jaga bergiliran berurutan sesuai urutan daftar, dan giliran
 * terus bersambung antar-bulan: tanggal 1 dijaga oleh orang/divisi setelah yang jaga
 * pada hari terakhir SP sebelumnya (bukan selalu dimulai dari orang yang sama).
 */

export const mod = (n, m) => ((n % m) + m) % m;

const toUtc = (dateStr) => {
    const [y, m, d] = String(dateStr).substring(0, 10).split('-').map(v => parseInt(v, 10));
    return Date.UTC(y, m - 1, d);
};

export const daysBetween = (fromDate, toDate) => Math.round((toUtc(toDate) - toUtc(fromDate)) / 86400000);

const normalize = (v) => String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();

/**
 * Index perwira yang jaga pada tanggal startDate, melanjutkan anchor dari SP sebelumnya.
 * anchor.perwira = { index, nama, nrp } = giliran pada hari setelah anchor.end_date.
 * Orang dicari berdasarkan NRP/nama agar tetap benar walau urutan daftar berubah.
 */
export const resolvePerwiraStart = (anchor, perwiras, startDate) => {
    const n = perwiras.length;
    if (!n || !anchor?.perwira || !anchor.end_date || !startDate) return null;
    const a = anchor.perwira;
    let base = perwiras.findIndex(p => a.nrp && normalize(p.nrp) === normalize(a.nrp));
    if (base < 0) base = perwiras.findIndex(p => a.nama && normalize(p.nama) === normalize(a.nama));
    if (base < 0) base = a.index;
    return mod(base + daysBetween(anchor.end_date, startDate) - 1, n);
};

/** Index divisi yang jaga pada tanggal startDate, melanjutkan anchor dari SP sebelumnya. */
export const resolveDivisiStart = (anchor, divisiCount, startDate) => {
    if (!divisiCount || !anchor?.divisi || !anchor.end_date || !startDate) return null;
    return mod(anchor.divisi.index + daysBetween(anchor.end_date, startDate) - 1, divisiCount);
};

/**
 * Susun tabel tanggal perwira format lampiran resmi: tiap kolom = satu putaran daftar.
 * Baris sebelum perwira pembuka bernilai '-' di kolom pertama (contoh: Februari 2026).
 * @returns {{ rows: string[][], columns: number }} rows[i] berisi 7 nilai tgl_1..tgl_7
 */
export const buildPerwiraTable = (count, startIdx, startDay, endDay) => {
    const rows = Array.from({ length: count }, () => Array(7).fill('-'));
    if (!count) return { rows, columns: 0 };
    let col = 0;
    let idx = mod(startIdx, count);
    for (let d = startDay; d <= endDay; d++) {
        if (d > startDay && idx === 0) col++;
        if (col < 7) rows[idx][col] = String(d).padStart(2, '0');
        idx = (idx + 1) % count;
    }
    return { rows, columns: col + 1 };
};

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

/** Index perwira yang memegang tanggal `day` di tabel yang sudah ada (fallback saat tidak ada anchor). */
export const findPerwiraOnDay = (perwiras, day) => {
    const target = parseInt(day, 10);
    return perwiras.findIndex(p => [1, 2, 3, 4, 5, 6, 7].some(i => parseInt(p[`tgl_${i}`], 10) === target));
};

/** Index divisi yang memegang tanggal `day` berdasarkan teks tanggal (fallback saat tidak ada anchor). */
export const findDivisiOnDay = (anggotas, day) => {
    const target = parseInt(day, 10);
    return anggotas.findIndex(a => (String(a.tanggal_list_text || '').match(/\b\d{1,2}\b/g) || [])
        .some(v => parseInt(v, 10) === target));
};
