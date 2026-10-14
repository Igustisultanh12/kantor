# SISINDEN Android v1.5.0 (versionCode 13)

Notifikasi push seperti WhatsApp, kunci PIN setiap keluar aplikasi, dan pembaruan otomatis dari dalam aplikasi.

## Yang baru

- **Notifikasi push (Firebase Cloud Messaging)** — warta dinas, pesan Chat Dinas, dan **panggilan masuk** muncul & berdering di HP walau aplikasi sedang ditutup. Panggilan tampil layar penuh di layar kunci dengan nada dering HP dan tombol **Jawab / Tolak**; dering berhenti otomatis bila pemanggil membatalkan atau panggilan dijawab dari perangkat lain.
- **Kunci setiap keluar aplikasi** — kembali dari Home/aplikasi lain atau setelah layar mati langsung meminta **PIN / biometrik**. Membuka pemilih berkas, menyimpan/membagikan PDF, atau membuka lampiran dari dalam aplikasi tidak memicu kunci. Kunci otomatis 5 menit tanpa sentuhan tetap berlaku.
- **Pembaruan dari dalam aplikasi** — cek otomatis ke GitHub Releases saat aplikasi dibuka (tiap ±6 jam) dan di latar belakang lewat Wi-Fi (tiap ±12 jam), unduh, cocokkan SHA-256 & tanda tangan, lalu pasang. Di Android 12+ pembaruan berikutnya dapat terpasang otomatis tanpa konfirmasi; di versi lain cukup satu ketukan **Perbarui**. Menu manual: **Pengaturan → Pembaruan aplikasi**.
- **Tanda tangan rilis tetap** — mulai versi ini APK ditandatangani kunci rilis resmi, sehingga pembaruan cukup dipasang di atas versi lama **tanpa uninstall**.

## Penting saat pindah ke v1.5.0

- Versi ≤ 1.4.0 ditandatangani kunci debug yang berbeda-beda. **Uninstall sekali** versi lama, lalu pasang v1.5.0. Setelah itu tidak perlu uninstall lagi.

## Wajib di server

1. `git pull`, `php artisan migrate --force` (tabel `device_tokens`), `php artisan config:clear`, `npm run build`.
2. Buat project Firebase (gratis) → tambahkan aplikasi Android `id.my.sisinden.app` → unduh **google-services.json**; lalu **Project settings → Service accounts → Generate new private key**.
3. Web **Pengaturan → Notifikasi Push Aplikasi** → pilih kedua berkas JSON → Simpan. Service account disimpan terenkripsi dan tidak pernah dikirim ke browser/aplikasi.

## Keterbatasan

- Push tidak diterima bila aplikasi di-*force stop* atau dibatasi penghemat baterai pabrikan (Xiaomi/Oppo/Vivo: izinkan *Autostart* & tanpa batasan baterai) — sama seperti aplikasi chat lain.

---

# SISINDEN Android v1.4.0 (versionCode 12)

Keamanan bergaya M-Banking: PIN, kunci otomatis 5 menit, login biometrik, dan blokir screenshot/rekam layar.

## Yang baru

- **PIN 6 angka** — setelah masuk dengan NRP & kata sandi pertama kali di HP ini, personel diminta **membuat PIN** (dan mengulanginya). PIN yang terlalu mudah (angka sama semua / berurutan) ditolak. PIN hanya disimpan sebagai hash PBKDF2 di HP, tidak pernah dikirim ke server.
- **Kunci otomatis 5 menit** — aplikasi terkunci bila tidak disentuh selama 5 menit atau berada di latar belakang ≥ 5 menit, dan selalu terkunci saat dibuka ulang. Buka kembali dengan **PIN** atau **biometrik**. Panggilan video yang sedang berlangsung tidak ikut terkunci.
- **Login biometrik** — sidik jari / pengenalan wajah yang terdaftar di HP (Android 9 ke atas). Bisa diaktifkan saat membuat PIN atau di **Pengaturan → Keamanan**.
- **5 kali salah PIN** → keluar otomatis dan wajib masuk lagi dengan kata sandi, lalu membuat PIN baru. Tersedia juga tombol **"Lupa PIN? Masuk dengan kata sandi"**.
- **Ubah PIN** di Pengaturan → Keamanan (PIN lama → PIN baru → ulangi).
- **Screenshot & rekam layar diblokir** di seluruh aplikasi; isi aplikasi juga disamarkan di daftar aplikasi terbaru.

## Catatan

- Android 8.0/8.1 tidak mendukung dialog biometrik bawaan, sehingga hanya memakai PIN.
- Pengguna yang memperbarui dari v1.3.0 dengan sesi aktif akan langsung diminta membuat PIN saat membuka aplikasi.

---

# SISINDEN Android v1.3.0 (versionCode 11)

Chat Dinas dengan panggilan video/suara, pratinjau dokumen TTE, TTE SP Jaga oleh Pasops, notifikasi bersuara di bilah status, dan Cloudflare Turnstile yang diatur dari web.

## Yang baru

- **Chat Dinas** (menu Operasional) — sama dengan halaman Chat di web. Personel langsung masuk ke *Pusat Komunikasi Dinas*; operator (admin/developer) melihat daftar utas dengan pencarian, filter Semua/Aktif/Ditutup, status online, jumlah belum dibaca, **Chat baru** ke personel mana pun, **Tutup/Buka sesi**, dan **Hapus utas**. Mendukung lampiran gambar & dokumen (jpg, png, webp, pdf, doc/x, xls/x, txt, zip; maks. 20 MB) dan indikator *sedang mengetik*.
- **Panggilan video & suara (Vicon)** dari utas chat — memakai kanal Agora dan sinyal panggilan yang sama dengan modul Vicon web, sehingga **aplikasi ↔ web saling tersambung**. Ada nada dering untuk panggilan masuk, nada sambung, bisukan mikrofon, matikan/ganti kamera, pengeras suara/earpiece, dan durasi. Riwayat panggilan tercatat di chat seperti di web.
- **Pratinjau dokumen TTE** — Komandan, Admin, dan pengaju dapat membuka PDF yang diajukan untuk TTE sebelum disahkan (menu Tanda Tangan). Antrean SKHPP yang menunggu TTE Komandan juga tampil dengan tombol pratinjau.
- **TTE SP Jaga oleh Pasops** — setiap pengajuan SP Jaga (TTE) memunculkan notifikasi untuk Pasops. Di tab **Jaga**, Pasops melihat kartu *Menunggu TTE Anda* dengan tombol **Lihat PDF** dan **Tandatangani**. Setelah disahkan, seluruh personel mendapat notifikasi *Jadwal jaga terbit*.
- **Notifikasi bersuara di bilah status** — semua warta baru (bukan hanya yang terbaru) dan pesan Chat Dinas berbunyi di bilah HP. Menekan notifikasi membuka modul terkait (TTE → Tanda Tangan, SP Jaga → Jaga, SKHPP, Chat).
- **Cloudflare Turnstile aktif kembali, diatur dari web** — site key, secret key, dan saklar aktif diisi Admin di **Pengaturan → Keamanan Login Aplikasi Mobile**. Secret key disimpan terenkripsi di database dan tidak pernah dikirim ke browser maupun aplikasi.

## Keterbatasan yang perlu diketahui

- Server belum memakai push (FCM). Saat aplikasi terbuka, notifikasi dicek tiap ±30 detik dan panggilan masuk tiap ±4 detik. Saat aplikasi ditutup, notifikasi dicek WorkManager paling cepat tiap 15 menit (bisa tertunda mode hemat daya), dan **panggilan masuk hanya berdering bila aplikasi sedang terbuka**.
- Panggilan dari personel diteruskan ke operator utas (atau admin pertama), sama seperti di web.

## Wajib di server saat rilis

1. Pull `main`, lalu `php artisan config:clear` dan `npm run build` (halaman Pengaturan web berubah).
2. Buka **Pengaturan** web → *Keamanan Login Aplikasi Mobile (Cloudflare Turnstile)* → isi site key & secret key → centang **Aktif** → Simpan.
3. Pastikan hostname `sisinden.my.id` terdaftar di widget Turnstile (Cloudflare → Turnstile → Hostname Management).
4. Pastikan `agora_app_id` / `agora_app_certificate` di Pengaturan sudah terisi (sama dengan yang dipakai Vicon web).

## Keamanan

- Perbaikan kebocoran: pengaturan rahasia (mis. `google_client_secret`, `agora_app_certificate`) tidak lagi ikut terkirim ke setiap halaman web.
- Endpoint chat & panggilan mobile memeriksa kepemilikan utas: personel hanya dapat mengakses utasnya sendiri.

## Status pengujian

- Feature test PHP `MobileApiSecurityTest`: Turnstile dari pengaturan admin (secret terenkripsi & tidak bocor), kepemilikan utas chat, sinyal panggilan & log durasi, akses pratinjau TTE, notifikasi & TTE SP Jaga.
- Unit test Android baru terhadap respons asli server (chat, panggilan, token Agora, antrean SP Jaga, pemetaan tautan notifikasi, lampiran).
- Build, unit test, dan lint dijalankan oleh GitHub Actions pada setiap push ke `main`.

---

# SISINDEN Android v1.2.1 (versionCode 10)

- **Cloudflare Turnstile dinonaktifkan sementara.** Widget pemeriksaan keamanan hanya tampil bila server mengaktifkannya (`TURNSTILE_SECRET_KEY` terisi di `.env`). Selama kosong, Masuk & Aktivasi langsung berjalan dengan animasi *Memeriksa keamanan sistem → Memeriksa sesi login → Masuk ke beranda*.
- Mengaktifkan kembali cukup dengan mengisi `TURNSTILE_SECRET_KEY` di server — tanpa perlu memperbarui aplikasi.

---

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
