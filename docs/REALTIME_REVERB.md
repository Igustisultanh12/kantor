# Realtime SISINDEN dengan Laravel Reverb (server sendiri)

Setelah Reverb aktif, semua menu yang menampilkan data (web dan aplikasi Android)
ikut berubah sendiri saat ada data baru atau data diubah, tanpa refresh.

Cara kerjanya:

1. Setiap aksi simpan/ubah/hapus (POST/PUT/PATCH/DELETE) yang menulis ke tabel data
   mencatat modulnya (`letters`, `skhpp`, `attendance`, `duty`, `chat`, `backup`, …).
   Pemetaan tabel → modul ada di `app/Support/Realtime.php`.
2. Setelah respons terkirim, server menyiarkan satu sinyal `changed` ke kanal privat
   `private-sinden`. **Isinya hanya nama modul**, bukan isi data.
3. Web (`resources/js/Utils/realtime.js`) memuat ulang data halaman yang sedang dibuka
   lewat Inertia. Saat pengguna sedang mengetik atau membuka dialog, muat ulang ditunda.
4. Aplikasi Android (v2.0.4+) memuat ulang layar yang sedang tampil. Saat aplikasi kembali dari
   latar belakang, semua layar memuat ulang sekali.

Sebelum Reverb dipasang, aplikasi berjalan seperti biasa tanpa realtime (`BROADCAST_CONNECTION`
bukan `reverb`): tidak ada error, tidak ada sinyal.

## 1. Isi `.env` di server

Buat kunci acak (jalankan 3 kali, satu untuk tiap nilai):

```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
```

Tambahkan/ubah di `/www/wwwroot/Kantor.site/.env`:

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=sinden
REVERB_APP_KEY=<kunci-acak-1>
REVERB_APP_SECRET=<kunci-acak-2>

# Server Reverb mendengarkan di lokal saja; Laravel mengirim sinyal ke sini.
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Alamat yang dipakai peramban & aplikasi Android (lewat Nginx, HTTPS 443).
REVERB_PUBLIC_HOST=sisinden.my.id
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https

# Dibaca saat "npm run build" (masuk ke JavaScript web). KEY saja, jangan SECRET.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=sisinden.my.id
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Hapus `VITE_ABLY_KEY` bila masih ada (klien Ably sudah tidak dipakai; kuncinya dulu
terbaca oleh siapa pun yang membuka situs, jadi sebaiknya kunci itu juga dicabut di dasbor Ably).

## 2. Jalankan server Reverb terus-menerus (Supervisor di aaPanel)

aaPanel → App Store → pasang **Supervisor** → Add Daemon:

- Name: `sinden-reverb`
- Run user: `www`
- Run dir: `/www/wwwroot/Kantor.site`
- Start command: `php artisan reverb:start --host=127.0.0.1 --port=8080`

Cek di log Supervisor bahwa muncul `Starting server on 127.0.0.1:8080`.

## 3. Teruskan WebSocket lewat Nginx

aaPanel → Website → sisinden.my.id → Config, tambahkan **di dalam blok `server { ... }` untuk 443**:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto https;
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
}
```

Simpan, lalu reload Nginx. Bila memakai Cloudflare: pastikan **Network → WebSockets** aktif.

## 4. Terapkan

```bash
cd /www/wwwroot/Kantor.site
git pull
php artisan config:clear && php artisan config:cache
npm install && npm run build      # agar VITE_REVERB_* masuk ke JavaScript
```

Lalu restart daemon `sinden-reverb` di Supervisor.

## 5. Cek

- Buka sisinden.my.id → DevTools → Network → filter `WS`: harus ada koneksi
  `wss://sisinden.my.id/app/<key>` berstatus 101, dan pesan `pusher_internal:subscription_succeeded`
  untuk `private-sinden`.
- Buka Agenda Surat di dua peramban (dua akun). Tambah surat di satu peramban: daftar di peramban
  lain bertambah dalam ±2 detik tanpa refresh.
- `GET https://sisinden.my.id/api/mobile/config` harus berisi objek `realtime` (bukan `null`).
  Aplikasi Android v2.0.4+ memakai alamat itu.

## Catatan

- Sinyal hanya dikirim dari aksi tulis (bukan dari membuka halaman), supaya penandaan
  "sudah dibaca" saat polling tidak memicu muat ulang berantai.
- Perubahan yang dibuat langsung di database (di luar aplikasi) tidak disiarkan,
  kecuali dijalankan lewat queue/scheduler.
- Tabel teknis (sesi, log kunjungan, token, perangkat) diabaikan; daftarnya di
  `Realtime::IGNORED_TABLES`.
