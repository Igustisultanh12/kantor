# SISINDEN Android v1.2.0 (versionCode 9)

Tampilan baru perpaduan **Elder Friendly Livin'** (ramah lansia) dan **iOS 26 Liquid Glass**, Daftar Jaga pribadi, pengingat H-1, dan halaman masuk baru.

## Yang baru

- **Desain "Livin Glass"** — biru Livin, huruf Figtree yang lebih besar (isi 16sp, judul 20sp tebal), ikon selalu berlabel, ubin fitur bergradien, kartu putih bergaris tegas; mode gelap memakai permukaan kaca tembus pandang dengan cahaya lembut.
- **Tab bar mengambang** berbentuk kapsul kaca; tombol **Presensi** menonjol di tengah.
- **Top bar biru** dengan judul di tengah dan tombol **"Kembali" berlabel**.
- **Beranda baru** — kartu akun (sapaan, pangkat/jabatan, tanggal) dengan bar presensi hari ini, "Fitur Utama Anda", Daftar Jaga Anda, Ringkasan, dan "Fitur Lainnya" berkategori.
- **Daftar Jaga pribadi** — kartu per bulan (bulan ini & bulan lalu) yang hanya menampilkan **tanggal jaga milik Anda** beserta peran dan statusnya (Selesai/Hari ini/Besok). Susunan lengkap seluruh personel tetap tersedia lewat tombol **Lihat PDF** dan **Unduh PDF** (PDF resmi server).
- **Pengingat jaga H-1** — notifikasi otomatis pukul 07.00 sehari sebelum tanggal jaga; tetap berjalan saat aplikasi ditutup dan dipasang ulang setelah ponsel dinyalakan ulang. Pengingat dihapus saat keluar akun.
- **Halaman masuk baru** seperti web SISINDEN — foto latar dari pengaturan Admin (lapisan gelap), logo mengambang halus, kartu kaca gelap, tombol oranye.
- **Ukuran teks** — pilihan Normal / Besar / Sangat besar di Pengaturan.
- **Cloudflare Turnstile** pada Masuk & Aktivasi akun, dengan animasi bertahap: *Memeriksa keamanan sistem → Memeriksa sesi login → Masuk ke beranda* (aktivasi: *→ Memverifikasi token aktivasi → Mengaktifkan akun*). Token diverifikasi server; secret key hanya ada di `.env` server.
- **Ikon aplikasi baru** dari `public/images/logo_sinden_app_icon.png` (adaptif, bulat, dan ikon lama).

## Wajib di server sebelum/ saat rilis

1. Deploy `main` lalu tambahkan ke `.env`: `TURNSTILE_SECRET_KEY=<secret dari Cloudflare>` (dan opsional `TURNSTILE_SITE_KEY`), lalu `php artisan config:clear`.
2. Pastikan hostname `sisinden.my.id` terdaftar di widget Turnstile (Cloudflare → Turnstile → Hostname Management).
3. Setelah secret diisi, aplikasi versi lama (≤ 1.1.x) tidak dapat masuk karena tidak mengirim token Turnstile — minta personel memperbarui aplikasi.

## Perubahan

- Pengaturan **alamat server dihapus** (halaman masuk & Pengaturan); aplikasi selalu terhubung ke server resmi `https://sisinden.my.id`.
- Ikon notifikasi monokrom agar tampil jelas di status bar.

## Status pengujian

- Unit test baru: penyaringan jadwal pribadi (tanggal personel lain tidak tampil), status relatif tanggal, dan konfigurasi latar login.
- Build, unit test, dan lint dijalankan oleh GitHub Actions pada setiap push ke `main`.

---

# SISINDEN Android v1.1.0 (versionCode 7)

Rilis desain ulang total aplikasi Android SISINDEN, dengan hak akses mengikuti pengaturan Admin, **Daftar Jaga** di beranda, dan pratinjau/unduh PDF resmi.

> ⚠️ **Wajib sebelum dipakai:** server SISINDEN harus diperbarui ke branch yang sama dan menjalankan `php artisan migrate`. Aplikasi ini memakai token Sanctum dan endpoint baru (`/api/mobile/me`, `/dashboard`, `/duty-roster`, `/pdf/*`).
>
> Pemasangan di atas v1.0.x: versi lama ditandatangani kunci debug CI yang berganti-ganti, sehingga Android dapat menolak pembaruan. Uninstall versi lama terlebih dahulu bila muncul pesan "aplikasi tidak terpasang".

## Yang baru

- **Tampilan baru menyeluruh** — palet "Samudra" (biru angkatan laut + teal + amber), tipografi berjenjang, kartu konsisten, mode terang/gelap/ikut sistem, animasi ringan, skeleton loading, empty/error state yang jelas, dan halaman "Akses ditolak".
- **Beranda sebagai pusat aktivitas** — sapaan sesuai waktu, tanggal, pangkat/jabatan, status presensi hari ini, ringkasan modul yang diizinkan, dan akses cepat.
- **Daftar Jaga** — jaga hari ini (penanda bila Anda bertugas), jadwal Anda berikutnya, jadwal mendatang, tab Jaga lengkap per bulan dengan filter "Jadwal saya"/"Mendatang", pencarian personel, detail per tanggal, dan PDF SP Jaga resmi. Data diambil langsung dari SP Jaga yang diterbitkan di web.
- **Menu dinamis sesuai hak akses** — tab, menu, tombol tambah/ubah/hapus/setujui, dan PDF hanya muncul bila server memberi izin. Perubahan hak akses oleh Admin tersinkron saat aplikasi dibuka atau beranda di-refresh.
- **PDF lengkap** — buat → pratinjau (zoom cubit, ketuk ganda, navigasi halaman) → simpan ke perangkat → buka/bagikan. Tersedia untuk SP Jaga, Buku Kas, Rekap Agenda Surat (filter kategori & tanggal), SKHPP, dan Rekening Koran Komandan — memakai template resmi server.
- **Modul baru di Android:** Rekening Komandan, Kelola Pengguna (admin), Profil.
- **SKHPP** — formulir lengkap seperti web (foto, pengikut), persetujuan Komandan dengan derajat surat, pengembalian dengan catatan revisi.
- **Tanda Tangan** — pengesahan membubuhkan stempel QR seperti di web; pengembalian dengan alasan.
- **Pelanggaran** — formulir sesuai web, riwayat perkembangan kasus, pembaruan status.
- **Presensi** — lokasi GPS nyata (opsional, izin diminta saat dibutuhkan), riwayat 30 hari.

## Perbaikan bug

- Agenda surat, SKHPP, dan pelanggaran tidak lagi menampilkan/menyimpan data kosong akibat kolom yang tidak cocok dengan server.
- Pengajuan SKHPP dari aplikasi kini benar-benar tersimpan (sebelumnya selalu gagal di server).
- Presensi tidak lagi mengirim koordinat palsu; tanggal riwayat tidak lagi mundur satu hari.
- Menu Kas tidak lagi tampil untuk semua personel; beranda tidak lagi memuat saldo kas bagi yang tidak berhak.
- Error jaringan tidak lagi ditampilkan sebagai angka 0; ada tombol coba lagi.
- Sesi kedaluwarsa/akun dinonaktifkan kini mengembalikan pengguna ke halaman masuk dengan pesan yang jelas.
- Permintaan ganda (ketuk berulang) dicegah pada semua aksi simpan.

## Keamanan

- Seluruh API mobile kini mewajibkan token dan memeriksa hak akses di server (sebelumnya terbuka tanpa autentikasi).
- Aktivasi akun wajib token dari Admin; log server tidak lagi publik; login tidak membocorkan keberadaan akun.
- Token tidak lagi tercatat di log; backup aplikasi dimatikan; token hanya dikirim ke host server SISINDEN; alamat server wajib HTTPS.

## Kompatibilitas

- Android 8.0 (API 26) – Android 14 (API 34).
- Ukuran layar ponsel hingga tablet; mendukung mode gelap dan ukuran huruf sistem.
- Izin: Internet, Notifikasi (Android 13+), Lokasi (opsional, hanya saat presensi).

## Status pengujian

- Backend: 19 feature test keamanan & kontrak API mobile lulus (81 asersi).
- Android: unit test kontrak API (fixture dari backend asli) & utilitas, build debug/release, dan lint dijalankan oleh GitHub Actions. Uji manual di perangkat mengikuti `docs/ROLE_PERMISSION_MATRIX.md` bagian 3.

Checksum SHA-256 APK tersedia di berkas `SHA256SUMS.txt` pada rilis ini.
