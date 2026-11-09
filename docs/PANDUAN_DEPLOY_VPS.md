# Panduan Deploy ORION GRAFIKA ke VPS dengan Subdomain Sendiri

Panduan ini memindahkan aplikasi dari komputer kantor (XAMPP, Windows) ke VPS Linux, lalu membukanya lewat
subdomain dari domain yang sudah Anda miliki di Hostinger, misalnya `erp.namadomain.com`.

Contoh nilai yang dipakai di seluruh panduan (ganti dengan milik Anda):

| Contoh | Artinya |
|---|---|
| `namadomain.com` | Domain yang sudah Anda punya di Hostinger |
| `erp.namadomain.com` | Subdomain untuk aplikasi |
| `203.0.113.10` | Alamat IP VPS |
| `/var/www/orion` | Folder aplikasi di VPS |
| `orion` | Nama pengguna database di VPS |

---

## 1. Bisakah memakai subdomain dari domain yang sudah ada?

**Bisa, dan itu cara yang disarankan.** Anda tidak perlu membeli domain baru. Subdomain hanyalah satu catatan DNS
(A record) di domain yang sudah ada:

- `namadomain.com` tetap mengarah ke website Anda yang sekarang (tidak berubah sama sekali).
- `erp.namadomain.com` diarahkan ke IP VPS tempat aplikasi ini berjalan.

Subdomain tidak dikenai biaya, dan sertifikat HTTPS-nya gratis (Let's Encrypt, langkah 10).

Syaratnya hanya satu: Anda bisa mengubah DNS domain itu. Kalau domain memakai nameserver Hostinger
(`ns1.dns-parking.com` / `ns2.dns-parking.com`), DNS diatur di hPanel. Kalau nameserver-nya dialihkan ke
layanan lain (misalnya Cloudflare), tambahkan catatan DNS di layanan tersebut, bukan di hPanel.

---

## 2. Gambaran akhir

```
Pengguna (browser / HP)
        │  https://erp.namadomain.com
        ▼
VPS Ubuntu 24.04
 ├─ Apache 2.4 + PHP 8.3-FPM     → melayani folder /var/www/orion/public
 ├─ MariaDB 10.11                → database oriondb
 ├─ Certbot (Let's Encrypt)      → HTTPS otomatis diperpanjang
 └─ Cron tiap menit              → backup harian/mingguan/bulanan, rapikan data, cek integritas
```

**Mengapa Apache, bukan Nginx?** Aplikasi ini sudah memakai aturan keamanan di `public/.htaccess`: berkas
unggahan berisi data usaha (desain pelanggan, bukti bayar, bukti serah terima) hanya bisa dibuka setelah login,
skrip di folder unggahan tidak bisa dijalankan, dan berkas HTML/SVG unggahan tidak bisa menjalankan skrip.
Dengan Apache aturan itu langsung berlaku tanpa diterjemahkan ulang. Kalau tetap memakai Nginx, semua aturan
itu wajib ditulis ulang di konfigurasi Nginx. Bila terlewat, berkas pelanggan terbuka untuk siapa saja.

---

## 3. Spesifikasi VPS

| | Minimum | Disarankan |
|---|---|---|
| CPU | 1 vCPU | 2 vCPU |
| RAM | 2 GB | 4 GB |
| Disk | 40 GB SSD | 50 GB NVMe |
| Sistem | Ubuntu 24.04 LTS | Ubuntu 24.04 LTS |
| Lokasi | Indonesia / Singapura | Indonesia / Singapura (latensi rendah) |

Contoh di Hostinger: paket **KVM 2** (2 vCPU, 8 GB RAM) sudah sangat longgar; **KVM 1** cukup untuk awal.
Pilih template **Ubuntu 24.04** (polos, tanpa panel).

> Catatan: "Web hosting / shared hosting" Hostinger **bukan** VPS. Panduan ini untuk VPS (akses root lewat SSH).

---

## 4. Persiapan di komputer kantor (sebelum pindah)

Lakukan menjelang pindah, saat aplikasi tidak sedang dipakai, supaya data yang dibawa adalah data terakhir.

### 4.1 Hentikan input sementara

Beri tahu kasir dan operator. Opsional, nyalakan mode perawatan:

```bash
php artisan down
```

### 4.2 Ekspor database

Buka Command Prompt di komputer kantor:

```bash
"C:\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction --routines --triggers --default-character-set=utf8mb4 oriondb > oriondb_pindah.sql
```

Atau pakai menu backup di aplikasi (Backup → Buat backup manual). Berkasnya ada di
`storage\app\backups\manual\`.

### 4.3 Salin berkas unggahan

Folder yang wajib dibawa: `C:\xampp\htdocs\grafikaorion\storage\app\public\` (isi saat ini: `company_logo`,
`jersey_thumbnails`, `kas_kecil_bukti`, `order_thumbnails`, `posts`, dan folder unggahan lain bila ada).

Kompres **isi** folder itu (bukan foldernya) menjadi `unggahan.zip`.

### 4.4 Catat APP_KEY

Buka `.env` di komputer kantor dan salin nilai `APP_KEY=base64:...`. **Nilai ini harus sama persis di VPS.**
Kalau berbeda, semua sesi login lama tidak berlaku (pengguna cukup login ulang), dan data apa pun yang dienkripsi
aplikasi tidak bisa dibaca lagi.

Simpan APP_KEY dan sandi-sandi lain di tempat aman. Jangan dikirim lewat chat atau email biasa.

---

## 5. Arahkan subdomain ke VPS (DNS di Hostinger)

Kerjakan langkah ini lebih awal, karena perubahan DNS butuh waktu menyebar (biasanya 5–30 menit, paling lama
beberapa jam).

1. Login ke **hPanel Hostinger**.
2. Menu **Domains** → pilih `namadomain.com` → **DNS / Nameservers** → tab **DNS records**.
3. Tambahkan catatan baru:

   | Type | Name | Points to | TTL |
   |---|---|---|---|
   | A | `erp` | `203.0.113.10` (IP VPS) | 300 |

4. Simpan. **Jangan** mengubah catatan `@` atau `www`, karena itu website utama Anda.
5. Opsional: bila VPS punya IPv6, tambahkan juga catatan **AAAA** dengan nama `erp` ke alamat IPv6 VPS.

Cek dari komputer mana pun:

```bash
nslookup erp.namadomain.com
```

Hasilnya harus menunjukkan IP VPS.

---

## 6. Menyiapkan server

Masuk ke VPS sebagai root (sandi atau kunci SSH dari panel VPS):

```bash
ssh root@203.0.113.10
```

### 6.1 Perbarui sistem, zona waktu, dan pengguna kerja

```bash
apt update && apt -y upgrade
timedatectl set-timezone Asia/Jakarta

adduser deploy                 # pengguna untuk bekerja sehari-hari
usermod -aG sudo deploy
usermod -aG www-data deploy
```

Mulai dari sini, login sebagai `deploy` (`ssh deploy@203.0.113.10`) dan pakai `sudo` bila perlu.

### 6.2 Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Jangan membuka port database (3306) ke internet.

Opsional tapi disarankan: `sudo apt -y install fail2ban` untuk memblokir tebakan sandi SSH.

### 6.3 Pasang Apache, PHP, MariaDB, dan alat lain

```bash
sudo apt -y install apache2 mariadb-server mariadb-client git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-opcache

sudo a2enmod rewrite headers proxy_fcgi setenvif ssl
sudo a2enconf php8.3-fpm
sudo systemctl restart apache2
```

Aplikasi dikembangkan dengan PHP 8.2. PHP 8.3 bawaan Ubuntu 24.04 kompatibel (Laravel 12 mendukung 8.2–8.4).

### 6.4 Composer dan Node.js

```bash
# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (hanya untuk membangun tampilan / CSS)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt -y install nodejs
```

### 6.5 Atur PHP

Edit `/etc/php/8.3/fpm/php.ini` (dan bagian yang sama di `/etc/php/8.3/cli/php.ini`):

```ini
upload_max_filesize = 64M     ; Berkas Order menerima sampai 50 MB
post_max_size = 64M
memory_limit = 256M
max_execution_time = 120
date.timezone = Asia/Jakarta
opcache.enable = 1
opcache.memory_consumption = 128
```

```bash
sudo systemctl restart php8.3-fpm
```

---

## 7. Database

### 7.1 Amankan dan buat database

```bash
sudo mariadb-secure-installation
```

Jawab: hapus anonymous user **Y**, larang root remote **Y**, hapus database test **Y**, reload **Y**.

```bash
sudo mariadb
```

```sql
CREATE DATABASE oriondb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'orion'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_SANDI_KUAT';
GRANT ALL PRIVILEGES ON oriondb.* TO 'orion'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 7.2 Penyetelan MariaDB

Buat `/etc/mysql/mariadb.conf.d/60-orion.cnf`:

```ini
[mysqld]
# ± 25-40% RAM. VPS 2 GB: pakai 512M
innodb_buffer_pool_size = 1G
max_connections = 100
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

```bash
sudo systemctl restart mariadb
```

### 7.3 Impor data dari komputer kantor

Kirim berkas dari komputer kantor ke VPS (pakai WinSCP, atau dari Command Prompt):

```bash
scp oriondb_pindah.sql unggahan.zip deploy@203.0.113.10:/home/deploy/
```

Di VPS:

```bash
mariadb -u orion -p oriondb < /home/deploy/oriondb_pindah.sql
```

---

## 8. Memasang kode aplikasi

### 8.1 Akses ke repositori GitHub (privat)

Buat kunci khusus server, lalu daftarkan sebagai **Deploy key** (hanya baca):

```bash
ssh-keygen -t ed25519 -C "vps-orion" -f ~/.ssh/github_orion -N ""
cat ~/.ssh/github_orion.pub
```

Salin isinya ke GitHub: repositori `percetakan-app` → **Settings → Deploy keys → Add deploy key** (jangan
centang "Allow write access").

Tambahkan ke `~/.ssh/config`:

```
Host github.com
    IdentityFile ~/.ssh/github_orion
```

### 8.2 Ambil kode dan pasang dependensi

```bash
sudo mkdir -p /var/www/orion && sudo chown deploy:www-data /var/www/orion
git clone git@github.com:steverog1945-bit/percetakan-app.git /var/www/orion
cd /var/www/orion

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # membuat public/build (CSS & JS)
```

### 8.3 Berkas .env

```bash
cp .env.example .env
nano .env
```

Isi minimal (sesuaikan):

```dotenv
APP_NAME="ORION GRAFIKA"
APP_ENV=production
APP_DEBUG=false
# SALIN APP_KEY dari komputer kantor (langkah 4.4)
APP_KEY=base64:...
APP_URL=https://erp.namadomain.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=oriondb
DB_USERNAME=orion
DB_PASSWORD=GANTI_DENGAN_SANDI_KUAT

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
# wajib true setelah HTTPS aktif (langkah 10)
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log

MYSQLDUMP_PATH=mysqldump
# kosong = tanpa salinan kedua; lihat langkah 12
BACKUP_SECONDARY_DISK=
```

Bandingkan juga dengan `.env` di komputer kantor. Variabel lain yang sengaja Anda isi di sana (selain alamat,
sandi, dan path Windows) ikut dipindahkan.

`BACKUP_SALINAN_PATH=D:/BackupERP` adalah path Windows. Jangan disalin apa adanya (lihat langkah 12).

### 8.4 Berkas unggahan, hak akses, dan migrasi

```bash
cd /var/www/orion
unzip /home/deploy/unggahan.zip -d storage/app/public/
php artisan storage:link

sudo chown -R deploy:www-data /var/www/orion
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;

# tidak ada yang berubah bila database sudah terbaru
php artisan migrate --force
```

Periksa isi zip setelah diekstrak: folder `order_thumbnails`, `posts`, dan lainnya harus langsung berada di
`storage/app/public/`, bukan di dalam subfolder tambahan.

### 8.5 Optimasi (khusus server)

```bash
php artisan optimize
```

Di server produksi ini **boleh** dan dianjurkan. Larangan `config:cache` hanya berlaku untuk komputer
pengembangan (karena tes otomatis bisa salah mengarah ke database asli).

Gunakan `php artisan app:config-cache` (bukan `config:cache`): perintah ini menulis cache secara
atomik dan menolak menulis kalau `app.key` kosong atau konfigurasinya menunjuk database uji.
`config:cache` biasa menulis berkas tanpa atomik - ada saat berkasnya terisi separuh, dan permintaan
web yang membacanya pada saat itu mendapat error 500 di seluruh halaman.

**Setiap kali `.env` diubah**, jalankan `php artisan optimize:clear && php artisan app:config-cache`.

---

## 9. Situs Apache untuk subdomain

Buat `/etc/apache2/sites-available/orion.conf`:

```apache
<VirtualHost *:80>
    ServerName erp.namadomain.com
    DocumentRoot /var/www/orion/public

    <Directory /var/www/orion/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/orion_error.log
    CustomLog ${APACHE_LOG_DIR}/orion_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite orion.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

**Penting:** `DocumentRoot` harus folder `public`, bukan folder proyek. Di XAMPP aplikasi dilayani dari folder
proyek, sehingga `.htaccess` di root memblokir `.env` dan folder lain. Di VPS folder-folder itu memang tidak
pernah bisa diakses dari luar, karena berada di luar `public`.

Coba buka `http://erp.namadomain.com`. Halaman awal ORION harus tampil.

---

## 10. HTTPS (gratis, Let's Encrypt)

```bash
sudo apt -y install certbot python3-certbot-apache
sudo certbot --apache -d erp.namadomain.com
```

Pilih opsi **redirect** (semua akses http dialihkan ke https). Certbot memperpanjang sertifikat otomatis. Uji
dengan:

```bash
sudo certbot renew --dry-run
```

Pastikan `.env` sudah `APP_URL=https://...` dan `SESSION_SECURE_COOKIE=true`, lalu:

```bash
php artisan optimize:clear && php artisan optimize
```

Setelah diakses lewat HTTPS, aplikasi otomatis mengirim header HSTS.

---

## 11. Jadwal otomatis (wajib)

Backup harian 01.00, backup mingguan Senin 01.30, backup bulanan tanggal 1 pukul 02.00, perapian data 03.00, dan
pemeriksaan integritas 04.00 hanya berjalan kalau scheduler dipanggil tiap menit.

```bash
sudo crontab -u www-data -e
```

Tambahkan baris:

```
* * * * * cd /var/www/orion && php artisan schedule:run >> /dev/null 2>&1
```

Cek jadwal:

```bash
php artisan schedule:list
```

Queue worker **tidak diperlukan** saat ini: aplikasi tidak memakai pekerjaan antrean.

---

## 12. Backup di luar VPS (sangat disarankan)

Backup otomatis tersimpan di `storage/app/backups/` **di dalam VPS yang sama**. Kalau VPS rusak atau terhapus,
backup ikut hilang. Pilih minimal satu cara berikut:

1. **Snapshot / backup VPS dari penyedia.** Di Hostinger: VPS → Backups. Aktifkan backup mingguan otomatis.
2. **Salinan kedua ke penyimpanan awan S3-compatible** (Cloudflare R2, Backblaze B2, Wasabi, dan sejenisnya).
   Isi di `.env`:

   ```dotenv
   BACKUP_SECONDARY_DISK=s3
   AWS_ACCESS_KEY_ID=...
   AWS_SECRET_ACCESS_KEY=...
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=orion-backup
   AWS_ENDPOINT=https://<akun>.r2.cloudflarestorage.com
   AWS_USE_PATH_STYLE_ENDPOINT=true
   ```

   Disk `s3` butuh paket tambahan: `composer require league/flysystem-aws-s3-v3`. Commit perubahan
   `composer.json`/`composer.lock` itu di repositori supaya ikut terpasang pada pembaruan berikutnya.
3. **Tarik salinan ke komputer kantor** secara berkala (misalnya tiap pagi) dengan WinSCP atau
   `scp deploy@203.0.113.10:/var/www/orion/storage/app/backups/daily/* D:\BackupERP\`.

Uji backup sekali secara manual:

```bash
cd /var/www/orion && sudo -u www-data php artisan backup:run manual
```

Hasilnya harus tercatat "Sukses" di menu Backup aplikasi.

---

## 13. Pengaturan di dalam aplikasi setelah pindah

Login sebagai Administrator, lalu:

1. **Pengaturan Aplikasi → Alamat Akses Aplikasi (untuk QR di HP)**: ganti menjadi
   `https://erp.namadomain.com`. Alamat ini dipakai QR di SPK dan nota yang dipindai dari HP. Bila masih berisi
   alamat lama (mis. `http://192.168.1.10/...`), QR tidak bisa dibuka dari luar kantor.
2. Periksa logo perusahaan dan data toko di halaman yang sama.
3. Minta semua pengguna login ulang di alamat baru.

---

## 14. Pemeriksaan akhir (wajib dicoba semua)

| # | Coba | Hasil yang benar |
|---|---|---|
| 1 | Buka `http://erp.namadomain.com` | Dialihkan ke `https://` |
| 2 | Login Administrator | Masuk dashboard |
| 3 | Buat nota baru dengan gambar thumbnail | Tersimpan, gambar tampil |
| 4 | Salin alamat gambar thumbnail, buka di jendela penyamaran (belum login) | **Tidak tampil**, diminta login |
| 5 | Buka `https://erp.namadomain.com/.env` | 404 (tidak ada) |
| 6 | Cetak nota dan SPK, pindai QR dari HP | Halaman nota/SPK terbuka di HP |
| 7 | Bayar nota di kasir | Jurnal dan laporan kas bertambah |
| 8 | Menu Backup → buat backup manual | Status Sukses |
| 9 | Keesokan harinya, cek menu Backup | Ada backup harian pukul 01.00 |
| 10 | `php artisan about` | Environment `production`, Debug `OFF` |

---

## 15. Memperbarui aplikasi di kemudian hari

Setiap ada perubahan yang sudah di-push ke GitHub:

```bash
cd /var/www/orion
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
sudo systemctl reload php8.3-fpm
php artisan up
```

Biasanya selesai dalam 1–2 menit. Lakukan di luar jam sibuk, dan pastikan backup terakhir sukses sebelum
`migrate`.

---

## 16. Masalah yang sering muncul

| Gejala | Penyebab umum | Perbaikan |
|---|---|---|
| Halaman 500 kosong | Hak akses folder | Ulangi perintah `chown`/`chmod` di langkah 8.4, lihat `storage/logs/` |
| Tampilan tanpa gaya (polos) | `public/build` belum dibuat | `npm ci && npm run build` |
| Gambar unggahan 404 | Tautan storage belum ada | `php artisan storage:link` |
| Login berulang / "Page Expired" | `SESSION_SECURE_COOKIE=true` tapi diakses lewat http, atau `APP_URL` salah | Akses via https, perbaiki `.env`, lalu `optimize:clear` |
| Backup gagal | `mysqldump` tidak ada / sandi salah | `sudo apt install mariadb-client`, cek `DB_PASSWORD` |
| Unggah berkas besar gagal | Batas PHP | Naikkan `upload_max_filesize`/`post_max_size` (langkah 6.5) |
| Subdomain belum bisa dibuka | DNS belum menyebar | Tunggu, cek `nslookup erp.namadomain.com` |
| "Permission denied" pada `storage/logs/laravel-*.log` | Berkas log dibuat oleh pengguna `deploy` saat menjalankan artisan | `sudo chown -R deploy:www-data storage && sudo chmod -R ug+rwX storage` |
| Perubahan `.env` tidak terasa | Konfigurasi masih ter-cache | `php artisan optimize:clear && php artisan optimize` |

---

## Ringkasan urutan kerja

1. Beli/siapkan VPS Ubuntu 24.04 → catat IP.
2. hPanel Hostinger: tambah A record `erp` → IP VPS.
3. Siapkan server: pengguna, firewall, Apache, PHP 8.3, MariaDB, Composer, Node.
4. Ekspor database, salin unggahan, catat APP_KEY dari komputer kantor.
5. Buat database, impor data, clone kode, isi `.env`, build, storage:link, migrate, optimize.
6. Situs Apache → HTTPS dengan Certbot.
7. Cron scheduler + backup di luar VPS.
8. Ubah Alamat Akses Aplikasi di Pengaturan, lalu jalankan pemeriksaan akhir.
