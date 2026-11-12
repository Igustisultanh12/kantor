# PRD — Perombakan Total UI/UX SISINDEN ("Nova")

| | |
|---|---|
| Produk | SISINDEN — Web (Laravel + Vue/Inertia) & Android (Kotlin/Compose) |
| Versi dokumen | 1.1 · 11 November 2026 (revisi setelah audit skill ui-ux-pro-max, lihat bagian 15) |
| Status | Draf untuk ditinjau |
| Desain | Figma: https://www.figma.com/design/Pkmbmm6h8xBKcP6eRgq2Z6 |
| Referensi gaya | 21st.dev (shadcn/ui ecosystem): bento grid, sidebar collapsible, data grid, approval card, chat bubble, empty state, border beam, spotlight card |

---

## 1. Latar belakang

SISINDEN sudah dipakai harian untuk surat, SKHPP, SP Jaga, absensi, keuangan, komunikasi, dan administrasi. Tampilannya sudah dipoles beberapa kali (Aurora/kaca, v1.9.x), tetapi masih ada masalah struktural:

- **Inkonsisten.** Web memakai Tailwind ad-hoc (`text-[#64748B]`, `rounded-2xl`, warna ikon per menu berbeda-beda). Android memakai token sendiri (`Blue600`, `Canvas`, `Night*`). Tidak ada satu sumber token bersama.
- **Navigasi padat.** Sidebar web punya ±28 tautan dalam 6 grup tanpa pencarian. Di Android, menu ditumpuk dalam satu layar Menu.
- **Kaca berlebihan.** Efek kaca global (`.sinden-shell .bg-white` → kaca) menurunkan kontras tabel dan form yang padat data.
- **Animasi tidak terstandar.** Durasi dan easing berbeda-beda; transisi layar Android pernah memunculkan dua layar sekaligus.
- **Halaman lama.** Banyak berkas cadangan (`Index - copy.vue`, `MapIndex - backup.vue`, `Index - BACKUP.vue`) dan pola tabel/form yang ditulis ulang di setiap halaman.

## 2. Tujuan

1. **Satu bahasa desain** ("Nova") untuk web dan Android, dengan token yang sama (warna, tipografi, radius, jarak, gerak).
2. **Kekinian dan interaktif** ala 21st.dev/shadcn, tetapi tetap **padat data dan cepat dibaca** untuk aplikasi dinas.
3. **Navigasi ringkas.** Semua fitur bisa dicapai ≤ 2 langkah atau lewat Command Palette (⌘K / Ctrl+K).
4. **Gerak bermakna.** Setiap animasi menjelaskan perubahan state, ≤ 300 ms untuk interaksi, dan menghormati `prefers-reduced-motion` / pengaturan animasi Android.
5. **Aksesibel.** Kontras WCAG 2.2 AA (teks 4.5:1, komponen 3:1), target sentuh ≥ 44 px, fokus keyboard terlihat.

### Bukan tujuan

- Mengubah logika bisnis, API, atau skema database.
- Menambah fitur baru di luar yang dibutuhkan pola UI (misalnya Command Palette dan pencarian global boleh, tetapi modul baru tidak).

## 3. Metrik keberhasilan

| Metrik | Saat ini | Target |
|---|---|---|
| Waktu menemukan menu (uji 5 tugas) | belum diukur | turun 40% |
| Skor SUS (System Usability Scale) | belum diukur | ≥ 80 |
| Lighthouse Accessibility (web) | belum diukur | ≥ 95 |
| Halaman yang memakai komponen Nova | 0% | 100% halaman Authenticated |
| Warna hardcoded di `.vue` (`#xxxxxx`, `text-[#…]`) | banyak | 0 di halaman yang sudah migrasi |
| Jank animasi Android (frame > 16 ms saat transisi) | belum diukur | < 1% |

## 4. Pengguna dan peran

| Peran | Kebutuhan utama |
|---|---|
| Admin / Operator | Kelola surat, SKHPP, user, keuangan, backup, pengaturan. Butuh tabel cepat, filter, aksi massal. |
| Pimpinan (Commander) | Ringkasan, persetujuan, TTE. Butuh dashboard ringkas dan aksi "setujui/tolak" sekali ketuk. |
| Anggota Sintel | Absensi, jadwal SP Jaga, tracking SC. Terutama di Android, satu tangan, sinyal lemah. |
| Publik | Verifikasi dokumen (TTE/SKHPP), tracking SC, absen QR rapat. Tanpa login, harus jelas dan meyakinkan. |

## 5. Prinsip desain Nova

1. **Tenang dulu, mencolok kemudian.** Permukaan netral (zinc), satu warna aksen (Nova Blue). Warna status hanya untuk status.
2. **Data adalah pahlawan.** Angka tabular agar mudah dibandingkan. *(Revisi 11-11-2026, keputusan pemilik proyek:)* gaya kaca dan latar lama (Aurora / Polos / Gambar dari Pengaturan → Tampilan Web) **dipertahankan** di Nova, termasuk sidebar, topbar, dan kartu konten. Kontras teks di atas kaca tetap wajib ≥ 4.5:1.
3. **Bento, bukan tumpukan.** Dashboard memakai grid bento dengan ukuran tile bermakna (penting = besar).
4. **Satu gerak, satu makna.** Masuk = fade + naik 8 px. Pilih = spring. Hapus = collapse. Data baru = highlight singkat.
5. **Sama di dua platform, asli di masing-masing.** Token sama, pola mengikuti platform (sidebar di web, bottom dock di Android).

## 6. Fondasi desain (token)

Token ditulis sekali di Figma (Variables, mode Light/Dark), lalu diekspor ke:
- Web: `resources/css/tokens.css` (CSS variables) + `tailwind.config.js` (memetakan `bg-surface`, `text-fg-muted`, dst.).
- Android: `core/ui/theme/NovaTokens.kt` (warna, `Dp`, `TextStyle`, spesifikasi gerak).

### 6.1 Warna (semantik)

| Token | Light | Dark | Pemakaian |
|---|---|---|---|
| `bg/canvas` | #FAFAFA | #09090B | latar aplikasi |
| `bg/surface` | #FFFFFF | #111113 | kartu, tabel, panel |
| `bg/subtle` | #F4F4F5 | #18181B | baris hover, input, chip |
| `border/default` | #E4E4E7 | #27272A | garis kartu, pemisah |
| `fg/default` | #09090B | #FAFAFA | teks utama |
| `fg/muted` | #52525B | #A1A1AA | teks sekunder |
| `fg/subtle` | #6B6B74 | #8B8B94 | placeholder, keterangan (v1.1: dulu #71717A, gagal 4.5:1) |
| `border/input` | #8E8E96 | #63636B | garis field & checkbox, kontras ≥ 3:1 (baru v1.1) |
| `accent/default` | #2563EB | #3B82F6 | tautan, ikon aktif, fokus |
| `accent/solid` | #2563EB | #2563EB | latar tombol utama, teks putih 5.2:1 (baru v1.1) |
| `accent/fg` | #FFFFFF | #FFFFFF | teks di atas `accent/solid` |
| `accent/soft` | #EFF6FF | #172554 | latar menu aktif, badge info |
| `accent/on-soft` | #2563EB | #93C5FD | teks di atas `accent/soft` (baru v1.1: 4.75 / 8.15) |
| `success` | #16A34A | #22C55E | ikon, titik, garis status |
| `success/fg` | #15803D | #22C55E | teks status sukses (baru v1.1: 5.0:1) |
| `warning` | #D97706 | #F59E0B | ikon, titik, garis status |
| `warning/fg` | #B45309 | #F59E0B | teks status menunggu (baru v1.1: 5.0:1) |
| `danger` | #DC2626 | #EF4444 | ditolak, pelanggaran, hapus |
| `glow/violet` | #8B5CF6 | #A78BFA | aksen dekoratif (border beam, aurora login) |

Aturan pakai (v1.1): warna status mentah (`success`, `warning`) **tidak boleh** untuk teks di atas putih karena hanya 3.2–3.3:1. Teks status selalu memakai `*/fg`. Semua angka kontras di tabel sudah diukur dengan rumus WCAG.

### 6.2 Tipografi

- **Figtree** (UI, font lama aplikasi; dipilih kembali 11-11-2026 karena Geist terasa terlalu kaku), **Geist Mono** hanya untuk kode/nomor di elemen kecil (kbd, nomor surat).
- Skala: Display 40/44 · H1 30/36 · H2 24/30 · H3 18/26 · Body 14/22 · Body-sm 13/20 · Caption 12/16 · Overline 11/14 (caps, +6% tracking).
- Android memakai skala yang sama dalam `sp`, body naik ke 15 sp untuk keterbacaan.

### 6.3 Ruang, radius, elevasi

- Grid 4 px. Jarak: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.
- Radius: `sm` 6 · `md` 10 · `lg` 14 · `xl` 20 · `full`.
- Elevasi: `e0` garis saja · `e1` 0 1 2 rgba(0,0,0,.05) · `e2` 0 8 24 -6 rgba(0,0,0,.12) (popover, dialog) · `glow` 0 0 0 1 accent/30 + 0 8 32 -8 accent/40 (fokus/aktif).

### 6.4 Gerak

| Token | Durasi | Easing | Pemakaian |
|---|---|---|---|
| `motion/instant` | 100 ms | ease-out | hover, tekan |
| `motion/fast` | 160 ms | cubic-bezier(.2,.8,.2,1) | tooltip, dropdown |
| `motion/base` | 240 ms | cubic-bezier(.2,.8,.2,1) | kartu masuk, tab |
| `motion/slow` | 360 ms | cubic-bezier(.16,1,.3,1) | dialog, sheet, halaman |
| `spring/snappy` | — | stiffness 400, damping 30 | toggle, segmented, dock |
| `stagger` | 40 ms/item, maks 8 item | — | daftar, grid bento |

Web: `framer-motion/dom` (sudah ada di `resources/js/motion.js`) + View Transitions API untuk pindah halaman Inertia. Android: `animate*AsState` + `spring()`, `AnimatedContent` dengan `fadeOut` selesai sebelum `fadeIn` (pelajaran v1.9.1). Semua gerak dimatikan saat reduced motion.

## 7. Komponen inti

| Komponen | Varian / state | Catatan interaksi (referensi 21st.dev) |
|---|---|---|
| Button | primary, secondary, ghost, destructive · sm/md/lg · loading, disabled | tekan: scale .97; loading: spinner + teks tetap |
| Input / Select / DatePicker | default, focus, error, disabled | fokus: ring accent 3 px + glow lembut |
| Badge / Status pill | info, success, warning, danger, neutral | titik status berdenyut untuk "live" |
| KPI Tile (bento) | angka + delta + sparkline | angka count-up 600 ms saat masuk; spotlight hover di web |
| Data Grid | sort, filter chip, pilih baris, aksi massal, kolom tetap | baris baru: highlight accent/soft 1.2 s; skeleton saat memuat |
| Approval Card | menunggu / disetujui / ditolak | tombol setujui/tolak; timeline status (dibuat → diparaf → TTE) |
| Sidebar (web) | lebar 260 / rail 72 · grup bisa dilipat | collapse spring; tooltip di mode rail; indikator aktif bergeser (layout animation) |
| Command Palette | ⌘K / Ctrl+K | pencarian fuzzy menu + data (surat, user, SKHPP); navigasi keyboard |
| Bottom Dock (Android) | 5 tujuan + FAB tengah (Absen) | indikator aktif "pill" bergeser dengan spring |
| Sheet / Dialog | bottom sheet (mobile), dialog (web) | masuk slow, backdrop blur 8 px |
| Toast | sukses, gagal, info | masuk dari bawah-kanan (web) / atas (Android), auto-hilang 4 s |
| Empty State | ilustrasi garis + judul + aksi | tidak pernah layar kosong tanpa ajakan aksi |
| Skeleton | baris, kartu, grafik | shimmer 1.2 s, dimatikan pada reduced motion |
| Chat bubble | masuk/keluar, status kirim (✓, ✓✓, dibaca) | grup pesan per menit; ketik "…" animasi |

## 8. Arsitektur informasi baru

### Web — sidebar 5 grup (dari 6) + Command Palette

1. **Beranda**: Dashboard · Notifikasi · Komunikasi & VC · WhatsApp
2. **Persuratan**: Agenda Surat · Buat Surat · SKHPP · Tracking SC · SP Jaga · Rapat Terbuka · Kategori
3. **Validasi**: Tanda Tangan (TTE) · Stempel · Layanan Cetak
4. **Keuangan & Logistik**: Simpan Pinjam · Kas · Kas Teknis · Rekening Komandan · Ibu Beti · Mitra
5. **Administrasi**: Pelanggaran · Aktivitas/Peta · Pengguna · Log Pengunjung · Audit · Backup · Cek Sistem · Pengaturan

Topbar: breadcrumb · Command Palette · notifikasi · tema · profil.

### Android — Bottom Dock

Beranda · Surat · **Absen (FAB)** · Pesan · Lainnya. "Lainnya" berisi grid fitur yang dikelompokkan sama seperti web dan bisa dicari.

## 9. Layar prioritas (fase 1)

| # | Layar | Web | Android | Inti perubahan |
|---|---|---|---|---|
| 1 | Login | ✔ | ✔ | aurora + border beam pada kartu, GPS check jadi langkah inline, bukan dialog |
| 2 | Dashboard / Beranda | ✔ | ✔ | bento KPI, approval queue, agenda hari ini, aktivitas terbaru |
| 3 | Agenda Surat | ✔ | ✔ | data grid + filter chip + drawer detail (web), list + sheet (Android) |
| 4 | Absensi | — | ✔ | kartu status hari ini, tombol absen besar, riwayat minggu ini |
| 5 | Notifikasi | ✔ | ✔ | dikelompokkan per hari, swipe untuk tandai dibaca |
| 6 | Command Palette | ✔ | — | pencarian global |
| 7 | Halaman publik (Verifikasi, Tracking SC, Absen QR) | ✔ | — | satu template "Public Scene" |

Fase 2: SKHPP, SP Jaga, Rapat, Chat/VC, Keuangan. Fase 3: Admin & sistem (User, Backup, Audit, Pengaturan).

## 10. Kebutuhan fungsional UI

- **FR-1** Tema Light/Dark/Sistem di web dan Android, tersimpan per user.
- **FR-2** Command Palette membuka semua menu yang diizinkan peran user dan mencari surat/SKHPP/user lewat endpoint yang sudah ada.
- **FR-3** Semua tabel memakai komponen `DataGrid` (sort, filter, pagination, pilih baris, ekspor tetap memakai endpoint lama).
- **FR-4** Semua daftar kosong menampilkan Empty State dengan aksi.
- **FR-5** Semua pemuatan > 300 ms menampilkan skeleton, bukan spinner layar penuh.
- **FR-6** Sidebar web bisa dilipat ke mode rail dan diingat (localStorage).
- **FR-7** Android: Bottom Dock dengan FAB Absen. Badge angka di Pesan dan Notifikasi.
- **FR-8** Pengaturan admin "Tampilan Web" (`ui_*`) tetap berlaku, tetapi dipetakan ke token Nova (warna aksen, intensitas efek dekoratif).

## 11. Kebutuhan non-fungsional

- **NFR-1 Performa.** LCP < 2.5 s di 4G. Animasi hanya `transform`/`opacity`. Bundle CSS/JS tambahan ≤ 30 KB gzip.
- **NFR-2 Aksesibilitas.** WCAG 2.2 AA, fokus terlihat, label ARIA untuk ikon-saja, TalkBack/contentDescription di Android.
- **NFR-3 Reduced motion.** Semua gerak punya alternatif statis.
- **NFR-4 Kompatibilitas.** Chrome/Edge/Firefox 2 versi terakhir, Safari 16+. Android minSdk tidak berubah.
- **NFR-5 Tanpa regresi.** `php artisan test` dan unit test Android tetap hijau. Fitur tidak berubah perilakunya.

## 12. Rencana implementasi

| Fase | Isi | Keluaran |
|---|---|---|
| 0. Fondasi (1 minggu) | token Figma → `tokens.css`, Tailwind, `NovaTokens.kt`; font Geist | PR fondasi, tanpa perubahan tampilan besar |
| 1. Komponen (2 minggu) | Button, Input, Badge, Card, KPI, DataGrid, Sheet/Dialog, Toast, Empty, Skeleton, Sidebar, Dock, Command Palette | `resources/js/Components/nova/*`, `core/ui/nova/*` |
| 2. Layar prioritas (2 minggu) | 7 layar di bagian 9 | rilis web + Android v2.0.0 |
| 3. Migrasi sisa (3 minggu) | semua halaman Authenticated + publik; hapus berkas `- copy/- backup` | 100% halaman Nova |
| 4. Uji & rapikan (1 minggu) | uji usability 5 tugas, Lighthouse, audit kontras | laporan + perbaikan |

Strategi rilis: web di belakang feature flag `ui_nova` (pengaturan admin) supaya bisa dibandingkan dan dikembalikan. Android rilis v2.0.0 lewat workflow `android-release.yml` seperti biasa.

## 13. Risiko

| Risiko | Mitigasi |
|---|---|
| Perombakan besar memicu regresi fitur | migrasi per halaman, feature flag, test tetap hijau di setiap PR |
| Animasi berat di HP kelas bawah | hanya transform/opacity, matikan efek dekoratif saat `ui_effects=low` atau baterai hemat |
| Pengguna lama bingung dengan navigasi baru | banner "Tampilan baru" + tur singkat 3 langkah, opsi kembali ke tampilan lama selama fase 2–3 |
| Kaca/glow menurunkan kontras | dibatasi ke area dekoratif; semua teks di atas `bg/surface` solid |

## 14. Pertanyaan terbuka

1. Apakah warna aksen tetap biru (identitas lama) atau ada warna satuan resmi yang harus dipakai? (Opsi dari audit: navy #1E3A5F, lihat 15.1.)
2. Apakah logo/lambang satuan boleh tampil di layar login publik?
3. Siapa 5 pengguna untuk uji usability (2 admin, 1 pimpinan, 2 anggota)?
4. Apakah Command Palette boleh mencari data sensitif (nama/NRP), atau hanya menu?

## 15. Audit ui-ux-pro-max (v1.1)

Audit memakai skill **ui-ux-pro-max**: `--design-system` (internal admin dashboard, kepadatan 8/10), pencarian domain `ux`/`style`, stack `vue` dan `jetpack-compose`, serta checklist `quick-reference.md` dan `pro-rules.md`.

### 15.1 Rekomendasi design system dan keputusannya

| Rekomendasi skill | Keputusan Nova |
|---|---|
| Gaya **Minimalism & Swiss** untuk dashboard enterprise | **Diterima.** Sama dengan arah Nova (zinc netral, satu aksen). Kaca hanya untuk overlay/nav sesuai aturan `blur-purpose`. |
| Primer **navy #1E3A5F** + sekunder biru #2563EB ("trust navy") | **Jadi opsi** untuk pertanyaan terbuka no. 1. Default tetap biru #2563EB, navy dipertimbangkan untuk sidebar/header bila satuan ingin kesan lebih resmi. |
| Font Fira Code + Fira Sans | **Ditolak.** Fira Code sebagai heading terlalu "kode". UI memakai Figtree (font lama aplikasi), Geist Mono di-*self-host* untuk kode/nomor. |
| Anti-pola: *confusing signing flow, weak audit trail, hidden consent* | **Diterima, jadi FR-9 s.d. FR-11** di bawah. |

### 15.2 Temuan dan perbaikan

| # | Prioritas | Temuan (aturan skill) | Perbaikan di PRD |
|---|---|---|---|
| A1 | Kritis | `color-contrast`: `success`/`warning` sebagai teks 3.3 / 3.2:1, `fg/subtle` dark 3.9–4.1:1, teks putih di tombol aksen dark 3.7:1 | Token baru `*/fg`, `accent/solid`, `accent/on-soft`; `fg/subtle` diubah (6.1) |
| A2 | Kritis | Batas field `#E4E4E7` hanya 1.27:1 (non-text 3:1) | Token `border/input` untuk field/checkbox |
| A3 | Kritis | `accessible-authentication`: kotak token 6 digit harus menerima tempel & autofill | Input OTP = satu `<input autocomplete="one-time-code" inputmode="numeric">`, tampil sebagai 6 kotak; tempel 6 digit langsung terisi |
| A4 | Kritis | `password-toggle` belum ada di field kata kunci | Tombol tampil/sembunyikan (ikon mata, `aria-pressed`) di semua field kata kunci; teks login tidak berubah |
| A5 | Tinggi | `error-placement`, `aria-live-errors`: getar kartu tidak cukup | Getar selalu disertai pesan di bawah field + `role="alert"` |
| A6 | Tinggi | `excessive-motion`: login punya aurora, sorotan, tilt, dan border beam sekaligus | Border beam hanya di kartu login dan berhenti saat reduced motion atau `ui_effects=low`; tilt dimatikan di layar sentuh |
| A7 | Tinggi | `exit-faster-than-enter` | Token keluar: ±65% durasi masuk (instant 70, fast 110, base 160, slow 240 ms) |
| A8 | Tinggi | `focus-not-obscured` (WCAG 2.2): topbar lengket & bilah aksi massal | `scroll-padding-top` = tinggi topbar; bilah aksi massal tidak menutupi baris yang difokus |
| A9 | Tinggi | `focus-on-route-change`, `skip-links` | Tautan "Lewati ke konten" + fokus pindah ke `<main>` setelah navigasi Inertia |
| A10 | Tinggi | `deep-linking`, `state-preservation` | Filter/sort/halaman Agenda Surat disimpan di query string; notifikasi membuka URL langsung |
| A11 | Sedang | `readable-font-size`: label 9–11 px di halaman publik/badge | Minimum 12 px untuk semua teks; overline 11 px hanya untuk label grup sidebar |
| A12 | Sedang | Android body 15 sp | Naik ke **16 sp**; dukung skala huruf sistem tanpa terpotong |
| A13 | Sedang | `toast-accessibility`, waktu cukup | Toast `aria-live="polite"`; toast dengan "Urungkan" tetap 6 s dan berhenti saat hover/fokus |
| A14 | Sedang | `sortable-table`, `virtualize-lists`, tabel di mobile | DataGrid memakai `aria-sort`; virtualisasi > 50 baris; di < 768 px jadi daftar kartu |
| A15 | Sedang | `data-table`, `screen-reader-summary` untuk grafik | Setiap grafik punya ringkasan teks dan tabel alternatif |
| A16 | Sedang | `offline-support` (anggota di sinyal lemah) | Banner offline; absen ditahan di antrean lokal dan dikirim ulang (Android) |
| A17 | Sedang | `gesture-alternative` untuk swipe di daftar surat Android | Aksi swipe juga ada di menu "…" dan sheet detail |
| A18 | Rendah | `font-loading` | Geist self-host + `font-display: swap`, preload hanya 1 berat |

### 15.3 Kebutuhan fungsional tambahan

- **FR-9 Alur TTE yang jelas.** Tombol "Setujui & TTE" di approval card **tidak** langsung menandatangani. Ia membuka sheet/dialog berisi pratinjau dokumen, posisi tanda tangan, nama penandatangan, dan field passphrase. Tombol akhir bernama "Tanda tangani" dan toast hasilnya "Dokumen ditandatangani". (Mengoreksi target "sekali ketuk" di bagian 4.)
- **FR-10 Jejak audit terlihat.** Timeline persetujuan menampilkan siapa, kapan (tanggal-jam WITA, Geist Mono), dan aksi apa untuk setiap langkah Dibuat → Diparaf → TTE.
- **FR-11 Persetujuan eksplisit.** Penolakan wajib alasan; aksi tidak bisa dibatalkan (TTE, hapus) memakai dialog konfirmasi, bukan "Urungkan".

### 15.4 Checklist sebelum serah terima (dari `pro-rules.md`)

- [ ] Kontras teks ≥ 4.5:1 dan batas komponen ≥ 3:1 di mode terang **dan** gelap (diukur, bukan diasumsikan)
- [ ] Target sentuh ≥ 48 dp Android / ≥ 24 px web (tombol utama tetap 40–44 px)
- [ ] Reduced motion dan skala huruf terbesar diuji
- [ ] Ikon hanya Lucide 2 px, tanpa emoji; ikon dekoratif `aria-hidden`
- [ ] Field punya label terlihat, pesan error di bawahnya, dan ringkasan error saat banyak kesalahan
- [ ] Diuji di 375, 768, 1024, 1440 px dan landscape
