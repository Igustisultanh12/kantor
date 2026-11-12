# Pengerasan nginx untuk /storage (audit keamanan 2026-11, item T06/V03/S05)

Folder `public/storage` adalah symlink ke `storage/app/public`, sehingga isinya dilayani
langsung oleh nginx tanpa melewati Laravel. Tambahkan blok di bawah ini ke konfigurasi
situs **kantor.site** di aaPanel (Website → kantor.site → Config), **di atas** blok
`location ~ [^/]\.php(/|$)` bawaan aaPanel, lalu simpan (aaPanel akan reload nginx).

```nginx
# 1) Jangan pernah mengeksekusi skrip apa pun di bawah /storage
location ~* ^/storage/.*\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh)$ {
    deny all;
    return 403;
}

# 2) Blob backup terenkripsi & cache tidak untuk diunduh langsung
location ^~ /storage/backups/ { deny all; return 403; }
location ^~ /storage/office_cache/ { deny all; return 403; }
location ^~ /storage/cache_thumbs/ { deny all; return 403; }
location ^~ /storage/cache_arw/ { deny all; return 403; }

# 3) Berkas unggahan: jangan biarkan peramban menebak tipe & jangan render HTML/SVG
location ^~ /storage/ {
    add_header X-Content-Type-Options "nosniff" always;
    add_header Content-Security-Policy "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox" always;
    location ~* \.(html?|svg|xml|xhtml)$ {
        add_header Content-Disposition "attachment" always;
        add_header X-Content-Type-Options "nosniff" always;
    }
    try_files $uri =404;
}
```

## Cara cek (V03)

1. Di aaPanel → File Manager, buat berkas uji `storage/app/public/uji-keamanan.php` berisi
   `<?php echo 'EKSEKUSI';`
2. Buka `https://<domain>/storage/uji-keamanan.php`.
   - Muncul teks **EKSEKUSI** → nginx MENGEKSEKUSI PHP di /storage (berbahaya). Pasang blok di atas.
   - Muncul **403** atau berkas terunduh sebagai teks → aman.
3. **Hapus** berkas uji setelah selesai.
