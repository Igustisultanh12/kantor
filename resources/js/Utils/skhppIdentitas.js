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
    ({ NRP: 'Pangkat/Korps/NRP', NIP: 'Pangkat/Gol/NIP', NIM: 'NIM', NIK: 'NIK' })[jenis] || 'NIK';

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
