// Aturan jenis nomor identitas SKHPP — dipakai form (Buat/Revisi) dan pratinjau dokumen.
// Penyimpanan tetap di kolom lama: NRP/NIP → `pangkat_korps_nrp` ("Kapten Laut (K/W) NRP 22608/P"),
// NIM/NIK → `nik`. Kolom `jenis_identitas` hanya mencatat pilihan dropdown-nya.
// Logika yang sama ada di resources/views/pdf/skhpp.blade.php.

export const JENIS_IDENTITAS = ['NRP', 'NIP', 'NIM', 'NIK'];

export const berpangkat = (jenis) => jenis === 'NRP' || jenis === 'NIP';

/** Jenis identitas sebuah SKHPP; data lama (tanpa jenis_identitas) ditebak dari kolom yang terisi. */
export const jenisIdentitas = (skhpp) =>
    skhpp?.jenis_identitas || (skhpp?.pangkat_korps_nrp ? 'NRP' : 'NIK');

/** Label baris "b." pada dokumen. */
export const labelIdentitas = (jenis) =>
    ({ NRP: 'Pangkat/Korp/NRP', NIP: 'Pangkat/Gol/NIP', NIM: 'NIM', NIK: 'NIK' })[jenis] || 'NIK';

/** Label kolom identitas pengikut (form & lampiran), mengikuti jenis identitas pemohon. */
export const labelPengikut = (jenis) =>
    ({ NRP: 'Pangkat/Korp/NRP', NIP: 'Pangkat/Gol/NIP', NIM: 'NIM', NIK: 'NIK' })[jenis] || 'NIK';

/** Awalan poin 3 (sama dengan Skhpp::AWALAN_PERUNTUKAN); operator cukup menulis kegiatannya. */
export const AWALAN_PERUNTUKAN = 'dalam rangka melengkapi administrasi ';

/** Kegiatan singkat dari peruntukan lengkap berawalan baku ('' bila tidak berpola). */
export const kegiatanDariPeruntukan = (peruntukan) => {
    const p = String(peruntukan || '').trim();
    if (!p.toLowerCase().startsWith(AWALAN_PERUNTUKAN.trim())) return '';
    return p.slice(AWALAN_PERUNTUKAN.trim().length).trim().replace(/\.+$/, '');
};

/** Kalimat poin 3 dari kegiatan singkat: huruf pertama kegiatan dikecilkan ("seleksi Diktukpa ..."). */
export const peruntukanDariKegiatan = (kegiatan) => {
    const k = String(kegiatan || '').trim().replace(/\.+$/, '');
    return k ? AWALAN_PERUNTUKAN + k.charAt(0).toLowerCase() + k.slice(1) : '';
};

/** Judul lampiran pengikut (sama dengan Skhpp::judulLampiran() di PHP). */
export const judulLampiran = (satuan, kegiatan, peruntukan = '', perusahaan = false) => {
    const s = String(satuan || '').trim();
    const k = String(kegiatan || '').trim() || kegiatanDariPeruntukan(peruntukan);
    // SKHPP-P (perusahaan): tanpa "PERSONEL <satuan>".
    if (perusahaan && k) return `DAFTAR NAMA YANG MELAKSANAKAN SC ${k}`.toUpperCase();
    if (!s || !k) return 'DAFTAR NAMA-NAMA ANGGOTA PENGIKUT';
    return `DAFTAR NAMA PERSONEL ${s} YANG MELAKSANAKAN SC ${k}`.toUpperCase();
};

/** Satuan dari "Surat Dari": buang awalan jabatan "Dan" (Danpomal Kodaeral V -> Pomal Kodaeral V). */
export const satuanDariPengantar = (dari) => {
    const t = String(dari || '').trim().replace(/^dan\s*/i, '');
    return t ? t.charAt(0).toUpperCase() + t.slice(1) : '';
};

/** Jumlah pengikut untuk poin 2h (0 = baris 2h tidak ditampilkan). */
export const jumlahPengikut = (skhpp) =>
    skhpp?.has_pengikut ? (skhpp.members?.length || 0) : 0;

export const teksPengikut = (n) => `${n} orang (daftar nama terlampir)`;

/** Gabungkan pangkat + jenis + nomor menjadi isi kolom pangkat_korps_nrp. */
export const gabungPangkat = (pangkat, jenis, nomor) =>
    [pangkat, jenis, nomor].map((v) => String(v || '').trim()).filter(Boolean).join(' ');

/** Kebalikan gabungPangkat, untuk mengisi form Revisi dari data tersimpan. */
export function uraiIdentitas(skhpp) {
    const teks = skhpp?.pangkat_korps_nrp || '';
    if (!skhpp?.jenis_identitas && !teks) {
        return { jenis: 'NIK', pangkat: '', nomor: skhpp?.nik || '' };
    }
    let jenis = skhpp.jenis_identitas || (skhpp.kategori_personel === 'militer' ? 'NRP' : 'NIP');
    if (!berpangkat(jenis)) {
        return { jenis, pangkat: '', nomor: skhpp.nik || '' };
    }
    const m = teks.match(/^(.*?)\s*\b(NRP|NIP)\b[.:]?\s*(.*)$/i);
    if (!m) return { jenis, pangkat: teks, nomor: '' };
    if (!skhpp.jenis_identitas) jenis = m[2].toUpperCase();
    return { jenis, pangkat: m[1].trim(), nomor: m[3].trim() };
}
