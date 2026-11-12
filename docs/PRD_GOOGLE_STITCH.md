# PRD + Prompt Google Stitch — Redesign UI/UX SISINDEN

Versi 1.0 · 11 November 2026 · Untuk dipakai di https://stitch.withgoogle.com

Dokumen ini berisi kebutuhan desain beserta **prompt siap tempel** untuk Google Stitch, untuk web (Vue/Inertia) dan Android (Kotlin/Compose).

---

## 0. Cara pakai di Stitch

1. Buat **dua project** di Stitch: `SISINDEN Web` (mode **Web**) dan `SISINDEN Android` (mode **App/Mobile**).
2. Tempel **Prompt Gaya Global (bagian 3)** sebagai prompt pertama di masing-masing project. Prompt ini menjadi dasar semua layar.
3. Buat layar satu per satu dengan prompt di bagian 5 (web) dan 6 (Android). **Satu prompt = satu layar.** Prompt yang lebih pendek hasilnya lebih stabil.
4. Untuk **Login**, unggah **screenshot login yang sekarang** sebagai gambar referensi, lalu pakai prompt Login. Ini wajib supaya tulisan, logo, dan latar tidak berubah (lihat bagian 2).
5. Perbaiki hasil dengan prompt pendek, misalnya "make the sidebar narrower" atau "use the same card style as Dashboard".
6. Ekspor ke Figma (Copy to Figma) atau ambil kode HTML, lalu serahkan ke developer untuk dipindahkan ke Vue dan Compose.

Prompt sengaja ditulis dalam bahasa Inggris karena Stitch paling akurat dengan bahasa Inggris. **Semua teks UI tetap bahasa Indonesia** dan sudah ditulis di dalam prompt.

---

## 1. Ringkasan produk

| | |
|---|---|
| Produk | SISINDEN (Sistem Informasi Detasemen Intelijen) |
| Platform | Web admin (desktop dan tablet) + aplikasi Android |
| Pengguna | Admin/Operator, Pimpinan (persetujuan dan TTE), Anggota (absensi, SP Jaga, tracking SC), Publik (verifikasi dokumen, tracking SC) |
| Tujuan | Tampilan lebih modern, interaktif, dan beranimasi, tetap padat data dan mudah dibaca. Satu bahasa desain untuk web dan Android. |
| Referensi gaya | 21st.dev / shadcn/ui: bento grid, sidebar yang bisa dilipat, data grid, approval card, command palette (Ctrl+K), empty state, border beam, spotlight card |
| Bukan tujuan | Mengubah fitur, alur bisnis, atau data. Mengubah tulisan, logo, dan latar halaman Login. |

### Masalah saat ini
- Gaya web dan Android tidak seragam (warna dan radius berbeda-beda).
- Sidebar web berisi ±28 menu dalam 6 grup tanpa pencarian.
- Efek kaca dipakai di semua tempat sehingga tabel dan form kurang kontras.
- Animasi tidak konsisten.

### Target
- Semua fitur bisa dicapai dalam ≤ 2 langkah atau lewat pencarian Ctrl+K.
- Kontras WCAG AA (teks 4.5:1), target sentuh ≥ 44 px.
- Animasi ≤ 300 ms untuk interaksi, dan bisa dimatikan (reduced motion).

---

## 2. ATURAN TERKUNCI: Halaman Login (web & Android)

> **Tulisan, logo, dan latar belakang di halaman Login TIDAK BOLEH diubah.** Yang boleh diperhalus hanya tata letak, jarak, bentuk kartu, gaya field, tombol, dan animasi.

### 2.1 Login Web: elemen yang harus sama persis

**Latar belakang (jangan diubah):**
- Warna dasar gelap `#050b1a`.
- Tiga gumpalan "aurora" bergerak pelan: biru `#1d4ed8` (kiri atas), indigo `#4f46e5` (kanan), teal `#0d9488` (bawah tengah, redup).
- Sorotan cahaya biru lembut yang mengikuti kursor.
- Jika admin mengunggah gambar latar (`login_background`), gambar itu dipakai dengan overlay `#050b1a` 80% + blur 3 px.

**Logo (jangan diubah):** logo yang diatur admin (gambar `configuredLogo`), besar di kolom kiri dengan halo cahaya di belakangnya. Di layar sempit, logo kecil 44 px di atas kartu. Jika tidak ada logo, kotak kaca berisi huruf "S".

**Tulisan (wajib sama persis):**
- Kolom kiri: `{Nama Aplikasi}` (huruf besar), lalu
  `Sistem Informasi Detasemen Intelijen Integrasi Otoritas Akses, Pengawasan Lokasi, Administrasi Surat & Logistik Terpadu.`
- Footer kiri: `© {tahun} {Nama Aplikasi}. All Rights Reserved.`
- Judul kartu: `Masuk Akun`
- Sub-judul: `Gunakan akun internal Anda untuk mengakses sistem dashboard SINDEN.`
- Field 1: `NRP / Username / Email` · Field 2: `Kata Kunci`
- `Ingat Saya` · `Lupa Password?`
- Tombol: `Masuk Sistem` (status: `Mengunci GPS...`, `Memverifikasi...`)
- Pemisah: `Atau` · Tombol: `Masuk dengan Akun Google`
- Status GPS: `Verifikasi GPS Sistem` / `Disarankan mengaktifkan GPS`. Saat terkunci: `GPS Presisi Terkunci` / `Koordinat: …`. Saat bermasalah: `Peringatan Lokasi`.
- Kartu tautan: `Cek Riwayat Pengajuan SC`, dengan teks kecil `Layanan publik mandiri via NRP / NIP / NIK`
- Bawah: `Aktivasi Akun` · `Belum punya akun? Daftar Sekarang`
- (Kondisional) `Akses Tautan Berbagi Terproteksi`, dengan teks `Silakan masuk dengan akun dinas Anda untuk membuka folder.`
- Judul tab browser: `Otoritas Login - SINDEN`

### 2.2 Login Android: elemen yang harus sama persis

**Latar belakang (jangan diubah):** latar `BrandBackdrop` yang dipakai sekarang (aurora gelap, atau gambar latar dari pengaturan admin `loginBackground`).

**Logo (jangan diubah):** logo melayang 120 dp di tengah atas (`FloatingLogo`), sama seperti sekarang.

**Tulisan (wajib sama persis):**
- `{NAMA APLIKASI}` (huruf besar, default `SI SINDEN`)
- `Sistem Informasi Detasemen Intelijen`
- Judul kartu: `Masuk Akun`
- `Gunakan akun internal Anda untuk mengakses SISINDEN.`
- Field: `NRP, Username, atau Email` · `Kata sandi`
- Tombol: `Masuk` (dengan ikon perisai). Saat proses: `Memverifikasi...`
- `Belum aktif? Aktivasi akun dengan token Admin`
- Banner kondisional: `Sesi Anda telah berakhir. Silakan masuk kembali.` / `Akun Anda dinonaktifkan oleh Admin.`
- Footer: `© {tahun} {Nama Aplikasi} · Versi {versi}`

---

## 3. Prompt Gaya Global (tempel pertama di setiap project)

```
Design system for "SISINDEN", an Indonesian military-intelligence office app (letters, approvals,
digital signatures, attendance with GPS, duty roster, finance, chat). All UI text in Bahasa Indonesia.

Style: modern shadcn/ui + 21st.dev aesthetic. Calm, data-dense, premium. Light mode default
with a matching dark mode.
Colors: background #FAFAFA, surface/cards #FFFFFF, subtle #F4F4F5, borders #E4E4E7,
text #09090B, muted text #52525B. One accent: blue #2563EB (soft #EFF6FF).
Status colors only for status: success #16A34A, warning #D97706, danger #DC2626.
Decorative glow violet #8B5CF6, used only for highlights.
Dark mode: bg #09090B, surface #111113, border #27272A, text #FAFAFA, accent #3B82F6.
Typography: Geist for UI, Geist Mono for numbers, letter numbers (e.g. 005/SINTEL/XI/2026),
dates and NRP. Tabular numbers.
Shapes: radius 10px controls, 14px cards, 20-24px mobile cards/sheets. 1px hairline borders,
very soft shadows. No heavy glassmorphism on data; glass/blur only on overlays, nav bars and dock.
Icons: Lucide outline, 2px stroke. No emoji.
Motion: cards fade+rise 8px with 40ms stagger, buttons press scale 0.97, spring for toggles/tabs,
skeleton shimmer while loading, count-up numbers on KPI tiles. Respect reduced motion.
Accessibility: WCAG AA contrast, 44px touch targets, visible focus ring (3px blue glow).
```

---

## 4. Daftar layar & prioritas

| # | Layar | Web | Android | Prioritas |
|---|---|---|---|---|
| 1 | Login (TERKUNCI, lihat bagian 2) | ✔ | ✔ | P1 |
| 2 | Dashboard / Beranda | ✔ | ✔ | P1 |
| 3 | Agenda Surat (daftar) + detail | ✔ | ✔ | P1 |
| 4 | Buat Surat (form) | ✔ | — | P1 |
| 5 | Absensi | — | ✔ | P1 |
| 6 | Notifikasi | ✔ | ✔ | P1 |
| 7 | Command Palette (Ctrl+K) | ✔ | — | P1 |
| 8 | Persetujuan & TTE | ✔ | ✔ | P2 |
| 9 | SKHPP (daftar + form) | ✔ | ✔ | P2 |
| 10 | SP Jaga (jadwal) | ✔ | ✔ | P2 |
| 11 | Komunikasi & VC (chat) | ✔ | ✔ | P2 |
| 12 | Keuangan (Simpan Pinjam / Kas) | ✔ | ✔ | P2 |
| 13 | Menu "Lainnya" | — | ✔ | P2 |
| 14 | Pengguna & Pengaturan | ✔ | ✔ | P3 |
| 15 | Halaman publik: Verifikasi Dokumen, Tracking SC, Absen QR Rapat | ✔ | — | P3 |
| 16 | Profil | ✔ | ✔ | P3 |

### Navigasi baru
- **Web, sidebar 5 grup** (bisa dilipat ke ikon saja, ada kolom cari dan pintasan Ctrl+K):
  1. **Beranda:** Dashboard, Notifikasi, Komunikasi & VC, WhatsApp Web
  2. **Persuratan:** Agenda Surat, Buat & Draf Surat, Penerbitan SKHPP, Tracking Pengajuan SC, SP Jaga Siaga, Rapat Terbuka, Kategori
  3. **Validasi:** Tanda Tangan (TTE), Stempel, Layanan Cetak
  4. **Keuangan & Logistik:** Simpan Pinjam, Kas, Kas Teknis, Rekening Komandan, Ibu Beti, Mitra
  5. **Administrasi:** Pelanggaran Prajurit, Aktivitas & Peta, Pengguna, Log Pengunjung, Audit, Backup, Cek Sistem, Pengaturan
- **Android, bottom dock:** Beranda · Surat · **Absen (tombol bulat di tengah)** · Pesan · Lainnya

---

## 5. Prompt layar WEB (project mode Web, 1440 px)

### 5.1 Login (TERKUNCI)
Unggah screenshot login web yang sekarang sebagai referensi, lalu tempel:
```
Refine this exact login page. DO NOT change any text, the logo, or the background:
keep the dark #050b1a background with the three slow-moving aurora blobs (blue #1d4ed8,
indigo #4f46e5, teal #0d9488) and the cursor spotlight; keep the admin-configured logo with its
glow halo on the left column; keep every label exactly as in the screenshot
("Masuk Akun", "NRP / Username / Email", "Kata Kunci", "Ingat Saya", "Lupa Password?",
"Masuk Sistem", "Atau", "Masuk dengan Akun Google", "Verifikasi GPS Sistem",
"Cek Riwayat Pengajuan SC", "Aktivasi Akun", "Belum punya akun? Daftar Sekarang", footer).
Only polish: glass login card with a thin animated border beam, cleaner spacing, floating-label
inputs with blue focus glow, iOS-style "Ingat Saya" switch, primary button with arrow that slides
on hover, GPS status pill with pulsing dot (grey / amber / green). Two-column layout on desktop,
single column with small logo on mobile.
```

### 5.2 Dashboard
```
Admin dashboard, desktop 1440. Left collapsible sidebar (260px) with app logo + "SISINDEN",
search box with "Ctrl K" hint, and 5 groups: Beranda, Persuratan, Validasi, Keuangan & Logistik,
Administrasi (items listed: Dashboard active, Notifikasi 8, Komunikasi & VC 3, Agenda Surat 12,
Buat Surat, SKHPP 4, Tracking SC, SP Jaga, Tanda Tangan (TTE) 2, Layanan Cetak, Simpan Pinjam,
Kas, Pengguna, Backup, Pengaturan). Top bar: breadcrumb "Beranda / Dashboard", command search
"Cari apa saja…", theme toggle, bell, avatar.
Header: small date "Rabu, 11 November 2026 · 08.42 WITA", title "Selamat pagi, {Nama}",
buttons "Absen QR Rapat" (secondary) and "+ Buat surat" (primary).
Bento grid: 4 KPI tiles with icon, big mono number, green delta text and sparkline:
"Surat masuk 1.284 +12% minggu ini", "Menunggu TTE 7 · 3 mendesak", "Hadir hari ini 86/94",
"SC diproses 23". Then "Antrian persetujuan" card with 2 approval cards (avatar, title,
"Menunggu" badge, mini timeline Dibuat → Diparaf → TTE, buttons "Tolak" and "Setujui & TTE"),
next to a bar chart "Arus surat 14 hari" (Masuk blue, Keluar violet).
Bottom: table "Surat terbaru" with live badge "Live · diperbarui 08.41": columns nomor (mono),
perihal, asal, tanggal, status badge, arrow.
```

### 5.3 Agenda Surat (data grid + detail)
```
"Agenda Surat" page using the same sidebar and top bar as Dashboard (Agenda Surat active).
Header with title, count "1.284 surat", buttons "Ekspor" and "+ Catat surat".
Toolbar: segmented control "Semua / Masuk / Keluar / Disposisi", search field, filter chips
"Status", "Kategori", "Tanggal", "Asal", and a "Kolom" menu.
Data grid with checkbox column, sticky header, sortable columns: Nomor (mono), Perihal, Asal/Tujuan,
Kategori, Tanggal (mono), Status badge (Menunggu amber, Diproses blue, Disetujui green, Ditolak red),
row actions. 10 rows, one newly arrived row softly highlighted blue.
When rows are selected a floating bulk-action bar appears at the bottom: "3 dipilih · Disposisi ·
Cetak · Arsipkan". Right side drawer (420px) showing selected letter detail: number, perihal,
metadata list, PDF preview thumbnail, disposition timeline, buttons "Disposisi" and "Setujui".
Pagination footer.
```

### 5.4 Buat Surat (form)
```
"Buat & Draf Surat" form page, same shell. Two columns: left form card, right live preview of
the letter (A4 paper preview with kop surat area). Form sections with clear labels above fields:
"Jenis surat" select, "Nomor surat" (mono, auto-suggest), "Perihal", "Tujuan" (multi-select chips),
"Tanggal", "Sifat" (Biasa / Segera / Rahasia segmented), "Isi surat" rich-text editor,
"Lampiran" drag-and-drop upload zone. Sticky footer bar: "Simpan draf" (secondary),
"Kirim untuk paraf" (primary). Show one inline error example under "Nomor surat":
"Nomor surat sudah dipakai".
```

### 5.5 Command Palette (Ctrl+K)
```
Command palette overlay on top of the dimmed, blurred Dashboard. Centered dialog 640px, input
"Cari menu, surat, pengguna…" with "Esc" hint. Grouped results: "Menu" (Agenda Surat, SKHPP,
SP Jaga), "Surat" (005/SINTEL/XI/2026 Undangan rapat koordinasi…, mono number + status badge),
"Pengguna" (avatar + name + NRP), "Aksi cepat" (Buat surat, Absen QR Rapat, Ganti tema).
Highlighted active row, matched letters in bold, footer with keyboard hints ↑ ↓ Enter.
```

### 5.6 Notifikasi
```
"Notifikasi" page, same shell. Tabs "Semua / Belum dibaca / Persetujuan / Sistem" and a
"Tandai semua dibaca" button. List grouped by "Hari ini", "Kemarin", "Minggu ini".
Each item: colored icon circle, title, one-line description, relative time, unread blue dot,
hover actions. Include an empty-state variant: line illustration, "Semua sudah dibaca", button.
```

### 5.7 Persetujuan & TTE
```
"Tanda Tangan (TTE)" page, same shell. Left: queue list of documents awaiting signature with
filter chips (Mendesak, Hari ini, Semua). Center: large PDF viewer with page thumbnails and zoom.
Right panel: document info, approval timeline (Dibuat → Diparaf → TTE), signature placement
preview, passphrase field, primary button "Tanda tangani", secondary "Tolak dengan catatan".
After success show a toast bottom-right "Dokumen berhasil ditandatangani · Urungkan".
```

### 5.8 Komunikasi & VC
```
"Komunikasi & VC" chat page, same shell. Three panes: conversation list with search and unread
badges; chat thread with grouped bubbles (own = blue, others = white with border), delivery ticks,
typing indicator "…", date separators, attachments (PDF card, image); right info pane with members
and shared files. Header buttons "Panggilan suara" and "Video call" (Vicon badge).
Composer with attach, emoji-free, send button.
```

### 5.9 Halaman publik (Verifikasi Dokumen / Tracking SC / Absen QR Rapat)
```
Public verification page (no login), same dark aurora backdrop style as Login.
Centered glass card: logo, title "Verifikasi Dokumen", big result state: green shield check
"Dokumen Asli & Valid" with document number (mono), title, signer name & rank, signed date,
QR hash. Variant states: "Tidak ditemukan" (amber) and "Tidak valid" (red).
Button "Unduh PDF" and small footer.
```

---

## 6. Prompt layar ANDROID (project mode App, 390×844)

### 6.1 Login (TERKUNCI)
Unggah screenshot login Android yang sekarang sebagai referensi, lalu tempel:
```
Refine this exact Android login screen. DO NOT change any text, the logo or the background:
keep the existing dark aurora backdrop (or admin background image), the floating logo at top,
"{NAMA APLIKASI}" in uppercase, "Sistem Informasi Detasemen Intelijen", card title "Masuk Akun",
"Gunakan akun internal Anda untuk mengakses SISINDEN.", fields "NRP, Username, atau Email" and
"Kata sandi", button "Masuk" with shield icon, link "Belum aktif? Aktivasi akun dengan token Admin",
footer "© {tahun} {Nama Aplikasi} · Versi {versi}".
Only polish: frosted dark glass card (radius 30), thin light line on its top edge, larger
56dp fields with clear focus state, gradient blue→indigo button 58dp that squishes on press,
gentle shake on error, staggered entrance animation. One-handed friendly spacing.
```

### 6.2 Beranda
```
Android home screen "Beranda". Top: avatar, small date "Rabu, 11 Nov", greeting "Pagi, {Nama}",
bell button with badge. Hero card (blue→indigo gradient, rounded 24) "ABSENSI HARI INI":
big mono time "07.12", "masuk · tepat waktu", white button "Absen pulang · 16.00".
2×2 bento KPI tiles: "Surat masuk 12", "Menunggu TTE 7", "SP Jaga besok", "Pesan 3".
Section "Perlu tindakan" with "Lihat semua" and one approval card (Tolak / Setujui & TTE).
Section "Agenda hari ini" timeline. Floating bottom dock: Beranda (active), Surat,
center round blue "Absen" button with scan icon, Pesan, Lainnya.
```

### 6.3 Surat (daftar + detail)
```
Android "Surat" screen. Large title "Surat", search bar, horizontal filter chips
(Semua, Masuk, Keluar, Menunggu, Disetujui). List of letter cards: mono number, perihal (2 lines),
asal, date, status badge; swipe right = "Disposisi", swipe left = "Arsip". Pull to refresh,
skeleton while loading, FAB "+ Catat". Second frame: letter detail as bottom sheet expanding to
full screen: header with number + status, metadata, PDF preview, timeline, sticky bottom actions
"Disposisi" and "Setujui".
```

### 6.4 Absensi
```
Android "Absensi" screen. Large title "Absensi" with green badge "Dalam radius".
Segmented control "Hari ini / Minggu / Bulan / Riwayat". Big circular scan button in the center
(gradient blue→indigo with pulsing rings) labeled "Ketuk untuk absen". Location card:
pin icon "Makodim … · ±12 m" and note "Verifikasi wajah + GPS". Weekly row Sen–Jum with status
circles (hadir green check, hari ini blue, belum grey). States to show: outside radius
(amber, button "Ajukan izin lokasi") and success (green check morph + time).
Bottom dock with center Absen button active.
```

### 6.5 Notifikasi
```
Android "Notifikasi" screen. Large title, "Tandai semua dibaca" text button, chips
(Semua, Belum dibaca, Persetujuan, Sistem). Grouped list "Hari ini / Kemarin": icon circle,
title, description, time, unread dot. Swipe to mark as read. Empty state "Semua sudah dibaca".
```

### 6.6 Pesan (chat)
```
Android chat list + chat thread. List: search, avatars with online dot, last message, time,
unread count. Thread: top bar with name, "Video call" and "Telepon" icons; grouped bubbles,
delivery ticks, typing indicator, attachment cards; composer with attach and send.
```

### 6.7 Lainnya (semua fitur)
```
Android "Semua fitur" screen. Large title, search "Cari fitur…". Grouped icon grid (4 per row,
rounded 18 tiles with tinted icon squares and labels): "Persuratan": Agenda, Buat, SKHPP,
Tracking, SP Jaga, Rapat, TTE, Cetak. "Keuangan & Administrasi": Koperasi, Kas, Pelanggaran,
Aktivitas, Pengguna, Backup, Pengaturan, Profil. Bottom dock with "Lainnya" active.
```

### 6.8 Profil & Pengaturan
```
Android "Profil" screen. Header card with large avatar, name, rank, NRP (mono), unit.
Grouped settings list (iOS-style inset groups): Akun (Ubah kata sandi, Akun Google),
Tampilan (Tema: Sistem/Terang/Gelap, Kurangi animasi), Notifikasi, Keamanan (Sidik jari),
Tentang (Versi aplikasi, Periksa pembaruan). Red "Keluar" button at the bottom.
```

---

## 7. Komponen yang harus konsisten (cek setiap hasil Stitch)

| Komponen | Aturan |
|---|---|
| Button | Primary biru, Secondary putih + border, Ghost, Destructive merah. Radius 10, tinggi 40 (web) / 48–58 dp (Android). |
| Badge status | Pill dengan titik, latar 10% warna status. Selalu ada teks, bukan hanya warna. |
| Input | Label di atas field (web) atau floating label (login). Error merah tepat di bawah field. |
| Kartu | Putih, border 1 px `#E4E4E7`, radius 14 (web) / 20 (Android), bayangan sangat tipis. |
| KPI tile | Ikon dalam kotak biru muda, angka besar Geist Mono, delta hijau/merah dengan teks. |
| Tabel | Header lengket, baris 52 px, hover abu, nomor dan tanggal mono. |
| Empty state | Ilustrasi garis + judul + 1 kalimat + tombol aksi. |
| Loading | Skeleton, bukan spinner layar penuh. |
| Toast | Gelap, di kanan bawah (web) / atas (Android), dengan tombol "Urungkan" untuk aksi penting. |

## 8. Animasi (catatan untuk developer; Stitch hanya membuat tampilan statis)

| Pola | Spesifikasi |
|---|---|
| Kartu masuk | fade + naik 8 px, 240 ms, stagger 40 ms |
| Tekan tombol | scale 0.97, 100 ms |
| Tab / segmented / dock | indikator bergeser dengan spring |
| Angka KPI | count-up 600 ms |
| Dialog / sheet | 360 ms, backdrop blur 8 px |
| Border beam | hanya di kartu login dan 1 kartu "live" |
| Skeleton | shimmer 1,2 s |
| Reduced motion | semua animasi dimatikan, langsung ke keadaan akhir |

## 9. Kriteria selesai (checklist review hasil Stitch)

- [ ] Login web & Android: tulisan, logo, dan latar **identik** dengan aslinya (bandingkan dengan screenshot).
- [ ] Semua teks UI bahasa Indonesia, tanpa emoji.
- [ ] Warna hanya dari palet bagian 3.
- [ ] Angka, nomor surat, NRP, dan tanggal memakai Geist Mono.
- [ ] Setiap layar punya versi Light; layar utama juga punya versi Dark.
- [ ] Kontras teks cukup (tidak ada abu muda di atas putih untuk teks penting).
- [ ] Sidebar web dan dock Android sama di semua layar.
- [ ] Ada keadaan kosong (empty) dan loading untuk daftar utama.
- [ ] Hasil diekspor ke Figma / HTML dan diberi nama sesuai tabel bagian 4.
