// Pengganti alert()/confirm() bawaan browser dengan SweetAlert2 (tampilan seragam SISINDEN).
import Swal from 'sweetalert2';

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true,
});

const clean = (msg) => String(msg ?? '').replace(/^\s*(LAPOR!|Lapor!)\s*/i, '').trim();

/** Pesan informasi. Sukses tampil sebagai toast; gagal/peringatan sebagai dialog. */
export function notify(message, icon = null) {
    const text = clean(message);
    const kind = icon
        || (/(gagal|error|tidak dapat|tidak valid)/i.test(text) ? 'error'
            : /(berhasil|telah|diamankan|disinkronkan|dimusnahkan|diperbarui|tersimpan)/i.test(text) ? 'success'
                : /(harap|belum|maksimal|perhatian|peringatan)/i.test(text) ? 'warning' : 'info');
    if (kind === 'success') {
        return toast.fire({ icon: 'success', title: text });
    }
    return Swal.fire({ icon: kind, title: kind === 'error' ? 'Gagal' : kind === 'warning' ? 'Perhatian' : 'Informasi', text });
}

/** Konfirmasi. Resolve true bila pengguna menekan tombol konfirmasi. */
export async function ask(message, { confirmText = null, danger = null } = {}) {
    const text = clean(message);
    const isDanger = danger ?? /(hapus|musnah|permanen|peringatan|batalkan|tolak)/i.test(text);
    const res = await Swal.fire({
        icon: isDanger ? 'warning' : 'question',
        title: isDanger ? 'Yakin melanjutkan?' : 'Konfirmasi',
        text,
        showCancelButton: true,
        confirmButtonText: confirmText || (isDanger ? 'Ya, lanjutkan' : 'Ya'),
        cancelButtonText: 'Batal',
        confirmButtonColor: isDanger ? '#e11d48' : '#2563eb',
        reverseButtons: true,
        focusCancel: isDanger,
    });
    return res.isConfirmed;
}
