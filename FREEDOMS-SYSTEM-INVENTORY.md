# FreeDOMS — System Inventory untuk Backup & Disaster Recovery

**Tanggal audit:** 2026-10-26
**Repository:** `/workspaces/cmms` (branch `main`, HEAD `96869a0`, 4 commit di riwayat)
**Remote:** `https://github.com/ptbimtntc/cmms`
**Ruang lingkup:** audit saja. Tidak ada kode aplikasi, konfigurasi, database, atau file production yang diubah. Tidak ada migration yang dijalankan. Tidak ada nilai rahasia dari `.env` yang ditampilkan atau disalin.

**Written for:** tim IT/operator yang bertanggung jawab atas hosting dan backup FreeDOMS, yang akan menyusun paket backup dan prosedur restore.

**Legenda status verifikasi**
- ✅ **Terverifikasi** — diperiksa langsung di repository atau workspace.
- ⚠️ **Belum terverifikasi** — hanya bisa dipastikan dari akses hosting/cPanel/database production, yang tidak tersedia dalam audit ini. Cara memeriksanya dicantumkan di setiap butir.

---

## 1. Ringkasan Eksekutif

FreeDOMS adalah aplikasi Laravel (CMMS) dengan database MySQL. Sebagian besar isi repository dapat diambil ulang dari GitHub, tetapi **data bisnis yang paling penting tidak ada di GitHub**:

1. **Database MySQL production** (`u468140406_cmms`) — semua data mesin, PM, user, dan transaksi.
2. **File `.env`** — berisi `APP_KEY`, kredensial database, dan kredensial SMTP. Tanpa `APP_KEY`, kata sandi SMTP Gmail yang tersimpan terenkripsi di database tidak dapat dibaca lagi.
3. **File avatar user** di `storage/app/public/avatars/` — tidak ada di repository.
4. **Konfigurasi hosting** (domain, SSL, cron, queue worker, PHP version, extension) — tidak ada di repository.

Repository GitHub tidak boleh dianggap sebagai backup lengkap production.

**Temuan prioritas tinggi (perlu tindakan):**
- `.env` lokal menunjuk `DB_HOST` ke host hosting remote (`srv1763.hstgr.io`), bukan ke database lokal. Perlu dikonfirmasi apakah workspace ini terhubung ke database production. Lihat §8.
- `APP_DEBUG=true` dan `APP_ENV=local` pada `.env` ini. Harus dipastikan nilai production berbeda.
- Permission `.env` dan banyak file di workspace `rw-rw-rw-` (world-writable). Lihat §8.
- CI (`deploy.yml`) hanya mengirim `public/build/` lewat FTP. Kode PHP production tidak dideploy oleh CI. Metode deploy kode belum terverifikasi.

---

## 2. Identitas Sistem dan Versi

| Komponen | Versi / Nilai | Sumber | Status |
|---|---|---|---|
| Nama aplikasi | `FreeDOMS` (`APP_NAME`) | `.env` | ✅ |
| Framework | Laravel `v13.16.1` | `composer.lock` | ✅ |
| PHP yang disyaratkan | `^8.3` (platform check: `PHP_VERSION_ID >= 80300`) | `composer.json`, `vendor/composer/platform_check.php` | ✅ |
| Livewire | `v4.3.1` | `composer.lock` | ✅ |
| Flux (UI kit) | `v2.15.0`, diambil dari GitHub `livewire/flux` (bukan Flux Pro) | `composer.lock` | ✅ |
| Fortify | `v1.37.2` | `composer.lock` | ✅ |
| Export/import Excel | `maatwebsite/excel 3.1.69` | `composer.lock` | ✅ |
| PDF | `barryvdh/laravel-dompdf v3.1.2` | `composer.lock` | ✅ |
| Frontend build | Vite `^8.0.0`, Tailwind `^4.0.7`, Vitest `^5.0.0` | `package.json` | ✅ |
| Node.js di CI | `20` | `.github/workflows/deploy.yml` | ✅ |
| Node.js lokal | `v22.15.0`, npm `9.8.1` | perintah lokal | ✅ |
| Database engine | MySQL (`DB_CONNECTION=mysql`), charset `utf8mb4_unicode_ci`, `strict=true` | `.env`, `config/database.php` | ✅ |
| Versi MySQL production | Tidak diketahui. Docker lokal memakai `mysql:8.0` | `docker-compose.yml` | ⚠️ |
| Timezone | `Asia/Jakarta` | `.env` | ✅ |
| Driver session / cache / queue | `database` / `database` / `database` | `.env` | ✅ |
| Mailer | `log` di `.env` ini. SMTP Gmail diatur lewat menu aplikasi dan tersimpan di tabel `email_notification_settings` | `.env`, `app/Services/GmailSmtpMailer.php` | ✅ (lokal), ⚠️ (production) |
| Filesystem disk | `local` (`storage/app/private`), `public` (`storage/app/public`) | `config/filesystems.php` | ✅ |
| Migration | 48 file di `database/migrations/` (Juni–Oktober 2026) | repository | ✅ |

---

## 3. Komponen yang WAJIB Dibackup

Urutan prioritas: **K** = kritis (tanpa ini aplikasi tidak bisa dipulihkan atau data hilang), **P** = penting, **O** = opsional.

| Prio | Komponen | Lokasi | Ada di Git? | Catatan |
|---|---|---|---|---|
| **K** | Database MySQL production | Host `srv1763.hstgr.io`, port `3306`, nama `u468140406_cmms` | ❌ | Satu-satunya sumber data bisnis. Berisi user (termasuk hash password), mesin, PM, greasing, oil audit, sync operation, pengaturan email. |
| **K** | File `.env` production | Root project | ❌ (ter-ignore `.env`; tidak pernah ada di riwayat Git — ✅ diperiksa) | Berisi `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, `REDIS_PASSWORD`, `AWS_*`. Harus dienkripsi. |
| **K** | `APP_KEY` | Di dalam `.env` | ❌ | Dibutuhkan untuk mendekripsi `sender_password` (cast `encrypted`) di `EmailNotificationSetting`, serta session/cookie terenkripsi. Kehilangan kunci = kata sandi SMTP tidak bisa dibaca dan session user lama tidak valid. |
| **K** | Avatar user | `storage/app/public/avatars/` | ❌ (folder `.gitignore` berisi `*`) | Dipakai `UserController`, `Profile.php`, dan `User::avatar_url`. Di workspace ini folder kosong, sehingga jumlah file production belum terverifikasi. ⚠️ |
| **K** | Source code aplikasi | Seluruh repository | ✅ (`main`, commit `96869a0`) | Git hanya menjamin kode di commit tersebut. Jika production berjalan dari commit/branch lain, perlu dicatat commit hash yang benar-benar aktif. ⚠️ |
| **P** | Storage privat | `storage/app/private/` | ❌ | Kosong di workspace (hanya `.gitignore`). Disk `local` default mengarah ke sini. ⚠️ isi production belum terverifikasi. |
| **P** | Log aplikasi | `storage/logs/laravel.log` | ❌ (ter-ignore) | Di workspace: ±208 KB, 68 entri `ERROR`, tanggal 2026-10-21 s/d 2026-10-23. Berguna untuk investigasi insiden. Bisa berisi data sensitif. |
| **P** | Konfigurasi web server | `.htaccess` (root dan `public/`) | ✅ | Ada dua file: root mengarah ke `public/`, `public/.htaccess` mengatur rewrite Laravel. Konfigurasi VirtualHost/PHP di panel hosting tidak ada di repo. ⚠️ |
| **P** | Cron job | Panel hosting | ❌ | Dibutuhkan untuk scheduler (lihat §7). ⚠️ |
| **O** | Build frontend | `public/build/` | ✅ (ter-track) | Bisa dibuat ulang dengan `npm run build`. Juga dideploy CI lewat FTP. |
| **O** | Symlink `public/storage` | `public/storage` → `storage/app/public` | ❌ (ter-ignore) | Di workspace ini symlink **belum ada**. Dibuat lewat `php artisan storage:link` atau fitur symlink hosting. |

**Data yang TIDAK perlu dibackup terpisah** (karena ada di database atau dapat dibuat ulang):
- Session (`SESSION_DRIVER=database`) dan cache (`CACHE_STORE=database`) tersimpan di tabel database. Folder `storage/framework/sessions` dan `storage/framework/cache/data` tidak dipakai sebagai penyimpanan utama.
- Job queue (`QUEUE_CONNECTION=database`) juga ada di database. Backup database sudah mencakupnya.
- Export Excel dan PDF dibuat saat diunduh dan tidak disimpan sebagai file persisten.

---

## 4. Komponen yang Dapat Dibuat Ulang dari Lockfile

| Komponen | Sumber rekonstruksi | Syarat | Status |
|---|---|---|---|
| `vendor/` | `composer.lock` (116 paket runtime + 52 paket dev, `content-hash` tersedia) | `composer install` dengan PHP ^8.3 dan ekstensi yang sesuai | ✅ lock lengkap. ⚠️ belum dicoba install ulang di server target |
| `node_modules/` | `package-lock.json` | `npm ci` | ✅ lock ada. ⚠️ lihat catatan di bawah |
| `public/build/` | `npm run build` dari source `resources/` | Node 20 | ✅ bisa dibuat ulang |
| `bootstrap/cache/*.php` | Dibuat otomatis (`php artisan optimize`/`config:cache`) | — | ✅ bisa dibuat ulang. Di workspace ada `packages.php` dan `services.php` (ter-ignore). |
| `storage/framework/views/*`, `storage/framework/testing/*` | Dibuat otomatis dan ter-ignore | — | ✅ tidak perlu dibackup |
| Symlink `public/storage` | `php artisan storage:link` | Izin membuat symlink di hosting | ✅ prosedur ada di `docs/tasks/fitur-foto-profil-user.md` |

**Catatan risiko rekonstruksi:**
- **Flux** diambil dari GitHub (`livewire/flux`, commit `c570fb836278e9be881d08b7e386a1a62ab9faf7`). Ini paket gratis, jadi tidak perlu lisensi. Namun instalasi bergantung pada ketersediaan GitHub dan ketersediaan commit tersebut.
- **Workflow CI** menjalankan `composer config http-basic.composer.fluxui.dev` memakai secret `FLUX_USERNAME`/`FLUX_LICENSE_KEY`. Padahal `composer.lock` tidak memakai `fluxui.dev`. Langkah ini tampaknya tidak diperlukan. ✅ diperiksa di lock. Konfirmasi dengan pemilik repo sebelum menghapusnya.
- **`.npmrc`** berisi `ignore-scripts=true`. Native binary (misalnya `@tailwindcss/oxide`, `lightningcss`) mungkin perlu `npm rebuild` di server. `package.json` juga mengunci `@rollup/rollup-linux-x64-gnu` ke `4.9.5` di `optionalDependencies`. ⚠️ belum diverifikasi dengan `npm ci && npm run build` di lingkungan target.
- Build frontend sebaiknya **dibandingkan** dengan `public/build/` yang ter-track di Git agar hasilnya konsisten.

---

## 5. Komponen yang TIDAK Bisa Direkonstruksi dari GitHub

| Komponen | Alasan | Tindakan |
|---|---|---|
| Isi database production | Data tidak pernah masuk Git | Backup SQL terenkripsi, minimal harian |
| `.env` production (`APP_KEY`, password DB, password SMTP, kredensial AWS/Redis jika dipakai) | Dengan sengaja di-ignore dan tidak boleh ada di Git | Simpan terenkripsi di luar server dan di password manager |
| Avatar dan file upload user | Diunggah saat runtime | Backup folder `storage/app/public` |
| Log production | Dihasilkan saat runtime | Opsional, tetapi berguna untuk audit |
| Konfigurasi hosting: domain, SSL, PHP version, `php.ini`, extension, batas upload, document root | Ada di panel hosting | Catat dan screenshot/ekspor dari panel |
| Cron job dan queue worker | Ada di panel hosting/proses server | Dokumentasikan perintahnya |
| Kredensial SMTP (Gmail App Password), FTP, Flux, dan GitHub Secrets | Secret GitHub tidak bisa diekspor | Simpan di password manager tim |
| Hash password user | Ada di tabel `users` | Termasuk dalam backup database |
| Kode yang benar-benar aktif di production (commit hash) | Git tidak tahu apa yang dideploy | Catat commit hash saat backup |
| Dokumentasi deploy kode PHP | Tidak ada di repo. CI hanya mengirim `public/build` | Konfirmasi dengan pemilik deploy |

---

## 6. Metode Backup yang Tersedia di cPanel / Hosting

⚠️ **Semua butir di bagian ini belum terverifikasi.** Akses ke hosting tidak tersedia dalam audit ini.

**Indikasi jenis hosting:** nama database `u468140406_cmms` dan host `srv1763.hstgr.io` mengikuti pola **Hostinger**. Hostinger biasanya memakai **hPanel**, bukan cPanel. Pastikan panel yang dipakai benar sebelum mengikuti prosedur di bawah.

| Metode | Ketersediaan | Yang tercakup | Batasan restore |
|---|---|---|---|
| **Full account backup** (Backup Wizard / hPanel Backup) | ⚠️ | Home directory, database, email, cron | Biasanya hanya bisa dipulihkan ke akun yang sama |
| **Home directory backup** | ⚠️ | Seluruh file akun (termasuk `.env`, `storage/`, `vendor/`) | Restore file perlu dicek ulang: izin, symlink, dan ownership bisa berubah |
| **Database backup** (phpMyAdmin → Export, atau Backup Wizard) | ⚠️ | Dump SQL database `u468140406_cmms` | Pastikan format dan charset `utf8mb4` terjaga. Perhatikan `DEFINER` pada view/routine jika ada |
| **Database remote dump** (`mysqldump` dari luar) | ⚠️ | Dump SQL | Perlu izin akses remote MySQL dan IP yang di-whitelist |
| **Backup otomatis dari provider** | ⚠️ | Snapshot harian/mingguan (tergantung paket) | Retensi dan lokasi penyimpanan belum diketahui |

---

## 7. Konfigurasi Runtime yang Wajib Dipastikan di Server Baru

**PHP dan ekstensi** (dari `composer.lock` untuk paket runtime, ✅):
`ctype`, `date`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `session`, `simplexml`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`. Ditambah `pdo_mysql` untuk database.

- **Perhatian:** di lingkungan workspace ini, `php -m` **tidak** menampilkan `gd` dan `zip`. Dependensi `phpoffice/phpspreadsheet` (Excel) membutuhkan keduanya. Pastikan server production memilikinya. Dokumen `docs/tasks/fix-import-machine-checklist-excel.md` juga menyebut masalah `ext-zip`.

**Database:**
- Versi MySQL production ⚠️ (Docker lokal memakai 8.0). Pastikan versi production kompatibel dengan migration yang ada.
- Charset `utf8mb4` dan collation `utf8mb4_unicode_ci` ✅.

**Cron (scheduler) — wajib:**
- `bootstrap/app.php` menjadwalkan `pm-schedules:mark-overdue-missed` setiap hari pukul `00:05`. Ini hanya berjalan jika ada cron server:
  `* * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1`
- ⚠️ Belum terverifikasi apakah cron ini sudah ada di production.

**Queue worker — wajib untuk notifikasi email:**
- `QUEUE_CONNECTION=database`. Email notifikasi oil audit kritis (`SendOilAuditCriticalNotification`) diproses lewat queue.
- Di hosting bersama biasanya tidak ada proses daemon. Opsi: `php artisan queue:work --stop-when-empty` lewat cron tiap menit, atau Supervisor jika tersedia.
- Halaman "Notifikasi Email" di aplikasi menampilkan status worker berdasarkan heartbeat terakhir (maksimal 2 menit).

**Email:**
- Mailer production ⚠️ belum diketahui. Lokal memakai `log`.
- SMTP Gmail (`smtp.gmail.com:587`, STARTTLS) diatur dari menu aplikasi. Kata sandi disimpan terenkripsi dengan `APP_KEY`.

**Domain, SSL, dan URL:**
- `APP_URL` di `.env` ini: URL Codespace (`*.app.github.dev`), bukan domain production. Domain production ⚠️ belum diketahui.
- SSL ⚠️ belum diverifikasi.
- `.htaccess` root dan `public/.htaccess` sudah ada ✅. Document root harus mengarah ke `public/`.

**Lainnya:**
- `APP_TIMEZONE=Asia/Jakarta`. Pastikan timezone server dan MySQL sesuai, karena migration `2026_09_03_000000_shift_existing_timestamps_to_jakarta` sudah menggeser timestamp ke Jakarta.

---

## 8. Risiko, Keterbatasan, dan Hal yang Perlu Dikonfirmasi

| # | Risiko / temuan | Tingkat | Bukti | Konfirmasi / tindakan |
|---|---|---|---|---|
| R1 | `.env` lokal memakai `DB_HOST=srv1763.hstgr.io` dan `DB_DATABASE=u468140406_cmms`. Ini host remote, bukan database lokal. Jika ini database production, perubahan lokal (migration, seeder, test) bisa langsung mengenai data live. | **Tinggi** | `.env` (hanya nama host dan database, tanpa password) | Konfirmasi apakah `srv1763.hstgr.io` adalah production. Jika ya, pisahkan `.env` lokal dari production dan jangan jalankan `migrate` atau seeder dari workspace ini. |
| R2 | `APP_DEBUG=true` dan `APP_ENV=local` di `.env`. Jika nilai ini terbawa ke production, error akan menampilkan variabel lingkungan. | **Tinggi** | `.env` | Pastikan production memakai `APP_ENV=production` dan `APP_DEBUG=false`. |
| R3 | Permission file world-writable: `.env` dan banyak file lain `rw-rw-rw-`. Siapa pun yang bisa menulis ke folder bisa mengubah konfigurasi. | **Sedang** | `stat` pada `.env` dan folder | Di production, set `.env` ke `600` dan pastikan owner adalah user PHP. Perlu diuji di server. |
| R4 | Kehilangan `APP_KEY` membuat kata sandi SMTP Gmail tidak bisa dibaca dan session user tidak valid. | **Tinggi** | `EmailNotificationSetting` (cast `encrypted`) | Backup `APP_KEY` bersama `.env` terenkripsi. Simpan salinan kedua di password manager. |
| R5 | `.devcontainer/post-create.sh` (ter-track di Git) berisi password default MySQL untuk lingkungan lokal. Nilainya tidak ditampilkan di laporan ini. | **Sedang** | `grep` pada file | Pindahkan ke variabel atau hapus dari repo jika password itu pernah dipakai di production. |
| R6 | `database/seeders/DatabaseSeeder.php` membuat user admin dengan `updateOrCreate`. Jika seeder dijalankan di production, akun admin default bisa dibuat atau direset. | **Sedang** | `DatabaseSeeder.php` | Jangan jalankan seeder saat restore. Pastikan kredensial admin default sudah diganti. |
| R7 | CI (`deploy.yml`) hanya men-deploy `public/build/` lewat FTP. Kode PHP, `vendor/`, `routes/`, dan `.env` tidak dideploy oleh CI. | **Tinggi** | `.github/workflows/deploy.yml` | Konfirmasi cara kode PHP sampai ke production (manual FTP, cPanel Git, atau cara lain). Catat commit yang aktif. |
| R8 | Belum ada `README` di root. Prosedur deploy, restore, dan cron tidak terdokumentasi di repo. | **Sedang** | `ls` | Tulis runbook setelah audit ini selesai. |
| R9 | Riwayat Git hanya 4 commit. Commit production belum tentu sama dengan HEAD `main`. | **Sedang** | `git log` | Catat commit yang aktif di production sebelum backup. |
| R10 | `storage/app/public` kosong di workspace. Jumlah dan ukuran avatar di production tidak diketahui. | **Sedang** | `find storage/app` | Hitung file dan ukuran di production (lihat §9). |
| R11 | `docker-compose.yml` mengekspos MySQL di port `3306` dan phpMyAdmin di `0.0.0.0:8081`. Ini aman untuk lokal, tetapi jangan dipakai di server production. | **Sedang** | `docker-compose.yml` | Pastikan tidak ada deploy dari konfigurasi ini. |
| R12 | Tidak ada file `.env` lain (`.env.production`, `.env.backup`) di workspace. Ini bagus, tetapi berarti `.env` production belum tentu ter-backup. | **Info** | `ls -la` | Ambil `.env` dari server production. |
| R13 | Dependensi frontend memakai `optionalDependencies` platform-spesifik (`linux-x64-gnu`). Build di macOS/Windows bisa gagal, dan restore di server ARM juga bisa bermasalah. | **Sedang** | `package.json` | Verifikasi `npm ci && npm run build` di arsitektur target. |

**Hal yang belum dapat diverifikasi dari workspace ini:**
- Isi dan ukuran database production; versi MySQL; apakah backup database berhasil.
- Isi `storage/app/public` dan `storage/app/private` di production.
- Konfigurasi PHP, ekstensi, cron, queue worker, SSL, dan domain di hosting.
- Panel hosting yang dipakai (cPanel atau hPanel) dan fitur backup yang tersedia di paket tersebut.
- Commit yang aktif di production.
- Apakah `srv1763.hstgr.io` adalah production (lihat R1).

**Pertanyaan untuk provider hosting:**
1. Panel apa yang dipakai (cPanel atau hPanel)? Apakah ada Full Backup, dan apa retensinya?
2. Apakah ada backup otomatis harian/mingguan? Di mana disimpannya, dan apakah bisa diunduh?
3. Versi MySQL/MariaDB dan batas ukuran dump?
4. Apakah mysqldump remote diizinkan? Apakah IP tertentu harus di-whitelist?
5. Versi PHP yang tersedia, dan apakah `gd`, `zip`, `pdo_mysql`, `fileinfo`, `intl` (jika dibutuhkan) aktif?
6. Bisakah cron dijadwalkan tiap menit? Bisakah `queue:work` berjalan terus (atau hanya lewat cron)?
7. Apakah ada batas `max_execution_time`, `upload_max_filesize`, dan `post_max_size` yang memengaruhi impor Excel?
8. Apakah restore Full Backup bisa dilakukan ke akun/domain berbeda?

---

## 9. Cara Memeriksa Item yang Belum Terverifikasi

| Item | Cara memeriksa | Di mana |
|---|---|---|
| Ukuran dan isi `storage/app/public` | `du -sh storage/app/public && find storage/app/public -type f \| wc -l` | Server production (via SSH atau File Manager) |
| Versi MySQL | `SELECT VERSION();` (hanya baca) | Database production, lewat phpMyAdmin |
| Ukuran database | `SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb FROM information_schema.tables WHERE table_schema='u468140406_cmms' GROUP BY table_schema;` | phpMyAdmin (hanya baca) |
| Versi PHP dan ekstensi | `php -v` dan `php -m` | SSH atau halaman phpinfo sementara (hapus setelahnya) |
| Commit yang aktif | `git rev-parse HEAD` jika deploy memakai Git, atau bandingkan checksum file dengan GitHub | Server production |
| Cron | `crontab -l` atau panel Cron Jobs | Panel hosting |
| Queue worker | Halaman "Notifikasi Email" di aplikasi, atau `ps aux \| grep queue:work` | Server production |
| Domain dan SSL | Buka URL production dan periksa sertifikat | Browser |
| Mailer production | Baca `MAIL_MAILER` di `.env` production (jangan salin nilainya) | Server production |
| Apakah `srv1763.hstgr.io` adalah production | Bandingkan dengan `APP_URL` production dan tanyakan ke pemilik hosting | Panel hosting |
| `php artisan` status migration | `php artisan migrate:status` (hanya baca, dijalankan di server production, bukan di workspace ini) | Server production |

---

## 10. Rekomendasi

### 10.1 Struktur paket backup

```
freedoms-backup-YYYYMMDD-HHMM/
├── MANIFEST.txt                 # tanggal, commit hash, versi PHP/Laravel/MySQL, daftar file + SHA-256
├── database/
│   └── u468140406_cmms-YYYYMMDD-HHMM.sql.gz
├── config/
│   └── env.production.enc       # .env terenkripsi (berisi APP_KEY)
├── storage/
│   ├── app-public.tar.gz        # avatar dan file publik
│   ├── app-private.tar.gz       # jika ada isinya
│   └── logs-YYYYMMDD.log.gz     # opsional
├── code/
│   └── freedoms-<commit>.bundle # git bundle, atau tarball dari git archive <commit>
└── RESTORE.md                   # salinan prosedur restore (lihat 10.3)
```

**Catatan:**
- Kode cukup disimpan sebagai commit hash dan `git bundle`, karena GitHub sudah menyimpan riwayatnya. Simpan juga salinan `code/` jika repository GitHub bisa hilang.
- Jangan simpan `vendor/`, `node_modules/`, atau `public/build/` di paket. Ini dibuat ulang dari lockfile.

### 10.2 Enkripsi dan penyimpanan
- Enkripsi seluruh paket dengan `age` atau `gpg --symmetric --cipher-algo AES256`. Jangan simpan passphrase di tempat yang sama dengan paket.
- Simpan paket di minimal dua lokasi terpisah (aturan 3-2-1: tiga salinan, dua media, satu di luar lokasi).
- Hanya tim IT yang bisa mengakses paket. Catat siapa yang memegang kunci dekripsi.
- Hapus dan rotasi `.env.backup` yang tidak lagi dipakai.
- Pertimbangkan rotasi `APP_KEY` dan password DB/SMTP setelah audit ini, karena nilainya sudah pernah berada di workspace dan perlu dipastikan tidak bocor.

### 10.3 Prosedur restore (draf — belum diuji)

1. **Siapkan server baru:** PHP 8.3+, ekstensi sesuai §7, MySQL/MariaDB sesuai versi production, Node 20, Composer.
2. **Kode:** checkout commit yang dicatat di `MANIFEST.txt`, lalu `composer install --no-dev --optimize-autoloader --no-scripts` (sesuaikan dengan kebutuhan) dan `npm ci && npm run build`.
3. **Konfigurasi:** dekripsi `env.production.enc` ke `.env` dengan izin `600`. Jangan ubah `APP_KEY`.
4. **Storage:** ekstrak `storage/app-public.tar.gz` ke `storage/app/public/`. Jalankan `php artisan storage:link`.
5. **Database:** buat database dengan charset `utf8mb4`, lalu import dump:
   `gunzip -c database/*.sql.gz | mysql -u <user> -p <db>`
   Jangan jalankan seeder.
6. **Verifikasi:** `php artisan migrate:status` harus menunjukkan semua migration sudah tercatat (jangan jalankan `migrate --force` jika belum ada alasan jelas).
7. **Cache:** `php artisan config:cache && php artisan route:cache && php artisan view:cache` (setelah `.env` benar).
8. **Cron dan queue:** pasang cron `schedule:run` dan worker `queue:work` (lihat §7).
9. **Uji fungsi:** login admin, buka halaman mesin dan PM, buka halaman Notifikasi Email, kirim email uji, unggah avatar, dan ekspor Excel.
10. **Catat hasil** di `MANIFEST.txt` atau log restore.

### 10.4 Jadwal dan uji restore
- Backup database: **harian**. Backup `storage/app/public`: **harian** jika sering berubah, atau **mingguan** jika jarang.
- Backup `.env` dan `APP_KEY`: setiap kali nilainya berubah.
- Uji restore ke staging minimal **sekali per bulan**. Backup yang belum pernah diuji restore belum dianggap backup.

---

## 11. Lampiran: Daftar File Penting di Workspace

| Path | Fungsi | Di Git |
|---|---|---|
| `.env` | Konfigurasi rahasia | ❌ |
| `.env.example` | Template konfigurasi | ✅ |
| `composer.json`, `composer.lock` | Dependensi PHP | ✅ |
| `package.json`, `package-lock.json` | Dependensi frontend | ✅ |
| `.npmrc` | `ignore-scripts=true`, `audit=true` | ✅ |
| `bootstrap/app.php` | Konfigurasi aplikasi dan jadwal (`pm-schedules:mark-overdue-missed`) | ✅ |
| `bootstrap/cache/*.php` | Cache paket dan service | ❌ |
| `config/*.php` | Konfigurasi Laravel | ✅ |
| `database/migrations/` (48 file) | Struktur database | ✅ |
| `database/seeders/` | Seeder (lihat R6) | ✅ |
| `storage/app/public/` | File publik (avatar) | Hanya `.gitignore` |
| `storage/app/private/` | File privat | Hanya `.gitignore` |
| `storage/logs/laravel.log` | Log aplikasi | ❌ |
| `public/` | Document root | ✅ (kecuali `public/storage`) |
| `public/build/` | Aset frontend hasil build | ✅ |
| `public/storage` | Symlink (belum ada di workspace) | ❌ |
| `.github/workflows/deploy.yml` | Deploy `public/build` lewat FTP | ✅ |
| `.github/workflows/lint.yml` | Pint lint | ✅ |
| `docker-compose.yml`, `.devcontainer/` | Lingkungan lokal | ✅ |
| `docs/tasks/` | Catatan tugas, termasuk setup symlink avatar | ✅ |

---

*Dokumen ini dibuat dari audit read-only. Bagian bertanda ⚠️ harus diverifikasi di hosting sebelum dianggap final. Dokumen ini tidak di-commit ke Git karena memuat detail infrastruktur.*
